<?php

namespace App\Services\Files;

/** عند عدم توفر الفحص تبقى الملفات محجوزة ولا تُتاح */
class DisabledScanner implements MalwareScanner
{
    public function scan(callable $feed): array
    {
        return ['status' => 'error', 'engine' => 'disabled', 'signature' => 'scanner_disabled'];
    }
}
