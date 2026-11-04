<?php

namespace App\Models;

use App\Models\Concerns\HasLockVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Claim extends Model
{
    use HasLockVersion;

    protected $guarded = ['id', 'reference', 'lock_version'];

    public const STATUSES = [
        'draft' => 'مسودة',
        'under_review' => 'قيد الدراسة',
        'needs_completion' => 'بانتظار الاستكمال',
        'converted_case' => 'حُوّلت إلى قضية',
        'converted_execution' => 'حُوّلت إلى ملف تنفيذ',
        'closed' => 'مغلقة',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'submitted_at' => 'datetime', 'missing_documents' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ClaimMessage::class)->orderBy('created_at');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function convertedCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'converted_case_id');
    }

    public function convertedExecution(): BelongsTo
    {
        return $this->belongsTo(ExecutionRequest::class, 'converted_execution_id');
    }

    public function isEditableByClient(): bool
    {
        return in_array($this->status, ['draft', 'needs_completion'], true);
    }
}
