<?php

namespace App\Models;

use App\Models\Concerns\HasLockVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LegalCase extends Model
{
    use HasLockVersion;

    protected $guarded = ['id', 'reference', 'lock_version'];

    public const ACTIVE_STATUSES = ['draft', 'active'];

    public const RESUMABLE_STATUSES = ['suspended', 'struck_off'];

    public const STAGES = [
        'intake' => 'قيد الإعداد',
        'reconciliation' => 'الصلح (تراضي)',
        'filing' => 'طلب تقييد (ناجز)',
        'court' => 'منظورة أمام المحكمة',
        'judgment' => 'صدر الحكم',
        'objection' => 'الاعتراض',
        'execution' => 'التنفيذ',
        'closed' => 'منتهية',
    ];

    public const WORK_STATUSES = [
        'draft' => 'مسودة',
        'active' => 'سارية',
        'suspended' => 'موقوفة (قابلة للاستكمال)',
        'struck_off' => 'مشطوبة (قابلة للاستكمال)',
        'completed' => 'مكتملة',
    ];

    public const OUTCOMES = [
        'full_win' => 'حكم لصالح العميل كليًا',
        'partial_win' => 'حكم لصالح العميل جزئيًا',
        'dismissed' => 'رد الدعوى',
        'inadmissible' => 'عدم قبول الدعوى',
        'struck_off' => 'شطب الدعوى',
        'suspended' => 'وقف السير في الدعوى',
        'settled' => 'انتهت صلحًا',
    ];

    public const CLIENT_ROLES = ['plaintiff' => 'مدعٍ', 'defendant' => 'مدعى عليه', 'other' => 'أخرى'];

    protected function casts(): array
    {
        return ['claim_amount' => 'decimal:2', 'client_summary_updated_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (LegalCase $c) {
            $c->reference ??= References::next('CS', 'legal_cases');
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CaseCategory::class);
    }

    public function powerOfAttorney(): BelongsTo
    {
        return $this->belongsTo(PowerOfAttorney::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(CaseParty::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(ReconciliationRequest::class);
    }

    public function filings(): HasMany
    {
        return $this->hasMany(CourtFiling::class);
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(Hearing::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function memos(): HasMany
    {
        return $this->hasMany(Memo::class);
    }

    public function judgment(): HasOne
    {
        return $this->hasOne(Judgment::class);
    }

    public function executionRequests(): HasMany
    {
        return $this->hasMany(ExecutionRequest::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEvent::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function nextHearing(): ?Hearing
    {
        return $this->hearings()->where('status', 'scheduled')->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at')->first();
    }

    public function primaryCaseNumber(): ?string
    {
        return $this->filings()->whereNotNull('case_number')->latest('registered_on')->value('case_number');
    }
}
