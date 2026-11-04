<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\ClientMembership;
use App\Models\Document;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** عزل العملاء: تغيير المعرف، الروابط القديمة، البحث، الملفات، التقارير، والتعطيل */
class IsolationTest extends TestCase
{
    public function test_portal_lists_only_own_records_and_hides_internal_fields(): void
    {
        $this->demo();
        $cfo = $this->user('cfo@example.test');
        $org = $this->client('شركة الأمثلة');
        $resp = $this->actingAs($cfo)->get('/portal')->assertOk();
        $resp->assertInertia(fn (Assert $p) => $p->component('Portal/Home')
            ->has('cases', LegalCase::where('client_id', $org->id)->where('work_status', '!=', 'draft')->count())
            ->has('executions', ExecutionRequest::where('client_id', $org->id)->where('status', '!=', 'draft')->count()));
        $json = json_encode($resp->viewData('page'));
        foreach (['internal_notes', 'internalNotes', 'استراتيجية داخلية', 'نزاع إيجاري', 'tasks', 'deadlines', 'lock_version', 'audit'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, "تسرب حقل داخلي: {$needle}");
        }
    }

    public function test_changing_ids_never_exposes_other_clients(): void
    {
        $this->demo();
        $salem = $this->user('salem@example.test');
        $foreignCase = LegalCase::where('client_id', '!=', $this->client('سالم')->id)->first();
        $foreignExec = ExecutionRequest::first();
        $foreignClaim = Claim::first();
        $this->actingAs($salem)->get('/portal/cases/'.$foreignCase->id)->assertNotFound();
        $this->actingAs($salem)->get('/portal/executions/'.$foreignExec->id)->assertNotFound();
        $this->actingAs($salem)->get('/portal/claims/'.$foreignClaim->id)->assertNotFound();
        $this->actingAs($salem)->put('/portal/claims/'.$foreignClaim->id, ['currency' => 'SAR', 'lock_version' => 1, 'opponent_name' => 'x'])->assertNotFound();
        $this->actingAs($salem)->post('/portal/claims/'.$foreignClaim->id.'/submit', ['submit_key' => (string) \Illuminate\Support\Str::uuid()])->assertNotFound();
        $this->actingAs($salem)->get('/portal/cases/999999')->assertNotFound();
    }

    public function test_internal_case_page_props_are_not_reachable_and_own_case_detail_is_allow_listed(): void
    {
        $this->demo();
        $salem = $this->user('salem@example.test');
        $own = LegalCase::where('client_id', $this->client('سالم')->id)->where('work_status', 'active')->first();
        $own->forceFill(['internal_notes' => 'SECRET-STRATEGY-123'])->save();
        $resp = $this->actingAs($salem)->get('/portal/cases/'.$own->id)->assertOk();
        $json = json_encode($resp->viewData('page'));
        $this->assertStringNotContainsString('SECRET-STRATEGY-123', $json);
        $this->assertStringNotContainsString('مذكرة رد', $json, 'مسودات المذكرات لا تظهر في البوابة');
    }

    public function test_draft_cases_are_hidden_from_portal(): void
    {
        $this->demo();
        $ind = $this->client('سالم');
        $draft = LegalCase::create(['client_id' => $ind->id, 'title' => 'مسودة سرية', 'client_role' => 'plaintiff', 'work_status' => 'draft']);
        $this->actingAs($this->user('salem@example.test'))->get('/portal/cases/'.$draft->id)->assertNotFound();
    }

    public function test_file_access_is_authorised_per_file_and_per_request(): void
    {
        $this->demo();
        $salem = $this->user('salem@example.test');
        $cfo = $this->user('cfo@example.test');
        $viewer = $this->user('viewer@example.test');
        $org = $this->client('شركة الأمثلة');

        $otherShared = Document::where('client_id', $this->client('نورة')->id)->where('shared_with_client', true)->first();
        $orgShared = Document::where('client_id', $org->id)->where('shared_with_client', true)->first();
        $orgInternal = Document::where('client_id', $org->id)->where('category', 'fee_contract')->first();
        $claimDoc = Document::where('documentable_type', (new Claim)->getMorphClass())->first();

        $this->actingAs($salem)->get('/files/'.$otherShared->uuid)->assertNotFound();
        $this->actingAs($salem)->get('/files/'.$orgShared->uuid)->assertNotFound();
        $this->actingAs($cfo)->get('/files/'.$orgShared->uuid)->assertOk();
        $this->assertStringContainsString('no-store', $this->actingAs($cfo)->get('/files/'.$orgShared->uuid)->headers->get('Cache-Control'));
        $this->actingAs($cfo)->get('/files/'.$orgInternal->uuid)->assertNotFound();
        $this->actingAs($cfo)->get('/files/'.$claimDoc->uuid)->assertOk();
        // صلاحية المتابعة فقط لا تفتح مرفقات المطالبات
        $this->actingAs($viewer)->get('/files/'.$claimDoc->uuid)->assertNotFound();
        $this->actingAs($viewer)->get('/files/'.$orgShared->uuid)->assertOk();
        $this->actingAs($this->lawyer())->get('/files/'.$orgInternal->uuid)->assertOk();
        $this->actingAs($cfo)->get('/files/'.\Illuminate\Support\Str::uuid())->assertNotFound();
    }

    public function test_view_only_member_cannot_submit_or_see_claims(): void
    {
        $this->demo();
        $viewer = $this->user('viewer@example.test');
        $this->actingAs($viewer)->post('/portal/claims', [])->assertForbidden();
        $this->actingAs($viewer)->get('/portal/claims/'.Claim::first()->id)->assertNotFound();
        $this->actingAs($viewer)->get('/portal')->assertOk()->assertInertia(fn (Assert $p) => $p->where('canSubmit', false)->has('claims', 0));
    }

    public function test_claims_member_cannot_create_claim_for_another_client(): void
    {
        $this->demo();
        $cfo = $this->user('cfo@example.test');
        $this->actingAs($cfo)->post('/portal/claims', ['client_id' => $this->client('نورة')->id])->assertSessionHasErrors('client_id');
        $this->assertSame(0, Claim::where('client_id', $this->client('نورة')->id)->count());
    }

    public function test_revoked_membership_and_old_links_lose_access(): void
    {
        $this->demo();
        $cfo = $this->user('cfo@example.test');
        $case = LegalCase::where('client_id', $this->client('شركة الأمثلة')->id)->where('work_status', '!=', 'draft')->first();
        $doc = Document::where('client_id', $case->client_id)->where('shared_with_client', true)->first();
        $this->actingAs($cfo)->get('/portal/cases/'.$case->id)->assertOk();
        ClientMembership::where('user_id', $cfo->id)->update(['revoked_at' => now()]);
        $this->actingAs($cfo->fresh())->get('/portal/cases/'.$case->id)->assertNotFound();
        $this->actingAs($cfo->fresh())->get('/files/'.$doc->uuid)->assertNotFound();
        $this->actingAs($cfo->fresh())->get('/portal')->assertOk()->assertInertia(fn (Assert $p) => $p->has('cases', 0));
    }

    public function test_unsharing_a_document_revokes_old_links(): void
    {
        $this->demo();
        $cfo = $this->user('cfo@example.test');
        $doc = Document::where('client_id', $this->client('شركة الأمثلة')->id)->where('shared_with_client', true)->first();
        $this->actingAs($cfo)->get('/files/'.$doc->uuid)->assertOk();
        $this->actingAs($this->lawyer())->post(route('documents.share', $doc), ['shared' => false]);
        $this->actingAs($cfo)->get('/files/'.$doc->uuid)->assertNotFound();
    }

    public function test_internal_search_is_lawyer_only_and_finds_by_numbers(): void
    {
        $this->demo();
        $this->actingAs($this->user('cfo@example.test'))->getJson('/office/search?q=4470')->assertForbidden();
        $r = $this->actingAs($this->lawyer())->getJson('/office/search?q=4470123456')->assertOk()->json('results');
        $this->assertNotEmpty($r);
        $this->assertSame('قضية', $r[0]['type']);
        $this->actingAs($this->lawyer())->getJson('/office/search?q=NAFITH')->assertOk()->assertJsonFragment(['type' => 'تنفيذ']);
        $this->actingAs($this->lawyer())->getJson('/office/search?q=44100003')->assertOk()->assertJsonFragment(['type' => 'وكالة']);
    }
}
