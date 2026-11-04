<?php

namespace App\Models;

use App\Models\Concerns\HasLockVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasLockVersion;

    protected $guarded = ['id', 'lock_version'];

    public const KINDS = [
        'manual' => 'مهمة يدوية',
        'prepare_statement_of_claim' => 'إعداد لائحة الدعوى',
        'submit_response' => 'تقديم رد',
        'attach_document' => 'إرفاق مستند',
        'prepare_memo' => 'إعداد مذكرة',
        'execution_followup' => 'متابعة تنفيذ',
        'hearing_followup' => 'متابعة جلسة',
    ];

    public const DUE_MODES = [
        'date' => 'تاريخ صريح',
        'relative' => 'مدة من واقعة معلومة',
        'next_hearing' => 'مرتبط بالجلسة القادمة',
        'unscheduled' => 'بانتظار تحديد موعد',
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'relative_from' => 'date', 'completed_at' => 'datetime'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function executionRequest(): BelongsTo
    {
        return $this->belongsTo(ExecutionRequest::class);
    }

    public function anchorHearing(): BelongsTo
    {
        return $this->belongsTo(Hearing::class, 'anchor_hearing_id');
    }

    public function sourceHearing(): BelongsTo
    {
        return $this->belongsTo(Hearing::class, 'source_hearing_id');
    }

    public function dueChanges(): HasMany
    {
        return $this->hasMany(TaskDueChange::class)->orderByDesc('created_at');
    }
}
