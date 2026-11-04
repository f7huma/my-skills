<?php

namespace App\Services\Ops;

use App\Models\BackupRun;
use App\Models\Document;
use App\Services\Audit;
use App\Services\Files\FileCipher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * نسخة احتياطية مشفرة لقاعدة البيانات والمرفقات معًا بنقطة استعادة متسقة:
 * 1) pg_dump يأخذ لقطة متسقة للمعاملات.
 * 2) ثم تُنسخ المرفقات. المرفقات تُكتب قبل إدراج سجلها وتُعامل كملفات غير قابلة للتعديل،
 *    فكل سجل في اللقطة له ملفه في النسخة. والحذف النهائي (التطهير) لا يعمل أثناء النسخ (قفل مشترك).
 * 3) بيان (manifest) بقيم SHA-256 لكل مكوّن يُتحقق منه عند التحقق والاستعادة.
 * 4) الحزمة كاملة تُشفّر بمفتاح نسخ منفصل (BACKUP_ENCRYPTION_KEY).
 */
class BackupService
{
    public const LOCK = 'ops:backup-or-purge';

    public function dir(): string
    {
        $d = rtrim(config('backup.path'), '/');
        @mkdir($d, 0700, true);

        return $d;
    }

    private function cipher(): FileCipher
    {
        return new FileCipher(config('backup.key'));
    }

    private function pgEnv(array $conn): array
    {
        return ['PGPASSWORD' => (string) ($conn['password'] ?? ''), 'PGHOST' => $conn['host'], 'PGPORT' => (string) $conn['port'], 'PGUSER' => $conn['username']];
    }

    public function run(): BackupRun
    {
        $lock = Cache::lock(self::LOCK, 3600);
        if (! $lock->get()) {
            throw new RuntimeException('عملية نسخ أو تطهير أخرى قيد التشغيل.');
        }
        $name = 'backup-'.now()->utc()->format('Ymd-His').'-'.Str::lower(Str::random(4));
        $run = BackupRun::create(['name' => $name, 'status' => 'running', 'started_at' => now()]);
        $work = sys_get_temp_dir().'/'.$name;
        try {
            @mkdir($work.'/files', 0700, true);
            $conn = config('database.connections.'.config('database.default'));
            $dump = Process::env($this->pgEnv($conn))->timeout(3600)
                ->run([config('backup.pg_dump'), '--format=custom', '--no-owner', '--no-privileges', '--file='.$work.'/db.dump', $conn['database']]);
            if (! $dump->successful()) {
                throw new RuntimeException('pg_dump failed: '.Str::limit($dump->errorOutput(), 300));
            }

            $root = rtrim(config('attachments.root'), '/');
            $files = [];
            if (is_dir($root.'/objects')) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/objects', \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if (! $f->isFile() || str_ends_with($f->getFilename(), '.part')) {
                        continue;
                    }
                    $rel = substr($f->getPathname(), strlen($root) + 1);
                    @mkdir(dirname($work.'/files/'.$rel), 0700, true);
                    copy($f->getPathname(), $work.'/files/'.$rel);
                    $files[$rel] = hash_file('sha256', $work.'/files/'.$rel);
                }
            }
            $manifest = [
                'format' => 1,
                'name' => $name,
                'created_at' => now()->toIso8601String(),
                'app_env' => app()->environment(),
                'database' => ['file' => 'db.dump', 'sha256' => hash_file('sha256', $work.'/db.dump')],
                'documents_in_db' => Document::count(),
                'files' => $files,
                'file_key_ids' => Document::query()->distinct()->pluck('key_id')->all(),
            ];
            file_put_contents($work.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $tar = $work.'.tar';
            $t = Process::timeout(3600)->run(['tar', '-C', $work, '-cf', $tar, 'manifest.json', 'db.dump', 'files']);
            if (! $t->successful()) {
                throw new RuntimeException('tar failed');
            }
            $target = $this->dir().'/'.$name.'.tar.enc';
            $in = fopen($tar, 'rb');
            $out = fopen($target.'.part', 'wb');
            $this->cipher()->encryptStream($in, $out);
            fclose($in);
            fclose($out);
            rename($target.'.part', $target);
            chmod($target, 0600);
            $sha = hash_file('sha256', $target);
            file_put_contents($target.'.sha256', $sha.'  '.basename($target)."\n");

            $offsite = false;
            if ($off = config('backup.offsite_path')) {
                if (is_dir($off) && is_writable($off)) {
                    copy($target, rtrim($off, '/').'/'.basename($target));
                    copy($target.'.sha256', rtrim($off, '/').'/'.basename($target).'.sha256');
                    $offsite = hash_file('sha256', rtrim($off, '/').'/'.basename($target)) === $sha;
                }
            }
            $run->forceFill(['status' => 'completed', 'size' => filesize($target), 'sha256' => $sha, 'file_count' => count($files),
                'offsite_copied' => $offsite, 'finished_at' => now()])->save();
            Audit::log('backup.completed', $run, ['files' => count($files), 'offsite' => $offsite], null);

            return $run;
        } catch (\Throwable $e) {
            $run->forceFill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500), 'finished_at' => now()])->save();
            Audit::log('backup.failed', $run, [], null);
            throw $e;
        } finally {
            Process::run(['rm', '-rf', $work, $work.'.tar']);
            $lock->release();
        }
    }

    /** فك التشفير إلى مجلد مؤقت والتحقق من البيان وكل ملف */
    public function extract(string $name, string $into): array
    {
        $path = $this->dir().'/'.$name.'.tar.enc';
        if (! is_file($path)) {
            throw new RuntimeException('النسخة غير موجودة: '.$name);
        }
        $expected = trim(explode(' ', (string) @file_get_contents($path.'.sha256'))[0] ?? '');
        if ($expected && hash_file('sha256', $path) !== $expected) {
            throw new RuntimeException('بصمة ملف النسخة لا تطابق ملف البصمة المرافق.');
        }
        @mkdir($into, 0700, true);
        $tar = $into.'/bundle.tar';
        $out = fopen($tar, 'wb');
        $in = fopen($path, 'rb');
        $this->cipher()->decryptStream($in, fn ($c) => fwrite($out, $c));
        fclose($in);
        fclose($out);
        $x = Process::run(['tar', '-C', $into, '-xf', $tar]);
        @unlink($tar);
        if (! $x->successful()) {
            throw new RuntimeException('تعذر فك الحزمة.');
        }
        $m = json_decode((string) file_get_contents($into.'/manifest.json'), true);
        if (hash_file('sha256', $into.'/db.dump') !== $m['database']['sha256']) {
            throw new RuntimeException('سلامة ملف قاعدة البيانات غير مطابقة.');
        }
        $bad = [];
        foreach ($m['files'] as $rel => $sha) {
            if (! is_file($into.'/files/'.$rel) || hash_file('sha256', $into.'/files/'.$rel) !== $sha) {
                $bad[] = $rel;
            }
        }
        if ($bad) {
            throw new RuntimeException('ملفات تالفة أو مفقودة في النسخة: '.count($bad));
        }

        return $m;
    }

    public function verify(BackupRun $run): array
    {
        $tmp = sys_get_temp_dir().'/verify-'.Str::random(8);
        try {
            $m = $this->extract($run->name, $tmp);
            $list = Process::run([config('backup.pg_restore'), '--list', $tmp.'/db.dump']);
            if (! $list->successful()) {
                throw new RuntimeException('pg_restore --list failed');
            }
            $run->forceFill(['status' => 'verified', 'verified_at' => now()])->save();
            Audit::log('backup.verified', $run, ['files' => count($m['files'])], null);

            return ['ok' => true, 'files' => count($m['files'])];
        } finally {
            Process::run(['rm', '-rf', $tmp]);
        }
    }

    /**
     * استعادة إلى بيئة معزولة فقط: قاعدة بيانات مستهدفة مختلفة عن قاعدة التطبيق ومسار ملفات مختلف.
     * بعدها يتحقق من: وجود حساب محامٍ نشط بكلمة مرور، وارتباط كل مستند بملفه، وإمكان فك تشفير المرفقات ومطابقة بصمتها.
     */
    public function restoreIsolated(string $name, string $targetDb, string $filesPath, ?callable $log = null): array
    {
        $log ??= fn ($m) => null;
        $conn = config('database.connections.'.config('database.default'));
        if ($targetDb === $conn['database']) {
            throw new RuntimeException('مرفوض: لا يجوز أن تكون قاعدة الاستعادة هي قاعدة التطبيق الحالية.');
        }
        if (realpath($filesPath) && realpath($filesPath) === realpath(config('attachments.root'))) {
            throw new RuntimeException('مرفوض: مسار ملفات الاستعادة هو مسار المرفقات الحالي.');
        }
        $tmp = sys_get_temp_dir().'/restore-'.Str::random(8);
        try {
            $log('فك التشفير والتحقق من البيان...');
            $m = $this->extract($name, $tmp);
            $env = $this->pgEnv($conn);
            $log('إنشاء قاعدة الاستعادة '.$targetDb.'...');
            Process::env($env)->run(['dropdb', '--if-exists', $targetDb]);
            $c = Process::env($env)->run(['createdb', $targetDb]);
            if (! $c->successful()) {
                throw new RuntimeException('createdb failed: '.Str::limit($c->errorOutput(), 200));
            }
            $r = Process::env($env)->timeout(3600)->run([config('backup.pg_restore'), '--no-owner', '--no-privileges', '--dbname='.$targetDb, $tmp.'/db.dump']);
            if (! $r->successful()) {
                throw new RuntimeException('pg_restore failed: '.Str::limit($r->errorOutput(), 300));
            }
            $log('نسخ المرفقات إلى '.$filesPath.'...');
            @mkdir($filesPath, 0700, true);
            Process::run(['cp', '-a', $tmp.'/files/.', $filesPath.'/']);

            config(['database.connections.restore_check' => array_merge($conn, ['database' => $targetDb])]);
            DB::purge('restore_check');
            $db = DB::connection('restore_check');
            $lawyers = $db->table('users')->where('role', 'lawyer')->where('is_active', true)->whereNotNull('password')->count();
            $docs = $db->table('documents')->whereNull('deleted_at')->whereNotIn('scan_status', ['infected'])->get(['id', 'storage_path', 'sha256']);
            $cipher = app(FileCipher::class);
            $missing = 0;
            $corrupt = 0;
            foreach ($docs as $d) {
                $p = $filesPath.'/'.$d->storage_path;
                if (! is_file($p)) {
                    $missing++;

                    continue;
                }
                $ctx = hash_init('sha256');
                try {
                    $h = fopen($p, 'rb');
                    $cipher->decryptStream($h, fn ($chunk) => hash_update($ctx, $chunk));
                    fclose($h);
                    if (hash_final($ctx) !== $d->sha256) {
                        $corrupt++;
                    }
                } catch (\Throwable) {
                    $corrupt++;
                }
            }
            $casesLinked = $db->table('documents')->where('documentable_type', (new \App\Models\LegalCase)->getMorphClass())
                ->whereNotExists(fn ($q) => $q->from('legal_cases')->whereColumn('legal_cases.id', 'documents.documentable_id'))->count();
            DB::disconnect('restore_check');
            $result = [
                'backup' => $name, 'target_db' => $targetDb, 'files_path' => $filesPath,
                'active_lawyers' => $lawyers, 'documents_checked' => count($docs), 'missing_files' => $missing, 'corrupt_files' => $corrupt,
                'orphan_case_documents' => $casesLinked,
                'ok' => $lawyers > 0 && $missing === 0 && $corrupt === 0 && $casesLinked === 0,
            ];
            Audit::log('backup.restore_tested', null, ['backup' => $name, 'ok' => $result['ok'], 'documents' => count($docs)], null);

            return $result;
        } finally {
            Process::run(['rm', '-rf', $tmp]);
        }
    }

    /** سياسة الاحتفاظ: آخر N يومية + أول نسخة لكل أسبوع لعدد W أسابيع */
    public function prune(): int
    {
        $runs = BackupRun::whereIn('status', ['completed', 'verified'])->orderByDesc('started_at')->get();
        $keep = $runs->take(config('backup.retention_daily'))->pluck('id')->all();
        $weeks = [];
        foreach ($runs as $r) {
            $w = $r->started_at->format('o-W');
            if (! isset($weeks[$w]) && count($weeks) < config('backup.retention_weekly')) {
                $weeks[$w] = $r->id;
            }
        }
        $keep = array_unique(array_merge($keep, array_values($weeks)));
        $n = 0;
        foreach ($runs as $r) {
            if (! in_array($r->id, $keep, true)) {
                @unlink($this->dir().'/'.$r->name.'.tar.enc');
                @unlink($this->dir().'/'.$r->name.'.tar.enc.sha256');
                $r->forceFill(['status' => 'pruned'])->save();
                $n++;
            }
        }

        return $n;
    }
}
