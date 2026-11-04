<?php

namespace App\Models;

use App\Models\Concerns\HasLockVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExecutionRequest extends Model
{
    use HasLockVersion;

    protected $guarded = ['id', 'reference', 'lock_version'];

    public const EXECUTION_TYPES = ['financial' => 'تنفيذ مالي', 'direct' => 'تنفيذ مباشر'];

    public const INSTRUMENT_TYPES = [
        'judgment_deed' => 'صك حكم',
        'electronic_promissory_note' => 'سند لأمر إلكتروني',
        'other' => 'نوع آخر',
    ];

    public const STATUSES = [
        'draft' => 'قيد الإعداد',
        'submitted' => 'مقدّم',
        'accepted' => 'مقبول',
        'order_issued' => 'صدر أمر التنفيذ',
        'enforcement' => 'إجراءات جبرية',
        'completed' => 'منتهٍ',
        'closed' => 'مغلق',
        'rejected' => 'مرفوض',
    ];

    protected function casts(): array
    {
        return [
            'submitted_on' => 'date', 'accepted_on' => 'date', 'order_issued_on' => 'date', 'notified_on' => 'date',
            'enforcement_started_on' => 'date', 'deed_date' => 'date', 'note_issued_on' => 'date', 'note_due_on' => 'date',
            'note_amount' => 'decimal:2', 'amount_claimed' => 'decimal:2', 'weekly_followup' => 'boolean',
            'client_summary_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExecutionRequest $e) {
            $e->reference ??= References::next('EX', 'execution_requests');
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(ExecutionParty::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ExecutionDecision::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ExecutionAction::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEvent::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
