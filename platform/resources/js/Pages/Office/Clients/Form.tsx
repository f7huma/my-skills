import { Head, useForm } from '@inertiajs/react';
import { Button, cx, PageHeader, TextArea, TextField } from '@/Components/ui';

type C = { id: number; type: string; name: string; national_id: string | null; unified_number: string | null; commercial_registration: string | null; address: string | null; national_address: string | null; phone: string | null; email: string | null; internal_notes: string | null };

export default function Form({ client }: { client: C | null }) {
  const form = useForm({
    type: client?.type ?? 'individual', name: client?.name ?? '', national_id: client?.national_id ?? '', unified_number: client?.unified_number ?? '',
    commercial_registration: client?.commercial_registration ?? '', address: client?.address ?? '', national_address: client?.national_address ?? '',
    phone: client?.phone ?? '', email: client?.email ?? '', internal_notes: client?.internal_notes ?? '',
  });
  const org = form.data.type === 'organization';
  const submit = (e: React.FormEvent) => { e.preventDefault(); client ? form.put(`/office/clients/${client.id}`) : form.post('/office/clients'); };
  const digits = (k: 'national_id' | 'unified_number' | 'commercial_registration') => (e: React.ChangeEvent<HTMLInputElement>) => form.setData(k, e.target.value.replace(/\D/g, '').slice(0, 10));
  return (
    <div className="max-w-[880px]">
      <Head title={client ? 'تعديل عميل' : 'عميل جديد'} />
      <PageHeader title={client ? `تعديل: ${client.name}` : 'عميل جديد'} crumbs={[{ label: 'العملاء', href: '/office/clients' }]} />
      <form onSubmit={submit} className="space-y-6" noValidate>
        <fieldset>
          <legend className="field-label">نوع العميل</legend>
          <div className="flex gap-2">
            {[['individual', 'فرد'], ['organization', 'منشأة']].map(([v, l]) => (
              <label key={v} className={cx('btn btn-secondary cursor-pointer', form.data.type === v && '!border-ink !bg-active font-semibold')}>
                <input type="radio" className="sr-only" name="type" value={v} checked={form.data.type === v} onChange={() => form.setData('type', v)} />{l}
              </label>
            ))}
          </div>
        </fieldset>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField className="sm:col-span-2" label={org ? 'اسم المنشأة' : 'الاسم الكامل'} required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
          {!org && <TextField label="رقم الهوية الوطنية / الإقامة" ltr inputMode="numeric" value={form.data.national_id} onChange={digits('national_id')} error={form.errors.national_id} hint="10 أرقام." />}
          {org && <TextField label="الرقم الوطني الموحد" ltr inputMode="numeric" value={form.data.unified_number} onChange={digits('unified_number')} error={form.errors.unified_number} hint="10 أرقام يبدأ بـ 7." />}
          {org && <TextField label="رقم السجل التجاري" ltr inputMode="numeric" value={form.data.commercial_registration} onChange={digits('commercial_registration')} error={form.errors.commercial_registration} />}
          <TextField label="الجوال" ltr inputMode="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} error={form.errors.phone} />
          <TextField label="البريد الإلكتروني" ltr type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
          <TextField label="العنوان الوطني (الرمز المختصر)" ltr value={form.data.national_address} onChange={(e) => form.setData('national_address', e.target.value.toUpperCase())} error={form.errors.national_address} />
          <TextField className="sm:col-span-2" label="العنوان" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
          <TextArea className="sm:col-span-2" label="ملاحظات داخلية (لا تظهر للعميل)" rows={3} value={form.data.internal_notes} onChange={(e) => form.setData('internal_notes', e.target.value)} />
        </div>
        <Button variant="primary" type="submit" loading={form.processing}>{client ? 'حفظ' : 'إضافة العميل'}</Button>
      </form>
    </div>
  );
}
