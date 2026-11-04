<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\ExecutionAction;
use App\Models\ExecutionRequest;
use App\Models\Hearing;
use App\Models\Memo;
use App\Models\Task;
use App\Services\Workflow\ExecutionService;
use Carbon\CarbonImmutable;

/**
 * قائمة إنجاز الأسبوع: تُستمد آليًا من الجلسات والمهام والمذكرات ومتابعات التنفيذ والعدادات.
 * كل بند يحمل مصدره ورابطه؛ ولأن البند يُبنى من السجل نفسه فإن تغيّر الموعد يحدّث البند مباشرة.
 */
class AgendaService
{
    public function __construct(private Calendar $cal, private ExecutionService $exec) {}

    /** @return array{today: array, overdue: array, week: array, unscheduled: array, range: array} */
    public function week(): array
    {
        $tz = config('office.display_timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $end = $today->addDays(6);
        $items = collect();

        // الجلسات المجدولة (صلح وقضاء)
        Hearing::with('legalCase.client:id,name')->where('status', 'scheduled')
            ->where('scheduled_at', '<', $end->addDay()->utc())
            ->get()->each(function (Hearing $h) use ($items) {
                $items->push([
                    'key' => 'hearing-'.$h->id, 'type' => 'hearing', 'label' => Hearing::KINDS[$h->kind],
                    'title' => $h->legalCase->title, 'date' => $h->localDate(),
                    'time' => $h->scheduled_at->copy()->setTimezone(config('office.display_timezone'))->format('H:i'),
                    'ref' => $h->legalCase->reference, 'client' => $h->legalCase->client->name,
                    'href' => route('cases.show', ['case' => $h->legal_case_id, 'tab' => 'hearings']).'#hearing-'.$h->id,
                ]);
            });

        // المهام المفتوحة (من الجلسات والقضايا والتنفيذ واليدوية)
        Task::with(['legalCase:id,reference,title,client_id', 'legalCase.client:id,name', 'executionRequest:id,reference,client_id', 'executionRequest.client:id,name'])
            ->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('due_on')->orWhereDate('due_on', '<=', $end->toDateString()))
            ->get()->each(function (Task $t) use ($items) {
                $owner = $t->legalCase ?? $t->executionRequest;
                $items->push([
                    'key' => 'task-'.$t->id, 'type' => 'task', 'label' => Task::KINDS[$t->kind] ?? 'مهمة', 'title' => $t->title,
                    'date' => $t->due_on?->toDateString(), 'ref' => $owner?->reference, 'client' => $owner?->client?->name,
                    'dueMode' => $t->due_mode, 'taskId' => $t->id, 'lockVersion' => $t->lock_version,
                    'href' => $t->legal_case_id ? route('cases.show', ['case' => $t->legal_case_id, 'tab' => 'tasks']).'#task-'.$t->id
                        : ($t->execution_request_id ? route('executions.show', $t->execution_request_id).'#task-'.$t->id : route('tasks.index').'#task-'.$t->id),
                ]);
            });

        // مواعيد المذكرات غير المودعة
        Memo::with('legalCase:id,reference,title,client_id', 'legalCase.client:id,name')->whereNotNull('due_on')->where('status', '!=', 'filed')
            ->whereDate('due_on', '<=', $end->toDateString())->get()->each(function (Memo $m) use ($items) {
                $items->push([
                    'key' => 'memo-'.$m->id, 'type' => 'memo', 'label' => Memo::TYPES[$m->type], 'title' => $m->title,
                    'date' => $m->due_on->toDateString(), 'ref' => $m->legalCase->reference, 'client' => $m->legalCase->client->name,
                    'href' => route('memos.edit', $m->id),
                ]);
            });

        // متابعات التنفيذ: أقرب موعد متابعة لطلبات الإجراء، والمتابعة الأسبوعية الاختيارية
        ExecutionAction::with('executionRequest:id,reference,client_id', 'executionRequest.client:id,name', 'type:id,name')
            ->whereNotNull('next_followup_on')->whereIn('status', ['submitted', 'accepted'])
            ->whereDate('next_followup_on', '<=', $end->toDateString())->get()->each(function (ExecutionAction $a) use ($items) {
                $items->push([
                    'key' => 'action-'.$a->id, 'type' => 'execution', 'label' => 'متابعة طلب إجراء', 'title' => $a->type->name,
                    'date' => $a->next_followup_on->toDateString(), 'ref' => $a->executionRequest->reference, 'client' => $a->executionRequest->client->name,
                    'href' => route('executions.show', $a->execution_request_id).'#action-'.$a->id,
                ]);
            });
        ExecutionRequest::with('client:id,name')->where('weekly_followup', true)->whereNotIn('status', ['completed', 'closed'])->get()
            ->each(function (ExecutionRequest $e) use ($items, $end) {
                $due = $this->exec->weeklyFollowupDue($e);
                if ($due && $due <= $end->toDateString()) {
                    $items->push([
                        'key' => 'weekly-'.$e->id, 'type' => 'execution', 'label' => 'متابعة أسبوعية داخلية', 'title' => 'مراجعة ملف التنفيذ',
                        'date' => $due, 'ref' => $e->reference, 'client' => $e->client->name, 'executionId' => $e->id,
                        'href' => route('executions.show', $e->id),
                    ]);
                }
            });

        // العدادات والمواعيد المهمة
        Deadline::with('client:id,name', 'legalCase:id,reference', 'executionRequest:id,reference')->whereIn('status', ['open', 'alerted'])
            ->where(fn ($q) => $q->whereNull('end_on')->orWhereDate('end_on', '<=', $end->toDateString()))->get()
            ->each(function (Deadline $d) use ($items) {
                $items->push([
                    'key' => 'deadline-'.$d->id, 'type' => 'deadline', 'label' => Deadline::CERTAINTY[$d->certainty], 'title' => $d->reason,
                    'date' => $d->end_on?->toDateString(), 'ref' => $d->legalCase?->reference ?? $d->executionRequest?->reference,
                    'client' => $d->client->name, 'certainty' => $d->certainty, 'alerted' => $d->status === 'alerted',
                    'href' => route('deadlines.index').'#deadline-'.$d->id,
                ]);
            });

        $t = $today->toDateString();
        $sorted = $items->sortBy(fn ($i) => ($i['date'] ?? '9999').($i['time'] ?? '00:00'))->values();

        return [
            'range' => ['from' => $t, 'to' => $end->toDateString(), 'fromHijri' => $this->cal->hijri($t)],
            'overdue' => $sorted->filter(fn ($i) => $i['date'] && $i['date'] < $t)->values()->all(),
            'today' => $sorted->filter(fn ($i) => $i['date'] === $t)->values()->all(),
            'week' => $sorted->filter(fn ($i) => $i['date'] && $i['date'] > $t)->values()->all(),
            'unscheduled' => $sorted->filter(fn ($i) => ! $i['date'])->values()->all(),
        ];
    }
}
