import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DeadlineList, type DeadlineRow } from '@/Components/Deadlines';
import { Button, Modal, PageHeader, SelectField, TextField, useModal } from '@/Components/ui';
import type { Option } from '@/types';

export default function Index({ deadlines, filters, kinds, certainties, cases, executions, today, todayHijri }: {
  deadlines: DeadlineRow[]; filters: { kind?: string; certainty?: string; status?: string }; kinds: Option[]; certainties: Option[]; cases: Option[]; executions: Option[]; today: string; todayHijri: string;
}) {
  const modal = useModal();
  const form = useForm({ legal_case_id: '', execution_request_id: '', kind: 'custom', certainty: 'confirmed', reason: '', end_on: '', start_on: '', duration: '', day_type: 'calendar', basis: '' });
  const apply = (k: string, v: string) => router.get('/office/deadlines', { ...filters, [k]: v || undefined }, { preserveState: true, replace: true });
  const alerted = deadlines.filter((d) => d.status === 'alerted').length;
  const undetermined = deadlines.filter((d) => d.certainty === 'needs_determination').length;
  return (
    <>
      <Head title="المواعيد والعدادات" />
      <PageHeader title="المواعيد والعدادات" subtitle={<>اليوم <bdi className="num">{today}</bdi> ({todayHijri}) — بتوقيت الرياض. {alerted > 0 && <span className="font-medium text-negative">{alerted} عداد انتهى ويحتاج متابعة. </span>}{undetermined > 0 && <span className="font-medium text-text">{undetermined} موعد يحتاج تحديدًا.</span>}</>}
        actions={<Button variant="primary" onClick={modal.show}><Plus className="size-4" aria-hidden />موعد جديد</Button>} />
      <div className="mb-4 grid gap-3 text-[13px] text-muted sm:grid-cols-4">
        <p><strong className="text-text">متابعة تقديرية:</strong> تذكير داخلي، ليس مهلة نظامية.</p>
        <p><strong className="text-text">يحتاج مراجعة:</strong> محسوب من قاعدة معتمدة ويحتاج تأكيدك.</p>
        <p><strong className="text-text">موعد مؤكد:</strong> اعتمده المحامي ولا يُستبدل آليًا.</p>
        <p><strong className="text-text">يحتاج تحديدًا:</strong> لم يتأكد الانطباق أو نقطة البداية.</p>
      </div>
      <div className="mb-4 flex flex-wrap gap-2">
        <SelectField label="النوع" className="w-44" options={kinds} placeholder="الكل" value={filters.kind ?? ''} onChange={(e) => apply('kind', e.target.value)} />
        <SelectField label="اليقين" className="w-44" options={certainties} placeholder="الكل" value={filters.certainty ?? ''} onChange={(e) => apply('certainty', e.target.value)} />
        <SelectField label="الحالة" className="w-44" options={[{ value: 'alerted', label: 'منتهية (تنبيه)' }, { value: 'open', label: 'مفتوحة' }, { value: 'closed', label: 'مغلقة' }, { value: 'all', label: 'الكل' }]} placeholder="المفتوحة والمنبهة" value={filters.status ?? ''} onChange={(e) => apply('status', e.target.value)} />
      </div>
      <DeadlineList rows={deadlines} kinds={kinds} certainties={certainties} showRef />
      <Modal open={modal.open} onClose={modal.close} title="موعد جديد">
        <form onSubmit={(e) => { e.preventDefault(); form.post('/office/deadlines', { preserveScroll: true, onSuccess: () => { modal.close(); form.reset(); } }); }} className="grid gap-4 sm:grid-cols-2">
          <SelectField className="sm:col-span-2" label="القضية" placeholder="—" options={cases} value={form.data.legal_case_id} onChange={(e) => form.setData('legal_case_id', e.target.value)} />
          <SelectField className="sm:col-span-2" label="أو طلب التنفيذ" placeholder="—" options={executions} value={form.data.execution_request_id} onChange={(e) => form.setData('execution_request_id', e.target.value)} />
          <TextField className="sm:col-span-2" label="سبب المتابعة" required value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} error={form.errors.reason} />
          <SelectField label="النوع" options={kinds} value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)} />
          <SelectField label="اليقين" options={certainties} value={form.data.certainty} onChange={(e) => form.setData('certainty', e.target.value)} />
          <TextField label="تاريخ النهاية" type="date" value={form.data.end_on} onChange={(e) => form.setData('end_on', e.target.value)} hint="أو أدخل مدة من تاريخ بداية:" />
          <div />
          <TextField label="تاريخ البداية" type="date" value={form.data.start_on} onChange={(e) => form.setData('start_on', e.target.value)} />
          <div className="grid grid-cols-2 gap-2">
            <TextField label="المدة" ltr value={form.data.duration} onChange={(e) => form.setData('duration', e.target.value.replace(/\D/g, ''))} />
            <SelectField label="الأيام" options={[{ value: 'calendar', label: 'تقويمية' }, { value: 'business', label: 'عمل' }]} value={form.data.day_type} onChange={(e) => form.setData('day_type', e.target.value)} />
          </div>
          <TextField className="sm:col-span-2" label="أساس الحساب" value={form.data.basis} onChange={(e) => form.setData('basis', e.target.value)} />
          <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={form.processing}>إضافة</Button></div>
        </form>
      </Modal>
    </>
  );
}
