import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { IconReports } from '@/Components/Icons';
import { StatusPill } from '@/Components/ReportView';
import { Button, cx, DateText, EmptyState, PageHeader, SelectField, TextField } from '@/Components/ui';
import { countNoun } from '@/lib/format';
import type { Option } from '@/types';

type Row = { id: number; title: string; client: string; clientId: number; period: string; start: string; end: string; status: string; statusLabel: string; items: number; publishedAt: string | null; closedAt: string | null };

const FILTERS: [string, string][] = [['all', 'الكل'], ['draft', 'مفتوحة'], ['closed', 'مكتملة'], ['published', 'منشورة']];

/** التقارير الدورية: فتح تقرير جديد وقائمة التقارير */
export default function Index({ reports, clients, preset }: { reports: Row[]; clients: Option[]; preset: { client: number | null; today: string } }) {
  const form = useForm({ client_id: preset.client ? String(preset.client) : '', period: 'monthly', anchor: preset.today });
  const [filter, setFilter] = useState('all');
  const shown = filter === 'all' ? reports : reports.filter((r) => r.status === filter);
  const periods: [string, string, string][] = [['weekly', 'أسبوعي', 'الأحد إلى السبت'], ['monthly', 'شهري', 'الشهر كاملًا'], ['yearly', 'سنوي', 'السنة كاملة']];
  return (
    <>
      <Head title="التقارير الدورية" />
      <PageHeader title="التقارير الدورية للعملاء" subtitle="افتح التقرير في بداية الفترة: يبقى مسودة تلتقط كل إجراء جديد على أعمال العميل تلقائيًا، وتضيف فيه متى شئت، ثم تُغلقه وتعتمده فيُنشر في صفحة العميل." />

      <section className="mist mb-6 rounded-[22px] p-5 sm:p-6" aria-labelledby="new-r">
        <h2 id="new-r" className="mb-4 flex items-center gap-2 text-[17px] font-semibold"><IconReports size={20} />تقرير جديد</h2>
        <form onSubmit={(e) => { e.preventDefault(); form.post('/office/client-reports'); }} className="grid gap-4 lg:grid-cols-[1.2fr_1.3fr_1fr_auto] lg:items-end">
          <SelectField label="العميل" required options={clients} placeholder="اختر العميل" value={form.data.client_id} onChange={(e) => form.setData('client_id', e.target.value)} error={form.errors.client_id} />
          <fieldset>
            <legend className="field-label">نوع التقرير</legend>
            <div className="grid grid-cols-3 gap-1.5">
              {periods.map(([k, l, h]) => (
                <label key={k} className={cx('cursor-pointer rounded-[12px] border px-3 py-2 text-center transition-colors duration-150', form.data.period === k ? 'border-ink bg-ink text-white' : 'border-line bg-white hover:border-control')}>
                  <input type="radio" className="sr-only" name="period" checked={form.data.period === k} onChange={() => form.setData('period', k)} />
                  <span className="block text-[14px] font-medium">{l}</span><span className={cx('block text-[11.5px]', form.data.period === k ? 'text-white/75' : 'text-muted')}>{h}</span>
                </label>
              ))}
            </div>
          </fieldset>
          <TextField label="أي يوم داخل الفترة" type="date" value={form.data.anchor} onChange={(e) => form.setData('anchor', e.target.value)} error={form.errors.anchor} />
          <Button variant="primary" type="submit" loading={form.processing}>فتح التقرير</Button>
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
                <p className="mt-auto pt-3 text-[12.5px] text-muted">{r.period} · <DateText value={r.start} /> — <DateText value={r.end} /> · {countNoun(r.items, { one: 'إجراء واحد', two: 'إجراءان', few: 'إجراءات', many: 'إجراءً' })}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
