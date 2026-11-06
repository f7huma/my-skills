<?php

namespace Database\Seeders;

use App\Models\Claim;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Collection;
use App\Models\Deadline;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * بيانات «أسوأ حالة» واقعية لاختبار التصميم محليًا فقط (أسماء بأقصى طول مسموح، مبالغ ضخمة، بريد طويل،
 * قوائم كثيفة، عدد = 1). لا تُشغَّل في الإنتاج ولا في بيئة التجربة.
 * التشغيل:  php artisan migrate:fresh --seed && php artisan db:seed --class=WorstCaseSeeder
 */
class WorstCaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('WorstCaseSeeder is for local design testing only.');
        }
        $lawyer = User::where('role', 'lawyer')->firstOrFail();
        $today = today_local();

        // اسم منشأة بالحد الأقصى المسموح (200) — نمط حقيقي لأسماء الشركات القابضة
        $longName = mb_substr('شركة مجموعة عبدالرحمن بن عبدالعزيز بن محمد آل الشيخ القابضة للتطوير العقاري والاستثمار والمقاولات العامة وصيانة المباني والطرق والجسور والأعمال الكهربائية والميكانيكية — فرع المنطقة الشرقية بالدمام', 0, 200);
        $big = Client::create(['name' => $longName, 'type' => 'organization', 'unified_number' => '7009999999', 'commercial_registration' => '2050999999',
            'phone' => '+966 13 899 9999', 'email' => 'bartholomew.fitzgerald@northwind-industries-holdings.example.com', 'is_synthetic' => true,
            'address' => 'الدمام — حي الشاطئ الغربي — طريق الأمير محمد بن فهد — مبنى رقم 7788 — الدور الحادي عشر — مكتب 1104']);
        $one = Client::create(['name' => 'جو', 'type' => 'individual', 'national_id' => '1000000099', 'is_synthetic' => true]);

        $u = User::create(['email' => 'bartholomew.fitzgerald@northwind-industries-holdings.example.com', 'name' => 'المدير المالي والإداري للمجموعة — إدارة الشؤون القانونية والتحصيل',
            'password' => DemoSeeder::PASSWORD, 'role' => 'client']);
        ClientMembership::create(['client_id' => $big->id, 'user_id' => $u->id, 'can_view' => true, 'can_submit_claims' => true]);

        $title = mb_substr('دعوى مطالبة بمستحقات مالية متأخرة عن عقود توريد وتركيب أنظمة التكييف المركزي لمشروع المجمع السكني والتجاري بحي الشاطئ مع التعويض عن الأضرار الناتجة عن التأخير في السداد وفسخ العقد الأصلي وملاحقه الثلاثة وإلزام المدعى عليها بالمصاريف والأتعاب', 0, 250);
        $cases = [];
        foreach (['court', 'judgment', 'execution', 'intake', 'reconciliation', 'filing'] as $i => $stage) {
            $cases[] = LegalCase::create(['client_id' => $big->id, 'title' => $i === 0 ? $title : 'قضية '.($i + 1).' — '.Str::limit($title, 80, ''), 'client_role' => 'plaintiff',
                'stage' => $stage, 'work_status' => 'active', 'claim_amount' => '9999999999999.99', 'currency' => 'SAR',
                'client_summary' => str_repeat('نعمل حاليًا على متابعة الجلسة القادمة وإعداد المذكرة الجوابية، وسيتم تحديثكم فور صدور أي قرار من الدائرة. ', 12)]);
        }
        LegalCase::create(['client_id' => $one->id, 'title' => 'ب', 'client_role' => 'defendant', 'stage' => 'court', 'work_status' => 'active']);

        // مبلغ مقبوض ضخم هذا الشهر — يختبر عرض العداد في الشريط الفحمي
        Collection::create(['client_id' => $big->id, 'legal_case_id' => $cases[2]->id, 'kind' => 'received', 'source' => 'other', 'amount' => '1234567890.50',
            'currency' => 'SAR', 'occurred_on' => $today, 'entry_key' => (string) Str::uuid(), 'visible_to_client' => true]);
        Collection::create(['client_id' => $big->id, 'legal_case_id' => $cases[2]->id, 'kind' => 'claimed', 'source' => 'other', 'amount' => '9999999999999.99',
            'currency' => 'SAR', 'occurred_on' => $today, 'entry_key' => (string) Str::uuid(), 'visible_to_client' => true]);

        // يوم مزدحم: 24 بندًا اليوم وعناوين طويلة
        for ($i = 0; $i < 24; $i++) {
            Task::create(['client_id' => $big->id, 'legal_case_id' => $cases[$i % 6]->id, 'kind' => 'manual', 'due_mode' => 'date', 'due_on' => $today->toDateString(),
                'title' => $i % 3 === 0 ? 'مراجعة مستندات التوريد والفواتير الضريبية وإشعارات الدائن والمدين ومطابقتها مع كشوف الحساب البنكية للفترة من محرم إلى ذي الحجة' : 'متابعة '.($i + 1),
                'created_by' => $lawyer->id]);
        }
        Hearing::create(['legal_case_id' => $cases[0]->id, 'kind' => 'court', 'scheduled_at' => now()->addDay()->setTime(9, 30),
            'authority' => 'المحكمة التجارية بالدمام — الدائرة التجارية العاشرة — قاعة الجلسات الإلكترونية (عن بعد عبر منصة معين)', 'status' => 'scheduled']);
        Deadline::create(['client_id' => $big->id, 'legal_case_id' => $cases[1]->id, 'kind' => 'custom', 'certainty' => 'needs_review',
            'reason' => 'مهلة تقديم الاعتراض على الحكم الصادر في الدعوى التجارية المقيدة برقم 4470000001 لدى الدائرة العاشرة بعد استلام نسخة الصك المصدقة', 'end_on' => $today->copy()->addDays(1)->toDateString()]);
        Claim::create(['client_id' => $big->id, 'submitted_by' => $u->id, 'client_name' => $longName, 'status' => 'under_review', 'opponent_name' => $longName, 'amount' => '9999999999999.99',
            'currency' => 'SAR', 'submitted_at' => now(), 'reference' => 'CLM-2026-9999']);
    }
}
