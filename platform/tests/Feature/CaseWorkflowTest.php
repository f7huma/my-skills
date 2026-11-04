<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\TimelineEvent;
use App\Services\AgendaService;
use Database\Seeders\DemoCasesSeeder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CaseWorkflowTest extends TestCase
{
    private function newCase(array $over = []): LegalCase
    {
        $this->actingAs($this->lawyer())->post(route('cases.store'), $over + [
            'client_id' => $this->client('سالم')->id, 'title' => 'قضية اختبار', 'client_role' => 'plaintiff', 'start_point' => 'reconciliation',
        ])->assertSessionHasNoErrors();

        return LegalCase::latest('id')->first();
    }

    public function test_minimal_draft_case_can_be_saved(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer())->post(route('cases.store'), ['client_id' => $this->client('سالم')->id, 'title' => 'مسودة', 'client_role' => 'plaintiff', 'start_point' => 'draft'])
            ->assertSessionHasNoErrors();
        $c = LegalCase::latest('id')->first();
        $this->assertSame('draft', $c->work_status);
        $this->assertSame(0, $c->filings()->count());
    }

    public function test_failed_reconciliation_creates_statement_task_exactly_once(): void
    {
        $this->demo();
        $case = $this->newCase();
        $r = $case->reconciliations()->first();
        $this->actingAs($this->lawyer())->post(route('reconciliations.outcome', $r), ['status' => 'failed', 'outcome_on' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->actingAs($this->lawyer())->post(route('reconciliations.move', $r));
        $this->actingAs($this->lawyer())->post(route('reconciliations.move', $r));
        $this->assertSame(1, Task::where('legal_case_id', $case->id)->where('kind', 'prepare_statement_of_claim')->count());
        $this->assertSame('filing', $case->fresh()->stage);
        $this->assertSame(1, $case->filings()->count());
        $this->assertTrue(TimelineEvent::where('legal_case_id', $case->id)->where('type', 'case.moved_to_court')->exists());
    }

    public function test_settlement_closes_track_and_records_agreed_amount(): void
    {
        $this->demo();
        $case = $this->newCase();
        $r = $case->reconciliations()->first();
        $this->actingAs($this->lawyer())->post(route('reconciliations.outcome', $r), ['status' => 'settled', 'agreed_amount' => '10000.10', 'currency' => 'SAR', 'close_case' => true]);
        $case->refresh();
        $this->assertSame('completed', $case->work_status);
        $this->assertSame('settled', $case->outcome);
        $this->assertSame('10000.10', $case->collections()->where('kind', 'agreed')->value('amount'));
    }

    public function test_defendant_case_can_start_directly_in_court_with_case_number(): void
    {
        $this->demo();
        $case = $this->newCase(['client_role' => 'defendant', 'start_point' => 'court', 'case_number' => '4479999999', 'court' => 'محكمة الاختبار']);
        $this->assertSame('court', $case->stage);
        $this->assertSame('4479999999', $case->filings()->value('case_number'));
        $this->assertSame(0, $case->reconciliations()->count());
    }

    public function test_najiz_filing_return_and_resubmission_keep_history(): void
    {
        $this->demo();
        $case = $this->newCase(['start_point' => 'filing']);
        $f = $case->filings()->first();
        foreach (['submitted', 'returned', 'resubmitted'] as $st) {
            $this->actingAs($this->lawyer())->put(route('filings.update', $f), ['status' => $st, 'filing_request_number' => 'REQ-1', 'event_note' => 'خطوة '.$st])->assertSessionHasNoErrors();
        }
        $this->actingAs($this->lawyer())->put(route('filings.update', $f), ['status' => 'registered', 'filing_request_number' => 'REQ-1', 'case_number' => '4471', 'court' => 'م', 'circuit' => 'د']);
        $types = TimelineEvent::where('legal_case_id', $case->id)->pluck('type')->all();
        $this->assertSame(4, collect($types)->filter(fn ($t) => $t === 'filing.status')->count());
        $this->assertContains('filing.registered', $types);
        $this->assertSame('court', $case->fresh()->stage);
    }

    public function test_postponing_hearing_updates_weekly_list_and_reanchors_tasks(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = $this->newCase(['start_point' => 'court', 'case_number' => '1']);
        $today = Carbon::now('Asia/Riyadh')->startOfDay();
        $this->post(route('hearings.store', $case), ['kind' => 'court', 'scheduled_at' => $today->copy()->addDays(2)->format('Y-m-d').' 10:00'])->assertSessionHasNoErrors();
        $h = $case->hearings()->first();
        $this->post(route('cases.tasks.store', $case), ['title' => 'تقديم رد', 'kind' => 'submit_response', 'due_mode' => 'next_hearing', 'anchor_offset_days' => -1])->assertSessionHasNoErrors();
        $task = Task::latest('id')->first();
        $this->assertSame($today->copy()->addDay()->toDateString(), $task->due_on->toDateString());

        $agenda = app(AgendaService::class)->week();
        $this->assertTrue(collect($agenda['week'])->contains('key', 'hearing-'.$h->id));

        $newDate = $today->copy()->addDays(20)->format('Y-m-d');
        $preview = $this->getJson(route('hearings.preview', $h).'?scheduled_at='.$newDate)->json('tasks');
        $this->assertSame($today->copy()->addDays(19)->toDateString(), $preview[0]['new']);

        $this->post(route('hearings.change', $h), ['action' => 'postpone', 'scheduled_at' => $newDate.' 10:00', 'reason' => 'طلب المحكمة', 'lock_version' => $h->lock_version])->assertSessionHasNoErrors();
        $this->assertSame('postponed', $h->fresh()->status);
        $task->refresh();
        $this->assertSame($today->copy()->addDays(19)->toDateString(), $task->due_on->toDateString());
        $this->assertSame('طلب المحكمة', mb_substr($task->dueChanges()->first()->reason, -11));
        $agenda = app(AgendaService::class)->week();
        $this->assertFalse(collect($agenda['week'])->contains('key', 'hearing-'.$h->id));
        $this->assertFalse(collect($agenda['week'])->contains('key', 'task-'.$task->id));
        $this->assertTrue(TimelineEvent::where('legal_case_id', $case->id)->where('type', 'hearing.postponed')->exists());
    }

    public function test_cancelled_hearing_moves_anchored_tasks_to_unscheduled(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = $this->newCase(['start_point' => 'court']);
        $this->post(route('hearings.store', $case), ['kind' => 'court', 'scheduled_at' => now('Asia/Riyadh')->addDays(3)->format('Y-m-d H:i')]);
        $h = $case->hearings()->first();
        $this->post(route('cases.tasks.store', $case), ['title' => 'إعداد مذكرة', 'kind' => 'prepare_memo', 'due_mode' => 'next_hearing', 'anchor_offset_days' => 0]);
        $this->post(route('hearings.change', $h), ['action' => 'cancel', 'reason' => 'إلغاء', 'lock_version' => $h->lock_version])->assertSessionHasNoErrors();
        $t = Task::latest('id')->first();
        $this->assertSame('unscheduled', $t->due_mode);
        $this->assertTrue(collect(app(AgendaService::class)->week()['unscheduled'])->contains('key', 'task-'.$t->id));
    }

    public function test_completing_hearing_adds_multiple_tasks_and_minutes(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = $this->newCase(['start_point' => 'court']);
        $this->post(route('hearings.store', $case), ['kind' => 'court', 'scheduled_at' => now('Asia/Riyadh')->format('Y-m-d H:i')]);
        $h = $case->hearings()->first();
        $this->post(route('documents.store'), ['owner_type' => 'case', 'owner_id' => $case->id, 'file' => DemoCasesSeeder::fakePdf('Minutes'), 'category' => 'hearing_minutes']);
        $minutes = \App\Models\Document::latest('id')->first();
        $next = now('Asia/Riyadh')->addDays(14)->format('Y-m-d').' 09:00';
        $this->post(route('hearings.complete', $h), [
            'attendance' => ['client' => 'represented', 'opponent' => 'absent', 'lawyer' => 'present'],
            'result' => 'أجلت للرد', 'directives' => 'تقديم الرد خلال عشرة أيام', 'minutes_document' => $minutes->uuid, 'next_hearing_at' => $next,
            'lock_version' => $h->lock_version,
            'tasks' => [
                ['title' => 'تقديم رد', 'kind' => 'submit_response', 'due_mode' => 'relative', 'relative_from' => now('Asia/Riyadh')->toDateString(), 'relative_event' => 'تاريخ الجلسة', 'relative_days' => 10, 'relative_day_type' => 'calendar'],
                ['title' => 'إرفاق مستند', 'kind' => 'attach_document', 'due_mode' => 'next_hearing', 'anchor_offset_days' => -2],
                ['title' => 'إعداد مذكرة', 'kind' => 'prepare_memo', 'due_mode' => 'date', 'due_on' => now('Asia/Riyadh')->addDays(5)->toDateString()],
            ],
        ])->assertSessionHasNoErrors();
        $h->refresh();
        $this->assertSame('completed', $h->status);
        $this->assertSame($minutes->id, $h->minutes_document_id);
        $this->assertNotNull($h->next_hearing_id);
        $this->assertSame(3, Task::where('source_hearing_id', $h->id)->count());
        $anchored = Task::where('source_hearing_id', $h->id)->where('due_mode', 'next_hearing')->first();
        $this->assertSame($h->next_hearing_id, $anchored->anchor_hearing_id);
    }

    public function test_judgment_with_awaited_deed_keeps_followup_separate_from_objection(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = $this->newCase(['start_point' => 'court']);
        $this->post(route('judgment.save', $case), ['outcome' => 'partial_win', 'pronounced_on' => '2026-11-01', 'deed_status' => 'awaiting'])->assertSessionHasNoErrors();
        $follow = Deadline::where('legal_case_id', $case->id)->where('kind', 'deed_followup')->first();
        $objection = Deadline::where('legal_case_id', $case->id)->where('kind', 'objection')->first();
        $this->assertSame('estimated', $follow->certainty);
        $this->assertNotNull($follow->end_on);
        // لا يُفترض أن تاريخ النطق بداية مهلة الاعتراض
        $this->assertSame('needs_determination', $objection->certainty);
        $this->assertNull($objection->end_on);
        $this->assertNull($objection->start_on);

        // صدور الصك يغلق المتابعة التقديرية ولا يمس عداد الاعتراض
        $this->post(route('judgment.save', $case), ['outcome' => 'partial_win', 'pronounced_on' => '2026-11-01', 'deed_status' => 'issued', 'deed_number' => '1', 'deed_date' => '2026-11-03']);
        $this->assertSame('closed', $follow->fresh()->status);
        $this->assertSame('open', $objection->fresh()->status);
        $this->assertSame('not_final', $case->judgment->fresh()->finality_status);

        // تأكيد النهائية إجراء صريح
        $this->post(route('judgment.finality', $case), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('final', $case->judgment->fresh()->finality_status);
        $this->assertSame('closed', $objection->fresh()->status);
    }

    public function test_struck_off_case_counts_as_resumable_not_completed_and_can_resume(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = $this->newCase(['start_point' => 'court']);
        $this->post(route('judgment.save', $case), ['outcome' => 'struck_off', 'pronounced_on' => '2026-11-01', 'deed_status' => 'awaiting']);
        $this->assertSame('struck_off', $case->fresh()->work_status);
        $counts = $case->client->fresh()->caseCounts();
        $this->assertGreaterThanOrEqual(1, $counts['resumable']);
        $completedBefore = $counts['completed'];
        $this->post(route('judgment.save', $case), ['outcome' => 'struck_off', 'pronounced_on' => '2026-11-01', 'deed_status' => 'awaiting', 'resumed_on' => '2026-11-04']);
        $this->assertSame('active', $case->fresh()->work_status);
        $this->assertSame($completedBefore, $case->client->fresh()->caseCounts()['completed']);
    }

    public function test_post_judgment_execution_decision_creates_linked_execution_once(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = $this->newCase(['start_point' => 'court']);
        $this->post(route('judgment.save', $case), ['outcome' => 'full_win', 'pronounced_on' => '2026-10-01', 'deed_status' => 'issued', 'deed_number' => 'D-9', 'deed_date' => '2026-10-05']);
        $this->post(route('judgment.execution', $case), ['decision' => 'pursue', 'execution_type' => 'financial']);
        $this->post(route('judgment.execution', $case), ['decision' => 'pursue', 'execution_type' => 'financial']);
        $this->assertSame(1, $case->executionRequests()->count());
        $e = $case->executionRequests()->first();
        $this->assertSame('D-9', $e->deed_number);
        $this->assertSame('execution', $case->fresh()->stage);
        $this->assertSame('pursue', $case->judgment->fresh()->execution_decision);
    }

    public function test_memo_versions_and_filing_dates_are_separate_and_sanitised(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = LegalCase::first();
        $this->post(route('memos.store', $case), ['type' => 'response', 'title' => 'مذكرة'])->assertRedirect();
        $memo = \App\Models\Memo::latest('id')->first();
        $this->put(route('memos.update', $memo), ['title' => 'مذكرة', 'body' => '<p>نص <script>alert(1)</script><img src=x onerror=alert(1)></p>', 'lock_version' => $memo->lock_version]);
        $memo->refresh();
        $this->put(route('memos.update', $memo), ['title' => 'مذكرة', 'body' => '<p style="text-align: right; color:red">نص ثانٍ</p>', 'lock_version' => $memo->lock_version]);
        $memo->refresh();
        $this->assertSame(2, $memo->current_version);
        $body1 = $memo->versions()->where('version', 1)->value('body_html');
        $this->assertStringNotContainsString('script', $body1);
        $this->assertStringNotContainsString('onerror', $body1);
        $this->assertStringNotContainsString('color', $memo->latestVersion()->body_html);
        $this->post(route('memos.status', $memo), ['status' => 'approved', 'lock_version' => $memo->lock_version]);
        $memo->refresh();
        $approvedAt = $memo->approved_at;
        $this->post(route('memos.status', $memo), ['status' => 'filed', 'filed_on' => '2026-12-01', 'lock_version' => $memo->lock_version])->assertSessionHasNoErrors();
        $memo->refresh();
        $this->assertSame('2026-12-01', $memo->filed_on->toDateString());
        $this->assertEquals($approvedAt->timestamp, $memo->approved_at->timestamp);
    }

    public function test_concurrent_edit_is_rejected_with_conflict(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $case = LegalCase::first();
        $v = $case->lock_version;
        $payload = ['title' => 'تعديل أول', 'client_role' => $case->client_role, 'stage' => $case->stage, 'currency' => 'SAR', 'lock_version' => $v];
        $this->put(route('cases.update', $case), $payload)->assertSessionHasNoErrors();
        $this->put(route('cases.update', $case), ['title' => 'تعديل متزامن قديم'] + $payload)->assertSessionHasErrors('lock_version');
        $this->putJson(route('cases.update', $case), ['title' => 'تعديل متزامن قديم'] + $payload)->assertStatus(409);
        $this->assertSame('تعديل أول', $case->fresh()->title);
    }
}
