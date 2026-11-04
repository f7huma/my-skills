<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StaleRecordException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('stale_record');
    }

    public function render(Request $request)
    {
        throw ValidationException::withMessages([
            'lock_version' => 'عُدّل هذا السجل من جلسة أخرى بعد فتحك له. حدّث الصفحة لمراجعة آخر نسخة ثم أعد الحفظ.',
        ])->status(409);
    }
}
