<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\CaseCategory;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Collection;
use App\Models\CourtFiling;
use App\Models\Deadline;
use App\Models\Document;
use App\Models\ExecutionRequest;
use App\Models\Hearing;
use App\Models\Judgment;
use App\Models\LegalCase;
use App\Models\Memo;
use App\Models\ReconciliationRequest;
use App\Models\SavedFilter;
use App\Models\Task;
use App\Services\Audit;
use App\Services\Calendar;
use App\Services\Timeline;
use App\Services\Workflow\CaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:100', 'status' => 'nullable|string|max:30', 'stage' => 'nullable|string|max:30',
            'category' => 'nullable|integer', 'role' => 'nullable|string|max:20', 'client' => 'nullable|integer',
        ]);
        $cases = LegalCase::with(['client:id,name', 'category:id,name', 'filings:id,legal_case_id,case_number'])
            ->when($f['q'] ?? null, function ($w, $q) {
                $like = '%'.$q.'%';
                $w->where(fn ($x) => $x->where('reference', 'ilike', $like)->orWhere('title', 'ilike', $like)
                    ->orWhereHas('filings', fn ($y) => $y->where('case_number', 'ilike', $like)->orWhere('filing_request_number', 'ilike', $like))
                    ->orWhereHas('parties', fn ($y) => $y->where('name', 'ilike', $like))
                    ->orWhereHas('client', fn ($y) => $y->where('name', 'ilike', $like)));
            })
            ->when($f['status'] ?? null, fn ($w, $s) => $s === 'open' ? $w->whereIn('work_status', LegalCase::ACTIVE_STATUSES) : $w->where('work_status', $s))
            ->when($f['stage'] ?? null, fn ($w, $s) => $w->where('stage', $s))
            ->when($f['category'] ?? null, fn ($w, $s) => $w->where('category_id', $s))
            ->when($f['role'] ?? null, fn ($w, $s) => $w->where('client_role', $s))
            ->when($f['client'] ?? null, fn ($w, $s) => $w->where('client_id', $s))
            ->whereNull('archived_at')
            ->orderByDesc('updated_at')->paginate(25)->withQueryString();

        $next = Hearing::whereIn('legal_case_id', $cases->pluck('id'))->where('status', 'scheduled')->where('scheduled_at', '>=', now()->startOfDay())
            ->orderBy('scheduled_at')->get()->groupBy('legal_case_id')->map->first();

        return Inertia::render('Office/Cases/Index', [
            'cases' => $cases->through(fn (LegalCase $c) => [
                'id' => $c->id, 'reference' => $c->reference, 'title' => $c->title, 'client' => $c->client->name, 'clientId' => $c->client_id,
                'caseNumber' => $c->filings->whereNotNull('case_number')->first()?->case_number, 'stage' => LegalCase::STAGES[$c->stage],
                'status' => $c->work_status, 'statusLabel' => LegalCase::WORK_STATUSES[$c->work_status], 'category' => $c->category?->name,
                'role' => LegalCase::CLIENT_ROLES[$c->client_role], 'nextHearing' => $next->get($c->id)?->scheduled_at?->toIso8601String(),
                'updatedAt' => $c->updated_at->toIso8601String(),
            ]),
            'filters' => $f,
            'savedFilters' => SavedFilter::where('user_id', $request->user()->id)->where('page', 'cases')->get(['id', 'name', 'query']),
            'options' => $this->options(),
        ]);
    }

    public function options(): array
    {
        return [
            'stages' => Office::options(LegalCase::STAGES),
            'statuses' => Office::options(LegalCase::WORK_STATUSES),
            'roles' => Office::options(LegalCase::CLIENT_ROLES),
            'outcomes' => Office::options(LegalCase::OUTCOMES),
            'categories' => CaseCategory::where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'currencies' => config('office.currencies'),
        ];
    }

    public function create(Request $request)
    {
        return Inertia::render('Office/Cases/Create', [
            'clients' => Client::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'reference'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name.' — '.$c->reference]),
            'poas' => \App\Models\PowerOfAttorney::where('status', 'active')->get(['id', 'client_id', 'number', 'expires_on'])
                ->map(fn ($p) => ['value' => $p->id, 'clientId' => $p->client_id, 'label' => 'وكالة '.$p->number.($p->expires_on ? ' — تنتهي '.$p->expires_on->toDateString() : '')]),
            'options' => $this->options(),
            'preset' => ['client_id' => $request->integer('client') ?: null],
        ]);
    }

    /** المسودة بأقل البيانات: العميل والعنوان فقط. لا تُشترط حقول المراحل القضائية عند الإنشاء */
    public function store(Request $request, CaseService $cases)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:250',
            'category_id' => 'nullable|exists:case_categories,id',
            'client_role' => ['required', Rule::in(array_keys(LegalCase::CLIENT_ROLES))],
            'claim_amount' => 'nullable|numeric|min:0|max:9999999999999999',
            'currency' => ['nullable', Rule::in(config('office.currencies'))],
            'power_of_attorney_id' => ['nullable', Rule::exists('powers_of_attorney', 'id')->where('client_id', $request->input('client_id'))],
            'start_point' => 'required|in:draft,reconciliation,filing,court',
            'case_number' => 'nullable|string|max:60',
            'court' => 'nullable|string|max:150',
            'circuit' => 'nullable|string|max:150',
            'parties' => 'array|max:20',
            'parties.*.name' => 'required|string|max:200',
            'parties.*.role' => 'required|in:plaintiff,defendant,other',
            'parties.*.party_type' => 'nullable|in:individual,organization',
            'parties.*.identifier' => 'nullable|string|max:30',
        ]);
        $case = LegalCase::create([
            'client_id' => $data['client_id'], 'title' => $data['title'], 'category_id' => $data['category_id'] ?? null,
            'client_role' => $data['client_role'], 'claim_amount' => $data['claim_amount'] ?? null, 'currency' => $data['currency'] ?? 'SAR',
            'power_of_attorney_id' => $data['power_of_attorney_id'] ?? null,
            'stage' => match ($data['start_point']) { 'draft' => 'intake', 'reconciliation' => 'reconciliation', 'filing' => 'filing', 'court' => 'court' },
            'work_status' => $data['start_point'] === 'draft' ? 'draft' : 'active',
        ]);
        foreach ($data['parties'] ?? [] as $p) {
            $case->parties()->create($p + ['party_type' => 'individual']);
        }
        if ($data['start_point'] === 'reconciliation') {
            $case->reconciliations()->create(['status' => 'submitted']);
        } elseif ($data['start_point'] === 'filing') {
            $case->filings()->create(['status' => 'draft']);
        } elseif ($data['start_point'] === 'court') {
            // قضية مقيدة مباشرة — بما في ذلك قضايا المدعى عليه
            $case->filings()->create(['status' => 'registered', 'case_number' => $data['case_number'] ?? null, 'court' => $data['court'] ?? null, 'circuit' => $data['circuit'] ?? null]);
        }
        if ($case->claim_amount) {
            Collection::create(['client_id' => $case->client_id, 'legal_case_id' => $case->id, 'kind' => 'claimed', 'source' => 'other',
                'amount' => $case->claim_amount, 'currency' => $case->currency, 'occurred_on' => today_local(), 'entry_key' => (string) \Illuminate\Support\Str::uuid(),
                'created_by' => $request->user()->id, 'description' => 'المبلغ محل الدعوى']);
        }
        Timeline::record($case, 'case.created', 'فتح ملف القضية', null, $case, false);
        Audit::log('case.created', $case, ['start' => $data['start_point']]);

        return redirect()->route('cases.show', $case)->with('success', 'أُنشئت القضية '.$case->reference.'.');
    }

    public function show(Request $request, LegalCase $case, Calendar $cal)
    {
        $case->load(['client:id,name,type', 'category:id,name', 'powerOfAttorney', 'parties', 'reconciliations.report', 'filings',
            'hearings' => fn ($q) => $q->orderBy('scheduled_at'), 'hearings.minutes', 'tasks' => fn ($q) => $q->orderByRaw('due_on asc nulls last'),
            'tasks.dueChanges', 'memos', 'judgment', 'executionRequests', 'deadlines.rule', 'timeline' => fn ($q) => $q->orderByDesc('occurred_at'), 'timeline.user:id,name']);
        $claimDocs = $case->source_claim_id ? Document::alive()->where('documentable_type', (new Claim)->getMorphClass())->where('documentable_id', $case->source_claim_id)->get() : collect();
        $docs = Document::alive()->where(fn ($q) => $q->where(fn ($x) => $x->where('documentable_type', $case->getMorphClass())->where('documentable_id', $case->id)))
            ->orderByDesc('created_at')->get()->concat($claimDocs);
        $j = $case->judgment;

        return Inertia::render('Office/Cases/Show', [
            'tab' => $request->query('tab', 'summary'),
            'case' => [
                'id' => $case->id, 'reference' => $case->reference, 'title' => $case->title, 'clientId' => $case->client_id, 'client' => $case->client->name,
                'categoryId' => $case->category_id, 'category' => $case->category?->name, 'clientRole' => $case->client_role, 'clientRoleLabel' => LegalCase::CLIENT_ROLES[$case->client_role],
                'stage' => $case->stage, 'stageLabel' => LegalCase::STAGES[$case->stage], 'status' => $case->work_status, 'statusLabel' => LegalCase::WORK_STATUSES[$case->work_status],
                'outcome' => $case->outcome, 'outcomeLabel' => $case->outcome ? LegalCase::OUTCOMES[$case->outcome] : null,
                'claimAmount' => $case->claim_amount, 'currency' => $case->currency, 'poaId' => $case->power_of_attorney_id,
                'poa' => $case->powerOfAttorney ? ['number' => $case->powerOfAttorney->number, 'state' => $case->powerOfAttorney->effectiveStatus()] : null,
                'clientSummary' => $case->client_summary, 'clientSummaryUpdatedAt' => $case->client_summary_updated_at?->toIso8601String(),
                'internalNotes' => $case->internal_notes, 'lockVersion' => $case->lock_version, 'sourceClaimId' => $case->source_claim_id,
                'createdAt' => $case->created_at->toIso8601String(),
            ],
            'parties' => $case->parties->map->only(['id', 'name', 'role', 'party_type', 'identifier']),
            'reconciliations' => $case->reconciliations->map(fn (ReconciliationRequest $r) => [
                'id' => $r->id, 'number' => $r->request_number, 'submittedOn' => $r->submitted_on?->toDateString(), 'status' => $r->status,
                'statusLabel' => ReconciliationRequest::STATUSES[$r->status], 'outcomeOn' => $r->outcome_on?->toDateString(), 'agreedAmount' => $r->agreed_amount,
                'currency' => $r->currency, 'notes' => $r->notes, 'movedToCourt' => (bool) $r->moved_to_court_at,
                'report' => $r->report ? Office::document($r->report) : null]),
            'filings' => $case->filings->map(fn (CourtFiling $f) => [
                'id' => $f->id, 'requestNumber' => $f->filing_request_number, 'submittedOn' => $f->submitted_on?->toDateString(), 'status' => $f->status,
                'statusLabel' => CourtFiling::STATUSES[$f->status], 'caseNumber' => $f->case_number, 'registeredOn' => $f->registered_on?->toDateString(),
                'referredOn' => $f->referred_on?->toDateString(), 'court' => $f->court, 'circuit' => $f->circuit, 'notes' => $f->notes]),
            'hearings' => $case->hearings->map(fn (Hearing $h) => [
                'id' => $h->id, 'kind' => $h->kind, 'kindLabel' => Hearing::KINDS[$h->kind], 'at' => $h->scheduled_at?->toIso8601String(),
                'localAt' => $h->scheduled_at?->copy()->setTimezone(config('office.display_timezone'))->format('Y-m-d\TH:i'),
                'authority' => $h->authority, 'meetingUrl' => $h->meeting_url, 'status' => $h->status, 'statusLabel' => Hearing::STATUSES[$h->status],
                'attendance' => $h->attendance, 'result' => $h->result, 'directives' => $h->directives, 'changeReason' => $h->change_reason,
                'minutes' => $h->minutes ? Office::document($h->minutes) : null, 'nextHearingId' => $h->next_hearing_id, 'lockVersion' => $h->lock_version,
                'reconciliationId' => $h->reconciliation_request_id, 'filingId' => $h->court_filing_id,
                'anchoredTasks' => $case->tasks->where('anchor_hearing_id', $h->id)->where('status', 'open')->count()]),
            'tasks' => $case->tasks->map(fn (Task $t) => self::task($t)),
            'memos' => $case->memos->map(fn (Memo $m) => ['id' => $m->id, 'title' => $m->title, 'type' => Memo::TYPES[$m->type], 'status' => $m->status,
                'statusLabel' => Memo::STATUSES[$m->status], 'dueOn' => $m->due_on?->toDateString(), 'filedOn' => $m->filed_on?->toDateString(),
                'approvedAt' => $m->approved_at?->toIso8601String(), 'version' => $m->current_version]),
            'judgment' => $j ? [
                'outcome' => $j->outcome, 'outcomeLabel' => LegalCase::OUTCOMES[$j->outcome], 'pronouncedOn' => $j->pronounced_on?->toDateString(),
                'pronouncedHijri' => $cal->hijri($j->pronounced_on), 'deedStatus' => $j->deed_status, 'deedNumber' => $j->deed_number,
                'deedDate' => $j->deed_date?->toDateString(), 'objectionStatus' => $j->objection_status, 'objectionFiledOn' => $j->objection_filed_on?->toDateString(),
                'finality' => $j->finality_status, 'finalityConfirmedAt' => $j->finality_confirmed_at?->toIso8601String(),
                'executionDecision' => $j->execution_decision, 'resumedOn' => $j->resumed_on?->toDateString(), 'notes' => $j->notes, 'id' => $j->id,
            ] : null,
            'executions' => $case->executionRequests->map(fn (ExecutionRequest $e) => ['id' => $e->id, 'reference' => $e->reference, 'requestNumber' => $e->request_number,
                'type' => ExecutionRequest::EXECUTION_TYPES[$e->execution_type], 'instrument' => ExecutionRequest::INSTRUMENT_TYPES[$e->instrument_type],
                'status' => ExecutionRequest::STATUSES[$e->status] ?? $e->status]),
            'deadlines' => $case->deadlines->map(fn (Deadline $d) => DeadlineController::present($d, $cal)),
            'timeline' => $case->timeline->map(fn ($e) => ['id' => $e->id, 'type' => $e->type, 'title' => $e->title, 'details' => $e->details,
                'at' => $e->occurred_at->toIso8601String(), 'clientVisible' => $e->client_visible, 'user' => $e->user?->name]),
            'documents' => $docs->map(fn ($d) => Office::document($d))->values(),
            'collections' => $case->collections()->with('document')->orderByDesc('occurred_on')->get()->map(fn ($c) => CollectionController::present($c)),
            'totals' => Collection::totals(Collection::where('legal_case_id', $case->id)),
            'options' => $this->options() + [
                'hearingKinds' => Office::options(Hearing::KINDS),
                'reconciliationStatuses' => Office::options(ReconciliationRequest::STATUSES),
                'filingStatuses' => Office::options(CourtFiling::STATUSES),
                'taskKinds' => Office::options(Task::KINDS),
                'dueModes' => Office::options(Task::DUE_MODES),
                'memoTypes' => Office::options(Memo::TYPES),
                'deedStatuses' => Office::options(Judgment::DEED_STATUSES),
                'objectionStatuses' => Office::options(Judgment::OBJECTION_STATUSES),
                'executionDecisions' => Office::options(Judgment::EXECUTION_DECISIONS),
                'documentCategories' => Office::options(Document::CATEGORIES),
                'deadlineKinds' => Office::options(Deadline::KINDS),
                'certainties' => Office::options(Deadline::CERTAINTY),
                'collectionKinds' => Office::options(Collection::KINDS),
                'collectionSources' => Office::options(Collection::SOURCES),
                'poas' => \App\Models\PowerOfAttorney::where('client_id', $case->client_id)->get()->map(fn ($p) => ['value' => $p->id, 'label' => 'وكالة '.$p->number.' ('.match ($p->effectiveStatus()) {
                    'active' => 'سارية', 'expiring' => 'قريبة الانتهاء', 'expired' => 'منتهية', 'revoked' => 'ملغاة' }.')']),
            ],
        ]);
    }

    public static function task(Task $t): array
    {
        return [
            'id' => $t->id, 'title' => $t->title, 'description' => $t->description, 'kind' => $t->kind, 'kindLabel' => Task::KINDS[$t->kind] ?? $t->kind,
            'dueMode' => $t->due_mode, 'dueOn' => $t->due_on?->toDateString(), 'relativeFrom' => $t->relative_from?->toDateString(),
            'relativeEvent' => $t->relative_event, 'relativeDays' => $t->relative_days, 'relativeDayType' => $t->relative_day_type,
            'anchorHearingId' => $t->anchor_hearing_id, 'anchorOffset' => $t->anchor_offset_days, 'status' => $t->status,
            'completedAt' => $t->completed_at?->toIso8601String(), 'lockVersion' => $t->lock_version, 'sourceHearingId' => $t->source_hearing_id,
            'changes' => $t->relationLoaded('dueChanges') ? $t->dueChanges->map(fn ($c) => ['old' => $c->old_due_on?->toDateString(), 'new' => $c->new_due_on?->toDateString(), 'reason' => $c->reason, 'at' => $c->created_at->toIso8601String()]) : [],
        ];
    }

    public function update(Request $request, LegalCase $case)
    {
        $data = $request->validate([
            'title' => 'required|string|max:250',
            'category_id' => 'nullable|exists:case_categories,id',
            'client_role' => ['required', Rule::in(array_keys(LegalCase::CLIENT_ROLES))],
            'stage' => ['required', Rule::in(array_keys(LegalCase::STAGES))],
            'claim_amount' => 'nullable|numeric|min:0|max:9999999999999999',
            'currency' => ['required', Rule::in(config('office.currencies'))],
            'power_of_attorney_id' => ['nullable', Rule::exists('powers_of_attorney', 'id')->where('client_id', $case->client_id)],
            'internal_notes' => 'nullable|string|max:20000',
            'lock_version' => 'required|integer',
        ]);
        $case->fill(collect($data)->except('lock_version')->all());
        $changed = array_keys($case->getDirty());
        $case->saveWithVersion((int) $data['lock_version']);
        Audit::log('case.updated', $case, ['fields' => $changed]);

        return back()->with('success', 'حُفظت بيانات القضية.');
    }

    public function updateStatus(Request $request, LegalCase $case, CaseService $svc)
    {
        $data = $request->validate(['work_status' => ['required', Rule::in(array_keys(LegalCase::WORK_STATUSES))], 'note' => 'nullable|string|max:500']);
        $svc->changeStatus($case, $data['work_status'], $data['note'] ?? null);

        return back()->with('success', 'حُدّثت حالة العمل.');
    }

    /** الملخص الموجّه للعميل منفصل عن ملاحظات المحامي ويظهر كآخر تحديث معتمد */
    public function clientSummary(Request $request, LegalCase $case)
    {
        $data = $request->validate(['client_summary' => 'nullable|string|max:3000', 'publish_event' => 'boolean']);
        $case->forceFill(['client_summary' => $data['client_summary'], 'client_summary_updated_at' => now()])->save();
        if ($request->boolean('publish_event') && $data['client_summary']) {
            Timeline::record($case, 'case.client_update', 'تحديث من المكتب', $data['client_summary'], $case, true);
        }
        Audit::log('case.client_summary_updated', $case);

        return back()->with('success', 'نُشر التحديث في بوابة العميل.');
    }

    public function storeParty(Request $request, LegalCase $case)
    {
        $data = $request->validate(['name' => 'required|string|max:200', 'role' => 'required|in:plaintiff,defendant,other',
            'party_type' => 'required|in:individual,organization', 'identifier' => 'nullable|string|max:30']);
        $p = $case->parties()->create($data);
        Audit::log('case.party_added', $case, ['party' => $p->id]);

        return back();
    }

    public function destroyParty(LegalCase $case, int $party)
    {
        $case->parties()->whereKey($party)->delete();
        Audit::log('case.party_removed', $case, ['party' => $party]);

        return back();
    }

    public function saveFilter(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:60', 'query' => 'required|array', 'page' => 'required|in:cases,executions,deadlines']);
        SavedFilter::create(['user_id' => $request->user()->id, 'page' => $data['page'], 'name' => $data['name'],
            'query' => collect($data['query'])->only(['q', 'status', 'stage', 'category', 'role', 'client', 'kind', 'certainty', 'type'])->all()]);

        return back()->with('success', 'حُفظ الفلتر.');
    }

    public function deleteFilter(Request $request, SavedFilter $filter)
    {
        abort_unless($filter->user_id === $request->user()->id, 404);
        $filter->delete();

        return back();
    }
}
