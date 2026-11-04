<?php

namespace App\Services\Ops;

use App\Models\BackupRun;
use App\Models\Document;
use App\Models\SystemCheck;
use App\Services\Files\FakeScanner;
use App\Services\Files\MalwareScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * فحوص تشغيلية مجدولة. التنبيهات لا تتضمن أسماء عملاء أو ملفات أو محتوى.
 */
class HealthService
{
    public function runAll(): array
    {
        $results = [
            'backup' => $this->backup(),
            'disk' => $this->disk(),
            'queue' => $this->queue(),
            'scanner' => $this->scanner(),
            'tls' => $this->tls(),
        ];
        foreach ($results as $check => [$status, $msg, $data]) {
            SystemCheck::create(['check' => $check, 'status' => $status, 'message' => $msg, 'data' => $data, 'checked_at' => now()]);
        }
        $failures = array_filter($results, fn ($r) => $r[0] === 'fail');
        if ($failures) {
            $this->alert($failures);
        }
        SystemCheck::where('checked_at', '<', now()->subDays(90))->delete();

        return $results;
    }

    private function backup(): array
    {
        $last = BackupRun::whereIn('status', ['completed', 'verified'])->latest('started_at')->first();
        $failed = BackupRun::where('status', 'failed')->where('started_at', '>', now()->subDay())->count();
        if (! $last) {
            return ['fail', 'لا توجد نسخة احتياطية ناجحة بعد.', ['failed_24h' => $failed]];
        }
        $age = $last->finished_at ? $last->finished_at->diffInHours(now()) : 999;
        $file = rtrim(config('backup.path'), '/').'/'.$last->name.'.tar.enc';
        if (! is_file($file)) {
            return ['fail', 'ملف آخر نسخة غير موجود على القرص.', ['name' => $last->name]];
        }
        if ($age > config('backup.max_age_hours')) {
            return ['fail', "آخر نسخة ناجحة قبل {$age} ساعة.", ['name' => $last->name, 'age_hours' => $age]];
        }
        $warn = [];
        if (! $last->verified_at) {
            $warn[] = 'لم يُتحقق منها بعد';
        }
        if (config('backup.offsite_path') && ! $last->offsite_copied) {
            $warn[] = 'لم تُنسخ إلى الوجهة المنفصلة';
        }
        if (! config('backup.offsite_path')) {
            $warn[] = 'لم تُضبط وجهة منفصلة عن قرص التطبيق';
        }

        return [$warn ? 'warn' : 'ok', 'آخر نسخة قبل '.$age.' ساعة'.($warn ? ' — '.implode('، ', $warn) : '').'.',
            ['name' => $last->name, 'age_hours' => $age, 'verified' => (bool) $last->verified_at, 'offsite' => $last->offsite_copied]];
    }

    private function disk(): array
    {
        $paths = array_unique([config('attachments.root'), config('backup.path'), storage_path()]);
        $data = [];
        $worst = 100;
        foreach ($paths as $p) {
            $p = is_dir($p) ? $p : dirname($p);
            if (! is_dir($p)) {
                continue;
            }
            $free = @disk_free_space($p);
            $total = @disk_total_space($p);
            if ($free === false || ! $total) {
                continue;
            }
            $pct = round($free / $total * 100, 1);
            $worst = min($worst, $pct);
            $data[] = ['free_percent' => $pct, 'free_gb' => round($free / 1073741824, 1)];
        }
        $min = config('office.ops.disk_min_free_percent');

        return [$worst < $min ? 'fail' : ($worst < $min * 1.5 ? 'warn' : 'ok'), "أقل مساحة حرة {$worst}%.", $data];
    }

    private function queue(): array
    {
        $failed = DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())->count();
        $stale = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(30)->timestamp)->count();
        $heartbeat = cache('ops:worker-heartbeat');
        $workerOk = $heartbeat && now()->timestamp - (int) $heartbeat < 900;
        $status = (! $workerOk || $stale > 0) ? 'fail' : ($failed > 0 ? 'warn' : 'ok');

        return [$status, ($workerOk ? 'العامل يعمل' : 'لا نبض من العامل خلال 15 دقيقة')."، مهام متأخرة: {$stale}، فاشلة خلال 24 ساعة: {$failed}.",
            ['failed_24h' => $failed, 'stale' => $stale, 'worker' => $workerOk]];
    }

    private function scanner(): array
    {
        $probe = app(MalwareScanner::class)->scan(fn ($sink) => $sink('health-probe'));
        $pending = Document::where('scan_status', 'pending')->where('created_at', '<', now()->subMinutes(30))->count();
        $errors = Document::where('scan_status', 'error')->whereNull('deleted_at')->count();
        $ok = $probe['status'] === 'clean';
        $status = ! $ok ? 'fail' : (($pending || $errors) ? 'warn' : 'ok');

        return [$status, ($ok ? 'محرك الفحص ('.$probe['engine'].') يعمل' : 'محرك الفحص لا يستجيب — المرفقات الجديدة محجوبة حتى يعود')
            ."، ملفات بانتظار الفحص منذ أكثر من 30 دقيقة: {$pending}، أخطاء فحص: {$errors}.", ['engine' => $probe['engine'], 'pending' => $pending, 'errors' => $errors]];
    }

    private function tls(): array
    {
        $host = config('office.ops.tls_check_host') ?: parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! str_starts_with((string) config('app.url'), 'https://') && ! config('office.ops.tls_check_host')) {
            return ['warn', 'الموقع لا يعمل بـ HTTPS في هذه البيئة (متوقع محليًا).', []];
        }
        $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $c = @stream_socket_client("ssl://{$host}:443", $e, $s, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (! $c) {
            return ['fail', 'تعذر الاتصال بالموقع للتحقق من الشهادة.', ['host' => $host]];
        }
        $cert = openssl_x509_parse(stream_context_get_params($c)['options']['ssl']['peer_certificate']);
        fclose($c);
        $days = (int) floor(($cert['validTo_time_t'] - time()) / 86400);
        $min = config('office.ops.tls_min_days');

        return [$days < $min ? 'fail' : 'ok', "تنتهي الشهادة بعد {$days} يومًا.", ['days' => $days, 'host' => $host]];
    }

    private function alert(array $failures): void
    {
        $text = 'تنبيه تشغيلي من منصة المكتب ('.app()->environment()."):\n".collect($failures)->map(fn ($r, $k) => "- {$k}: {$r[1]}")->implode("\n")
            ."\nراجع صفحة حالة النظام.";
        Log::warning('ops.alert', ['checks' => array_keys($failures)]);
        if ($to = config('office.ops.alert_mail')) {
            try {
                Mail::raw($text, fn ($m) => $m->to($to)->subject('تنبيه تشغيلي — منصة المكتب'));
            } catch (\Throwable $e) {
                Log::error('ops.alert_mail_failed');
            }
        }
        if ($hook = config('office.ops.alert_webhook')) {
            try {
                Http::timeout(10)->post($hook, ['text' => $text]);
            } catch (\Throwable) {
                Log::error('ops.alert_webhook_failed');
            }
        }
    }
}
