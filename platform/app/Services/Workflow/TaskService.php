<?php

namespace App\Services\Workflow;

use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\TaskDueChange;
use App\Services\Audit;
use App\Services\Calendar;
use Illuminate\Validation\ValidationException;

/**
 * المهام: الاستحقاق إمّا تاريخ صريح، أو مدة من واقعة معلومة، أو مرتبط بالجلسة القادمة، أو بانتظار تحديد موعد.
 * يُخزَّن due_on محسوبًا ليُستعلم عنه، ويُعاد حسابه عند تغيّر الجلسة مع حفظ التعديل وسببه.
 */
class TaskService
{
    public function __construct(private Calendar $cal) {}

    /** @param array<string,mixed> $data */
    public function create(array $data, ?LegalCase $case = null, ?Hearing $source = null): Task
    {
        $task = new Task([
            'client_id' => $data['client_id'] ?? $case?->client_id,
            'legal_case_id' => $case?->id ?? ($data['legal_case_id'] ?? null),
            'execution_request_id' => $data['execution_request_id'] ?? null,
            'source_hearing_id' => $source?->id,
            'kind' => $data['kind'] ?? 'manual',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'unique_key' => $data['unique_key'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $this->applyDue($task, $data, $case);
        $task->save();
        Audit::log('task.created', $task, ['due_mode' => $task->due_mode]);

        return $task;
    }

    /** إنشاء مهمة مرة واحدة فقط بمفتاح فريد (يحمي من الضغط المزدوج وإعادة المحاولة) */
    public function createOnce(string $uniqueKey, array $data, ?LegalCase $case = null): Task
    {
        $existing = Task::where('unique_key', $uniqueKey)->first();
        if ($existing) {
            return $existing;
        }
        try {
            return $this->create($data + ['unique_key' => $uniqueKey], $case);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return Task::where('unique_key', $uniqueKey)->firstOrFail();
        }
    }

    /** @param array<string,mixed> $data */
    public function applyDue(Task $task, array $data, ?LegalCase $case = null): void
    {
        $mode = $data['due_mode'] ?? 'date';
        $task->due_mode = $mode;
        $task->relative_from = null;
        $task->relative_event = null;
        $task->relative_days = null;
        $task->relative_day_type = null;
        $task->anchor_hearing_id = null;
        $task->anchor_offset_days = null;

        switch ($mode) {
            case 'date':
                if (empty($data['due_on'])) {
                    throw ValidationException::withMessages(['due_on' => 'حدد تاريخ الاستحقاق.']);
                }
                $task->due_on = $this->cal->date($data['due_on'])->toDateString();
                break;
            case 'relative':
                if (empty($data['relative_from']) || ! isset($data['relative_days'])) {
                    throw ValidationException::withMessages(['relative_days' => 'حدد الواقعة وتاريخها والمدة.']);
                }
                $task->relative_from = $data['relative_from'];
                $task->relative_event = $data['relative_event'] ?? null;
                $task->relative_days = (int) $data['relative_days'];
                $task->relative_day_type = $data['relative_day_type'] ?? 'calendar';
                $task->due_on = $this->cal->add($data['relative_from'], (int) $data['relative_days'], $task->relative_day_type)->toDateString();
                break;
            case 'next_hearing':
                $hearing = ! empty($data['anchor_hearing_id'])
                    ? Hearing::find($data['anchor_hearing_id'])
                    : $case?->nextHearing();
                if (! $hearing) {
                    throw ValidationException::withMessages(['due_mode' => 'لا توجد جلسة قادمة مجدولة لربط المهمة بها.']);
                }
                $task->anchor_hearing_id = $hearing->id;
                $task->anchor_offset_days = (int) ($data['anchor_offset_days'] ?? 0);
                $task->due_on = $this->cal->add($hearing->localDate(), $task->anchor_offset_days, 'calendar')->toDateString();
                break;
            case 'unscheduled':
                $task->due_on = null;
                break;
            default:
                throw ValidationException::withMessages(['due_mode' => 'نوع استحقاق غير معروف.']);
        }
    }

    /**
     * أثر تغيير موعد جلسة على المهام المرتبطة بها — يُعرض للمحامي قبل التعديل.
     *
     * @return array<int, array{id:int,title:string,old:?string,new:?string}>
     */
    public function previewHearingChange(Hearing $hearing, ?string $newLocalDate): array
    {
        return $hearing->anchoredTasks()->where('status', 'open')->get()->map(fn (Task $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'offset' => $t->anchor_offset_days,
            'old' => $t->due_on?->toDateString(),
            'new' => $newLocalDate ? $this->cal->add($newLocalDate, (int) $t->anchor_offset_days)->toDateString() : null,
        ])->all();
    }

    /** نقل المهام المرتبطة بجلسة إلى جلسة جديدة (أو إلى "بانتظار تحديد موعد") مع تسجيل السبب */
    public function reanchor(Hearing $from, ?Hearing $to, string $reason): int
    {
        $n = 0;
        foreach ($from->anchoredTasks()->where('status', 'open')->get() as $task) {
            $old = $task->due_on?->toDateString();
            if ($to) {
                $task->anchor_hearing_id = $to->id;
                $task->due_on = $this->cal->add($to->localDate(), (int) $task->anchor_offset_days)->toDateString();
            } else {
                $task->due_mode = 'unscheduled';
                $task->anchor_hearing_id = null;
                $task->due_on = null;
            }
            $task->save();
            $this->logDueChange($task, $old, $task->due_on?->toDateString(), $reason);
            $n++;
        }

        return $n;
    }

    public function logDueChange(Task $task, ?string $old, ?string $new, string $reason): void
    {
        if ($old === $new) {
            return;
        }
        TaskDueChange::create(['task_id' => $task->id, 'old_due_on' => $old, 'new_due_on' => $new, 'reason' => $reason, 'user_id' => auth()->id(), 'created_at' => now()]);
        Audit::log('task.due_changed', $task, ['old' => $old, 'new' => $new]);
    }
}
