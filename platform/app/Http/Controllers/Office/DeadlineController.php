<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\Client;
use App\Models\Deadline;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Services\Audit;
use App\Services\Calendar;
use App\Services\Workflow\DeadlineService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** الشاشة الموحدة لمتابعة المواعيد المهمة */
class DeadlineController extends Controller
{
    public static function present(Deadline $d, Calendar $cal): array
    {
        return [
            'id' => $d->id, 'kind' => $d->kind, 'kindLabel' => Deadline::KINDS[$d->kind] ?? $d->kind, 'certainty' => $d->certainty,
            'certaintyLabel' => Deadline::CERTAINTY[$d->certainty], 'reason' => $d->reason, 'startOn' => $d->start_on?->toDateString(),
            'endOn' => $d->end_on?->toDateString(), 'endHijri' => $cal->hijri($d->end_on), 'duration' => $d->duration, 'dayType' => $d->day_type,
            'remaining' => $d->end_on ? $cal->remaining($d->end_on, $d->day_type === 'business' ? 'business' : 'calendar') : null,
            'basis' => $d->basis, 'source' => $d->source, 'status' => $d->status, 'extensionReason' => $d->extension_reason,
            'rule' => $d->rule ? ['name' => $d->rule->name, 'version' => $d->rule->version, 'source' => $d->rule->source_reference] : null,
            'updatedAt' => $d->updated_at?->toIso8601String(), 'updatedBy' => $d->relationLoaded('updater') ? $d->updater?->name : null,
            'caseId' => $d->legal_case_id, 'executionId' => $d->execution_request_id,
            'ref' => $d->relationLoaded('legalCase') ? ($d->legalCase?->reference ?? ($d->relationLoaded('executionRequest') ? $d->executionRequest?->reference : null)) : null,
            'client' => $d->relationLoaded('client') ? $d->client?->name : null,
        ];
    }

    public function index(Request $request, Calendar $cal)
    {
        $f = $request->validate(['kind' => 'nullable|string|max:40', 'certainty' => 'nullable|string|max:30', 'status' => 'nullable|in:open,alerted,closed,all']);
        $rows = Deadline::with(['client:id,name', 'legalCase:id,reference', 'executionRequest:id,reference', 'rule', 'updater:id,name'])
            ->when($f['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($f['certainty'] ?? null, fn ($q, $v) => $q->where('certainty', $v))
            ->when(($f['status'] ?? 'active') !== 'all', fn ($q) => ($f['status'] ?? null) ? $q->where('status', $f['status']) : $q->whereIn('status', ['open', 'alerted']))
            ->orderByRaw('end_on asc nulls first')->limit(300)->get();

        return Inertia::render('Office/Deadlines/Index', [
            'deadlines' => $rows->map(fn ($d) => self::present($d, $cal)),
            'filters' => $f,
            'kinds' => Office::options(Deadline::KINDS),
            'certainties' => Office::options(Deadline::CERTAINTY),
            'cases' => LegalCase::whereIn('work_status', ['draft', 'active', 'suspended', 'struck_off'])->orderByDesc('updated_at')->limit(300)->get(['id', 'reference', 'title'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->reference.' — '.$c->title]),
            'executions' => ExecutionRequest::orderByDesc('updated_at')->limit(300)->get(['id', 'reference'])->map(fn ($e) => ['value' => $e->id, 'label' => $e->reference]),
            'today' => today_local()->toDateString(),
            'todayHijri' => $cal->hijri(today_local()),
        ]);
    }

    private function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(array_keys(Deadline::KINDS))],
            'certainty' => ['required', Rule::in(array_keys(Deadline::CERTAINTY))],
            'reason' => 'required|string|max:250',
            'end_on' => 'nullable|date',
            'start_on' => 'nullable|date',
            'duration' => 'nullable|integer|min:0|max:3650',
            'day_type' => 'nullable|in:calendar,business',
            'basis' => 'nullable|string|max:500',
            'extension_reason' => 'nullable|string|max:500',
        ];
    }

    public function store(Request $request, DeadlineService $svc)
    {
        $data = $request->validate($this->rules() + ['legal_case_id' => 'nullable|exists:legal_cases,id', 'execution_request_id' => 'nullable|exists:execution_requests,id']);
        abort_if(empty($data['legal_case_id']) && empty($data['execution_request_id']), 422, 'اربط الموعد بقضية أو طلب تنفيذ.');
        $clientId = ! empty($data['legal_case_id']) ? LegalCase::find($data['legal_case_id'])->client_id : ExecutionRequest::find($data['execution_request_id'])->client_id;
        $d = new Deadline(['client_id' => $clientId, 'legal_case_id' => $data['legal_case_id'] ?? null, 'execution_request_id' => $data['execution_request_id'] ?? null,
            'kind' => $data['kind'], 'certainty' => $data['certainty'], 'reason' => $data['reason'], 'source' => 'manual', 'status' => 'open']);
        $d->save();
        $svc->manualUpdate($d, $data);
        Audit::log('deadline.created', $d);

        return back()->with('success', 'أُضيف الموعد.');
    }

    public function update(Request $request, Deadline $deadline, DeadlineService $svc)
    {
        $data = $request->validate($this->rules());
        if (empty($data['end_on']) && (empty($data['start_on']) || ! isset($data['duration'])) && $data['certainty'] === 'confirmed') {
            abort(422, 'الموعد المؤكد يحتاج تاريخ نهاية أو مدة من تاريخ بداية.');
        }
        $deadline->kind = $data['kind'];
        $svc->manualUpdate($deadline, $data);

        return back()->with('success', 'حُفظ الموعد.');
    }

    public function close(Request $request, Deadline $deadline)
    {
        $deadline->forceFill(['status' => $deadline->status === 'closed' ? 'open' : 'closed', 'updated_by' => $request->user()->id])->save();
        Audit::log('deadline.status', $deadline, ['status' => $deadline->status]);

        return back();
    }
}
