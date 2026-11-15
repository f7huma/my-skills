import { Head, Link } from '@inertiajs/react';
import { ReportView, type Report } from '@/Components/ReportView';

export default function Show({ report }: { report: Report }) {
  return (
    <>
      <Head title={report.title} />
      <nav aria-label="مسار التنقل" className="mb-3 text-[13px] text-muted"><Link href="/portal/reports" className="hover:text-text">التقارير</Link> / {report.periodLabel}</nav>
      <ReportView report={report} pdfHref={`/portal/reports/${report.id}/pdf`} />
    </>
  );
}
