<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\CourtFiling;
use App\Models\Document;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\ReconciliationRequest;
use App\Models\Task;
use App\Services\Audit;
use App\Services\Timeline;
use App\Services\Workflow\CaseService;
use App\Services\Workflow\ExecutionService;
use App\Services\Workflow\HearingService;
use App\Services\Workflow\TaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** إجراءات القضية: تراضي، ناجز، الجلسات، المهام، الحكم */
class ProceedingsController extends Controller
{
    /** يتحقق أن المستند يخص عميل القضية نفسها */
    public static function docId(?string $uuid, int $clientId): ?int
    {
        if (! $uuid) {
            return null;
        }
        $id = Document::where('uuid', $uuid)->where('client_id', $clientId)->whereNull('deleted_at')->value('id');
        abort_unless($id, 422, 'مستند غير صالح');

        return $id;
    }

    // ---------------------------------------------------------------- تراضي
    public function storeReconciliation(Request $request, LegalCase $case, CaseService $svc)
    {
        $data = $request->validate(['request_number' => 'nullable|string|max:60', 'submitted_on' => 'nullable|date',
            'status' => ['required', Rule::in(['submitted', 'in_progress'])], 'notes' => 'nullable|string|max:2000']);
        $r = $case->reconciliations()->create($data);
        $svc->setStage($case, 'reconciliation');
        Timeline::record($case, 'reconciliation.created', 'تقديم طلب صلح (تراضي)'.($r->request_number ? ' رقم '.$r->request_number : ''), null, $r, true);
        Audit::log('reconciliation.created', $r);

        return back()->with('success', 'سُجّل طلب الصلح.');
    }

    public function updateReconciliation(Request $request, ReconciliationRequest $reconciliation)
    {
        $data = $request->validate(['request_number' => 'nullable|string|max:60', 'submitted_on' => 'nullable|date',
            'status' => ['required', Rule::in(['submitted', 'in_progress'])], 'notes' => 'nullable|string|max:2000']);
        $reconciliation->update($data);
        Audit::log('reconciliation.updated', $reconciliation);

        return back()->with('success', 'حُفظ طلب الصلح.');
    }

    public function reconciliationOutcome(Request $request, ReconciliationRequest $reconciliation, CaseService $svc)
    {
        $data = $request->validate([
            'status' => 'required|in:settled,failed,other_party_absent,closed',
            'outcome_on' => 'nullable|date',
            'agreed_amount' => 'nullable|required_if:status,settled|numeric|min:0|max:9999999999999999',
            'currency' => ['nullable', Rule::in(config('office.currencies'))],
            'notes' => 'nullable|string|max:2000',
            'report_document' => 'nullable|string',
            'close_case' => 'boolean',
        ], ['agreed_amount.required_if' => 'أدخل المبلغ المتفق عليه.']);
        $data['report_document_id'] = self::docId($data['report_document'] ?? null, $reconciliation->legalCase->client_id);
        $svc->recordReconciliationOutcome($reconciliation, $data);

        return back()->with('success', 'سُجّلت نتيجة الصلح.');
    }

    public function moveToCourt(ReconciliationRequest $reconciliation, CaseService $svc)
    {
        abort_unless(in_array($reconciliation->status, ['failed', 'other_party_absent'], true), 422);
        $svc->moveToCourt($reconciliation);

        return redirect()->route('cases.show', ['case' => $reconciliation->legal_case_id, 'tab' => 'court'])->with('success', 'انتقل الملف إلى ناجز وأُنشئت مهمة إعداد لائحة الدعوى.');
    }

    // ---------------------------------------------------------------- ناجز
    private function filingRules(): array
    {
        return [
            'filing_request_number' => 'nullable|string|max:60', 'submitted_on' => 'nullable|date',
            'status' => ['required', Rule::in(array_keys(CourtFiling::STATUSES))], 'case_number' => 'nullable|string|max:60',
            'registered_on' => 'nullable|date', 'referred_on' => 'nullable|date', 'court' => 'nullable|string|max:150',
            'circuit' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:2000', 'event_note' => 'nullable|string|max:500',
        ];
    }

    public function storeFiling(Request $request, LegalCase $case, CaseService $svc)
    {
        $data = $request->validate($this->filingRules());
        $f = $case->filings()->create(collect($data)->except('event_note')->all());
        Timeline::record($case, 'filing.created', $f->case_number ? 'إضافة قضية مقيدة برقم '.$f->case_number : 'طلب تقييد دعوى'.($f->filing_request_number ? ' رقم '.$f->filing_request_number : ''), null, $f, true);
        $svc->setStage($case, $f->case_number ? 'court' : 'filing');
        Audit::log('filing.created', $f);

        return back()->with('success', 'سُجّل طلب التقييد.');
    }

    public function updateFiling(Request $request, CourtFiling $filing, CaseService $svc)
    {
        $svc->updateFiling($filing, $request->validate($this->filingRules()));

        return back()->with('success', 'حُفظت بيانات ناجز.');
    }

    // ---------------------------------------------------------------- الجلسات
    public function storeHearing(Request $request, LegalCase $case, HearingService $svc)
    {
        $data = $request->validate([
            'kind' => 'required|in:reconciliation,court', 'scheduled_at' => 'required|date', 'authority' => 'nullable|string|max:150',
            'meeting_url' => 'nullable|url:https|max:300',
            'reconciliation_request_id' => ['nullable', Rule::exists('reconciliation_requests', 'id')->where('legal_case_id', $case->id)],
            'court_filing_id' => ['nullable', Rule::exists('court_filings', 'id')->where('legal_case_id', $case->id)],
        ]);
        $svc->schedule($case, $data);

        return back()->with('success', 'جُدولت الجلسة وظهرت في قائمة الإنجاز.');
    }

    /** معاينة أثر تغيير الجلسة على المهام المرتبطة قبل الحفظ */
    public function previewChange(Request $request, Hearing $hearing, TaskService $tasks)
    {
        $data = $request->validate(['scheduled_at' => 'nullable|date']);
        $local = isset($data['scheduled_at']) ? substr($data['scheduled_at'], 0, 10) : null;

        return response()->json(['tasks' => $tasks->previewHearingChange($hearing, $local)]);
    }

    public function changeHearing(Request $request, Hearing $hearing, HearingService $svc)
    {
        $data = $request->validate([
            'action' => 'required|in:reschedule,postpone,cancel',
            'scheduled_at' => 'nullable|required_if:action,reschedule|date',
            'reason' => 'required|string|max:300',
            'lock_version' => 'required|integer',
        ], ['reason.required' => 'سبب التعديل مطلوب ويُحفظ في سجل القضية.']);
        abort_unless($hearing->status === 'scheduled', 422, 'لا يمكن تعديل جلسة غير مجدولة.');
        match ($data['action']) {
            'reschedule' => $svc->reschedule($hearing, $data['scheduled_at'], $data['reason'], (int) $data['lock_version']),
            'postpone' => $svc->postpone($hearing, $data['scheduled_at'] ?? null, $data['reason'], (int) $data['lock_version']),
            'cancel' => $svc->cancel($hearing, $data['reason'], (int) $data['lock_version']),
        };

        return back()->with('success', match ($data['action']) { 'reschedule' => 'عُدّل موعد الجلسة.', 'postpone' => 'سُجّل التأجيل.', 'cancel' => 'أُلغيت الجلسة.' });
    }

    public function completeHearing(Request $request, Hearing $hearing, HearingService $svc)
    {
        $data = $request->validate([
            'attendance' => 'nullable|array',
            'attendance.client' => 'nullable|in:present,absent,represented',
            'attendance.opponent' => 'nullable|in:present,absent,represented',
            'attendance.lawyer' => 'nullable|in:present,absent',
            'result' => 'nullable|string|max:5000',
            'directives' => 'nullable|string|max:5000',
            'minutes_document' => 'nullable|string',
            'next_hearing_at' => 'nullable|date',
            'lock_version' => 'required|integer',
            'tasks' => 'array|max:15',
            'tasks.*.title' => 'required|string|max:250',
            'tasks.*.kind' => ['required', Rule::in(array_keys(Task::KINDS))],
            'tasks.*.due_mode' => ['required', Rule::in(array_keys(Task::DUE_MODES))],
            'tasks.*.due_on' => 'nullable|date',
            'tasks.*.relative_from' => 'nullable|date',
            'tasks.*.relative_event' => 'nullable|string|max:120',
            'tasks.*.relative_days' => 'nullable|integer|min:-365|max:365',
            'tasks.*.relative_day_type' => 'nullable|in:calendar,business',
            'tasks.*.anchor_offset_days' => 'nullable|integer|min:-60|max:60',
        ]);
        abort_unless(in_array($hearing->status, ['scheduled'], true), 422, 'الجلسة ليست مجدولة.');
        $data['minutes_document_id'] = self::docId($data['minutes_document'] ?? null, $hearing->legalCase->client_id);
        $svc->complete($hearing, $data, $data['tasks'] ?? [], (int) $data['lock_version']);

        return back()->with('success', 'سُجّل انعقاد الجلسة'.(count($data['tasks'] ?? []) ? ' وأُضيفت المهام.' : '.'));
    }

    // ---------------------------------------------------------------- المهام
    public function taskRules(): array
    {
        return [
            'title' => 'required|string|max:250', 'description' => 'nullable|string|max:2000',
            'kind' => ['required', Rule::in(array_keys(Task::KINDS))], 'due_mode' => ['required', Rule::in(array_keys(Task::DUE_MODES))],
            'due_on' => 'nullable|date', 'relative_from' => 'nullable|date', 'relative_event' => 'nullable|string|max:120',
            'relative_days' => 'nullable|integer|min:-365|max:365', 'relative_day_type' => 'nullable|in:calendar,business',
            'anchor_offset_days' => 'nullable|integer|min:-60|max:60',
        ];
    }

    public function storeTask(Request $request, LegalCase $case, TaskService $svc)
    {
        $svc->create($request->validate($this->taskRules()), $case);

        return back()->with('success', 'أُضيفت المهمة.');
    }

    public function updateTask(Request $request, Task $task, TaskService $svc)
    {
        $data = $request->validate($this->taskRules() + ['lock_version' => 'required|integer', 'change_reason' => 'nullable|string|max:300']);
        $old = $task->due_on?->toDateString();
        $task->fill(collect($data)->only(['title', 'description', 'kind'])->all());
        $svc->applyDue($task, $data, $task->legalCase);
        $task->saveWithVersion((int) $data['lock_version']);
        if ($old !== $task->due_on?->toDateString()) {
            $svc->logDueChange($task, $old, $task->due_on?->toDateString(), $data['change_reason'] ?: 'تعديل يدوي');
        }

        return back()->with('success', 'حُفظت المهمة.');
    }

    public function toggleTask(Request $request, Task $task)
    {
        $data = $request->validate(['status' => 'required|in:open,done,cancelled', 'lock_version' => 'required|integer']);
        $task->status = $data['status'];
        $task->completed_at = $data['status'] === 'done' ? now() : null;
        $task->saveWithVersion((int) $data['lock_version']);
        Audit::log('task.status', $task, ['status' => $task->status]);

        return back();
    }

    // ---------------------------------------------------------------- الحكم
    public function saveJudgment(Request $request, LegalCase $case, CaseService $svc)
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(LegalCase::OUTCOMES))],
            'pronounced_on' => 'nullable|date',
            'deed_status' => 'required|in:awaiting,issued',
            'deed_number' => 'nullable|string|max:60',
            'deed_date' => 'nullable|date',
            'deed_document' => 'nullable|string',
            'objection_status' => 'nullable|in:none,objected,waived,not_applicable',
            'objection_filed_on' => 'nullable|date',
            'resumed_on' => 'nullable|date',
            'notes' => 'nullable|string|max:3000',
        ]);
        if (! empty($data['deed_document'])) {
            $data['deed_document_id'] = self::docId($data['deed_document'], $case->client_id);
        }
        $svc->recordJudgment($case, $data);

        return back()->with('success', 'حُفظ الحكم.');
    }

    public function confirmFinality(Request $request, LegalCase $case, CaseService $svc)
    {
        $data = $request->validate(['document' => 'nullable|string', 'confirm' => 'accepted']);
        abort_unless($case->judgment, 422);
        $svc->confirmFinality($case->judgment, self::docId($data['document'] ?? null, $case->client_id));

        return back()->with('success', 'سُجّل تأكيد نهائية الحكم.');
    }

    /** بعد الحكم: متابعة التنفيذ أو عدمه أو عدم الانطباق. المتابعة تنشئ سجل تنفيذ مرتبطًا بالقضية */
    public function executionDecision(Request $request, LegalCase $case, CaseService $svc, ExecutionService $exec)
    {
        $data = $request->validate(['decision' => 'required|in:pursue,not_pursue,not_applicable', 'execution_type' => 'nullable|required_if:decision,pursue|in:financial,direct']);
        $j = $case->judgment;
        abort_unless($j, 422);
        $svc->setExecutionDecision($j, $data['decision']);
        if ($data['decision'] === 'pursue' && ! $case->executionRequests()->where('instrument_type', 'judgment_deed')->exists()) {
            $e = $exec->create(['execution_type' => $data['execution_type'], 'instrument_type' => 'judgment_deed', 'status' => 'draft', 'currency' => $case->currency,
                'parties' => $case->parties->where('role', '!=', $case->client_role)->map(fn ($p) => ['name' => $p->name, 'role' => 'respondent', 'party_type' => $p->party_type, 'identifier' => $p->identifier])->values()->all()], $case);

            return redirect()->route('executions.show', $e)->with('success', 'أُنشئ ملف التنفيذ المرتبط بالقضية.');
        }

        return back()->with('success', 'حُفظ قرار التنفيذ.');
    }
}
