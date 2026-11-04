<?php

namespace App\Services\Workflow;

use App\Models\Collection;
use App\Models\CourtFiling;
use App\Models\Judgment;
use App\Models\LegalCase;
use App\Models\ReconciliationRequest;
use App\Services\Audit;
use App\Services\Timeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseService
{
    public function __construct(private TaskService $tasks, private DeadlineService $deadlines) {}

    public function changeStatus(LegalCase $case, string $status, ?string $note = null): void
    {
        if ($case->work_status === $status) {
            return;
        }
        $old = $case->work_status;
        $case->work_status = $status;
        $case->save();
        Timeline::record($case, 'case.status', 'تغيير حالة العمل: '.LegalCase::WORK_STATUSES[$status], $note, $case, false);
        Audit::log('case.status_changed', $case, ['from' => $old, 'to' => $status]);
    }

    public function setStage(LegalCase $case, string $stage): void
    {
        if ($case->stage === $stage) {
            return;
        }
        $old = $case->stage;
        $case->stage = $stage;
        if ($case->work_status === 'draft') {
            $case->work_status = 'active';
        }
        $case->save();
        Audit::log('case.stage_changed', $case, ['from' => $old, 'to' => $stage]);
    }

    /** تسجيل نتيجة الصلح: تم الصلح / تعذّر / غياب الطرف الآخر */
    public function recordReconciliationOutcome(ReconciliationRequest $r, array $data): void
    {
        DB::transaction(function () use ($r, $data) {
            $case = $r->legalCase;
            $r->fill([
                'status' => $data['status'],
                'outcome_on' => $data['outcome_on'] ?? today_local()->toDateString(),
                'notes' => $data['notes'] ?? $r->notes,
            ]);
            if (! empty($data['report_document_id'])) {
                $r->report_document_id = $data['report_document_id'];
            }
            if ($data['status'] === 'settled') {
                $r->agreed_amount = $data['agreed_amount'] ?? null;
                $r->currency = $data['currency'] ?? 'SAR';
            }
            $r->save();

            $title = ReconciliationRequest::STATUSES[$data['status']];
            Timeline::record($case, 'reconciliation.outcome', 'نتيجة الصلح: '.$title, $data['notes'] ?? null, $r, true);
            Audit::log('reconciliation.outcome', $r, ['status' => $data['status']]);

            if ($data['status'] === 'settled') {
                if ($r->agreed_amount) {
                    Collection::firstOrCreate(
                        ['reconciliation_request_id' => $r->id, 'kind' => 'agreed'],
                        ['client_id' => $case->client_id, 'legal_case_id' => $case->id, 'source' => 'reconciliation', 'amount' => $r->agreed_amount,
                            'currency' => $r->currency, 'occurred_on' => $r->outcome_on, 'entry_key' => (string) Str::uuid(), 'created_by' => auth()->id(),
                            'description' => 'المبلغ المتفق عليه في الصلح'],
                    );
                }
                if (! empty($data['close_case'])) {
                    $case->outcome = 'settled';
                    $case->stage = 'closed';
                    $case->save();
                    $this->changeStatus($case, 'completed', 'إغلاق المسار بعد الصلح');
                }
            }
        });
    }

    /**
     * عند تعذّر الصلح: الانتقال إلى ناجز وإنشاء مهمة إعداد لائحة الدعوى مرة واحدة فقط.
     */
    public function moveToCourt(ReconciliationRequest $r): void
    {
        DB::transaction(function () use ($r) {
            $r = ReconciliationRequest::whereKey($r->id)->lockForUpdate()->first();
            $case = $r->legalCase;
            if (! $r->moved_to_court_at) {
                $r->forceFill(['moved_to_court_at' => now()])->save();
                Timeline::record($case, 'case.moved_to_court', 'الانتقال إلى ناجز بعد تعذّر الصلح', null, $r, true);
                Audit::log('case.moved_to_court', $case);
            }
            $this->setStage($case, 'filing');
            if (! $case->filings()->exists()) {
                $case->filings()->create(['status' => 'draft']);
            }
            $this->tasks->createOnce('case:'.$case->id.':prepare_statement_of_claim', [
                'kind' => 'prepare_statement_of_claim',
                'title' => 'إعداد لائحة الدعوى',
                'due_mode' => 'unscheduled',
            ], $case);
        });
    }

    /** تحديث طلب التقييد مع حفظ السجل (إعادة للاستكمال، إعادة تقديم، قيد، إحالة) */
    public function updateFiling(CourtFiling $f, array $data): void
    {
        $before = $f->only(['status', 'filing_request_number', 'case_number', 'court', 'circuit']);
        $f->fill($data)->save();
        $case = $f->legalCase;
        if ($before['status'] !== $f->status) {
            Timeline::record($case, 'filing.status', 'طلب التقييد: '.CourtFiling::STATUSES[$f->status], $data['event_note'] ?? null, $f, true);
        }
        if (! $before['case_number'] && $f->case_number) {
            Timeline::record($case, 'filing.registered', 'قيد القضية برقم '.$f->case_number, trim(($f->court ?? '').' '.($f->circuit ?? '')) ?: null, $f, true);
        }
        if ($f->status === 'registered' || $f->case_number) {
            $this->setStage($case, 'court');
        }
        Audit::log('filing.updated', $f, ['status' => $f->status]);
    }

    public function recordJudgment(LegalCase $case, array $data): Judgment
    {
        return DB::transaction(function () use ($case, $data) {
            $j = $case->judgment ?? new Judgment(['legal_case_id' => $case->id]);
            $wasNew = ! $j->exists;
            $oldDeed = $j->deed_status;
            $j->fill(collect($data)->only([
                'outcome', 'pronounced_on', 'deed_status', 'deed_number', 'deed_date', 'deed_document_id',
                'objection_status', 'objection_filed_on', 'notes', 'resumed_on',
            ])->all());
            $j->save();
            $case->setRelation('judgment', $j);
            $j->setRelation('legalCase', $case);
            $case->outcome = $j->outcome;
            $case->stage = 'judgment';
            if ($j->outcome === 'struck_off') {
                $case->work_status = $j->resumed_on ? 'active' : 'struck_off';
            } elseif ($j->outcome === 'suspended') {
                $case->work_status = $j->resumed_on ? 'active' : 'suspended';
            }
            $case->save();

            if ($wasNew) {
                Timeline::record($case, 'judgment.pronounced', LegalCase::OUTCOMES[$j->outcome], $j->pronounced_on ? 'تاريخ النطق: '.$j->pronounced_on->toDateString() : null, $j, true);
            }
            if ($j->deed_status === 'issued' && $oldDeed !== 'issued') {
                Timeline::record($case, 'judgment.deed_issued', 'صدور صك الحكم'.($j->deed_number ? ' رقم '.$j->deed_number : ''), null, $j, true);
            }
            if ($j->resumed_on && in_array($j->outcome, ['struck_off', 'suspended'], true)) {
                Timeline::record($case, 'case.resumed', 'استكمال السير في الدعوى', 'تاريخ الاستكمال: '.$j->resumed_on->toDateString(), $j, true);
            }
            $this->deadlines->syncJudgmentDeadlines($j);
            Audit::log($wasNew ? 'judgment.recorded' : 'judgment.updated', $j, ['outcome' => $j->outcome, 'deed' => $j->deed_status]);

            return $j;
        });
    }

    /** تأكيد نهائية الحكم إجراء صريح من المحامي، مع إمكانية إرفاق مستنده */
    public function confirmFinality(Judgment $j, ?int $documentId): void
    {
        $j->forceFill([
            'finality_status' => 'final', 'finality_confirmed_at' => now(), 'finality_confirmed_by' => auth()->id(),
            'finality_document_id' => $documentId ?? $j->finality_document_id,
        ])->save();
        $this->deadlines->closeFor($j->legalCase, ['objection']);
        Timeline::record($j->legalCase, 'judgment.final', 'تأكيد نهائية الحكم', null, $j, true);
        Audit::log('judgment.finality_confirmed', $j);
    }

    public function setExecutionDecision(Judgment $j, string $decision): void
    {
        $j->forceFill(['execution_decision' => $decision])->save();
        Timeline::record($j->legalCase, 'judgment.execution_decision', 'قرار التنفيذ: '.Judgment::EXECUTION_DECISIONS[$decision], null, $j, false);
        Audit::log('judgment.execution_decision', $j, ['decision' => $decision]);
    }
}
