<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientReport;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Services\Reports\ClientReportService;
use App\Services\Timeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** التقارير الدورية: أعمال منظَّمة يضيفها المحامي، وعكس أعمال المنصة اختياري، والنشر والعزل */
class ClientReportTest extends TestCase
{
    private function make(string $client = 'شركة الأمثلة', string $period = 'monthly', bool $auto = false): ClientReport
    {
        $c = $this->client($client);
        [$from, $to] = app(ClientReportService::class)->range($period, today_local()->toDateString());
        $this->actingAs($this->lawyer())->post('/office/client-reports', ['client_id' => $c->id, 'period' => $period,
            'start' => $from->toDateString(), 'end' => $to->toDateString(), 'auto_sync' => $auto])->assertRedirect()->assertSessionHasNoErrors();

        return ClientReport::latest('id')->firstOrFail();
    }

    private function add(ClientReport $r, string $kind, array $fields, ?string $notes = null)
    {
        $res = $this->actingAs($this->lawyer())->post("/office/client-reports/{$r->id}/items", ['kind' => $kind, 'fields' => $fields, 'notes' => $notes]);
        $this->assertLessThan(500, $res->status());

        return $res;
    }

    private function page(ClientReport $r): array
    {
        return $this->actingAs($this->lawyer())->get("/office/client-reports/{$r->id}")->assertOk()->viewData('page')['props'];
    }

    public function test_report_is_empty_by_default_and_platform_work_is_not_reflected(): void
    {
        $this->demo();
        $r = $this->make();
        $this->assertFalse($r->auto_sync);
        $this->assertSame(0, $r->items()->count());
        $case = LegalCase::where('client_id', $r->client_id)->whereNull('archived_at')->where('work_status', '!=', 'draft')->firstOrFail();
        Timeline::record($case, 'case.note', 'إجراء على المنصة', null, null, true, now());
        $this->assertSame(0, $r->items()->count(), 'العكس مطفأ افتراضيًا');
        $props = $this->page($r);
        $this->assertNotEmpty($props['catalog']);
        $this->assertSame([], $props['report']['groups']);
    }

    public function test_lawyer_adds_structured_work_grouped_by_stage(): void
    {
        $this->demo();
        $r = $this->make();
        $this->add($r, 'conciliation_filed', ['number' => '1447-00123', 'party' => 'مؤسسة النور'])->assertSessionHasNoErrors();
        $this->add($r, 'court_session', ['number' => '447011', 'court' => 'المحكمة التجارية بالرياض', 'method' => 'عن بُعد', 'result' => 'تأجيل لتقديم مذكرة'])->assertSessionHasNoErrors();
        $this->add($r, 'execution_action', ['action' => 'travel_ban', 'party' => 'خالد سعد', 'number' => '470099', 'duration' => 'سنة', 'amount' => '999'])->assertSessionHasNoErrors();
        $this->add($r, 'execution_action', ['action' => 'transfer', 'party' => 'خالد سعد', 'amount' => '12,500.50', 'bank' => 'مصرف الراجحي'])->assertSessionHasNoErrors();
        $this->add($r, 'debtor_contact', ['party' => 'شركة البناء', 'channel' => 'اتصال هاتفي', 'amount' => '3000'])->assertSessionHasNoErrors();
        $this->add($r, 'meeting', ['method' => 'حضوري', 'subject' => 'مراجعة المطالبات'], 'اتفقنا على أولويات الشهر')->assertSessionHasNoErrors();

        // التحقق: الحقول المطلوبة والقيم غير الصالحة
        $this->add($r, 'execution_action', ['action' => 'disclosure'])->assertSessionHasErrors('fields.party');
        $this->add($r, 'execution_action', ['action' => str_repeat('س', 301), 'party' => 'س'])->assertSessionHasErrors('fields.action');
        $this->add($r, 'debtor_contact', ['party' => 'س', 'amount' => 'مبلغ'])->assertSessionHasErrors('fields.amount');
        $this->add($r, 'nope', [])->assertSessionHasErrors('kind');

        $rep = $this->page($r)['report'];
        $this->assertSame(['conciliation', 'litigation', 'execution', 'collection', 'meetings'], collect($rep['groups'])->pluck('key')->all());
        $exec = collect($rep['groups'])->firstWhere('key', 'execution')['items'];
        $this->assertSame('إصدار قرار منع من السفر', $exec[0]['headline']);
        $facts = collect($exec[0]['facts'])->pluck('value', 'label');
        $this->assertSame('خالد سعد', $facts['المتخذ ضده الإجراء']);
        $this->assertSame('سنة', $facts['المدة']);
        $this->assertFalse($facts->has('المبلغ المحوَّل'), 'حقل لا يخص نوع الإجراء لا يُحفظ');
        $this->assertSame('تحويل مبالغ محجوزة', $exec[1]['headline']);
        $this->assertSame(6, $rep['stats']['total']);
        $this->assertEqualsWithDelta(15500.50, (float) $rep['stats']['collected'][0]['amount'], 0.001);

        // التعديل
        $item = $r->items()->where('kind', 'meeting')->firstOrFail();
        $this->put("/office/client-report-items/{$item->id}", ['kind' => 'meeting', 'fields' => ['method' => 'عن بُعد', 'subject' => 'متابعة'], 'notes' => null])->assertSessionHasNoErrors();
        $this->assertSame('عن بُعد', $item->fresh()->fields['method']);

        // النشر والعرض للعميل وPDF
        $cfo = User::where('email', 'cfo@example.test')->first();
        $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->assertNotFound();
        $this->actingAs($this->lawyer())->post("/office/client-reports/{$r->id}/publish")->assertSessionHasNoErrors();
        $p = $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->assertOk()->viewData('page')['props']['report'];
        $this->assertCount(5, $p['groups']);
        $pdf = $this->get("/portal/reports/{$r->id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_auto_sync_is_optional_and_can_be_switched_on_and_off(): void
    {
        $this->demo();
        $r = $this->make();
        $this->add($r, 'meeting', ['method' => 'حضوري'])->assertSessionHasNoErrors();
        $this->put("/office/client-reports/{$r->id}/auto-sync", ['auto_sync' => true])->assertSessionHasNoErrors();
        $this->assertTrue($r->fresh()->auto_sync);
        $this->assertGreaterThan(0, $r->items()->where('source', 'auto')->count());
        $this->assertGreaterThan(0, $this->page($r)['report']['platform']['count']);

        $this->put("/office/client-reports/{$r->id}/auto-sync", ['auto_sync' => false])->assertSessionHasNoErrors();
        $this->assertSame(0, $r->items()->where('source', 'auto')->count());
        $this->assertSame(1, $r->items()->where('source', 'manual')->count(), 'أعمال المحامي تبقى');
    }

    public function test_open_auto_report_captures_new_work_until_closed(): void
    {
        $this->demo();
        $r = $this->make('شركة الأمثلة', 'monthly', true);
        $case = LegalCase::where('client_id', $r->client_id)->whereNull('archived_at')->where('work_status', '!=', 'draft')->firstOrFail();
        $before = $r->items()->count();
        Timeline::record($case, 'case.note', 'تقديم مذكرة جوابية', null, null, true, now());
        $this->assertSame($before + 1, $r->items()->count());
        $item = $r->items()->where('title', 'تقديم مذكرة جوابية')->firstOrFail();

        $victim = $r->items()->where('source', 'auto')->where('id', '!=', $item->id)->firstOrFail();
        $this->actingAs($this->lawyer())->delete("/office/client-report-items/{$victim->id}")->assertSessionHasNoErrors();
        $this->get("/office/client-reports/{$r->id}")->assertOk();
        $this->assertFalse($r->items()->where('source_ref', $victim->source_ref)->exists(), 'المحذوف لا يعود');

        $this->post("/office/client-reports/{$r->id}/close")->assertSessionHasNoErrors();
        Timeline::record($case, 'case.note', 'إجراء بعد الإغلاق', null, null, true, now());
        $this->assertFalse($r->items()->where('title', 'إجراء بعد الإغلاق')->exists());
        $this->post("/office/client-reports/{$r->id}/reopen")->assertSessionHasNoErrors();
        $this->assertTrue($r->items()->where('title', 'إجراء بعد الإغلاق')->exists());

        // لا يدخل إلا الظاهر للعميل وداخل الفترة ولهذا العميل
        $other = LegalCase::where('client_id', '!=', $r->client_id)->whereNull('archived_at')->firstOrFail();
        $count = $r->items()->count();
        Timeline::record($other, 'case.note', 'إجراء لعميل آخر', null, null, true, now());
        Timeline::record($case, 'case.note', 'ملاحظة داخلية', null, null, false, now());
        $this->assertSame($count, $r->items()->count());
        foreach ($r->items as $i) {
            $this->assertDoesNotMatchRegularExpression('/\b(?:CS|EX|CLM|CL)-\d{4}-\d+/', $i->title.' '.$i->details);
        }
        $this->assertNotContains('ملاحظة داخلية', TimelineEvent::where('client_visible', false)->pluck('title')->intersect($r->items()->pluck('title'))->all());
    }

    public function test_reports_are_isolated_between_clients_and_lawyer_only_routes(): void
    {
        $this->demo();
        $r = $this->make();
        $this->post("/office/client-reports/{$r->id}/publish");
        $noura = User::where('email', 'noura@example.test')->first();
        $this->actingAs($noura)->get("/portal/reports/{$r->id}")->assertNotFound();
        $this->actingAs($noura)->get("/portal/reports/{$r->id}/pdf")->assertNotFound();
        $this->actingAs($noura)->get("/office/client-reports/{$r->id}")->assertForbidden();
        $this->actingAs($noura)->post("/office/client-reports/{$r->id}/items", ['kind' => 'meeting', 'fields' => []])->assertForbidden();
    }

    public function test_custom_period_and_changing_it_later(): void
    {
        $this->demo();
        $c = $this->client('شركة الأمثلة');
        $from = today_local()->subDays(10)->toDateString();
        $to = today_local()->toDateString();
        $this->actingAs($this->lawyer())->post('/office/client-reports', ['client_id' => $c->id, 'period' => 'custom', 'start' => $to, 'end' => $from])->assertSessionHasErrors('end');
        $this->post('/office/client-reports', ['client_id' => $c->id, 'period' => 'custom', 'start' => $from, 'end' => $to])->assertSessionHasNoErrors();
        $r = ClientReport::latest('id')->firstOrFail();
        $this->assertSame([$from, $to], [$r->period_start->toDateString(), $r->period_end->toDateString()]);
        $this->assertStringStartsWith('تقرير الأعمال — ', $r->title);
        $this->add($r, 'agency_visit', ['entity' => 'وزارة العدل'])->assertSessionHasNoErrors();
        $day = today_local()->subDays(400)->toDateString();
        $this->put("/office/client-reports/{$r->id}/period", ['period' => 'custom', 'start' => $day, 'end' => $day])->assertSessionHasNoErrors();
        $this->assertSame($day, $r->fresh()->period_start->toDateString());
        $this->assertSame(1, $r->items()->count(), 'أعمال المحامي تبقى عند تغيير الفترة');
        $this->post("/office/client-reports/{$r->id}/publish");
        $this->put("/office/client-reports/{$r->id}/period", ['period' => 'custom', 'start' => $from, 'end' => $to])->assertStatus(422);
        $w = $this->make('شركة الأمثلة', 'weekly');
        $this->assertSame(0, $w->period_start->dayOfWeek, 'الأسبوع يبدأ الأحد');
    }

    public function test_client_logo_upload_appears_in_report_and_is_isolated(): void
    {
        $this->demo();
        $r = $this->make();
        $this->actingAs($this->lawyer())->post("/office/clients/{$r->client_id}/logo", ['logo' => UploadedFile::fake()->image('logo.png', 1200, 400)])->assertSessionHasNoErrors();
        $client = Client::findOrFail($r->client_id);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($client->logo_path));
        $this->assertLessThanOrEqual(600, $w);
        $this->assertLessThanOrEqual(300, $h);
        $this->post("/office/clients/{$r->client_id}/logo", ['logo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');
        $logo = $this->page($r)['report']['client']['logo'];
        $this->get($logo)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get("/office/client-reports/{$r->id}/pdf")->assertOk();

        $this->post("/office/client-reports/{$r->id}/publish");
        $cfo = User::where('email', 'cfo@example.test')->first();
        $p = $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->viewData('page')['props']['report'];
        $this->actingAs($cfo)->get($p['client']['logo'])->assertOk();
        $noura = User::where('email', 'noura@example.test')->first();
        $this->actingAs($noura)->get("/portal/client-logo/{$r->client_id}")->assertNotFound();
        $this->actingAs($this->lawyer())->delete("/office/clients/{$r->client_id}/logo")->assertSessionHasNoErrors();
        $this->assertNull($client->fresh()->logo_path);
    }

    public function test_linked_work_is_reflected_on_its_request_or_case_page_once_on_publish(): void
    {
        $this->demo();
        $r = $this->make();
        $exec = ExecutionRequest::where('client_id', $r->client_id)->first()
            ?? ExecutionRequest::forceCreate(['reference' => 'EX-TEST-0001', 'client_id' => $r->client_id, 'execution_type' => 'financial',
                'instrument_type' => 'judgment_deed', 'request_number' => '470099887', 'status' => 'submitted']);
        $case = LegalCase::where('client_id', $r->client_id)->whereNull('archived_at')->firstOrFail();
        $foreign = LegalCase::where('client_id', '!=', $r->client_id)->firstOrFail();

        $links = $this->page($r)['links'];
        $this->assertContains($exec->id, array_column($links['executions'], 'id'));
        $this->assertNotContains($foreign->id, array_column($links['cases'], 'id'), 'القوائم لهذا العميل فقط');

        $this->add($r, 'execution_action', ['action' => 'travel_ban', 'execution_id' => $exec->id, 'party' => 'خالد سعد', 'duration' => 'سنة'])->assertSessionHasNoErrors();
        $this->add($r, 'execution_action', ['action' => 'طلب حجز على عقار', 'execution_id' => $exec->id, 'party' => 'خالد سعد'])->assertSessionHasNoErrors();
        $this->add($r, 'memo', ['case_id' => $case->id, 'subject' => 'الرد على دفوع المدعى عليه'])->assertSessionHasNoErrors();
        $this->add($r, 'debtor_contact', ['party' => 'شركة البناء', 'channel' => 'واتساب'])->assertSessionHasNoErrors(); // نص حر في قائمة
        $this->add($r, 'execution_custom', ['action_label' => 'متابعة مع مأمور التنفيذ', 'execution_id' => $exec->id])->assertSessionHasNoErrors();
        $this->add($r, 'memo', ['case_id' => $foreign->id])->assertSessionHasErrors('fields.case_id');

        $rep = $this->page($r)['report'];
        $items = collect($rep['groups'])->flatMap(fn ($g) => $g['items']);
        $this->assertSame(['إصدار قرار منع من السفر', 'طلب حجز على عقار'], $items->where('kind', 'execution_action')->pluck('headline')->values()->all());
        $this->assertSame('واتساب', collect($items->firstWhere('kind', 'debtor_contact')['facts'])->firstWhere('label', 'وسيلة التواصل')['value']);
        $this->assertFalse($items->firstWhere('kind', 'memo')['reflected']);

        $actionsBefore = $exec->actions()->count();
        $eventsBefore = $case->timeline()->count();
        $this->post("/office/client-reports/{$r->id}/publish")->assertSessionHasNoErrors();
        $this->assertSame($actionsBefore + 2, $exec->actions()->count(), 'إجراءا التنفيذ أُضيفا لسجل الطلب');
        $this->assertTrue($exec->actions()->whereHas('type', fn ($q) => $q->where('name', 'إصدار قرار منع من السفر'))->exists());
        $this->assertTrue(TimelineEvent::where('execution_request_id', $exec->id)->where('type', 'report.work')->where('title', 'متابعة مع مأمور التنفيذ')->exists());
        $this->assertSame($eventsBefore + 1, $case->timeline()->count());
        $this->assertSame(4, $r->items()->whereNotNull('reflected_at')->count());

        // إعادة النشر لا تكرر
        $this->post("/office/client-reports/{$r->id}/unpublish");
        $this->post("/office/client-reports/{$r->id}/publish");
        $this->assertSame($actionsBefore + 2, $exec->actions()->count());
        $this->assertSame($eventsBefore + 1, $case->timeline()->count());
        // والعميل لا يرى بيانات الربط الداخلية
        $cfo = User::where('email', 'cfo@example.test')->first();
        $p = $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->viewData('page')['props']['report'];
        $this->assertArrayNotHasKey('link', $p['groups'][0]['items'][0]);
    }
}
