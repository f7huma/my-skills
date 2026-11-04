<?php

namespace App\Services\Workflow;

use App\Models\ActionType;
use App\Models\Collection;
use App\Models\ExecutionAction;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Services\Audit;
use App\Services\Calendar;
use App\Services\Timeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExecutionService
{
    public const DATE_EVENTS = [
        'submitted_on' => 'تقديم الطلب',
        'accepted_on' => 'قبول الطلب',
        'order_issued_on' => 'صدور أمر التنفيذ',
        'notified_on' => 'التبليغ / الإعلان',
        'enforcement_started_on' => 'بدء الإجراءات الجبرية',
    ];

    public function __construct(private DeadlineService $deadlines, private Calendar $cal) {}

    public function create(array $data, ?LegalCase $case = null): ExecutionRequest
    {
        return DB::transaction(function () use ($data, $case) {
            $e = new ExecutionRequest($this->fields($data));
            $e->client_id = $case?->client_id ?? $data['client_id'];
            $e->legal_case_id = $case?->id;
            if ($case && ($e->instrument_type === 'judgment_deed') && $case->judgment) {
                $e->deed_number ??= $case->judgment->deed_number;
                $e->deed_date ??= $case->judgment->deed_date;
            }
            $e->save();
            foreach ($data['parties'] ?? [] as $p) {
                if (! empty($p['name'])) {
                    $e->parties()->create(collect($p)->only(['name', 'role', 'party_type', 'identifier'])->all());
                }
            }
            if ($case) {
                $case->stage = 'execution';
                $case->save();
                Timeline::record($case, 'execution.created', 'فتح طلب تنفيذ '.$e->reference, null, $e, true);
            }
            Timeline::record($e, 'execution.created', 'إنشاء طلب التنفيذ', ExecutionRequest::EXECUTION_TYPES[$e->execution_type].' — '.ExecutionRequest::INSTRUMENT_TYPES[$e->instrument_type], $e, true);
            if ($e->amount_claimed) {
                Collection::create(['client_id' => $e->client_id, 'legal_case_id' => $e->legal_case_id, 'execution_request_id' => $e->id,
                    'kind' => 'claimed', 'source' => 'execution', 'amount' => $e->amount_claimed, 'currency' => $e->currency,
                    'occurred_on' => $e->submitted_on ?? today_local(), 'entry_key' => (string) Str::uuid(), 'created_by' => auth()->id(),
                    'description' => 'المبلغ المطلوب في طلب التنفيذ']);
            }
            $this->deadlines->syncExecutionCompliance($e);
            Audit::log('execution.created', $e, ['type' => $e->execution_type, 'instrument' => $e->instrument_type]);

            return $e;
        });
    }

    public function update(ExecutionRequest $e, array $data, int $version): void
    {
        DB::transaction(function () use ($e, $data, $version) {
            $before = $e->only(array_keys(self::DATE_EVENTS) + ['status' => 'status']);
            $e->fill($this->fields($data));
            $e->saveWithVersion($version);
            foreach (self::DATE_EVENTS as $f => $label) {
                $old = $before[$f] ?? null;
                if ($e->{$f} && (! $old || $old->toDateString() !== $e->{$f}->toDateString())) {
                    Timeline::record($e, 'execution.'.$f, $label, 'التاريخ: '.$e->{$f}->toDateString(), $e, true);
                }
            }
            $this->deadlines->syncExecutionCompliance($e);
            Audit::log('execution.updated', $e, ['status' => $e->status]);
        });
    }

    private function fields(array $data): array
    {
        $allowed = ['execution_type', 'instrument_type', 'instrument_label', 'request_number', 'status', 'submitted_on', 'accepted_on', 'order_issued_on',
            'notified_on', 'notification_method', 'enforcement_started_on', 'amount_claimed', 'currency', 'client_summary', 'internal_notes', 'weekly_followup'];
        $type = $data['instrument_type'] ?? null;
        if ($type === 'judgment_deed') {
            $allowed = array_merge($allowed, ['deed_number', 'deed_date']);
        } elseif ($type === 'electronic_promissory_note') {
            $allowed = array_merge($allowed, ['note_number', 'note_amount', 'note_currency', 'note_issued_on', 'note_due_on']);
        }
        if (($data['execution_type'] ?? null) === 'direct') {
            $allowed = array_merge($allowed, ['subject_location', 'obligation_description']);
        }
        $out = collect($data)->only($allowed)->all();
        if (array_key_exists('client_summary', $out)) {
            $out['client_summary_updated_at'] = now();
        }

        return $out;
    }

    /**
     * تاريخ الأهلية لإعادة تقديم طلب الإجراء: من آخر طلب من النوع نفسه إذا نص شرط الخدمة على ذلك.
     * هذا شرط قبول في ناجز، ومنفصل عن المتابعة الداخلية الأسبوعية.
     */
    public function eligibleResubmissionDate(ExecutionRequest $e, ActionType $type, ?string $submittedOn): ?string
    {
        if (! $type->resubmission_interval_days) {
            return null;
        }
        $from = $submittedOn;
        if ($type->interval_from_last_same_type) {
            $last = $e->actions()->where('action_type_id', $type->id)->whereNotNull('submitted_on')->max('submitted_on');
            $from = $last && (! $submittedOn || $last > $submittedOn) ? $last : $submittedOn;
        }

        return $from ? $this->cal->add($from, $type->resubmission_interval_days, $type->interval_day_type)->toDateString() : null;
    }

    public function addAction(ExecutionRequest $e, array $data): ExecutionAction
    {
        $type = ActionType::findOrFail($data['action_type_id']);
        $a = $e->actions()->create([
            'action_type_id' => $type->id,
            'submitted_on' => $data['submitted_on'] ?? null,
            'status' => $data['status'] ?? 'submitted',
            'result' => $data['result'] ?? null,
            'next_followup_on' => $data['next_followup_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $a->eligible_resubmission_on = $this->eligibleResubmissionDate($e, $type, $a->submitted_on?->toDateString());
        $a->save();
        Timeline::record($e, 'execution.action', 'طلب إجراء: '.$type->name, $a->result, $a, false);
        Audit::log('execution.action_added', $a, ['type' => $type->id]);

        return $a;
    }

    /** المتابعة الداخلية كل سبعة أيام على مستوى الملف (اختيارية) */
    public function weeklyFollowupDue(ExecutionRequest $e): ?string
    {
        if (! $e->weekly_followup) {
            return null;
        }
        $last = $e->timeline()->where('type', 'execution.followup')->max('occurred_at');
        $base = $last ? local_date(\Illuminate\Support\Carbon::parse($last)) : ($e->submitted_on?->toDateString() ?? local_date($e->created_at));

        return $this->cal->add($base, 7)->toDateString();
    }
}
