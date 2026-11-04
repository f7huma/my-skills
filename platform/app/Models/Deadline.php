<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deadline extends Model
{
    protected $guarded = ['id'];

    public const KINDS = [
        'deed_followup' => 'متابعة صدور الصك',
        'objection' => 'موعد اعتراض',
        'execution_compliance' => 'مهلة التنفيذ',
        'resubmission' => 'إعادة تقديم طلب',
        'court' => 'موعد قضائي',
        'custom' => 'موعد آخر',
    ];

    public const CERTAINTY = [
        'estimated' => 'متابعة تقديرية',
        'needs_review' => 'يحتاج مراجعة',
        'confirmed' => 'موعد مؤكد',
        'needs_determination' => 'يحتاج تحديدًا',
    ];

    protected function casts(): array
    {
        return ['start_on' => 'date', 'end_on' => 'date', 'alerted_at' => 'datetime'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function executionRequest(): BelongsTo
    {
        return $this->belongsTo(ExecutionRequest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DeadlineRule::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
