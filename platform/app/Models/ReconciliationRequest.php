<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRequest extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'submitted' => 'مقدّم',
        'in_progress' => 'قيد النظر',
        'settled' => 'تم الصلح',
        'failed' => 'تعذّر الصلح',
        'other_party_absent' => 'غياب الطرف الآخر',
        'closed' => 'مغلق',
    ];

    protected function casts(): array
    {
        return ['submitted_on' => 'date', 'outcome_on' => 'date', 'agreed_amount' => 'decimal:2', 'moved_to_court_at' => 'datetime'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(Hearing::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'report_document_id');
    }
}
