<?php

namespace Database\Seeders;

use App\Models\ActionType;
use App\Models\CaseCategory;
use App\Models\DeadlineRule;
use App\Models\Holiday;
use Illuminate\Database\Seeder;

/** بيانات مرجعية آمنة للإنتاج: تصنيفات وأنواع إجراءات وإجازات (تحتاج مراجعة) وقواعد مواعيد كمسودات */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['تجاري', 'عمالي', 'أحوال شخصية', 'عقاري', 'إداري', 'جزائي', 'مطالبة مالية'] as $n) {
            CaseCategory::firstOrCreate(['name' => $n]);
        }

        // مهلة إعادة الطلب قابلة للضبط لكل خدمة. القيم الأولية فارغة حتى يراجع المحامي شروط الخدمة في ناجز/وزارة العدل.
        foreach ([
            ['name' => 'طلب الإفصاح', 'condition_note' => 'راجع شرط المدة بين طلبات الإفصاح في ناجز قبل ضبط المهلة.'],
            ['name' => 'طلب تحويل المبلغ المحجوز', 'condition_note' => 'راجع شروط الخدمة لدى وزارة العدل قبل ضبط المهلة.'],
            ['name' => 'طلب إدراج ممثل نظامي', 'condition_note' => null],
            ['name' => 'طلب اتخاذ أمر أو إجراء', 'condition_note' => null],
        ] as $t) {
            ActionType::firstOrCreate(['name' => $t['name']], $t + ['interval_day_type' => 'calendar', 'interval_from_last_same_type' => true]);
        }

        // إجازات رسمية تقريبية — reviewed=false: يجب أن يراجعها المحامي ويعدلها وفق الإعلان الرسمي
        foreach ([
            ['2026-02-22', 'يوم التأسيس'],
            ['2026-03-19', 'إجازة عيد الفطر (تقديري)'], ['2026-03-20', 'إجازة عيد الفطر (تقديري)'], ['2026-03-22', 'إجازة عيد الفطر (تقديري)'],
            ['2026-03-23', 'إجازة عيد الفطر (تقديري)'],
            ['2026-05-25', 'إجازة عيد الأضحى (تقديري)'], ['2026-05-26', 'إجازة عيد الأضحى (تقديري)'], ['2026-05-27', 'إجازة عيد الأضحى (تقديري)'],
            ['2026-05-28', 'إجازة عيد الأضحى (تقديري)'], ['2026-05-31', 'إجازة عيد الأضحى (تقديري)'],
            ['2026-09-23', 'اليوم الوطني'],
            ['2027-02-22', 'يوم التأسيس'], ['2027-09-23', 'اليوم الوطني'],
        ] as [$d, $n]) {
            Holiday::firstOrCreate(['date' => $d], ['name' => $n, 'kind' => 'official', 'reviewed' => false]);
        }

        // قواعد المواعيد: مسودات فقط. لا تعمل قبل أن يراجع المحامي النص النظامي ويعتمدها.
        DeadlineRule::firstOrCreate(['code' => 'execution_compliance', 'version' => 1], [
            'name' => 'مهلة المنفذ ضده بعد الإبلاغ بأمر التنفيذ',
            'deadline_kind' => 'execution_compliance',
            'duration' => 5,
            'day_type' => 'business',
            'start_event' => 'notified_on',
            'effective_from' => '2026-10-29',
            'source_reference' => 'نظام التنفيذ الساري من 29/10/2026 — المواد 14 و18 و36 ولائحته التنفيذية (أم القرى). يجب التحقق من النص ونقطة البداية (الإبلاغ أو الإعلان) قبل الاعتماد.',
            'status' => 'draft',
            'notes' => 'مسودة مبدئية: لم يُتحقق من النص الرسمي آليًا. اعتمدها فقط بعد مراجعة المواد.',
        ]);
        DeadlineRule::firstOrCreate(['code' => 'objection', 'version' => 1], [
            'name' => 'مدة الاعتراض على الحكم',
            'deadline_kind' => 'objection',
            'duration' => 30,
            'day_type' => 'calendar',
            'start_event' => 'deed_date',
            'effective_from' => '2022-01-01',
            'source_reference' => 'نظام المرافعات الشرعية — تحقق من المدة ونقطة البداية بحسب نوع الحكم وطريقة التسليم قبل الاعتماد.',
            'status' => 'draft',
            'notes' => 'مسودة: المدة تختلف بحسب نوع الدعوى والحكم؛ لا تعتمد إلا بعد المراجعة.',
        ]);
    }
}
