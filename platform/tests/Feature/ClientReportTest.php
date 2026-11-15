<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientReport;
use App\Models\Collection;
use App\Models\LegalCase;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Services\Reports\ClientReportService;
use App\Services\Timeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** التقارير الدورية: الإعداد من أعمال الفترة، تحرير المحامي، النشر، والعزل بين العملاء */
class ClientReportTest extends TestCase
{
    private function make(string $client = 'شركة الأمثلة', string $period = 'monthly'): ClientReport
    {
        $c = $this->client($client);
        [$from, $to] = app(ClientReportService::class)->range($period, today_local()->toDateString());
        $this->actingAs($this->lawyer())->post('/office/client-reports', ['client_id' => $c->id, 'period' => $period, 'start' => $from->toDateString(), 'end' => $to->toDateString()])
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

    public function test_open_draft_captures_new_work_automatically_until_closed(): void
    {
        $this->demo();
        $r = $this->make();
        $case = LegalCase::where('client_id', $r->client_id)->whereNull('archived_at')->where('work_status', '!=', 'draft')->firstOrFail();
        $before = $r->items()->count();

        // إجراء جديد على قضية العميل يدخل المسودة المفتوحة فورًا، بلا ضغط أي زر
        Timeline::record($case, 'case.note', 'تقديم مذكرة جوابية', null, null, true, now());
        $this->assertSame($before + 1, $r->items()->count());
        $item = $r->items()->where('title', 'تقديم مذكرة جوابية')->firstOrFail();
        $this->assertSame('auto', $item->source);
        $this->assertSame('case:'.$case->id, $item->matter_key);

        // ما يعدّله المحامي يبقى، وما يحذفه لا يعود عند فتح التقرير
        $this->actingAs($this->lawyer())->put("/office/client-report-items/{$item->id}", ['matter_key' => $item->matter_key, 'occurred_on' => $item->occurred_on->toDateString(), 'title' => 'قدّمنا مذكرة جوابية مفصلة', 'details' => null])->assertSessionHasNoErrors();
        $victim = $r->items()->where('source', 'auto')->where('id', '!=', $item->id)->firstOrFail();
        $this->delete("/office/client-report-items/{$victim->id}")->assertSessionHasNoErrors();
        $this->get("/office/client-reports/{$r->id}")->assertOk();
        $this->assertSame('قدّمنا مذكرة جوابية مفصلة', $item->fresh()->title);
        $this->assertFalse($r->items()->where('source_ref', $victim->source_ref)->exists(), 'المحذوف لا يعود');
        $this->assertSame($before, $r->items()->count());

        // بعد الإغلاق لا يدخل شيء جديد
        $this->post("/office/client-reports/{$r->id}/close")->assertSessionHasNoErrors();
        $this->assertSame('closed', $r->fresh()->status);
        $this->assertNotNull($r->fresh()->closed_at);
        Timeline::record($case, 'case.note', 'إجراء بعد الإغلاق', null, null, true, now());
        $this->get("/office/client-reports/{$r->id}")->assertOk();
        $this->assertFalse($r->items()->where('title', 'إجراء بعد الإغلاق')->exists());

        // المكتمل لا يراه العميل حتى يُنشر، والسحب يعيده مكتملًا لا مسودة
        $cfo = User::where('email', 'cfo@example.test')->first();
        $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->assertNotFound();
        $this->actingAs($this->lawyer())->post("/office/client-reports/{$r->id}/publish")->assertSessionHasNoErrors();
        $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->assertOk();
        $this->actingAs($this->lawyer())->post("/office/client-reports/{$r->id}/unpublish")->assertSessionHasNoErrors();
        $this->assertSame('closed', $r->fresh()->status);

        // إعادة الفتح تلتقط ما فاته
        $this->post("/office/client-reports/{$r->id}/reopen")->assertSessionHasNoErrors();
        $this->assertSame('draft', $r->fresh()->status);
        $this->assertTrue($r->items()->where('title', 'إجراء بعد الإغلاق')->exists());
        $this->assertFalse($r->items()->where('source_ref', $victim->source_ref)->exists(), 'المحذوف لا يعود حتى بعد إعادة الفتح');
    }

    public function test_work_outside_the_period_or_for_another_client_does_not_enter(): void
    {
        $this->demo();
        $r = $this->make();
        $other = LegalCase::where('client_id', '!=', $r->client_id)->whereNull('archived_at')->firstOrFail();
        $mine = LegalCase::where('client_id', $r->client_id)->whereNull('archived_at')->where('work_status', '!=', 'draft')->firstOrFail();
        $count = $r->items()->count();
        Timeline::record($other, 'case.note', 'إجراء لعميل آخر', null, null, true, now());
        Timeline::record($mine, 'case.note', 'إجراء خارج الفترة', null, null, true, now()->addMonths(2));
        Timeline::record($mine, 'case.note', 'ملاحظة داخلية', null, null, false, now());
        $this->assertSame($count, $r->items()->count());
    }

    public function test_only_matters_with_work_in_period_and_collected_indicator_only_when_collected(): void
    {
        $this->demo();
        $r = $this->make();
        $page = $this->actingAs($this->lawyer())->get("/office/client-reports/{$r->id}")->viewData('page')['props']['report'];
        foreach ($page['matters'] as $m) {
            $this->assertNotEmpty($m['items'], 'كل ملف معروض وقع فيه عمل خلال الفترة');
            foreach ($m['items'] as $i) {
                $this->assertStringNotContainsString('تحصيل مبلغ', $i['title'], 'التحصيل مؤشر لا بند في القائمة');
                $this->assertArrayNotHasKey('hijri', $i);
                if ($i['source'] === 'auto') {
                    $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $i['title'].' '.$i['details'], 'لا تواريخ في الأعمال');
                }
            }
        }
        $touched = $r->items()->pluck('matter_key')->unique()->all();
        foreach ($r->fresh()->matters as $m) {
            $this->assertContains($m['key'], $touched);
        }
        $expected = Collection::where('client_id', $r->client_id)->where('kind', 'received')->where('visible_to_client', true)
            ->whereBetween('occurred_on', [$r->period_start->toDateString(), $r->period_end->toDateString()])->sum('amount');
        if ((float) $expected > 0) {
            $this->assertEqualsWithDelta((float) $expected, collect($page['stats']['collected'])->sum(fn ($a) => (float) $a['amount']), 0.01);
        } else {
            $this->assertSame([], $page['stats']['collected'], 'لا مؤشر إن لم يُحصَّل شيء');
        }

        // عميل بلا تحصيل في الفترة: لا مؤشر إطلاقًا
        $c = Client::create(['name' => 'عميل بلا تحصيل', 'type' => 'individual']);
        $this->post('/office/client-reports', ['client_id' => $c->id, 'period' => 'monthly', 'start' => today_local()->startOfMonth()->toDateString(), 'end' => today_local()->endOfMonth()->toDateString()]);
        $empty = ClientReport::latest('id')->firstOrFail();
        $p2 = $this->get("/office/client-reports/{$empty->id}")->viewData('page')['props']['report'];
        $this->assertSame([], $p2['stats']['collected']);
    }

    public function test_lawyer_sets_custom_start_and_end_and_can_change_them_later(): void
    {
        $this->demo();
        $c = $this->client('شركة الأمثلة');
        $lawyer = $this->lawyer();
        $from = today_local()->subDays(10)->toDateString();
        $to = today_local()->toDateString();
        $this->actingAs($lawyer)->post('/office/client-reports', ['client_id' => $c->id, 'period' => 'custom', 'start' => $to, 'end' => $from])->assertSessionHasErrors('end');
        $this->post('/office/client-reports', ['client_id' => $c->id, 'period' => 'custom', 'start' => $from, 'end' => $to])->assertSessionHasNoErrors();
        $r = ClientReport::latest('id')->firstOrFail();
        $this->assertSame([$from, $to], [$r->period_start->toDateString(), $r->period_end->toDateString()]);
        $this->assertStringStartsWith('تقرير الأعمال — ', $r->title);
        $r->items()->create(['matter_key' => 'general', 'occurred_on' => $to, 'title' => 'عمل يدوي', 'source' => 'manual']);

        // تضييق الفترة إلى يوم واحد قديم: يخرج المولَّد خارجها، ويبقى اليدوي
        $day = today_local()->subDays(400)->toDateString();
        $this->put("/office/client-reports/{$r->id}/period", ['period' => 'custom', 'start' => $day, 'end' => $day])->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertSame($day, $r->period_start->toDateString());
        $this->assertSame(0, $r->items()->where('source', 'auto')->count());
        $this->assertTrue($r->items()->where('title', 'عمل يدوي')->exists());

        // إعادتها تعيد التقاط الأعمال
        $this->put("/office/client-reports/{$r->id}/period", ['period' => 'custom', 'start' => $from, 'end' => $to])->assertSessionHasNoErrors();
        $this->assertGreaterThan(0, $r->items()->where('source', 'auto')->count());
        // المنشور لا تُعدَّل فترته
        $this->post("/office/client-reports/{$r->id}/publish");
        $this->put("/office/client-reports/{$r->id}/period", ['period' => 'custom', 'start' => $from, 'end' => $to])->assertStatus(422);
    }

    public function test_client_logo_upload_appears_in_report_and_is_isolated(): void
    {
        $this->demo();
        $r = $this->make();
        $png = UploadedFile::fake()->image('logo.png', 1200, 400);
        $this->actingAs($this->lawyer())->post("/office/clients/{$r->client_id}/logo", ['logo' => $png])->assertSessionHasNoErrors();
        $client = $r->client()->first();
        $this->assertNotNull($client->logo_path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($client->logo_path));
        $this->assertLessThanOrEqual(600, $w);
        $this->assertLessThanOrEqual(300, $h);
        $this->post("/office/clients/{$r->client_id}/logo", ['logo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');

        $page = $this->get("/office/client-reports/{$r->id}")->viewData('page')['props']['report'];
        $this->assertStringStartsWith("/office/clients/{$r->client_id}/logo", $page['client']['logo']);
        $this->get($page['client']['logo'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get("/office/client-reports/{$r->id}/pdf")->assertOk();

        $this->post("/office/client-reports/{$r->id}/publish");
        $cfo = User::where('email', 'cfo@example.test')->first();
        $p = $this->actingAs($cfo)->get("/portal/reports/{$r->id}")->viewData('page')['props']['report'];
        $this->actingAs($cfo)->get($p['client']['logo'])->assertOk();
        $noura = User::where('email', 'noura@example.test')->first();
        $this->actingAs($noura)->get("/portal/client-logo/{$r->client_id}")->assertNotFound();
        $this->actingAs($noura)->get("/office/clients/{$r->client_id}/logo")->assertForbidden();

        $this->actingAs($this->lawyer())->delete("/office/clients/{$r->client_id}/logo")->assertSessionHasNoErrors();
        $this->assertNull($client->fresh()->logo_path);
    }
}
