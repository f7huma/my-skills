import { Head, router } from '@inertiajs/react';
import { RefreshCcw, Send, Trash2, Undo2 } from 'lucide-react';
import { ReportView, type Report } from '@/Components/ReportView';
import { Button, DateText, PageHeader, Pill } from '@/Components/ui';

/** محرر التقرير: كل الصلاحيات للمحامي — تعديل وإضافة وحذف الإجراءات، إخفاء ملفات، ثم الاعتماد والنشر */
export default function Show({ report }: { report: Report }) {
  const published = report.status === 'published';
  return (
    <>
      <Head title={report.title} />
      <PageHeader crumbs={[{ label: 'التقارير الدورية', href: '/office/client-reports' }, { label: report.client.name, href: `/office/clients/${report.client.id}` }]}
        title={<span className="flex flex-wrap items-center gap-3">تحرير التقرير <Pill tone={published ? 'positive' : 'warning'}>{report.statusLabel}</Pill></span>}
        subtitle={published && report.publishedAt ? <>منشور في صفحة العميل منذ <DateText value={report.publishedAt} time /> — أي تعديل يظهر له مباشرة.</> : 'مسودة لا يراها العميل. راجع الإجراءات وعدّلها، ثم اعتمد التقرير لنشره.'}
        actions={<>
          {published
            ? <Button onClick={() => router.post(`/office/client-reports/${report.id}/unpublish`, {}, { preserveScroll: true })}><Undo2 className="size-4" />سحب من صفحة العميل</Button>
            : <Button variant="primary" onClick={() => { if (confirm('اعتماد التقرير ونشره في صفحة العميل؟')) router.post(`/office/client-reports/${report.id}/publish`, {}, { preserveScroll: true }); }}><Send className="size-4" />اعتماد ونشر للعميل</Button>}
          <Button onClick={() => { if (confirm('تحديث الإجراءات المولَّدة من سجل الأعمال؟ إضافاتك اليدوية تبقى، أما تعديلاتك على الإجراءات المولَّدة فتُستبدل.')) router.post(`/office/client-reports/${report.id}/regenerate`, {}, { preserveScroll: true }); }}><RefreshCcw className="size-4" />تحديث من سجل الأعمال</Button>
          <Button variant="danger" onClick={() => { if (confirm('حذف التقرير نهائيًا؟')) router.delete(`/office/client-reports/${report.id}`); }}><Trash2 className="size-4" />حذف</Button>
        </>} />
      <ReportView report={report} editable pdfHref={`/office/client-reports/${report.id}/pdf`} />
    </>
  );
}
