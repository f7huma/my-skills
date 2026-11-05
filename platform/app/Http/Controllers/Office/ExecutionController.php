<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\ActionType;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Deadline;
use App\Models\Document;
use App\Models\ExecutionAction;
use App\Models\ExecutionDecision;
use App\Models\ExecutionRequest;
use App\Models\Task;
use App\Services\Audit;
use App\Services\Calendar;
use App\Services\Timeline;
use App\Services\Workflow\ExecutionService;
use App\Services\Workflow\TaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExecutionController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['q' => 'nullable|string|max:100', 'type' => 'nullable|in:financial,direct', 'status' => 'nullable|string|max:30']);
        $rows = ExecutionRequest::with('client:id,name', 'legalCase:id,reference')
            ->when($f['q'] ?? null, fn ($w, $q) => $w->where(fn ($x) => $x->where('reference', 'ilike', "%{$q}%")->orWhere('request_number', 'ilike', "%{$q}%")
                ->orWhereHas('client', fn ($y) => $y->where('name', 'ilike', "%{$q}%"))->orWhereHas('parties', fn ($y) => $y->where('name', 'ilike', "%{$q}%"))))
            ->when($f['type'] ?? null, fn ($w, $v) => $w->where('execution_type', $v))
            ->when($f['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
            ->orderByDesc('updated_at')->paginate(25)->withQueryString();
        $received = Collection::whereIn('execution_request_id', $rows->pluck('id'))->where('kind', 'received')
            ->selectRaw('execution_request_id, currency, sum(amount)::text as total')->groupBy('execution_request_id', 'currency')->get()->groupBy('execution_request_id');

        return Inertia::render('Office/Executions/Index', [
            'executions' => $rows->through(fn (ExecutionRequest $e) => [
                'id' => $e->id, 'reference' => $e->reference, 'requestNumber' => $e->request_number, 'client' => $e->client->name,
                'type' => ExecutionRequest::EXECUTION_TYPES[$e->execution_type], 'instrument' => $e->instrument_type === 'other' ? ($e->instrument_label ?: 'أخرى') : ExecutionRequest::INSTRUMENT_TYPES[$e->instrument_type],
                'status' => $e->status, 'statusLabel' => ExecutionRequest::STATUSES[$e->status] ?? $e->status, 'case' => $e->legalCase?->reference,
                'amount' => $e->amount_claimed, 'currency' => $e->currency,
                'received' => ($received[$e->id] ?? collect())->map(fn ($r) => ['currency' => $r->currency, 'total' => \App\Models\Money::normalize($r->total)])->values(),
            ]),
            'filters' => $f,
            'statuses' => Office::options(ExecutionRequest::STATUSES),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Office/Executions/Create', [
            'clients' => Client::whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'executionTypes' => Office::options(ExecutionRequest::EXECUTION_TYPES),
            'instrumentTypes' => Office::options(ExecutionRequest::INSTRUMENT_TYPES),
            'statuses' => Office::options(ExecutionRequest::STATUSES),
            'currencies' => config('office.currencies'),
            'cases' => \App\Models\LegalCase::whereNull('archived_at')->orderByDesc('updated_at')->get(['id', 'client_id', 'reference', 'title'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->reference.' — '.$c->title, 'clientId' => $c->client_id]),
            'preset' => ['client_id' => $request->integer('client') ?: null, 'case_id' => $request->integer('case') ?: null,
                'status' => in_array($request->query('status'), ['completed', 'closed'], true) ? $request->query('status') : null],
        ]);
    }

    public static function rules(): array
    {
        return [
            'execution_type' => 'required|in:financial,direct',
            'instrument_type' => 'required|in:judgment_deed,electronic_promissory_note,other',
            'instrument_label' => 'nullable|required_if:instrument_type,other|string|max:120',
            'request_number' => 'nullable|string|max:60',
            'status' => ['required', Rule::in(array_keys(ExecutionRequest::STATUSES))],
            'submitted_on' => 'nullable|date', 'accepted_on' => 'nullable|date', 'order_issued_on' => 'nullable|date',
            'notified_on' => 'nullable|date', 'notification_method' => 'nullable|in:notification,publication',
            'enforcement_started_on' => 'nullable|date',
            'deed_number' => 'nullable|string|max:60', 'deed_date' => 'nullable|date',
            'note_number' => 'nullable|string|max:60', 'note_amount' => 'nullable|numeric|min:0|max:9999999999999999',
            'note_currency' => ['nullable', Rule::in(config('office.currencies'))], 'note_issued_on' => 'nullable|date', 'note_due_on' => 'nullable|date',
            'subject_location' => 'nullable|required_if:execution_type,direct|string|max:250',
            'obligation_description' => 'nullable|required_if:execution_type,direct|string|max:3000',
            'amount_claimed' => 'nullable|numeric|min:0|max:9999999999999999',
            'currency' => ['required', Rule::in(config('office.currencies'))],
            'client_summary' => 'nullable|string|max:3000',
            'internal_notes' => 'nullable|string|max:10000',
            'weekly_followup' => 'boolean',
        ];
    }

    public static function messages(): array
    {
        return ['subject_location.required_if' => 'حدد محل التنفيذ في التنفيذ المباشر.', 'obligation_description.required_if' => 'صف الالتزام المطلوب تنفيذه.',
            'instrument_label.required_if' => 'سمِّ نوع السند.'];
    }

    public function store(Request $request, ExecutionService $svc)
    {
        $data = $request->validate(self::rules() + [
            'client_id' => 'required|exists:clients,id',
            'parties' => 'array|max:20', 'parties.*.name' => 'required|string|max:200', 'parties.*.role' => 'required|in:applicant,respondent',
            'parties.*.party_type' => 'nullable|in:individual,organization', 'parties.*.identifier' => 'nullable|string|max:30',
            'legal_case_id' => 'nullable|integer|exists:legal_cases,id',
        ], self::messages());
        $case = ! empty($data['legal_case_id']) ? \App\Models\LegalCase::find($data['legal_case_id']) : null;
        if ($case && $case->client_id !== (int) $data['client_id']) {
            return back()->withErrors(['legal_case_id' => 'القضية المختارة لعميل آخر.'])->withInput();
        }
        $e = $svc->create($data, $case);

        return redirect()->route('executions.show', $e)->with('success', 'أُنشئ طلب التنفيذ '.$e->reference.'.');
    }

    public function show(ExecutionRequest $execution, Calendar $cal, ExecutionService $svc)
    {
        $e = $execution->load(['client:id,name', 'legalCase:id,reference,title', 'parties', 'decisions.document', 'actions.type',
            'tasks' => fn ($q) => $q->orderByRaw('due_on asc nulls last'), 'deadlines.rule', 'timeline' => fn ($q) => $q->orderByDesc('occurred_at'), 'timeline.user:id,name']);
        $docs = Document::alive()->where('documentable_type', $e->getMorphClass())->where('documentable_id', $e->id)->orderByDesc('created_at')->get();

        return Inertia::render('Office/Executions/Show', [
            'execution' => [
                'id' => $e->id, 'reference' => $e->reference, 'client' => $e->client->name, 'clientId' => $e->client_id,
                'case' => $e->legalCase ? ['id' => $e->legalCase->id, 'reference' => $e->legalCase->reference, 'title' => $e->legalCase->title] : null,
                'lockVersion' => $e->lock_version, 'weeklyDue' => $svc->weeklyFollowupDue($e), 'legal_case_id' => $e->legal_case_id ? (string) $e->legal_case_id : '',
            ] + collect($e->getAttributes())->only(array_keys(self::rules()))->map(fn ($v, $k) => in_array($k, ['weekly_followup'], true) ? (bool) $v : $v)
                ->merge(collect(['submitted_on', 'accepted_on', 'order_issued_on', 'notified_on', 'enforcement_started_on', 'deed_date', 'note_issued_on', 'note_due_on'])
                    ->mapWithKeys(fn ($k) => [$k => $e->{$k}?->toDateString()]))->all(),
            'parties' => $e->parties->map->only(['id', 'name', 'role', 'party_type', 'identifier']),
            'cases' => \App\Models\LegalCase::where('client_id', $e->client_id)->orderByDesc('updated_at')->get(['id', 'reference', 'title'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->reference.' — '.$c->title]),
            'decisions' => $e->decisions->map(fn (ExecutionDecision $d) => ['id' => $d->id, 'label' => $d->label, 'number' => $d->number,
                'decidedOn' => $d->decided_on?->toDateString(), 'notes' => $d->notes, 'document' => $d->document ? Office::document($d->document) : null]),
            'actions' => $e->actions->sortByDesc('submitted_on')->values()->map(fn (ExecutionAction $a) => ['id' => $a->id, 'type' => $a->type->name, 'typeId' => $a->action_type_id,
                'submittedOn' => $a->submitted_on?->toDateString(), 'status' => $a->status, 'statusLabel' => ExecutionAction::STATUSES[$a->status],
                'result' => $a->result, 'eligibleOn' => $a->eligible_resubmission_on?->toDateString(), 'nextFollowupOn' => $a->next_followup_on?->toDateString(), 'notes' => $a->notes]),
            'tasks' => $e->tasks->map(fn (Task $t) => CaseController::task($t)),
            'deadlines' => $e->deadlines->map(fn ($d) => DeadlineController::present($d, $cal)),
            'timeline' => $e->timeline->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'details' => $t->details, 'at' => $t->occurred_at->toIso8601String(),
                'clientVisible' => $t->client_visible, 'user' => $t->user?->name]),
            'documents' => $docs->map(fn ($d) => Office::document($d)),
            'collections' => $e->collections()->with('document')->orderByDesc('occurred_on')->get()->map(fn ($c) => CollectionController::present($c)),
            'totals' => Collection::totals(Collection::where('execution_request_id', $e->id)),
            'options' => [
                'executionTypes' => Office::options(ExecutionRequest::EXECUTION_TYPES),
                'instrumentTypes' => Office::options(ExecutionRequest::INSTRUMENT_TYPES),
                'statuses' => Office::options(ExecutionRequest::STATUSES),
                'actionTypes' => ActionType::where('is_active', true)->orderBy('id')->get()->map(fn ($t) => ['value' => $t->id, 'label' => $t->name,
                    'interval' => $t->resubmission_interval_days, 'dayType' => $t->interval_day_type, 'fromLast' => $t->interval_from_last_same_type, 'condition' => $t->condition_note]),
                'actionStatuses' => Office::options(ExecutionAction::STATUSES),
                'decisionLabels' => ExecutionDecision::LABELS,
                'currencies' => config('office.currencies'),
                'documentCategories' => Office::options(Document::CATEGORIES),
                'deadlineKinds' => Office::options(Deadline::KINDS),
                'certainties' => Office::options(Deadline::CERTAINTY),
                'collectionKinds' => Office::options(Collection::KINDS),
                'collectionSources' => Office::options(Collection::SOURCES),
                'taskKinds' => Office::options(Task::KINDS),
                'dueModes' => Office::options(collect(Task::DUE_MODES)->except('next_hearing')->all()),
            ],
        ]);
    }

    public function update(Request $request, ExecutionRequest $execution, ExecutionService $svc)
    {
        $data = $request->validate(self::rules() + ['lock_version' => 'required|integer', 'legal_case_id' => 'nullable|integer|exists:legal_cases,id'], self::messages());
        if (! empty($data['legal_case_id']) && \App\Models\LegalCase::whereKey($data['legal_case_id'])->value('client_id') !== $execution->client_id) {
            return back()->withErrors(['legal_case_id' => 'القضية المختارة لعميل آخر.']);
        }
        $svc->update($execution, $data, (int) $data['lock_version']);

        return back()->with('success', 'حُفظ طلب التنفيذ.');
    }

    public function storeParty(Request $request, ExecutionRequest $execution)
    {
        $execution->parties()->create($request->validate(['name' => 'required|string|max:200', 'role' => 'required|in:applicant,respondent',
            'party_type' => 'required|in:individual,organization', 'identifier' => 'nullable|string|max:30']));

        return back();
    }

    /** قرار 34 / 46 / تسمية كما ظهرت فعليًا — يُسجَّل من واقع المستند فقط */
    public function storeDecision(Request $request, ExecutionRequest $execution)
    {
        $data = $request->validate(['label' => 'required|string|max:60', 'number' => 'nullable|string|max:60', 'decided_on' => 'required|date',
            'notes' => 'nullable|string|max:2000', 'document' => 'nullable|string']);
        $d = $execution->decisions()->create(['label' => $data['label'], 'number' => $data['number'] ?? null, 'decided_on' => $data['decided_on'],
            'notes' => $data['notes'] ?? null, 'document_id' => ProceedingsController::docId($data['document'] ?? null, $execution->client_id)]);
        Timeline::record($execution, 'execution.decision', $d->label.($d->number ? ' رقم '.$d->number : ''), 'تاريخ القرار: '.$d->decided_on->toDateString(), $d, true);
        Audit::log('execution.decision_recorded', $d, ['label' => $d->label]);

        return back()->with('success', 'سُجّل القرار.');
    }

    public function storeAction(Request $request, ExecutionRequest $execution, ExecutionService $svc)
    {
        $data = $request->validate(['action_type_id' => 'required|exists:action_types,id', 'submitted_on' => 'nullable|date',
            'status' => ['required', Rule::in(array_keys(ExecutionAction::STATUSES))], 'result' => 'nullable|string|max:2000',
            'next_followup_on' => 'nullable|date', 'notes' => 'nullable|string|max:2000']);
        $svc->addAction($execution, $data);

        return back()->with('success', 'سُجّل طلب الإجراء.');
    }

    public function updateAction(Request $request, ExecutionAction $action)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ExecutionAction::STATUSES))], 'result' => 'nullable|string|max:2000',
            'next_followup_on' => 'nullable|date', 'notes' => 'nullable|string|max:2000']);
        $old = $action->status;
        $action->update($data);
        if ($old !== $action->status) {
            Timeline::record($action->executionRequest, 'execution.action_status', 'طلب إجراء: '.$action->type->name.' — '.ExecutionAction::STATUSES[$action->status], $action->result, $action, false);
        }
        Audit::log('execution.action_updated', $action, ['status' => $action->status]);

        return back()->with('success', 'حُدّث طلب الإجراء.');
    }

    public function logFollowup(Request $request, ExecutionRequest $execution)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        Timeline::record($execution, 'execution.followup', 'متابعة داخلية للملف', $data['note'] ?? null, $execution, false);

        return back()->with('success', 'سُجّلت المتابعة.');
    }

    public function storeTask(Request $request, ExecutionRequest $execution, TaskService $svc)
    {
        $data = $request->validate((new ProceedingsController)->taskRules());
        $svc->create($data + ['execution_request_id' => $execution->id, 'client_id' => $execution->client_id]);

        return back()->with('success', 'أُضيفت المهمة.');
    }
}
