<?php

namespace App\Http\Presenters;

use App\Models\Claim;
use App\Models\Collection;
use App\Models\CourtFiling;
use App\Models\Document;
use App\Models\ExecutionRequest;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\TimelineEvent;

/**
 * حقول بوابة العميل المسموح بعرضها صراحةً. أي حقل غير مذكور هنا لا يصل إلى استجابة الخادم:
 * لا ملاحظات داخلية، لا مهام، لا عدادات، لا استراتيجية، لا مسودات، لا سجل تدقيق، لا ملفات داخلية.
 */
class Portal
{
    public static function caseSummary(LegalCase $c): array
    {
        return [
            'id' => $c->id,
            'reference' => $c->reference,
            'title' => $c->title,
            'stage' => LegalCase::STAGES[$c->stage] ?? null,
            'status' => LegalCase::WORK_STATUSES[$c->work_status] ?? null,
            'statusKey' => $c->work_status,
            'clientRole' => LegalCase::CLIENT_ROLES[$c->client_role] ?? null,
            'caseNumber' => $c->relationLoaded('filings') ? $c->filings->whereNotNull('case_number')->sortByDesc('registered_on')->first()?->case_number : null,
            'clientSummary' => $c->client_summary,
            'lastUpdate' => $c->client_summary_updated_at?->toIso8601String(),
        ];
    }

    public static function caseDetail(LegalCase $c): array
    {
        $filing = $c->filings->sortByDesc('id')->first();
        $next = $c->hearings->where('status', 'scheduled')->filter(fn ($h) => $h->scheduled_at && $h->scheduled_at->isFuture())->sortBy('scheduled_at')->first();

        return self::caseSummary($c) + [
            'outcome' => $c->outcome ? LegalCase::OUTCOMES[$c->outcome] : null,
            'court' => $filing?->court,
            'circuit' => $filing?->circuit,
            'filingStatus' => $filing ? CourtFiling::STATUSES[$filing->status] : null,
            'nextHearing' => $next ? ['kind' => Hearing::KINDS[$next->kind], 'at' => $next->scheduled_at->toIso8601String()] : null,
            'judgment' => $c->judgment ? [
                'outcome' => LegalCase::OUTCOMES[$c->judgment->outcome] ?? null,
                'pronouncedOn' => $c->judgment->pronounced_on?->toDateString(),
                'deedIssued' => $c->judgment->deed_status === 'issued',
                'final' => $c->judgment->finality_status === 'final',
            ] : null,
            'timeline' => $c->timeline->where('client_visible', true)->sortByDesc('occurred_at')->take(20)->map(fn (TimelineEvent $e) => self::event($e))->values(),
            'documents' => self::documents($c->documents),
            'collections' => self::collections($c->collections),
        ];
    }

    public static function executionSummary(ExecutionRequest $e): array
    {
        return [
            'id' => $e->id,
            'reference' => $e->reference,
            'requestNumber' => $e->request_number,
            'type' => ExecutionRequest::EXECUTION_TYPES[$e->execution_type],
            'instrument' => $e->instrument_type === 'other' ? ($e->instrument_label ?: 'أخرى') : ExecutionRequest::INSTRUMENT_TYPES[$e->instrument_type],
            'status' => ExecutionRequest::STATUSES[$e->status] ?? $e->status,
            'statusKey' => $e->status,
            'clientSummary' => $e->client_summary,
            'lastUpdate' => $e->client_summary_updated_at?->toIso8601String(),
            'amountClaimed' => $e->amount_claimed,
            'currency' => $e->currency,
        ];
    }

    public static function executionDetail(ExecutionRequest $e): array
    {
        return self::executionSummary($e) + [
            'submittedOn' => $e->submitted_on?->toDateString(),
            'acceptedOn' => $e->accepted_on?->toDateString(),
            'orderIssuedOn' => $e->order_issued_on?->toDateString(),
            'timeline' => $e->timeline->where('client_visible', true)->sortByDesc('occurred_at')->take(20)->map(fn (TimelineEvent $ev) => self::event($ev))->values(),
            'documents' => self::documents($e->documents),
            'collections' => self::collections($e->collections),
        ];
    }

    public static function event(TimelineEvent $e): array
    {
        return ['id' => $e->id, 'title' => $e->title, 'details' => $e->details, 'at' => $e->occurred_at->toIso8601String()];
    }

    public static function documents($docs): array
    {
        return collect($docs)->filter(fn (Document $d) => $d->shared_with_client && $d->isAvailable())
            ->map(fn (Document $d) => self::document($d))->values()->all();
    }

    public static function document(Document $d): array
    {
        return [
            'id' => $d->uuid,
            'name' => $d->original_name,
            'category' => Document::CATEGORIES[$d->category] ?? $d->category,
            'size' => $d->size,
            'available' => $d->isAvailable(),
            'scan' => $d->scan_status,
            'url' => $d->isAvailable() ? route('files.show', $d) : null,
        ];
    }

    public static function collections($rows): array
    {
        return collect($rows)->where('visible_to_client', true)->sortByDesc('occurred_on')->map(fn (Collection $c) => [
            'kind' => Collection::KINDS[$c->kind], 'kindKey' => $c->kind, 'source' => Collection::SOURCES[$c->source],
            'amount' => $c->amount, 'currency' => $c->currency, 'on' => $c->occurred_on->toDateString(),
        ])->values()->all();
    }

    public static function claim(Claim $c, bool $withDetail = false): array
    {
        $base = [
            'id' => $c->id,
            'reference' => $c->reference,
            'status' => Claim::STATUSES[$c->status],
            'statusKey' => $c->status,
            'clientName' => $c->client_name,
            'opponentName' => $c->opponent_name,
            'amount' => $c->amount,
            'currency' => $c->currency,
            'submittedAt' => $c->submitted_at?->toIso8601String(),
            'updatedAt' => $c->updated_at?->toIso8601String(),
            'editable' => $c->isEditableByClient(),
        ];
        if (! $withDetail) {
            return $base;
        }

        return $base + [
            'opponentType' => $c->opponent_type,
            'opponentIdentifier' => $c->opponent_identifier,
            'opponentAddress' => $c->opponent_address,
            'opponentPhone' => $c->opponent_phone,
            'description' => $c->description,
            'missingDocumentReason' => $c->missing_document_reason,
            'lockVersion' => $c->lock_version,
            'messages' => $c->messages->where('visible_to_client', true)->map(fn ($m) => [
                'id' => $m->id, 'kind' => $m->kind, 'body' => $m->body, 'at' => $m->created_at->toIso8601String(),
            ])->values(),
            'documents' => $c->documents->whereNull('deleted_at')->map(fn (Document $d) => self::document($d) + [
                'categoryKey' => $d->category, 'party' => Document::PARTIES[$d->party] ?? $d->party, 'label' => $d->label,
            ])->values(),
        ];
    }
}
