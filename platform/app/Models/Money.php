<?php

namespace App\Models;

/** أدوات مبالغ نصية دقيقة (bcmath غير مطلوب: الجمع يتم في قاعدة البيانات numeric) */
class Money
{
    public static function normalize(?string $v): string
    {
        if ($v === null || $v === '') {
            return '0.00';
        }
        if (! str_contains($v, '.')) {
            return $v.'.00';
        }
        [$i, $d] = explode('.', $v, 2);

        return $i.'.'.str_pad(substr($d, 0, 2), 2, '0');
    }
}
