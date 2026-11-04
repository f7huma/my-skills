<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    public function test_client_variant_excludes_internal_data_and_internal_variant_includes_options(): void
    {
        $this->demo();
        $other = $this->client('نورة');
        $q = ['type' => 'client', 'id' => $other->id, 'timeline' => 1];
        $client = $this->actingAs($this->lawyer())->get(route('reports.preview', $q + ['variant' => 'client']))->assertOk()->getContent();
        $this->assertStringContainsString(config('office.name'), $client);
        $this->assertStringContainsString('47529', $client);
        $this->assertStringNotContainsString('استراتيجية داخلية', $client);
        $internal = $this->actingAs($this->lawyer())->get(route('reports.preview', $q + ['variant' => 'internal', 'notes' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('استراتيجية داخلية', $internal);
        $this->assertStringContainsString('نسخة داخلية', $internal);
    }

    public function test_pdf_generation_for_client_and_case_reports(): void
    {
        $this->demo();
        $org = $this->client('شركة الأمثلة');
        // عميل بقضايا كثيرة ووصف طويل
        for ($i = 0; $i < 25; $i++) {
            LegalCase::create(['client_id' => $org->id, 'title' => 'قضية رقم '.$i.' '.str_repeat('وصف طويل للقضية لاختبار الالتفاف ', 4), 'client_role' => 'plaintiff', 'work_status' => 'active', 'stage' => 'court',
                'client_summary' => str_repeat('ملخص موجه للعميل يوضح آخر المستجدات. ', 10), 'client_summary_updated_at' => now()]);
        }
        $r = $this->actingAs($this->lawyer())->get(route('reports.pdf', ['type' => 'client', 'id' => $org->id, 'variant' => 'client', 'timeline' => 1]));
        $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $r->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        @mkdir(storage_path('app/report-samples'), 0700, true);
        file_put_contents(storage_path('app/report-samples/client-many-cases.pdf'), $pdf);
        $this->assertGreaterThan(2, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));

        $case = LegalCase::where('client_id', $this->client('سالم')->id)->where('work_status', 'active')->first();
        $r2 = $this->actingAs($this->lawyer())->get(route('reports.pdf', ['type' => 'case', 'id' => $case->id, 'variant' => 'client', 'timeline' => 1]));
        $r2->assertOk();
        file_put_contents(storage_path('app/report-samples/case-single.pdf'), $r2->getContent());
        $r3 = $this->actingAs($this->lawyer())->get(route('reports.pdf', ['type' => 'case', 'id' => $case->id, 'variant' => 'internal', 'timeline' => 1, 'tasks' => 1, 'hearings' => 1, 'parties' => 1, 'notes' => 1]));
        $r3->assertOk();
        file_put_contents(storage_path('app/report-samples/case-internal.pdf'), $r3->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.generated']);
    }
}
