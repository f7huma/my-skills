<?php

namespace App\Models;

use App\Models\Concerns\HasLockVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hearing extends Model
{
    use HasLockVersion;

    protected $guarded = ['id', 'lock_version'];

    public const KINDS = ['reconciliation' => 'جلسة صلح', 'court' => 'جلسة قضائية'];

    public const STATUSES = ['scheduled' => 'مجدولة', 'completed' => 'منعقدة', 'postponed' => 'مؤجلة', 'cancelled' => 'ملغاة'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'attendance' => 'array'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRequest::class, 'reconciliation_request_id');
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(CourtFiling::class, 'court_filing_id');
    }

    public function minutes(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'minutes_document_id');
    }

    public function nextHearing(): BelongsTo
    {
        return $this->belongsTo(Hearing::class, 'next_hearing_id');
    }

    public function anchoredTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'anchor_hearing_id');
    }

    public function localDate(): ?string
    {
        return $this->scheduled_at?->copy()->setTimezone(config('office.display_timezone'))->toDateString();
    }
}
