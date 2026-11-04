<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionAction extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['submitted' => 'مقدّم', 'accepted' => 'مقبول', 'rejected' => 'مرفوض', 'completed' => 'منجز'];

    protected function casts(): array
    {
        return ['submitted_on' => 'date', 'eligible_resubmission_on' => 'date', 'next_followup_on' => 'date'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ActionType::class, 'action_type_id');
    }

    public function executionRequest(): BelongsTo
    {
        return $this->belongsTo(ExecutionRequest::class);
    }
}
