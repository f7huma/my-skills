import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { IconReports } from '@/Components/Icons';
import { StatusPill } from '@/Components/ReportView';
import { Button, cx, EmptyState, PageHeader, SelectField, TextField } from '@/Components/ui';
import { countNoun } from '@/lib/format';
import type { Option } from '@/types';

type Row = { id: number; title: string; client: string; clientId: number; period: string; start: string; end: string; status: string; statusLabel: string; items: number; publishedAt: string | null; closedAt: string | null };

const FILTERS: [string, string][] = [['all', 'الكل'], ['draft', 'مفتوحة'], ['closed', 'مكتملة'], ['published', 'منشورة']];

/** التقارير الدورية: فتح تقرير جديد وقائمة التقارير */
export default function Index({ reports, clients, preset }: { reports: Row[]; clients: Option[]; preset: { client: number | null; today: string; ranges: Record<string, [string, string]> } }) {
  const [s0, e0] = preset.ranges.monthly;
  const form = useForm({ client_id: preset.client ? String(preset.client) : '', period: 'monthly', start: s0, end: e0 });
  const pick = (k: string) => { const r = preset.ranges[k]; form.setData((d) => ({ ...d, period: k, ...(r ? { start: r[0], end: r[1] } : {}) })); };
  const [filter, setFilter] = useState('all');
  const shown = filter === 'all' ? reports : reports.filter((r) => r.status === filter);
  const periods: [string, string][] = [['weekly', 'أسبوعي'], ['monthly', 'شهري'], ['yearly', 'سنوي'], ['custom', 'فترة محددة']];
  return (
    <>
      <Head title="التقارير الدورية" />
      <PageHeader title="التقارير الدورية للعملاء" subtitle="افتح التقرير في بداية الفترة: يبقى مسودة تلتقط كل إجراء جديد على أعمال العميل تلقائيًا، وتضيف فيه متى شئت، ثم تُغلقه وتعتمده فيُنشر في صفحة العميل." />

      <section className="mist mb-6 rounded-[22px] p-5 sm:p-6" aria-labelledby="new-r">
        <h2 id="new-r" className="mb-4 flex items-center gap-2 text-[17px] font-semibold"><IconReports size={20} />تقرير جديد</h2>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/office/client-reports'); }} className="grid gap-4">
          <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] lg:items-end">
          <SelectField label="العميل" required options={clients} placeholder="اختر العميل" value={form.data.client_id} onChange={(e) => form.setData('client_id', e.target.value)} error={form.errors.client_id} />
          <fieldset>
            <legend className="field-label">نوع التقرير</legend>
            <div className="grid grid-cols-4 gap-1.5">
              {periods.map(([k, l]) => (
                <label key={k} className={cx('cursor-pointer whitespace-nowrap rounded-[12px] border px-2 py-2.5 text-center text-[13.5px] font-medium transition-colors duration-150', form.data.period === k ? 'border-ink bg-ink text-white' : 'border-line bg-white hover:border-control')}>
                  <input type="radio" className="sr-only" name="period" checked={form.data.period === k} onChange={() => pick(k)} />{l}
                </label>
              ))}
            </div>
          </fieldset>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-start">
          <TextField label="من تاريخ" type="date" required value={form.data.start} onChange={(e) => form.setData('start', e.target.value)} error={form.errors.start} />
          <TextField label="إلى تاريخ" type="date" required value={form.data.end} onChange={(e) => form.setData('end', e.target.value)} error={form.errors.end} />
          <Button variant="primary" type="submit" loading={form.processing} className="sm:col-span-2 lg:col-span-1 lg:mt-[26px] lg:min-w-[140px]">فتح التقرير</Button>
          </div>
        </form>
      </section>

      {reports.length > 0 && (
        <div role="group" aria-label="تصفية حسب الحالة" className="mb-4 flex flex-wrap gap-1.5">
          {FILTERS.map(([k, l]) => {
            const n = k === 'all' ? reports.length : reports.filter((r) => r.status === k).length;
            return (
              <button key={k} type="button" aria-pressed={filter === k} onClick={() => setFilter(k)}
                className={cx('rounded-full border px-3.5 py-1.5 text-[13px] font-medium transition-colors duration-150', filter === k ? 'border-ink bg-ink text-white' : 'border-line bg-white text-text hover:border-control')}>
                {l} <span className={cx('tabular-nums', filter === k ? 'text-white/70' : 'text-muted')}>{n}</span>
              </button>
            );
          })}
        </div>
      )}
      {reports.length === 0 ? <EmptyState title="لا تقارير بعد" icon={<IconReports size={26} />}>افتح أول تقرير من الأعلى.</EmptyState>
        : shown.length === 0 ? <EmptyState title="لا تقارير بهذه الحالة" icon={<IconReports size={26} />}>اختر تصفية أخرى.</EmptyState> : (
        <ul className="stagger grid gap-3 md:grid-cols-2">
          {shown.map((r) => (
            <li key={r.id}>
              <Link href={`/office/client-reports/${r.id}`} className="lift flex h-full flex-col rounded-[18px] border border-line bg-surface px-5 py-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="font-semibold leading-snug text-text">{r.title}</p>
                    <p className="mt-0.5 text-[13px] text-muted">{r.client}</p>
                  </div>
                  <StatusPill status={r.status} label={r.statusLabel} />
                </div>
                <p className="mt-auto pt-3 text-[12.5px] text-muted">{r.period} · <bdi dir="ltr" className="num">{r.start}</bdi> إلى <bdi dir="ltr" className="num">{r.end}</bdi> · {countNoun(r.items, { one: 'إجراء واحد', two: 'إجراءان', few: 'إجراءات', many: 'إجراءً' })}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
