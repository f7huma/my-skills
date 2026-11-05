<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Models\Task;
use Tests\TestCase;

/** حصر أعمال العميل: إضافة قضايا وطلبات تنفيذ منتهية سابقًا دون مهام أو مواعيد تلقائية */
class HistoricalWorkTest extends TestCase
{
    public function test_lawyer_adds_finished_case_without_tasks_or_deadlines(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $org = $this->client('شركة الأمثلة');
        $tasks = Task::count(); $deadlines = Deadline::count();

        $this->post(route('cases.store'), ['client_id' => $org->id, 'title' => 'قضية قديمة منتهية', 'client_role' => 'plaintiff', 'start_point' => 'archived',
            'case_number' => '4400000001', 'court' => 'المحكمة التجارية', 'outcome' => 'full_win', 'closed_on' => '2024-03-10', 'claim_amount' => '1000'])
            ->assertSessionHasNoErrors();
        $case = LegalCase::latest('id')->first();
        $this->assertSame('closed', $case->stage);
        $this->assertSame('completed', $case->work_status);
        $this->assertSame('full_win', $case->outcome);
        $this->assertSame('2024-03-10', $case->closed_on->toDateString());
        $this->assertSame('4400000001', $case->filings()->first()->case_number);
        $this->assertSame($tasks, Task::count());
        $this->assertSame($deadlines, Deadline::count());
        $this->assertSame(0, $case->collections()->count(), 'لا يُسجَّل مبلغ مطالب به تلقائيًا لقضية منتهية');

        $cases = collect($this->get(route('clients.show', $org))->assertOk()->viewData('page')['props']['cases']);
        $row = $cases->firstWhere('title', 'قضية قديمة منتهية');
        $this->assertNotNull($row, 'القضية المنتهية تظهر في صفحة العميل');
        $this->assertSame('2024-03-10', $row['closedOn']);
        $this->post(route('cases.store'), ['client_id' => $org->id, 'title' => 'x', 'client_role' => 'plaintiff', 'start_point' => 'archived',
            'closed_on' => now()->addYear()->toDateString()])->assertSessionHasErrors('closed_on');
    }

    public function test_finished_execution_creates_no_compliance_deadline(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $org = $this->client('شركة الأمثلة');
        $deadlines = Deadline::count();
        $this->post(route('executions.store'), ['client_id' => $org->id, 'execution_type' => 'financial', 'instrument_type' => 'judgment_deed', 'status' => 'completed',
            'currency' => 'SAR', 'order_issued_on' => '2023-05-01', 'notified_on' => '2023-05-03'])->assertSessionHasNoErrors();
        $this->assertSame('completed', ExecutionRequest::latest('id')->first()->status);
        $this->assertSame($deadlines, Deadline::count());
    }
}
