<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** قاعدة مواعيد ذات إصدار مرتبطة بتاريخ السريان والنوع والمصدر الرسمي. لا تعمل قبل اعتماد المحامي. */
class DeadlineRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'reviewed_at' => 'datetime'];
    }

    public static function applicable(string $code, \DateTimeInterface $on): ?self
    {
        return static::query()->where('code', $code)->where('status', 'active')
            ->whereDate('effective_from', '<=', $on)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))
            ->orderByDesc('version')->first();
    }
}
