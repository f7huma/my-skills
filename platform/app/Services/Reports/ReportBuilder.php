<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Collection;
use App\Models\CourtFiling;
use App\Models\ExecutionRequest;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Memo;
use App\Models\Task;
use App\Services\Calendar;

/**
 * بيانات التقارير. نسخة العميل تُبنى من الحقول المسموح بعرضها فقط (مثل البوابة)،
 * والنسخة الداخلية تضيف الخيارات التي يختارها المحامي. الحقول الفارغة تُستبعد.
 */
class ReportBuilder
{
    public function __construct(private Calendar $cal) {}

    /** تاريخ ميلادي مع مقابله الهجري (أم القرى) في التقارير */
    private function dual($d): ?string
    {
        return $d ? $d->toDateString().' ('.$this->cal->hijri($d).')' : null;
    }

    public function clientReport(Client $client, string $variant, array $opt): array
    {
        $cases = $client->cases()->with(['filings', 'judgment', 'hearings', 'category', 'timeline', 'parties', 'tasks', 'memos'])
            ->when($variant === 'client', fn ($q) => $q->where('work_status', '!=', 'draft'))
            ->when(! empty($opt['case_status']) && $opt['case_status'] !== 'all', fn ($q) => $opt['case_status'] === 'open'
                ? $q->whereIn('work_status', ['active', 'draft', 'suspended', 'struck_off']) : $q->where('work_status', 'completed'))
            ->orderBy('created_at')->get();
        $execs = $client->executionRequests()->with(['legalCase:id,reference', 'timeline', 'decisions', 'actions.type', 'parties'])
            ->when($variant === 'client', fn ($q) => $q->where('status', '!=', 'draft'))->orderBy('created_at')->get();
        $collQ = Collection::where('client_id', $client->id)->when($variant === 'client', fn ($q) => $q->where('visible_to_client', true));

        return [
            'type' => 'client',
            'variant' => $variant,
            'title' => 'تقرير العميل',
            'subject' => $this->clientInfo($client, $variant),
            'counts' => $client->caseCounts(),
            'cases' => $cases->map(fn ($c) => $this->caseBlock($c, $variant, $opt, false))->all(),
            'executions' => $execs->map(fn ($e) => $this->execBlock($e, $variant, $opt))->all(),
            'totals' => Collection::totals($collQ),
            'options' => $opt,
        ];
    }

    public function caseReport(LegalCase $case, string $variant, array $opt): array
    {
        $case->load(['client', 'filings', 'judgment', 'hearings', 'category', 'timeline', 'parties', 'reconciliations', 'tasks', 'memos', 'executionRequests.timeline', 'executionRequests.decisions', 'executionRequests.actions.type', 'executionRequests.parties', 'executionRequests.legalCase']);
        $collQ = Collection::where('legal_case_id', $case->id)->when($variant === 'client', fn ($q) => $q->where('visible_to_client', true));

        return [
            'type' => 'case',
            'variant' => $variant,
            'title' => 'تقرير قضية',
            'subject' => $this->clientInfo($case->client, $variant),
            'cases' => [$this->caseBlock($case, $variant, $opt, true)],
            'executions' => $case->executionRequests->filter(fn ($e) => $variant !== 'client' || $e->status !== 'draft')->map(fn ($e) => $this->execBlock($e, $variant, $opt))->values()->all(),
            'totals' => Collection::totals($collQ),
            'options' => $opt,
        ];
    }

    private function clientInfo(Client $c, string $variant): array
    {
        return array_filter([
            'الاسم' => $c->name,
            'النوع' => Client::TYPES[$c->type],
            'رقم الهوية' => $c->type === 'individual' ? $c->national_id : null,
            'الرقم الوطني الموحد' => $c->type === 'organization' ? $c->unified_number : null,
            'السجل التجاري' => $c->type === 'organization' ? $c->commercial_registration : null,
            'المرجع لدى المكتب' => $c->reference,
            'الجوال' => $variant === 'internal' ? $c->phone : null,
        ], fn ($v) => filled($v));
    }

    private function caseBlock(LegalCase $c, string $variant, array $opt, bool $detailed): array
    {
        $filing = $c->filings->sortByDesc('id')->first();
        $next = $c->hearings->where('status', 'scheduled')->filter(fn ($h) => $h->scheduled_at?->isFuture())->sortBy('scheduled_at')->first();
        $j = $c->judgment;
        $fields = array_filter([
            'المرجع' => $c->reference,
            'رقم القضية' => $c->filings->whereNotNull('case_number')->sortByDesc('registered_on')->first()?->case_number,
            'رقم طلب التقييد' => $variant === 'internal' ? $filing?->filing_request_number : null,
            'التصنيف' => $c->category?->name,
            'صفة العميل' => LegalCase::CLIENT_ROLES[$c->client_role],
            'المرحلة' => LegalCase::STAGES[$c->stage],
            'حالة العمل' => LegalCase::WORK_STATUSES[$c->work_status],
            'المحكمة' => $filing?->court,
            'الدائرة' => $filing?->circuit,
            'حالة طلب التقييد' => $filing ? CourtFiling::STATUSES[$filing->status] : null,
            'الجلسة القادمة' => $next ? Hearing::KINDS[$next->kind].' — '.$this->dual($next->scheduled_at->copy()->setTimezone(config('office.display_timezone'))).' الساعة '.$next->scheduled_at->format('H:i') : null,
            'النتيجة' => $c->outcome ? LegalCase::OUTCOMES[$c->outcome] : null,
            'تاريخ النطق' => $this->dual($j?->pronounced_on),
            'صك الحكم' => $j ? ($j->deed_status === 'issued' ? 'صدر'.($j->deed_number ? ' — رقم '.$j->deed_number : '') : 'منتظر') : null,
            'النهائية' => $j && $j->finality_status === 'final' ? 'نهائي' : null,
            'المبلغ محل الدعوى' => $c->claim_amount ? number_format((float) $c->claim_amount, 2).' '.$c->currency : null,
        ], fn ($v) => filled($v));

        $block = [
            'title' => $c->title,
            'fields' => $fields,
            'summary' => $c->client_summary,
            'statusKey' => $c->work_status,
        ];
        if (! empty($opt['timeline'])) {
            $events = $c->timeline->when($variant === 'client', fn ($t) => $t->where('client_visible', true))->sortBy('occurred_at');
            $block['timeline'] = $events->take($detailed ? 60 : 8)->map(fn ($e) => ['date' => local_date($e->occurred_at), 'title' => $e->title,
                'details' => $detailed ? $e->details : null])->values()->all();
        }
        if ($variant === 'internal') {
            if (! empty($opt['parties'])) {
                $block['parties'] = $c->parties->map(fn ($p) => ['name' => $p->name, 'role' => LegalCase::CLIENT_ROLES[$p->role] ?? $p->role, 'id' => $p->identifier])->all();
            }
            if (! empty($opt['notes']) && $c->internal_notes) {
                $block['internalNotes'] = $c->internal_notes;
            }
            if (! empty($opt['tasks'])) {
                $block['tasks'] = $c->tasks->where('status', 'open')->map(fn ($t) => ['title' => $t->title, 'due' => $this->dual($t->due_on) ?? 'بانتظار تحديد موعد', 'kind' => Task::KINDS[$t->kind] ?? ''])->values()->all();
                $block['memos'] = $c->memos->map(fn ($m) => ['title' => $m->title, 'status' => Memo::STATUSES[$m->status], 'filed' => $this->dual($m->filed_on)])->all();
            }
            if (! empty($opt['hearings'])) {
                $block['hearings'] = $c->hearings->sortBy('scheduled_at')->map(fn ($h) => ['date' => $this->dual($h->scheduled_at?->copy()->setTimezone(config('office.display_timezone'))).' الساعة '.$h->scheduled_at?->format('H:i'),
                    'kind' => Hearing::KINDS[$h->kind], 'status' => Hearing::STATUSES[$h->status], 'result' => $h->result])->values()->all();
            }
        }

        return $block;
    }

    private function execBlock(ExecutionRequest $e, string $variant, array $opt): array
    {
        $block = [
            'title' => 'طلب تنفيذ '.($e->request_number ?? $e->reference),
            'fields' => array_filter([
                'المرجع' => $e->reference,
                'رقم الطلب' => $e->request_number,
                'القضية المرتبطة' => $e->legalCase?->reference,
                'نوع التنفيذ' => ExecutionRequest::EXECUTION_TYPES[$e->execution_type],
                'نوع السند' => $e->instrument_type === 'other' ? $e->instrument_label : ExecutionRequest::INSTRUMENT_TYPES[$e->instrument_type],
                'رقم السند' => $e->instrument_type === 'electronic_promissory_note' ? $e->note_number : ($e->instrument_type === 'judgment_deed' ? $e->deed_number : null),
                'قيمة السند' => $e->note_amount ? number_format((float) $e->note_amount, 2).' '.$e->note_currency : null,
                'محل التنفيذ' => $e->execution_type === 'direct' ? $e->subject_location : null,
                'الحالة' => ExecutionRequest::STATUSES[$e->status] ?? $e->status,
                'تاريخ التقديم' => $this->dual($e->submitted_on),
                'تاريخ القبول' => $this->dual($e->accepted_on),
                'صدور أمر التنفيذ' => $this->dual($e->order_issued_on),
                'التبليغ / الإعلان' => $this->dual($e->notified_on),
                'المبلغ المطلوب' => $e->amount_claimed ? number_format((float) $e->amount_claimed, 2).' '.$e->currency : null,
            ], fn ($v) => filled($v)),
            'summary' => $e->client_summary,
        ];
        if (! empty($opt['timeline'])) {
            $block['timeline'] = $e->timeline->when($variant === 'client', fn ($t) => $t->where('client_visible', true))->sortBy('occurred_at')->take(12)
                ->map(fn ($ev) => ['date' => local_date($ev->occurred_at), 'title' => $ev->title, 'details' => null])->values()->all();
        }
        if ($variant === 'internal') {
            $block['decisions'] = $e->decisions->map(fn ($d) => ['label' => $d->label, 'number' => $d->number, 'date' => $this->dual($d->decided_on)])->all();
            $block['actions'] = $e->actions->map(fn ($a) => ['type' => $a->type->name, 'date' => $this->dual($a->submitted_on), 'status' => \App\Models\ExecutionAction::STATUSES[$a->status]])->all();
        }

        return $block;
    }
}
