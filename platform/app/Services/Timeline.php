<?php

namespace App\Services;

use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Models\TimelineEvent;
use Illuminate\Database\Eloquent\Model;

class Timeline
{
    public static function record(LegalCase|ExecutionRequest $owner, string $type, string $title, ?string $details = null, ?Model $subject = null, bool $clientVisible = false, $occurredAt = null): TimelineEvent
    {
        return TimelineEvent::create([
            'client_id' => $owner->client_id,
            'legal_case_id' => $owner instanceof LegalCase ? $owner->id : $owner->legal_case_id,
            'execution_request_id' => $owner instanceof ExecutionRequest ? $owner->id : null,
            'type' => $type,
            'title' => $title,
            'details' => $details,
            'occurred_at' => $occurredAt ?? now(),
            'client_visible' => $clientVisible,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'user_id' => auth()->id(),
        ]);
    }
}
