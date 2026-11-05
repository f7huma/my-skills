import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button, cx, PageHeader, SelectField, TextArea, TextField } from '@/Components/ui';
import type { Option } from '@/types';

type Party = { name: string; role: string; party_type: string; identifier: string };

export default function Create({ clients, poas, options, preset }: { clients: Option[]; poas: (Option & { clientId: number })[]; options: { roles: Option[]; categories: Option[]; currencies: string[]; outcomes: Option[] }; preset: { client_id: number | null; archived?: boolean } }) {
  const form = useForm({
    client_id: preset.client_id ? String(preset.client_id) : '', title: '', category_id: '', client_role: 'plaintiff', claim_amount: '', currency: 'SAR',
    power_of_attorney_id: '', start_point: preset.archived ? 'archived' : 'draft', case_number: '', court: '', circuit: '', parties: [] as Party[],
    outcome: '', closed_on: '', client_summary: '',
  });
  const clientPoas = poas.filter((p) => String(p.clientId) === form.data.client_id);
  const setParty = (i: number, k: keyof Party, v: string) => form.setData('parties', form.data.parties.map((p, j) => (j === i ? { ...p, [k]: v } : p)));
  const starts = [
    ['draft', 'مسودة', 'احفظ بأقل البيانات وأكمل لاحقًا'],
    ['reconciliation', 'الصلح (تراضي)', 'يبدأ بطلب صلح'],
    ['filing', 'طلب تقييد (ناجز)', 'لائحة دعوى قيد التقديم'],
    ['court', 'قضية مقيدة', 'منظورة أمام المحكمة — للمدعي أو المدعى عليه'],
    ['archived', 'قضية منتهية سابقًا', 'لحصر أعمال العميل — بلا مهام ولا مواعيد'],
  ];
  const archived = form.data.start_point === 'archived';
  return (
    <div className="max-w-[920px]">
      <Head title="قضية جديدة" />
      <PageHeader title={archived ? 'إضافة قضية منتهية' : 'قضية جديدة'} subtitle={archived ? 'قضية انتهت سابقًا تُضاف لحصر أعمال العميل: لا تُنشأ لها مهام ولا مواعيد. أي حقل غير العميل والعنوان اختياري.' : 'العميل والعنوان كافيان لحفظ المسودة. لا يُشترط تعبئة المراحل القضائية الآن.'} crumbs={[{ label: 'القضايا', href: '/office/cases' }]} />
      <form onSubmit={(e) => { e.preventDefault(); form.post('/office/cases'); }} className="space-y-6" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField label="العميل" required options={clients} placeholder="اختر العميل" value={form.data.client_id} onChange={(e) => form.setData('client_id', e.target.value)} error={form.errors.client_id} />
          <TextField label="عنوان القضية" required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} maxLength={250} />
          <SelectField label="صفة العميل" options={options.roles} value={form.data.client_role} onChange={(e) => form.setData('client_role', e.target.value)} />
          <SelectField label="التصنيف" options={options.categories} placeholder="بدون تصنيف" value={form.data.category_id} onChange={(e) => form.setData('category_id', e.target.value)} />
          <SelectField label="الوكالة المستخدمة" options={clientPoas} placeholder={clientPoas.length ? 'اختر الوكالة' : 'لا وكالات سارية لهذا العميل'} value={form.data.power_of_attorney_id} onChange={(e) => form.setData('power_of_attorney_id', e.target.value)} error={form.errors.power_of_attorney_id} />
          <div className="grid grid-cols-[1fr_110px] gap-2">
            <TextField label="المبلغ محل الدعوى (اختياري)" ltr inputMode="decimal" value={form.data.claim_amount} onChange={(e) => form.setData('claim_amount', e.target.value.replace(/[^\d.]/g, ''))} error={form.errors.claim_amount} />
            <SelectField label="العملة" options={options.currencies} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} />
          </div>
        </div>

        <fieldset>
          <legend className="field-label">نقطة البداية</legend>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            {starts.map(([v, l, d]) => (
              <label key={v} className={cx('cursor-pointer rounded-lg border px-3 py-2.5 transition-colors', form.data.start_point === v ? 'border-ink bg-active' : 'border-line hover:bg-raised')}>
                <input type="radio" name="start" className="sr-only" checked={form.data.start_point === v} onChange={() => form.setData('start_point', v)} />
                <span className="block font-medium">{l}</span><span className="block text-[12.5px] text-muted">{d}</span>
              </label>
            ))}
          </div>
        </fieldset>
        {(form.data.start_point === 'court' || archived) && (
          <div className="grid gap-4 sm:grid-cols-3">
            <TextField label="رقم القضية" ltr value={form.data.case_number} onChange={(e) => form.setData('case_number', e.target.value)} />
            <TextField label="المحكمة" value={form.data.court} onChange={(e) => form.setData('court', e.target.value)} />
            <TextField label="الدائرة" value={form.data.circuit} onChange={(e) => form.setData('circuit', e.target.value)} />
          </div>
        )}
        {archived && (
          <div className="grid gap-4 sm:grid-cols-2">
            <SelectField label="النتيجة" options={options.outcomes} placeholder="غير محددة" value={form.data.outcome} onChange={(e) => form.setData('outcome', e.target.value)} error={form.errors.outcome} />
            <TextField label="تاريخ الانتهاء" type="date" value={form.data.closed_on} onChange={(e) => form.setData('closed_on', e.target.value)} error={form.errors.closed_on} />
            <TextArea label="ملخص يظهر للعميل (اختياري)" className="sm:col-span-2" value={form.data.client_summary} onChange={(e) => form.setData('client_summary', e.target.value)} error={form.errors.client_summary} maxLength={3000} />
          </div>
        )}

        <fieldset className="space-y-3">
          <legend className="field-label">الأطراف (اختياري)</legend>
          {form.data.parties.map((p, i) => (
            <div key={i} className="grid gap-2 rounded-lg bg-raised p-3 sm:grid-cols-[2fr_1fr_1fr_1.2fr_auto] sm:items-end">
              <TextField label="الاسم" value={p.name} onChange={(e) => setParty(i, 'name', e.target.value)} error={form.errors[`parties.${i}.name` as 'title']} />
              <SelectField label="الصفة" options={[{ value: 'plaintiff', label: 'مدعٍ' }, { value: 'defendant', label: 'مدعى عليه' }, { value: 'other', label: 'أخرى' }]} value={p.role} onChange={(e) => setParty(i, 'role', e.target.value)} />
              <SelectField label="النوع" options={[{ value: 'individual', label: 'فرد' }, { value: 'organization', label: 'منشأة' }]} value={p.party_type} onChange={(e) => setParty(i, 'party_type', e.target.value)} />
              <TextField label="الهوية / السجل" ltr value={p.identifier} onChange={(e) => setParty(i, 'identifier', e.target.value)} />
              <Button type="button" variant="ghost" aria-label="حذف الطرف" onClick={() => form.setData('parties', form.data.parties.filter((_, j) => j !== i))}><Trash2 className="size-4" /></Button>
            </div>
          ))}
          <Button type="button" size="sm" onClick={() => form.setData('parties', [...form.data.parties, { name: '', role: form.data.client_role === 'plaintiff' ? 'defendant' : 'plaintiff', party_type: 'individual', identifier: '' }])}><Plus className="size-4" aria-hidden />إضافة طرف</Button>
        </fieldset>
        <Button variant="primary" type="submit" loading={form.processing}>{form.data.start_point === 'draft' ? 'حفظ المسودة' : archived ? 'حفظ القضية المنتهية' : 'إنشاء القضية'}</Button>
      </form>
    </div>
  );
}
