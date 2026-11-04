<?php

use App\Jobs\ScanDocument;
use App\Jobs\WorkerHeartbeat;
use App\Models\BackupRun;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit;
use App\Services\Auth\InvitationService;
use App\Services\Files\FileCipher;
use App\Services\Files\FileVault;
use App\Services\Ops\BackupService;
use App\Services\Ops\HealthService;
use App\Services\Workflow\DeadlineService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// ------------------------------------------------------------------ الحسابات
Artisan::command('office:invite-lawyer {email} {name}', function (InvitationService $svc) {
    if (User::where('email', mb_strtolower($this->argument('email')))->exists()) {
        $this->error('يوجد حساب بهذا البريد.');

        return 1;
    }
    $res = $svc->create(['email' => $this->argument('email'), 'name' => $this->argument('name'), 'role' => 'lawyer'], null);
    $this->info('أُنشئت دعوة المحامي. الرابط (صالح مرة واحدة خلال '.config('office.invitation_ttl_hours').' ساعة):');
    $this->line($res['url']);

    return 0;
})->purpose('إنشاء دعوة لحساب المحامي الأول (لا تُكتب كلمة مرور في الطرفية)');

// ------------------------------------------------------------------ المفاتيح والملفات
Artisan::command('files:generate-key {--backup}', function () {
    $this->line(FileCipher::generateKey());
    $this->comment($this->option('backup') ? 'ضعه في BACKUP_ENCRYPTION_KEY واحفظ نسخة منه خارج الخادم.' : 'ضعه في FILES_ENCRYPTION_KEY واحفظ نسخة منه خارج الخادم.');
})->purpose('توليد مفتاح تشفير للملفات أو للنسخ الاحتياطية');

Artisan::command('files:rotate-key', function (FileVault $vault) {
    $n = 0;
    Document::whereNotIn('scan_status', ['infected'])->orderBy('id')->chunkById(100, function ($docs) use ($vault, &$n) {
        foreach ($docs as $d) {
            if ($vault->reencrypt($d)) {
                $n++;
            }
        }
    });
    Audit::log('files.key_rotated', null, ['count' => $n], null);
    $this->info("أعيد تشفير {$n} ملفًا بالمفتاح الحالي. بعد التحقق احذف المفتاح القديم من FILES_PREVIOUS_KEYS.");
})->purpose('إعادة تشفير المرفقات بالمفتاح الحالي بعد التدوير');

Artisan::command('files:rescan-pending', function () {
    $ids = Document::whereIn('scan_status', ['pending', 'error'])->whereNull('deleted_at')
        ->where('scan_attempts', '<', 50)->where('updated_at', '<', now()->subMinutes(10))->pluck('id');
    foreach ($ids as $id) {
        ScanDocument::dispatch($id);
    }
    $this->info('أعيدت جدولة '.count($ids).' ملف للفحص.');
})->purpose('إعادة فحص الملفات المعلقة أو التي تعذر فحصها');

Artisan::command('retention:purge {--days=365}', function () {
    $lock = Cache::lock(BackupService::LOCK, 3600);
    if (! $lock->get()) {
        $this->warn('نسخة احتياطية قيد التشغيل — أُجّل التطهير.');

        return 0;
    }
    try {
        $root = rtrim(config('attachments.root'), '/');
        $n = 0;
        Document::whereNotNull('deleted_at')->where('deleted_at', '<', now()->subDays((int) $this->option('days')))
            ->where('storage_path', '!=', '')->each(function (Document $d) use ($root, &$n) {
                @unlink($root.'/'.$d->storage_path);
                $d->forceFill(['storage_path' => '', 'original_name' => 'purged'])->save();
                Audit::log('file.purged', $d, [], null);
                $n++;
            });
        $this->info("طُهّر {$n} ملفًا محذوفًا تجاوز مدة الاحتفاظ.");
    } finally {
        $lock->release();
    }
})->purpose('حذف نهائي للمرفقات المحذوفة بعد مدة الاحتفاظ مع تسجيل الأثر');

// ------------------------------------------------------------------ النسخ الاحتياطي
Artisan::command('backup:run {--verify}', function (BackupService $svc) {
    $run = $svc->run();
    $this->info("اكتملت النسخة {$run->name} ({$run->file_count} ملف).");
    if ($this->option('verify')) {
        $svc->verify($run);
        $this->info('تم التحقق من سلامة النسخة.');
    }
    $pruned = $svc->prune();
    $this->line("حُذفت {$pruned} نسخة قديمة وفق سياسة الاحتفاظ.");
})->purpose('نسخة احتياطية مشفرة متسقة لقاعدة البيانات والمرفقات');

Artisan::command('backup:verify {name?}', function (BackupService $svc) {
    $run = $this->argument('name') ? BackupRun::where('name', $this->argument('name'))->firstOrFail() : BackupRun::whereIn('status', ['completed', 'verified'])->latest('started_at')->firstOrFail();
    $r = $svc->verify($run);
    $this->info("النسخة {$run->name} سليمة ({$r['files']} ملف).");
})->purpose('التحقق من سلامة نسخة احتياطية');

Artisan::command('backup:list', function () {
    $this->table(['name', 'status', 'files', 'size', 'verified'], BackupRun::latest('started_at')->limit(30)->get()
        ->map(fn ($b) => [$b->name, $b->status, $b->file_count, $b->size, $b->verified_at?->toDateTimeString()]));
});

Artisan::command('backup:restore-test {name} {--database=} {--files=}', function (BackupService $svc) {
    $db = $this->option('database') ?: config('database.connections.pgsql.database').'_restore_test';
    $files = $this->option('files') ?: sys_get_temp_dir().'/restore-test-files';
    $r = $svc->restoreIsolated($this->argument('name'), $db, $files, fn ($m) => $this->line($m));
    $this->table(['البند', 'القيمة'], collect($r)->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'نعم' : 'لا') : $v])->values());
    $r['ok'] ? $this->info('نجحت الاستعادة على البيئة المعزولة.') : $this->error('فشل التحقق بعد الاستعادة.');

    return $r['ok'] ? 0 : 1;
})->purpose('استعادة نسخة إلى قاعدة ومسار معزولين ثم التحقق من الدخول وارتباط الملفات وفتحها');

// ------------------------------------------------------------------ التشغيل
Artisan::command('ops:check', function (HealthService $h) {
    foreach ($h->runAll() as $k => [$s, $m]) {
        $this->line(str_pad($k, 10).' '.strtoupper($s).'  '.$m);
    }
})->purpose('تشغيل فحوص التشغيل وتسجيل النتائج');

Artisan::command('deadlines:alerts', function (DeadlineService $svc) {
    $this->info('تنبيهات جديدة: '.$svc->raiseExpiredAlerts());
})->purpose('رفع تنبيه لكل عداد انتهى');

Artisan::command('preview:assert-synthetic', function () {
    $real = \App\Models\Client::where('is_synthetic', false)->count();
    if (config('office.synthetic_only') && $real > 0) {
        $this->error("بيئة المعاينة تحتوي {$real} عميلًا غير اصطناعي.");

        return 1;
    }
    $this->info('بيانات المعاينة اصطناعية فقط.');

    return 0;
});

// ------------------------------------------------------------------ الجدولة
Schedule::command('deadlines:alerts')->dailyAt('05:00')->timezone('Asia/Riyadh')->withoutOverlapping();
Schedule::command('backup:run --verify')->dailyAt('02:30')->timezone('Asia/Riyadh')->withoutOverlapping(120)->onOneServer();
Schedule::command('files:rescan-pending')->everyFifteenMinutes()->withoutOverlapping();
Schedule::job(new WorkerHeartbeat)->everyFiveMinutes();
Schedule::command('ops:check')->hourly()->withoutOverlapping();
Schedule::command('retention:purge')->weeklyOn(5, '03:30')->timezone('Asia/Riyadh');
Schedule::command('queue:prune-failed --hours=720')->daily();

// ------------------------------------------------------------------ اختبار محرك الفحص
Artisan::command('ops:scanner-selftest', function (\App\Services\Files\MalwareScanner $scanner) {
    // نص الاختبار القياسي EICAR (غير ضار) يُرسل مباشرة لمحرك الفحص دون حفظه
    $eicar = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
    $bad = $scanner->scan(fn ($sink) => $sink($eicar));
    $good = $scanner->scan(fn ($sink) => $sink('humaidan clean sample '.now()));
    $this->line('EICAR: '.$bad['status'].' ('.$bad['engine'].')');
    $this->line('ملف سليم: '.$good['status']);
    $ok = $bad['status'] === 'infected' && $good['status'] === 'clean';
    $ok ? $this->info('محرك الفحص يعمل: يكتشف المصاب ويمرر السليم.') : $this->error('محرك الفحص لا يعمل كما ينبغي — المرفقات ستبقى محجوبة.');

    return $ok ? 0 : 1;
})->purpose('التحقق من أن ClamAV يكتشف ملف الاختبار القياسي EICAR');

// ------------------------------------------------------------------ مراجعة إعداد الإنتاج
Artisan::command('ops:security-check', function () {
    $checks = [
        ['APP_ENV=production', app()->environment('production')],
        ['APP_DEBUG=false', ! config('app.debug')],
        ['APP_URL يبدأ بـ https://', str_starts_with((string) config('app.url'), 'https://')],
        ['SESSION_SECURE_COOKIE=true', (bool) config('session.secure')],
        ['SESSION_ENCRYPT=true', (bool) config('session.encrypt')],
        ['المصادقة الثنائية إلزامية للمحامي', (bool) config('office.mfa_required')],
        ['SCANNER_DRIVER=clamav', config('attachments.scanner.driver') === 'clamav'],
        ['FILES_ENCRYPTION_KEY مضبوط', filled(config('attachments.key'))],
        ['BACKUP_ENCRYPTION_KEY مضبوط ومختلف عن مفتاح الملفات', filled(config('backup.key')) && config('backup.key') !== config('attachments.key')],
        ['مفتاح الملفات مختلف عن APP_KEY', config('attachments.key') !== config('app.key')],
        ['وجهة النسخ المنفصلة مضبوطة', filled(config('backup.offsite_path'))],
        ['المرفقات خارج جذر الويب', ! str_starts_with(realpath(config('attachments.root')) ?: config('attachments.root'), public_path())],
        ['قناة تنبيه مضبوطة', filled(config('office.ops.alert_mail')) || filled(config('office.ops.alert_webhook'))],
        ['لا حسابات تجريبية (@example.test)', \App\Models\User::where('email', 'like', '%@example.test')->doesntExist()],
        ['كل المحامين بمصادقة ثنائية', \App\Models\User::where('role', 'lawyer')->where('is_active', true)->whereNull('two_factor_confirmed_at')->doesntExist()],
        ['لا قواعد مواعيد معتمدة دون مراجعة', \App\Models\DeadlineRule::where('status', 'active')->whereNull('reviewed_at')->doesntExist()],
    ];
    $fail = 0;
    foreach ($checks as [$label, $ok]) {
        $this->line(($ok ? '<info>✓</info> ' : '<error>✗</error> ').$label);
        $fail += $ok ? 0 : 1;
    }
    $fail ? $this->error("{$fail} بند لم يتحقق — لا تُدخل بيانات حقيقية قبل معالجتها.") : $this->info('اكتملت مراجعة الإعداد الآلية. تبقى المراجعة البشرية في قائمة الإطلاق.');

    return $fail ? 1 : 0;
})->purpose('مراجعة آلية لإعداد الإنتاج قبل إدخال بيانات حقيقية');
