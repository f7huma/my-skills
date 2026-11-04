<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Judgment extends Model
{
    protected $guarded = ['id'];

    public const DEED_STATUSES = ['awaiting' => 'الصك منتظر', 'issued' => 'صدر الصك'];

    public const OBJECTION_STATUSES = [
        'none' => 'لم يُقدَّم اعتراض',
        'objected' => 'قُدّم اعتراض',
        'waived' => 'تنازل عن الاعتراض / قناعة',
        'not_applicable' => 'غير منطبق',
    ];

    public const EXECUTION_DECISIONS = [
        'pending' => 'لم يُحدد',
        'pursue' => 'متابعة التنفيذ',
        'not_pursue' => 'عدم التنفيذ',
        'not_applicable' => 'التنفيذ غير منطبق',
    ];

    protected function casts(): array
    {
        return [
            'pronounced_on' => 'date', 'deed_date' => 'date', 'objection_filed_on' => 'date',
            'finality_confirmed_at' => 'datetime', 'resumed_on' => 'date',
        ];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }
}
