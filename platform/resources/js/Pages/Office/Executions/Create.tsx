import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { ExecutionFields } from '@/Components/ExecutionFields';
import { Button, PageHeader, SelectField, TextField } from '@/Components/ui';
import type { Option } from '@/types';

export default function Create(p: { clients: Option[]; executionTypes: Option[]; instrumentTypes: Option[]; statuses: Option[]; currencies: string[]; cases: (Option & { clientId: number })[]; preset: { client_id: number | null; status?: string | null; case_id?: number | null } }) {
  const form = useForm<Record<string, any>>({
    client_id: p.preset.client_id ? String(p.preset.client_id) : '', execution_type: 'financial', instrument_type: 'electronic_promissory_note', instrument_label: '', status: p.preset.status ?? 'draft', legal_case_id: p.preset.case_id ? String(p.preset.case_id) : '', currency: 'SAR',
    request_number: '', amount_claimed: '', weekly_followup: false, parties: [{ name: '', role: 'respondent', party_type: 'individual', identifier: '' }],
  });
  const past = ['completed', 'closed', 'rejected'].includes(String(form.data.status));
  const parties = form.data.parties as { name: string; role: string; party_type: string; identifier: string }[];
  return (
    <div className="max-w-[960px]">
      <Head title="طلب تنفيذ" />
      <PageHeader title={p.preset.status ? 'إضافة طلب تنفيذ منتهٍ' : 'طلب تنفيذ مستقل'} subtitle={past ? 'طلب منتهٍ سابقًا لحصر أعمال العميل: لا تُنشأ له مهام ولا مواعيد تلقائية. يمكنك إضافة المبالغ المحصّلة بعد الحفظ.' : 'مثل التنفيذ بسند لأمر إلكتروني. طلبات التنفيذ المرتبطة بحكم تُنشأ من تبويب الحكم في القضية.'} crumbs={[{ label: 'التنفيذ', href: '/office/executions' }]} />
      <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, parties: (d.parties as { name: string }[]).filter((x) => x.name.trim()) })); form.post('/office/executions'); }} className="space-y-6" noValidate>
        <SelectField label="العميل (طالب التنفيذ)" required options={p.clients} placeholder="اختر العميل" value={form.data.client_id} onChange={(e) => form.setData((d: Record<string, any>) => ({ ...d, client_id: e.target.value, legal_case_id: '' }))} error={form.errors.client_id} className="max-w-md" />
        <ExecutionFields data={form.data} set={(k, v) => form.setData(k, v)} errors={form.errors as Record<string, string>} options={{ ...p, cases: p.cases.filter((c) => String(c.clientId) === String(form.data.client_id)) }} />
        <fieldset className="space-y-3">
          <legend className="font-semibold">المنفذ ضدهم</legend>
          {parties.map((x, i) => (
            <div key={i} className="grid gap-2 rounded-lg bg-raised p-3 sm:grid-cols-[2fr_1fr_1.2fr_auto] sm:items-end">
              <TextField label="الاسم" value={x.name} onChange={(e) => form.setData('parties', parties.map((y, j) => (j === i ? { ...y, name: e.target.value } : y)))} />
              <SelectField label="النوع" options={[{ value: 'individual', label: 'فرد' }, { value: 'organization', label: 'منشأة' }]} value={x.party_type} onChange={(e) => form.setData('parties', parties.map((y, j) => (j === i ? { ...y, party_type: e.target.value } : y)))} />
              <TextField label="الهوية / السجل" ltr value={x.identifier} onChange={(e) => form.setData('parties', parties.map((y, j) => (j === i ? { ...y, identifier: e.target.value } : y)))} />
              <Button type="button" variant="ghost" aria-label="حذف" onClick={() => form.setData('parties', parties.filter((_, j) => j !== i))}><Trash2 className="size-4" /></Button>
            </div>
          ))}
          <Button type="button" size="sm" onClick={() => form.setData('parties', [...parties, { name: '', role: 'respondent', party_type: 'individual', identifier: '' }])}><Plus className="size-4" aria-hidden />طرف</Button>
        </fieldset>
        <Button variant="primary" type="submit" loading={form.processing}>{past ? 'حفظ الطلب المنتهي' : 'إنشاء الطلب'}</Button>
      </form>
    </div>
  );
}
