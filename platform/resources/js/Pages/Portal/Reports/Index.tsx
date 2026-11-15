import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { IconReports } from '@/Components/Icons';
import { DiamondRule } from '@/Components/LegalArt';
import { DateText, EmptyState } from '@/Components/ui';
import { relative } from '@/lib/format';

type Row = { id: number; title: string; client: string; period: string; start: string; end: string; publishedAt: string | null };

/** خانة التقارير في صفحة العميل: كل التقارير التي اعتمدها المكتب */
export default function Index({ reports }: { reports: Row[] }) {
  return (
    <div className="stagger">
      <Head title="التقارير" />
      <h1 className="mb-1 flex items-center gap-2 text-[24px] font-semibold"><IconReports size={24} />التقارير</h1>
      <p className="text-[14px] text-muted">تقارير دورية يعدّها المكتب عن سير أعمالكم.</p>
      <DiamondRule className="mb-5 mt-3 max-w-[220px]" />
      {reports.length === 0 ? <EmptyState title="لا تقارير منشورة بعد" icon={<IconReports size={26} />}>ستظهر هنا التقارير فور اعتمادها من المكتب.</EmptyState> : (
        <ul className="stagger grid gap-3 md:grid-cols-2">
          {reports.map((r) => (
            <li key={r.id}>
              <Link href={`/portal/reports/${r.id}`} className="lift flex h-full items-start gap-4 rounded-[18px] border border-line bg-surface px-5 py-4">
                <span className="grid size-11 shrink-0 rotate-45 place-items-center rounded-[11px] bg-ink text-white"><span className="-rotate-45"><IconReports size={20} /></span></span>
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold leading-snug text-text">{r.title}</span>
                  <span className="mt-0.5 block text-[13px] text-muted">{r.client} · تقرير {r.period}</span>
                  <span className="mt-1 block text-[12.5px] text-muted"><DateText value={r.start} /> — <DateText value={r.end} />{r.publishedAt && <> · نُشر {relative(r.publishedAt)}</>}</span>
                </span>
                <ArrowLeft className="mt-1 size-5 shrink-0 text-muted" aria-hidden />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
