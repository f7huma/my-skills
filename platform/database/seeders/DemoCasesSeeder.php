<?php

namespace Database\Seeders;

use App\Models\CaseCategory;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Collection;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Files\FileVault;
use App\Services\Workflow\CaseService;
use App\Services\Workflow\ClaimService;
use App\Services\Workflow\ExecutionService;
use App\Services\Workflow\HearingService;
use App\Services\Workflow\TaskService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DemoCasesSeeder extends Seeder
{
    public static function fakePdf(string $title = 'Synthetic'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R>>endobj\n"
            ."4 0 obj<</Length 44>>stream\nBT /F1 12 Tf 72 720 Td ({$title}) Tj ET\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        file_put_contents($path, $content);

        return new UploadedFile($path, Str::slug($title).'.pdf', 'application/pdf', null, true);
    }

    public function run(User $lawyer, Client $ind, Client $org, Client $other): void
    {
        if (LegalCase::exists()) {
            return;
        }
        Auth::setUser($lawyer);
        $cases = app(CaseService::class);
        $hearings = app(HearingService::class);
        $tasks = app(TaskService::class);
        $exec = app(ExecutionService::class);
        $vault = app(FileVault::class);
        $tz = config('office.display_timezone');
        $today = now($tz)->startOfDay();
        $cat = fn ($n) => CaseCategory::where('name', $n)->value('id');
        $store = function ($file, $attrs, $owner = null) use ($vault) {
            $d = $vault->store($file, $attrs, $owner);
            $vault->scan($d);

            return $d->fresh();
        };

        // وكالات
        $ind->powersOfAttorney()->create(['number' => '44100001', 'issued_on' => $today->copy()->subYear(), 'expires_on' => $today->copy()->addDays(18), 'status' => 'active']);
        $org->powersOfAttorney()->create(['number' => '44100002', 'issued_on' => $today->copy()->subYears(2), 'expires_on' => $today->copy()->subDays(10), 'status' => 'active']);
        $poaOrg = $org->powersOfAttorney()->create(['number' => '44100003', 'issued_on' => $today->copy()->subMonth(), 'expires_on' => $today->copy()->addYears(2), 'status' => 'active']);
        $store(self::fakePdf('Fee contract'), ['client_id' => $org->id, 'category' => 'fee_contract', 'party' => 'office']);
        $store(self::fakePdf('Commercial registration'), ['client_id' => $org->id, 'category' => 'commercial_registration', 'party' => 'client', 'shared_with_client' => true]);

        // 1) قضية فرد: صلح تعذّر ← ناجز ← قيد ← جلسة قادمة
        $c1 = LegalCase::create(['client_id' => $ind->id, 'title' => 'مطالبة بمستحقات عقد توريد', 'category_id' => $cat('تجاري'), 'client_role' => 'plaintiff',
            'stage' => 'reconciliation', 'work_status' => 'active', 'claim_amount' => '185000.00', 'currency' => 'SAR',
            'client_summary' => 'قُيّدت الدعوى وحُدد موعد الجلسة الأولى، ونعمل على إعداد المذكرة.', 'client_summary_updated_at' => now()]);
        $c1->parties()->create(['name' => 'مؤسسة المثال للمقاولات (تجريبية)', 'role' => 'defendant', 'party_type' => 'organization', 'identifier' => '1010999999']);
        $r1 = $c1->reconciliations()->create(['request_number' => 'TR-26-00451', 'submitted_on' => $today->copy()->subDays(40), 'status' => 'in_progress']);
        $h0 = $hearings->schedule($c1, ['kind' => 'reconciliation', 'reconciliation_request_id' => $r1->id, 'scheduled_at' => $today->copy()->subDays(20)->setTime(10, 0)->format('Y-m-d H:i'), 'authority' => 'مركز المصالحة — تراضي']);
        $hearings->complete($h0, ['attendance' => ['client' => 'represented', 'opponent' => 'absent', 'lawyer' => 'present'], 'result' => 'غياب الطرف الآخر'], [], $h0->lock_version);
        $cases->recordReconciliationOutcome($r1, ['status' => 'failed', 'outcome_on' => $today->copy()->subDays(19)->toDateString(), 'notes' => 'تعذّر الصلح لغياب الطرف الآخر']);
        $cases->moveToCourt($r1->fresh());
        $f1 = $c1->filings()->first();
        $cases->updateFiling($f1, ['filing_request_number' => 'NJ-REQ-778812', 'submitted_on' => $today->copy()->subDays(12)->toDateString(), 'status' => 'registered',
            'case_number' => '4470123456', 'registered_on' => $today->copy()->subDays(8)->toDateString(), 'court' => 'المحكمة التجارية بالرياض', 'circuit' => 'الدائرة التجارية الخامسة']);
        $h1 = $hearings->schedule($c1, ['kind' => 'court', 'court_filing_id' => $f1->id, 'scheduled_at' => app(\App\Services\Calendar::class)->add($today, 2, 'business')->format('Y-m-d').' 09:30', 'authority' => 'المحكمة التجارية بالرياض', 'meeting_url' => 'https://example.test/hearing']);
        $tasks->create(['title' => 'إرفاق كشف الحساب المحدث', 'kind' => 'attach_document', 'due_mode' => 'next_hearing', 'anchor_offset_days' => -1], $c1);
        $tasks->create(['title' => 'مراجعة مستندات التوريد', 'kind' => 'manual', 'due_mode' => 'date', 'due_on' => $today->copy()->subDay()->toDateString()], $c1);
        $c1->memos()->create(['type' => 'statement_of_claim', 'title' => 'لائحة الدعوى — مستحقات التوريد', 'status' => 'filed', 'filed_on' => $today->copy()->subDays(12), 'approved_at' => now()->subDays(13)]);
        $c1->memos()->create(['type' => 'response', 'title' => 'مذكرة رد على دفوع المدعى عليه', 'status' => 'draft', 'due_on' => $today->copy()->addDays(1)]);

        // 2) قضية شركة كمدعى عليه تبدأ من القضاء — حكم نُطق به والصك منتظر
        $c2 = LegalCase::create(['client_id' => $org->id, 'title' => 'دعوى مقامة ضد الشركة بشأن فسخ عقد', 'category_id' => $cat('تجاري'), 'client_role' => 'defendant',
            'stage' => 'court', 'work_status' => 'active', 'power_of_attorney_id' => $poaOrg->id]);
        $c2->parties()->create(['name' => 'خالد المدعي (تجريبي)', 'role' => 'plaintiff', 'party_type' => 'individual', 'identifier' => '1000000099']);
        $c2->filings()->create(['status' => 'registered', 'case_number' => '4470654321', 'court' => 'المحكمة التجارية بجدة', 'circuit' => 'الدائرة الثانية']);
        $h2 = $hearings->schedule($c2, ['kind' => 'court', 'scheduled_at' => $today->copy()->setTime(13, 0)->format('Y-m-d H:i'), 'authority' => 'المحكمة التجارية بجدة']);
        $cases->recordJudgment($c2, ['outcome' => 'dismissed', 'pronounced_on' => $today->copy()->subDays(3)->toDateString(), 'deed_status' => 'awaiting']);

        // 3) قضية شركة: حكم لصالحها، صك صادر، تنفيذ مالي مع تحصيل جزئي
        $c3 = LegalCase::create(['client_id' => $org->id, 'title' => 'مطالبة بقيمة فواتير غير مسددة', 'category_id' => $cat('مطالبة مالية'), 'client_role' => 'plaintiff',
            'stage' => 'judgment', 'work_status' => 'active', 'claim_amount' => '92500.50', 'currency' => 'SAR',
            'client_summary' => 'صدر الحكم لصالح الشركة وبدأنا إجراءات التنفيذ.', 'client_summary_updated_at' => now()->subDays(2)]);
        $c3->parties()->create(['name' => 'مؤسسة المدين (تجريبية)', 'role' => 'defendant', 'party_type' => 'organization', 'identifier' => '1010888888']);
        $c3->filings()->create(['status' => 'registered', 'case_number' => '4470111222', 'court' => 'المحكمة التجارية بالرياض', 'circuit' => 'الدائرة الأولى']);
        $deed = $store(self::fakePdf('Judgment deed'), ['client_id' => $org->id, 'category' => 'deed', 'party' => 'court', 'shared_with_client' => true], $c3);
        $cases->recordJudgment($c3, ['outcome' => 'full_win', 'pronounced_on' => $today->copy()->subDays(60)->toDateString(), 'deed_status' => 'issued',
            'deed_number' => '4471234', 'deed_date' => $today->copy()->subDays(50)->toDateString(), 'deed_document_id' => $deed->id, 'objection_status' => 'none']);
        $cases->confirmFinality($c3->judgment->fresh(), null);
        $cases->setExecutionDecision($c3->judgment->fresh(), 'pursue');
        $e1 = $exec->create(['execution_type' => 'financial', 'instrument_type' => 'judgment_deed', 'status' => 'order_issued', 'request_number' => '470099887',
            'submitted_on' => $today->copy()->subDays(30)->toDateString(), 'accepted_on' => $today->copy()->subDays(28)->toDateString(),
            'order_issued_on' => $today->copy()->subDays(25)->toDateString(), 'notified_on' => $today->copy()->subDays(6)->toDateString(), 'notification_method' => 'notification',
            'amount_claimed' => '92500.50', 'currency' => 'SAR', 'weekly_followup' => true, 'client_summary' => 'صدر أمر التنفيذ وتم إبلاغ المنفذ ضده.',
            'parties' => [['name' => 'مؤسسة المدين (تجريبية)', 'role' => 'respondent', 'party_type' => 'organization', 'identifier' => '1010888888']]], $c3);
        $e1->forceFill(['client_summary_updated_at' => now()])->save();
        $e1->decisions()->create(['label' => 'قرار 34', 'number' => '34-778', 'decided_on' => $today->copy()->subDays(25)]);
        $exec->addAction($e1, ['action_type_id' => \App\Models\ActionType::where('name', 'طلب الإفصاح')->value('id'), 'submitted_on' => $today->copy()->subDays(5)->toDateString(),
            'status' => 'submitted', 'next_followup_on' => $today->copy()->addDays(3)->toDateString()]);
        Collection::create(['client_id' => $org->id, 'legal_case_id' => $c3->id, 'execution_request_id' => $e1->id, 'kind' => 'seized', 'source' => 'execution',
            'amount' => '40000.00', 'currency' => 'SAR', 'occurred_on' => $today->copy()->subDays(4), 'entry_key' => (string) Str::uuid(), 'description' => 'حجز على حساب بنكي']);
        Collection::create(['client_id' => $org->id, 'legal_case_id' => $c3->id, 'execution_request_id' => $e1->id, 'kind' => 'received', 'source' => 'execution',
            'amount' => '25000.25', 'currency' => 'SAR', 'occurred_on' => $today->copy()->subDays(2), 'entry_key' => (string) Str::uuid(), 'description' => 'تحويل مبلغ محجوز — دفعة أولى']);

        // 4) تنفيذ مستقل بسند لأمر إلكتروني (عملة أخرى)
        $exec->create(['client_id' => $org->id, 'execution_type' => 'financial', 'instrument_type' => 'electronic_promissory_note', 'status' => 'submitted',
            'request_number' => '470055441', 'submitted_on' => $today->copy()->subDays(3)->toDateString(), 'note_number' => 'NAFITH-55821', 'note_amount' => '15000.00',
            'note_currency' => 'USD', 'note_issued_on' => $today->copy()->subMonths(4)->toDateString(), 'note_due_on' => $today->copy()->subMonth()->toDateString(),
            'amount_claimed' => '15000.00', 'currency' => 'USD',
            'parties' => [['name' => 'محمد المدين (تجريبي)', 'role' => 'respondent', 'party_type' => 'individual', 'identifier' => '1000000777']]]);

        // 5) قضية عميل آخر (للتحقق من العزل)
        $c5 = LegalCase::create(['client_id' => $other->id, 'title' => 'نزاع إيجاري — قضية سرية لعميل آخر', 'category_id' => $cat('عقاري'), 'client_role' => 'plaintiff',
            'stage' => 'court', 'work_status' => 'active', 'internal_notes' => 'استراتيجية داخلية لا تظهر للعميل.', 'client_summary' => 'القضية منظورة.', 'client_summary_updated_at' => now()]);
        $store(self::fakePdf('Other client lease'), ['client_id' => $other->id, 'category' => 'agreement', 'party' => 'client', 'shared_with_client' => true], $c5);
        $c6 = LegalCase::create(['client_id' => $ind->id, 'title' => 'دعوى موقوفة قابلة للاستكمال', 'client_role' => 'plaintiff', 'stage' => 'court', 'work_status' => 'suspended']);

        // مطالبة واردة قيد الدراسة مع مرفقات
        $claim = Claim::create(['client_id' => $org->id, 'submitted_by' => User::where('email', 'cfo@example.test')->value('id'), 'client_name' => $org->name, 'status' => 'draft',
            'opponent_type' => 'organization', 'opponent_name' => 'شركة العميل المتعثر (تجريبية)', 'opponent_identifier' => '1010777777', 'amount' => '48250.00', 'currency' => 'SAR',
            'description' => 'فواتير توريد مستحقة منذ ستة أشهر.']);
        $store(self::fakePdf('Invoice 1001'), ['client_id' => $org->id, 'category' => 'invoice', 'party' => 'client', 'label' => 'فاتورة 1001'], $claim);
        $store(self::fakePdf('Invoice 1002'), ['client_id' => $org->id, 'category' => 'invoice', 'party' => 'client', 'label' => 'فاتورة 1002'], $claim);
        $store(self::fakePdf('Statement'), ['client_id' => $org->id, 'category' => 'statement', 'party' => 'client'], $claim);
        $claim->forceFill(['missing_document_reason' => 'السجل التجاري للطرف المطالب ضده غير متوفر لدينا حاليًا.'])->save();
        Auth::setUser(User::where('email', 'cfo@example.test')->first());
        app(ClaimService::class)->submit($claim, (string) Str::uuid());
        Auth::setUser($lawyer);
    }
}
