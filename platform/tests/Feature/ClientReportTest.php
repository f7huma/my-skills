<?php

namespace Tests\Feature;

use App\Models\ClientReport;
use App\Models\TimelineEvent;
use App\Models\User;
use Tests\TestCase;

/** التقارير الدورية: الإعداد من أعمال الفترة، تحرير المحامي، النشر، والعزل بين العملاء */
class ClientReportTest extends TestCase
{
    private function make(string $client = 'شركة الأمثلة', string $period = 'monthly'): ClientReport
    {
        $c = $this->client($client);
        $this->actingAs($this->lawyer())->post('/office/client-reports', ['client_id' => $c->id, 'period' => $period, 'anchor' => today_local()->toDateString()])
            ->assertRedirect();

        return ClientReport::latest('id')->firstOrFail();
    }

    public function test_report_is_built_from_client_visible_work_only_without_internal_references(): void
    {
        $this->demo();
        $r = $this->make();
        $this->assertSame('draft', $r->status);
        $this->assertGreaterThan(0, $r->items()->count());
        $this->assertNotEmpty($r->matters);
        // لا ملاحظات داخلية ولا رموز مراجع المكتب
        $internal = TimelineEvent::where('client_id', $r->client_id)->where('client_visible', false)->whereNotIn('type', ['execution.action', 'execution.action_status', 'hearing.completed'])->pluck('title');
        foreach ($r->items as $i) {
            $this->assertNotContains($i->title, $internal->all());
            $this->assertDoesNotMatchRegularExpression('/\b(?:CS|EX|CLM|CL)-\d{4}-\d+/', $i->title.' '.$i->details);
        }
        $this->assertSame(today_local()->startOfMonth()->toDateString(), $r->period_start->toDateString());
        $this->assertSame(today_local()->endOfMonth()->toDateString(), $r->period_end->toDateString());
    }

    public function test_lawyer_has_full_edit_control_and_publishing_controls_client_visibility(): void
    {
        $this->demo();
        $r = $this->make();
        $lawyer = $this->lawyer();
        $first = $r->items()->first();
        $this->actingAs($lawyer)->put("/office/client-report-items/{$first->id}", ['matter_key' => $first->matter_key, 'occurred_on' => $first->occurred_on->toDateString(), 'title' => 'عنوان معدّل', 'details' => null])->assertSessionHasNoErrors();
        $this->assertSame('عنوان معدّل', $first->fresh()->title);
        $this->post("/office/client-reports/{$r->id}/items", ['matter_key' => 'general', 'occurred_on' => today_local()->toDateString(), 'title' => 'اجتماع خارج المنصة مع ممثل الشركة'])->assertSessionHasNoErrors();
        $second = $r->items()->where('id', '!=', $first->id)->where('source', 'auto')->first();
        $this->delete("/office/client-report-items/{$second->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('client_report_items', ['id' => $second->id]);
        $hide = $r->matters[0]['key'];
        $this->put("/office/client-reports/{$r->id}", ['title' => 'تقرير نوفمبر', 'intro' => 'ملخص', 'next_steps' => 'خطوات', 'hidden' => [$hide], 'lock_version' => $r->lock_version])->assertSessionHasNoErrors();
        // إعادة التوليد تُبقي الإضافة اليدوية
        $this->post("/office/client-reports/{$r->id}/regenerate")->assertSessionHasNoErrors();
        $this->assertTrue($r->items()->where('source', 'manual')->where('title', 'اجتماع خارج المنصة مع ممثل الشركة')->exists());

        $cfo = User::where('email', 'cfo@example.test')->first();
        $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->assertNotFound();   // مسودة
        $this->actingAs($lawyer)->post("/office/client-reports/{$r->id}/publish")->assertSessionHasNoErrors();
        $page = $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->assertOk()->viewData('page')['props']['report'];
        $this->assertSame('تقرير نوفمبر', $page['title']);
        $this->assertNotContains($hide, collect($page['matters'])->pluck('key')->all(), 'الملف المخفي لا يصل للعميل');
        $this->assertTrue(collect($page['general'])->contains('title', 'اجتماع خارج المنصة مع ممثل الشركة'));
        $this->assertContains($r->id, collect($this->get('/portal/reports')->viewData('page')['props']['reports'])->pluck('id'));
        $pdf = $this->get("/portal/reports/{$r->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_reports_are_isolated_between_clients_and_lawyer_only_routes(): void
    {
        $this->demo();
        $r = $this->make();
        $this->post("/office/client-reports/{$r->id}/publish");
        $noura = User::where('email', 'noura@example.test')->first();
        $this->actingAs($noura)->get("/portal/reports/{$r->id}")->assertNotFound();
        $this->actingAs($noura)->get("/portal/reports/{$r->id}/pdf")->assertNotFound();
        $this->assertNotContains($r->id, collect($this->actingAs($noura)->get('/portal/reports')->viewData('page')['props']['reports'])->pluck('id'));
        $this->actingAs($noura)->get("/office/client-reports/{$r->id}")->assertForbidden();
        $this->actingAs($noura)->post("/office/client-reports/{$r->id}/publish")->assertForbidden();
    }

    public function test_office_pdf_and_weekly_yearly_ranges(): void
    {
        $this->demo();
        $r = $this->make('شركة الأمثلة', 'weekly');
        $this->assertSame(0, $r->period_start->dayOfWeek, 'الأسبوع يبدأ الأحد');
        $this->assertSame(6, (int) $r->period_start->diffInDays($r->period_end));
        $this->get("/office/client-reports/{$r->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $y = $this->make('شركة الأمثلة', 'yearly');
        $this->assertSame(today_local()->year.'-01-01', $y->period_start->toDateString());
    }
}
