<?php

namespace App\Models;

use App\Models\Concerns\HasLockVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memo extends Model
{
    use HasLockVersion;

    protected $guarded = ['id', 'lock_version'];

    public const TYPES = [
        'statement_of_claim' => 'لائحة الدعوى',
        'response' => 'مذكرة جوابية',
        'reply' => 'رد',
        'other' => 'مذكرة أخرى',
    ];

    public const STATUSES = ['draft' => 'مسودة', 'ready_for_review' => 'جاهزة للمراجعة', 'approved' => 'معتمدة', 'filed' => 'أودعت'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'approved_at' => 'datetime', 'filed_on' => 'date'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function hearing(): BelongsTo
    {
        return $this->belongsTo(Hearing::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MemoVersion::class)->orderByDesc('version');
    }

    public function filedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'filed_document_id');
    }

    public function latestVersion(): ?MemoVersion
    {
        return $this->versions()->first();
    }
}
