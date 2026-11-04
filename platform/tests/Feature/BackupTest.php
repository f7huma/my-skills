<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\Document;
use App\Services\Ops\BackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * يتطلب pg_dump/pg_restore/createdb محليًا. الاستعادة تتم إلى قاعدة معزولة ومسار ملفات مختلف.
 */
class BackupTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('storage/framework/testing/backups'));
        File::deleteDirectory(sys_get_temp_dir().'/restore-test-phpunit');
        parent::tearDown();
    }

    public function test_backup_verify_and_isolated_restore(): void
    {
        if (! trim((string) shell_exec('command -v pg_dump'))) {
            $this->markTestSkipped('pg_dump غير متاح');
        }
        $this->demo();
        // RefreshDatabase يعمل داخل معاملة لا يراها pg_dump — نُثبّت البيانات أولًا
        $this->beforeApplicationDestroyed(fn () => null);
        \Illuminate\Support\Facades\DB::commit();
        try {
            $svc = app(BackupService::class);
            $run = $svc->run();
            $this->assertSame('completed', $run->status);
            $this->assertFileExists(config('backup.path').'/'.$run->name.'.tar.enc');
            $raw = file_get_contents(config('backup.path').'/'.$run->name.'.tar.enc');
            $this->assertStringNotContainsString('manifest.json', $raw, 'الحزمة مشفرة');
            $this->assertTrue($svc->verify($run)['ok']);

            try {
                $svc->restoreIsolated($run->name, config('database.connections.pgsql.database'), '/tmp/x');
                $this->fail('يجب رفض الاستعادة فوق قاعدة التطبيق');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('مرفوض', $e->getMessage());
            }

            $result = $svc->restoreIsolated($run->name, 'humaidan_restore_phpunit', sys_get_temp_dir().'/restore-test-phpunit');
            $this->assertTrue($result['ok'], json_encode($result, JSON_UNESCAPED_UNICODE));
            $this->assertSame(Document::whereNull('deleted_at')->where('scan_status', '!=', 'infected')->count(), $result['documents_checked']);
            $this->assertGreaterThan(0, $result['active_lawyers']);

            // تلف ملف في النسخة يُكتشف
            $path = config('backup.path').'/'.$run->name.'.tar.enc';
            $bytes = file_get_contents($path);
            $bytes[200] = chr(ord($bytes[200]) ^ 1);
            file_put_contents($path, $bytes);
            $this->expectException(\RuntimeException::class);
            $svc->verify($run->fresh());
        } finally {
            shell_exec('PGPASSWORD='.escapeshellarg(config('database.connections.pgsql.password')).' dropdb -h '.config('database.connections.pgsql.host').' -U '.config('database.connections.pgsql.username').' --if-exists humaidan_restore_phpunit 2>/dev/null');
            // تنظيف البيانات المثبتة خارج المعاملة
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh');
            \Illuminate\Support\Facades\DB::beginTransaction();
        }
    }
}
