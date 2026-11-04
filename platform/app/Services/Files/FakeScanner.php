<?php

namespace App\Services\Files;

/**
 * للتطوير والاختبار فقط: يكتشف نص اختبار EICAR القياسي، ويحاكي التعطل عند ضبط FAKE_SCANNER_DOWN.
 * يُرفض استخدامه في الإنتاج (انظر AppServiceProvider).
 */
class FakeScanner implements MalwareScanner
{
    public static bool $down = false;

    public function scan(callable $feed): array
    {
        if (self::$down || env('FAKE_SCANNER_DOWN')) {
            return ['status' => 'error', 'engine' => 'fake', 'signature' => 'unreachable'];
        }
        $data = '';
        $feed(function (string $c) use (&$data) {
            $data .= $c;
        });
        if (str_contains($data, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) {
            return ['status' => 'infected', 'engine' => 'fake', 'signature' => 'Eicar-Test-Signature'];
        }

        return ['status' => 'clean', 'engine' => 'fake'];
    }
}
