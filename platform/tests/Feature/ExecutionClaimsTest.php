<?php

namespace Tests\Feature;

use App\Models\ActionType;
use App\Models\Claim;
use App\Models\Collection;
use App\Models\Deadline;
use App\Models\DeadlineRule;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use Database\Seeders\DemoCasesSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExecutionClaimsTest extends TestCase
{
    public function test_financial_and_direct_execution_fields_follow_type(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $org = $this->client('شركة الأمثلة');
        $this->post(route('executions.store'), ['client_id' => $org->id, 'execution_type' => 'direct', 'instrument_type' => 'judgment_deed', 'status' => 'draft', 'currency' => 'SAR'])
            ->assertSessionHasErrors(['subject_location', 'obligation_description']);
        $this->post(route('executions.store'), ['client_id' => $org->id, 'execution_type' => 'direct', 'instrument_type' => 'judgment_deed', 'status' => 'submitted', 'currency' => 'SAR',
            'subject_location' => 'عقار بالرياض', 'obligation_description' => 'تسليم العين المؤجرة', 'note_number' => 'SHOULD-BE-IGNORED'])->assertSessionHasNoErrors();
        $e = ExecutionRequest::latest('id')->first();
        $this->assertSame('عقار بالرياض', $e->subject_location);
        $this->assertNull($e->note_number, 'حقول السند لأمر لا تُحفظ لنوع صك الحكم');

        $this->post(route('executions.store'), ['client_id' => $org->id, 'execution_type' => 'financial', 'instrument_type' => 'electronic_promissory_note', 'status' => 'submitted',
            'note_number' => 'EPN-1', 'note_amount' => '1500.75', 'note_currency' => 'USD', 'currency' => 'USD', 'amount_claimed' => '1500.75'])->assertSessionHasNoErrors();
        $e2 = ExecutionRequest::latest('id')->first();
        $this->assertNull($e2->legal_case_id);
        $this->assertSame('1500.75', $e2->note_amount);
    }

    public function test_execution_dates_are_separate_and_compliance_needs_determination_without_active_rule(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $e = ExecutionRequest::where('instrument_type', 'electronic_promissory_note')->first();
        $data = ['execution_type' => 'financial', 'instrument_type' => 'electronic_promissory_note', 'status' => 'order_issued', 'currency' => 'USD',
            'submitted_on' => '2026-11-01', 'accepted_on' => '2026-11-02', 'order_issued_on' => '2026-11-03', 'lock_version' => $e->lock_version];
        $this->put(route('executions.update', $e), $data)->assertSessionHasNoErrors();
        $d = Deadline::where('unique_key', 'execution:'.$e->id.':compliance')->first();
        $this->assertSame('needs_determination', $d->certainty);
        $this->assertNull($d->end_on);
        // بعد الإبلاغ، ومع عدم وجود قاعدة معتمدة: يبقى "يحتاج تحديدًا"
        $this->put(route('executions.update', $e), ['notified_on' => '2026-11-05', 'notification_method' => 'notification', 'lock_version' => $e->fresh()->lock_version] + $data)->assertSessionHasNoErrors();
        $d->refresh();
        $this->assertSame('needs_determination', $d->certainty);
        $this->assertSame('2026-11-05', $d->start_on->toDateString());
        $this->assertSame(0, $e->decisions()->count(), 'لا يُنشأ قرار آليًا');
    }

    public function test_active_rule_computes_five_business_days_from_notification(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        DeadlineRule::where('code', 'execution_compliance')->update(['status' => 'active']);
        $e = ExecutionRequest::where('instrument_type', 'electronic_promissory_note')->first();
        // الأحد 2026-11-01 + 5 أيام عمل (تتخطى الجمعة والسبت) = الأحد 2026-11-08
        $this->put(route('executions.update', $e), ['execution_type' => 'financial', 'instrument_type' => 'electronic_promissory_note', 'status' => 'order_issued',
            'currency' => 'USD', 'order_issued_on' => '2026-10-30', 'notified_on' => '2026-11-01', 'notification_method' => 'notification', 'lock_version' => $e->lock_version])->assertSessionHasNoErrors();
        $d = Deadline::where('unique_key', 'execution:'.$e->id.':compliance')->first();
        $this->assertSame('needs_review', $d->certainty);
        $this->assertSame('2026-11-08', $d->end_on->toDateString());
        $this->assertStringContainsString('الإبلاغ', $d->basis);
        // قاعدة غير سارية في تاريخ الواقعة لا تنطبق
        $this->put(route('executions.update', $e), ['execution_type' => 'financial', 'instrument_type' => 'electronic_promissory_note', 'status' => 'order_issued',
            'currency' => 'USD', 'notified_on' => '2026-10-20', 'lock_version' => $e->fresh()->lock_version]);
        $this->assertSame('needs_determination', $d->fresh()->certainty);
        // التمديد اليدوي مع السبب يحوّله لموعد مؤكد لا يُستبدل آليًا
        $this->put(route('deadlines.update', $d), ['kind' => 'execution_compliance', 'certainty' => 'confirmed', 'reason' => $d->reason, 'end_on' => '2026-11-15', 'extension_reason' => 'تمديد بقرار'])->assertSessionHasNoErrors();
        $this->put(route('executions.update', $e), ['execution_type' => 'financial', 'instrument_type' => 'electronic_promissory_note', 'status' => 'order_issued',
            'currency' => 'USD', 'notified_on' => '2026-11-01', 'lock_version' => $e->fresh()->lock_version]);
        $this->assertSame('2026-11-15', $d->fresh()->end_on->toDateString());
        $this->assertSame('confirmed', $d->fresh()->certainty);
    }

    public function test_action_resubmission_interval_from_last_same_type(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $type = ActionType::where('name', 'طلب الإفصاح')->first();
        $type->update(['resubmission_interval_days' => 30, 'interval_from_last_same_type' => true]);
        $e = ExecutionRequest::where('instrument_type', 'electronic_promissory_note')->first();
        $this->post(route('executions.actions.store', $e), ['action_type_id' => $type->id, 'submitted_on' => '2026-10-01', 'status' => 'rejected']);
        $this->post(route('executions.actions.store', $e), ['action_type_id' => $type->id, 'submitted_on' => '2026-10-20', 'status' => 'submitted']);
        $this->assertSame('2026-11-19', $e->actions()->latest('id')->first()->eligible_resubmission_on->toDateString());
    }

    public function test_partial_collection_totals_per_currency_without_double_counting(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $e = ExecutionRequest::whereNotNull('legal_case_id')->first();
        $key = (string) Str::uuid();
        $payload = ['entry_key' => $key, 'execution_request_id' => $e->id, 'kind' => 'received', 'source' => 'execution', 'amount' => '0.10', 'currency' => 'SAR', 'occurred_on' => '2026-11-03'];
        $this->post(route('collections.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('collections.store'), $payload); // إعادة إرسال
        $this->post(route('collections.store'), ['entry_key' => (string) Str::uuid(), 'amount' => '0.20'] + $payload);
        $this->assertSame(1, Collection::where('entry_key', $key)->count());
        $row = Collection::where('entry_key', $key)->first();
        $this->assertSame($e->legal_case_id, $row->legal_case_id, 'الدفعة مرتبطة بالقضية والتنفيذ معًا');

        $caseTotals = Collection::totals(Collection::where('legal_case_id', $e->legal_case_id));
        $execTotals = Collection::totals(Collection::where('execution_request_id', $e->id));
        $clientTotals = Collection::totals(Collection::where('client_id', $e->client_id));
        // 25000.25 + 0.10 + 0.20 بدقة عشرية
        $this->assertSame('25000.55', $execTotals['received']['SAR']);
        $this->assertSame('25000.55', $caseTotals['received']['SAR']);
        $this->assertSame('25000.55', $clientTotals['received']['SAR']);
        $this->assertArrayHasKey('USD', $clientTotals['claimed']);
        $this->assertArrayNotHasKey('USD', $clientTotals['received'] ?? []);
    }

    public function test_claim_submission_is_idempotent_and_flags_missing_documents(): void
    {
        $this->demo();
        $cfo = $this->user('cfo@example.test');
        $this->actingAs($cfo)->post('/portal/claims')->assertRedirect();
        $claim = Claim::latest('id')->first();
        $this->assertSame('شركة الأمثلة التجارية (تجريبية)', $claim->client_name, 'اسم موكل المكتب يُملأ تلقائيًا');
        $this->actingAs($cfo)->put('/portal/claims/'.$claim->id, ['opponent_type' => 'organization', 'opponent_name' => 'خصم', 'opponent_identifier' => '1010101010', 'amount' => '1000', 'currency' => 'SAR', 'lock_version' => $claim->lock_version])->assertSessionHasNoErrors();
        $key = (string) Str::uuid();
        $this->actingAs($cfo)->post('/portal/claims/'.$claim->id.'/submit', ['submit_key' => $key])->assertSessionHasErrors('missing_document_reason');
        $this->actingAs($cfo)->post('/portal/claims/'.$claim->id.'/documents', ['file' => DemoCasesSeeder::fakePdf('Inv A'), 'category' => 'invoice', 'party' => 'client']);
        $this->actingAs($cfo)->post('/portal/claims/'.$claim->id.'/documents', ['file' => DemoCasesSeeder::fakePdf('Inv B'), 'category' => 'invoice', 'party' => 'client']);
        $claim->refresh();
        $this->actingAs($cfo)->put('/portal/claims/'.$claim->id, ['opponent_type' => 'organization', 'opponent_name' => 'خصم', 'opponent_identifier' => '1010101010', 'amount' => '1000', 'currency' => 'SAR',
            'missing_document_reason' => 'كشف الحساب والسجل غير متوفرين', 'lock_version' => $claim->lock_version]);
        $this->actingAs($cfo)->post('/portal/claims/'.$claim->id.'/submit', ['submit_key' => $key])->assertRedirect();
        $this->actingAs($cfo)->post('/portal/claims/'.$claim->id.'/submit', ['submit_key' => $key])->assertRedirect();
        $claim->refresh();
        $this->assertSame('under_review', $claim->status);
        $this->assertTrue($claim->missing_documents);
        $this->assertNotNull($claim->reference);
        $this->assertSame(1, $claim->messages()->where('kind', 'status')->count());
        $this->assertSame(2, $claim->documents()->where('category', 'invoice')->count());
        // بعد الإرسال لا يستطيع العميل التعديل
        $this->actingAs($cfo)->put('/portal/claims/'.$claim->id, ['currency' => 'SAR', 'lock_version' => $claim->lock_version])->assertForbidden();
    }

    public function test_claim_completion_and_conversion_to_case_is_not_duplicated(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $claim = Claim::where('status', 'under_review')->first();
        $this->post(route('claims.request-completion', $claim), ['message' => 'نرجو إرفاق السجل التجاري'])->assertSessionHasNoErrors();
        $this->assertSame('needs_completion', $claim->fresh()->status);
        $this->actingAs($this->user('cfo@example.test'))->get('/portal/claims/'.$claim->id)->assertOk()
            ->assertInertia(fn ($p) => $p->where('claim.messages', fn ($m) => collect($m)->contains('body', 'نرجو إرفاق السجل التجاري')));
        $this->post(route('claims.note', $claim), ['body' => 'ملاحظة داخلية سرية', 'visible_to_client' => false]);
        $this->actingAs($this->user('cfo@example.test'))->get('/portal/claims/'.$claim->id)
            ->assertInertia(fn ($p) => $p->where('claim.messages', fn ($m) => ! collect($m)->contains('body', 'ملاحظة داخلية سرية')));

        $this->actingAs($this->lawyer());
        $this->post(route('claims.convert-case', $claim), ['title' => 'قضية من مطالبة']);
        $this->post(route('claims.convert-case', $claim), ['title' => 'قضية من مطالبة']);
        $this->assertSame(1, LegalCase::where('source_claim_id', $claim->id)->count());
        $case = LegalCase::where('source_claim_id', $claim->id)->first();
        $this->assertSame('converted_case', $claim->fresh()->status);
        // مرفقات المطالبة تظهر في صفحة القضية
        $this->get(route('cases.show', $case))->assertOk()->assertInertia(fn ($p) => $p->where('documents', fn ($d) => count($d) >= 3));
        $this->post(route('claims.convert-execution', $claim), ['execution_type' => 'financial', 'instrument_type' => 'other', 'instrument_label' => 'فاتورة'])->assertSessionHasErrors('claim');
    }

    public function test_claim_can_be_closed_with_documented_reason(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $claim = Claim::where('status', 'under_review')->first();
        $this->post(route('claims.close', $claim), [])->assertSessionHasErrors('reason');
        $this->post(route('claims.close', $claim), ['reason' => 'خارج نطاق الخدمة', 'client_message' => 'نعتذر عن قبول المطالبة'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $claim->fresh()->status);
        $this->assertSame('خارج نطاق الخدمة', $claim->fresh()->closed_reason);
    }

    public function test_claim_converts_to_execution_with_link(): void
    {
        $this->demo();
        $this->actingAs($this->lawyer());
        $claim = Claim::where('status', 'under_review')->first();
        $this->post(route('claims.convert-execution', $claim), ['execution_type' => 'financial', 'instrument_type' => 'electronic_promissory_note'])->assertRedirect();
        $e = ExecutionRequest::where('source_claim_id', $claim->id)->first();
        $this->assertNotNull($e);
        $this->assertSame($claim->amount, $e->amount_claimed);
        $this->assertSame('converted_execution', $claim->fresh()->status);
    }
}
