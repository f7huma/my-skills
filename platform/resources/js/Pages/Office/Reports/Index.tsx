import { Head } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, Checkbox, cx, PageHeader, SelectField } from '@/Components/ui';
import type { Option } from '@/types';

export default function Index({ clients, cases, preset }: { clients: Option[]; cases: (Option & { clientId: number })[]; preset: { type?: string; client?: string; case?: string } }) {
  const [type, setType] = useState(preset.type === 'case' ? 'case' : 'client');
  const [clientId, setClientId] = useState(preset.client ?? '');
  const [caseId, setCaseId] = useState(preset.case ?? '');
  const [variant, setVariant] = useState('client');
  const [opt, setOpt] = useState({ timeline: true, parties: false, notes: false, tasks: false, hearings: true, case_status: 'all' });
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const id = type === 'client' ? clientId : caseId;
  const qs = useMemo(() => {
    const p = new URLSearchParams({ type, id, variant, timeline: opt.timeline ? '1' : '0', case_status: opt.case_status });
    if (variant === 'internal') (['parties', 'notes', 'tasks', 'hearings'] as const).forEach((k) => p.set(k, opt[k] ? '1' : '0'));
    return p.toString();
  }, [type, id, variant, opt]);
  return (
    <>
      <Head title="التقارير" />
      <PageHeader title="التقارير" subtitle="تقرير عميل بجميع قضاياه وطلبات تنفيذه، أو تقرير قضية واحدة. عاين قبل توليد PDF." />
      <div className="grid gap-5 xl:grid-cols-[340px_1fr]">
        <form className="card space-y-4 p-5" onSubmit={(e) => { e.preventDefault(); setLoading(true); setPreviewUrl('/office/reports/preview?' + qs); }}>
          <fieldset>
            <legend className="field-label">نوع التقرير</legend>
            <div className="flex gap-2">{[['client', 'تقرير عميل'], ['case', 'تقرير قضية']].map(([v, l]) => (
              <label key={v} className={cx('btn btn-secondary flex-1 cursor-pointer', type === v && '!border-ink !bg-active font-semibold')}><input type="radio" className="sr-only" checked={type === v} onChange={() => { setType(v); setPreviewUrl(null); }} />{l}</label>
            ))}</div>
          </fieldset>
          {type === 'client'
            ? <SelectField label="العميل" options={clients} placeholder="اختر العميل" value={clientId} onChange={(e) => { setClientId(e.target.value); setPreviewUrl(null); }} />
            : <SelectField label="القضية" options={cases} placeholder="اختر القضية" value={caseId} onChange={(e) => { setCaseId(e.target.value); setPreviewUrl(null); }} />}
          {type === 'client' && <SelectField label="نطاق القضايا" options={[{ value: 'all', label: 'كل القضايا' }, { value: 'open', label: 'غير المكتملة' }, { value: 'completed', label: 'المكتملة' }]} value={opt.case_status} onChange={(e) => setOpt({ ...opt, case_status: e.target.value })} />}
          <fieldset>
            <legend className="field-label">النسخة</legend>
            <div className="flex gap-2">{[['client', 'نسخة العميل'], ['internal', 'نسخة داخلية']].map(([v, l]) => (
              <label key={v} className={cx('btn btn-secondary flex-1 cursor-pointer', variant === v && '!border-ink !bg-active font-semibold')}><input type="radio" className="sr-only" checked={variant === v} onChange={() => setVariant(v)} />{l}</label>
            ))}</div>
            <p className="field-hint">{variant === 'client' ? 'من الحقول المسموح بعرضها للعميل فقط، دون الحقول الفارغة وبيانات الأطراف غير اللازمة.' : 'للمحامي فقط — لا تُسلَّم للعميل.'}</p>
          </fieldset>
          <div className="space-y-2">
            <Checkbox label="خط زمني مختصر" checked={opt.timeline} onChange={(v) => setOpt({ ...opt, timeline: v })} />
            {variant === 'internal' && <>
              <Checkbox label="الجلسات ونتائجها" checked={opt.hearings} onChange={(v) => setOpt({ ...opt, hearings: v })} />
              <Checkbox label="الأطراف وهوياتهم" checked={opt.parties} onChange={(v) => setOpt({ ...opt, parties: v })} />
              <Checkbox label="المهام المفتوحة والمذكرات" checked={opt.tasks} onChange={(v) => setOpt({ ...opt, tasks: v })} />
              <Checkbox label="ملاحظات المحامي الداخلية" checked={opt.notes} onChange={(v) => setOpt({ ...opt, notes: v })} />
            </>}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="submit" disabled={!id}><Eye className="size-4" aria-hidden />معاينة</Button>
            <a href={id ? '/office/reports/pdf?' + qs : undefined} aria-disabled={!id} className={cx('btn btn-primary', !id && 'pointer-events-none opacity-50')}><Download className="size-4" aria-hidden />تنزيل PDF</a>
          </div>
        </form>
        <section className="card min-h-[480px] overflow-hidden bg-[#e9ecef]" aria-label="معاينة التقرير">
          {previewUrl ? (
            <>
              {loading && <div className="p-6"><div className="skeleton mb-3 h-6 w-1/3" /><div className="skeleton mb-2 h-4 w-2/3" /><div className="skeleton h-64 w-full" /></div>}
              <iframe title="معاينة التقرير" src={previewUrl} onLoad={() => setLoading(false)} className={cx('h-[78vh] w-full border-0', loading && 'invisible h-0')} sandbox="allow-same-origin" />
            </>
          ) : <div className="flex h-full min-h-[480px] items-center justify-center p-6 text-center text-muted">اختر التقرير ونطاقه ثم اضغط «معاينة».</div>}
        </section>
      </div>
    </>
  );
}
