<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtFiling extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'draft' => 'قيد الإعداد',
        'submitted' => 'مقدّم',
        'returned' => 'أعيد للاستكمال',
        'resubmitted' => 'أعيد تقديمه',
        'registered' => 'مقيّد',
        'rejected' => 'مرفوض',
    ];

    protected function casts(): array
    {
        return ['submitted_on' => 'date', 'registered_on' => 'date', 'referred_on' => 'date'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }
}
