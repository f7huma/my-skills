<?php

namespace App\Services\Reports;

use Illuminate\Validation\ValidationException;

/**
 * أنواع الأعمال التي يرفعها المكتب في تقارير العميل، وحقول كل نوع.
 * المصدر الوحيد للنموذج في الشاشة، والتحقق في الخادم، والعرض في الشاشة وPDF.
 * حقل `when` يُظهر الحقل لأنواع إجراءات تنفيذ محددة فقط.
 */
final class WorkCatalog
{
    public const GROUPS = [
        'conciliation' => 'الصلح',
        'litigation' => 'الدعاوى القضائية',
        'execution' => 'التنفيذ',
        'collection' => 'التحصيل والتواصل',
        'meetings' => 'الاجتماعات والمراجعات',
        'other' => 'أعمال أخرى',
    ];

    public const METHODS = ['حضوري', 'عن بُعد'];

    public const EXECUTION_ACTIONS = [
        'transfer' => 'تحويل مبالغ محجوزة',
        'disclosure' => 'طلب إفصاح',
        'tracing' => 'تتبع أموال',
        'grace' => 'طلب إمهال',
        'end_instrument' => 'إنهاء السند',
        'travel_ban' => 'إصدار قرار منع من السفر',
        'travel_ban_extend' => 'تمديد قرار منع من السفر',
        'address_entity' => 'مخاطبة جهة',
        'other' => 'إجراء آخر',
    ];

    public static function kinds(): array
    {
        $party = fn (string $label = 'الطرف الآخر', bool $req = false) => ['key' => 'party', 'label' => $label, 'type' => 'text', 'required' => $req];
        $number = fn (string $label = 'رقم الطلب') => ['key' => 'number', 'label' => $label, 'type' => 'text'];
        $method = ['key' => 'method', 'label' => 'طريقة الحضور', 'type' => 'select', 'options' => self::METHODS];
        $court = ['key' => 'court', 'label' => 'المحكمة', 'type' => 'text'];

        return [
            'conciliation_filed' => ['group' => 'conciliation', 'label' => 'تقييد طلب صلح', 'fields' => [$number('رقم طلب الصلح'), $party('المطلوب الصلح معه')]],
            'conciliation_session' => ['group' => 'conciliation', 'label' => 'حضور جلسة صلح', 'fields' => [$number('رقم طلب الصلح'), $party(), $method,
                ['key' => 'result', 'label' => 'نتيجة الجلسة', 'type' => 'select', 'options' => ['تم الصلح', 'لم يتم الصلح', 'تأجيل الجلسة', 'أخرى']]]],
            'claim_drafted' => ['group' => 'litigation', 'label' => 'كتابة لائحة دعوى', 'fields' => [$party('المدعى عليه'), ['key' => 'subject', 'label' => 'موضوع الدعوى', 'type' => 'text']]],
            'case_filed' => ['group' => 'litigation', 'label' => 'تقييد دعوى قضائية', 'fields' => [$number('رقم الدعوى'), $party('المدعى عليه'), $court]],
            'court_session' => ['group' => 'litigation', 'label' => 'حضور جلسة قضائية', 'fields' => [$number('رقم الدعوى'), $party('المدعى عليه'), $court, $method,
                ['key' => 'result', 'label' => 'ما تم في الجلسة', 'type' => 'text']]],
            'memo' => ['group' => 'litigation', 'label' => 'كتابة مذكرة جوابية', 'fields' => [$number('رقم الدعوى'), $party('المدعى عليه'), ['key' => 'subject', 'label' => 'موضوع المذكرة', 'type' => 'text']]],
            'judgment' => ['group' => 'litigation', 'label' => 'صدور حكم أو نتيجة', 'fields' => [$number('رقم الدعوى'), $party('المدعى عليه'),
                ['key' => 'result', 'label' => 'النتيجة', 'type' => 'select', 'options' => ['حكم لصالح العميل', 'حكم لصالح العميل جزئيًا', 'حكم ضد العميل', 'صلح', 'شطب الدعوى', 'أخرى']],
                ['key' => 'amount', 'label' => 'المبلغ المحكوم به (ريال)', 'type' => 'money'],
                ['key' => 'subject', 'label' => 'ملخص المنطوق', 'type' => 'text']]],
            'execution_filed' => ['group' => 'execution', 'label' => 'تقديم طلب تنفيذ', 'fields' => [$number('رقم طلب التنفيذ'), $party('المنفذ ضده', true),
                ['key' => 'instrument', 'label' => 'نوع السند', 'type' => 'text'], ['key' => 'amount', 'label' => 'مبلغ التنفيذ (ريال)', 'type' => 'money']]],
            'execution_action' => ['group' => 'execution', 'label' => 'إجراء على طلب التنفيذ', 'fields' => [
                ['key' => 'action', 'label' => 'نوع الإجراء', 'type' => 'select', 'options' => self::EXECUTION_ACTIONS, 'required' => true, 'headline' => true],
                $party('المتخذ ضده الإجراء', true), $number('رقم طلب التنفيذ'),
                ['key' => 'amount', 'label' => 'المبلغ المحوَّل (ريال)', 'type' => 'money', 'when' => ['transfer'], 'collected' => true],
                ['key' => 'bank', 'label' => 'الجهة المحجوز لديها', 'type' => 'text', 'when' => ['transfer']],
                ['key' => 'entity', 'label' => 'الجهة', 'type' => 'text', 'when' => ['disclosure', 'tracing', 'address_entity']],
                ['key' => 'duration', 'label' => 'المدة', 'type' => 'text', 'when' => ['grace', 'travel_ban', 'travel_ban_extend']],
                ['key' => 'action_label', 'label' => 'اسم الإجراء', 'type' => 'text', 'when' => ['other']],
                ['key' => 'result', 'label' => 'النتيجة', 'type' => 'text']]],
            'debtor_contact' => ['group' => 'collection', 'label' => 'التواصل مع مدين ومتابعة التحصيل', 'fields' => [$party('المدين / عميل الشركة', true),
                ['key' => 'channel', 'label' => 'وسيلة التواصل', 'type' => 'select', 'options' => ['اتصال هاتفي', 'رسالة', 'بريد إلكتروني', 'زيارة', 'اجتماع']],
                ['key' => 'result', 'label' => 'النتيجة', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'المبلغ المحصَّل (ريال)', 'type' => 'money', 'collected' => true]]],
            'meeting' => ['group' => 'meetings', 'label' => 'اجتماع مع الشركة', 'fields' => [$method, ['key' => 'subject', 'label' => 'محاور الاجتماع', 'type' => 'text']]],
            'agency_visit' => ['group' => 'meetings', 'label' => 'مراجعة جهة', 'fields' => [['key' => 'entity', 'label' => 'الجهة', 'type' => 'text', 'required' => true],
                ['key' => 'subject', 'label' => 'الغرض', 'type' => 'text']]],
            'other' => ['group' => 'other', 'label' => 'عمل آخر', 'fields' => [['key' => 'action_label', 'label' => 'العمل', 'type' => 'text', 'required' => true, 'headline' => true]]],
        ];
    }

    /** للواجهة: المجموعات وأنواعها وحقولها */
    public static function forClient(): array
    {
        $kinds = self::kinds();

        return collect(self::GROUPS)->map(fn ($label, $g) => ['key' => $g, 'label' => $label,
            'kinds' => collect($kinds)->filter(fn ($k) => $k['group'] === $g)->map(fn ($k, $key) => ['key' => $key, 'label' => $k['label'],
                'fields' => collect($k['fields'])->map(fn ($f) => [
                    'key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'] ?? false, 'when' => $f['when'] ?? null,
                    'options' => isset($f['options']) ? collect($f['options'])->map(fn ($l, $v) => ['value' => is_int($v) ? $l : $v, 'label' => $l])->values()->all() : null,
                ])->all()])->values()->all()])->values()->all();
    }

    /** يتحقق من حقول النوع ويعيد [الحقول النظيفة، عنوان نصي مختصر] */
    public static function validate(string $kind, array $input): array
    {
        $def = self::kinds()[$kind] ?? throw ValidationException::withMessages(['kind' => 'اختر نوع العمل.']);
        $action = $input['action'] ?? null;
        $out = [];
        $errors = [];
        foreach ($def['fields'] as $f) {
            if (isset($f['when']) && ! in_array($action, $f['when'], true)) {
                continue;
            }
            $v = trim((string) ($input[$f['key']] ?? ''));
            if ($v === '') {
                if ($f['required'] ?? false) {
                    $errors['fields.'.$f['key']] = 'حقل «'.$f['label'].'» مطلوب.';
                }

                continue;
            }
            if ($f['type'] === 'select') {
                $allowed = collect($f['options'])->map(fn ($l, $k) => is_int($k) ? $l : $k)->values()->all();
                if (! in_array($v, $allowed, true)) {
                    $errors['fields.'.$f['key']] = 'قيمة غير صالحة.';

                    continue;
                }
            } elseif ($f['type'] === 'money') {
                $v = str_replace([',', '٬', ' '], '', strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']));
                if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $v)) {
                    $errors['fields.'.$f['key']] = 'اكتب المبلغ بالأرقام فقط.';

                    continue;
                }
            } elseif (mb_strlen($v) > 300) {
                $errors['fields.'.$f['key']] = 'النص طويل (300 حرف كحد أقصى).';

                continue;
            }
            $out[$f['key']] = $v;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $p = self::present($kind, $out);

        return [$out, mb_substr(trim($p['headline'].' '.collect($p['facts'])->map(fn ($x) => $x['label'].': '.$x['value'])->implode(' · ')), 0, 300)];
    }

    /** العرض: عنوان العمل، ثم الحقائق (تسمية: قيمة)، والمبلغ المحصَّل إن وُجد */
    public static function present(?string $kind, ?array $fields): array
    {
        $def = $kind ? (self::kinds()[$kind] ?? null) : null;
        if (! $def) {
            return ['headline' => null, 'facts' => [], 'collected' => null, 'group' => 'other'];
        }
        $fields ??= [];
        $headline = $def['label'];
        $facts = [];
        $collected = null;
        foreach ($def['fields'] as $f) {
            $v = $fields[$f['key']] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            if ($f['headline'] ?? false) {
                $headline = ($f['options'] ?? null) && ! array_is_list($f['options']) ? ($f['options'][$v] ?? $v) : $v;
                if ($v === 'other' && ! empty($fields['action_label'])) {
                    $headline = $fields['action_label'];
                }

                continue;
            }
            if ($f['key'] === 'action_label' && $kind === 'execution_action') {
                continue;
            }
            $value = $f['type'] === 'money' ? number_format((float) $v, 2).' ريال' : $v;
            $facts[] = ['label' => $f['label'] === 'المبلغ المحوَّل (ريال)' ? 'المبلغ المحوَّل' : preg_replace('/\s*\(ريال\)$/u', '', $f['label']), 'value' => $value];
            if ($f['collected'] ?? false) {
                $collected = (float) $v;
            }
        }

        return ['headline' => $headline, 'facts' => $facts, 'collected' => $collected, 'group' => $def['group']];
    }
}
