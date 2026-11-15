import { Head, router } from '@inertiajs/react';
import { Lock, LockOpen, RefreshCcw, Send, Trash2, Undo2 } from 'lucide-react';
import { ReportView, StatusPill, type Catalog, type Report } from '@/Components/ReportView';
import { Button, DateText, PageHeader } from '@/Components/ui';

/**
 * محرر التقرير بثلاث حالات:
 * - مسودة مفتوحة: تلتقط كل إجراء جديد على أعمال العميل تلقائيًا، وتضيف فيها وتعدّل متى شئت.
 * - مكتمل: أُغلق فثبت محتواه، بانتظار الاعتماد والنشر.
 * - منشور: يراه العميل.
 */
export default function Show({ report, catalog }: { report: Report; catalog: Catalog }) {
  const url = `/office/client-reports/${report.id}`;
  const post = (path: string, msg?: string) => { if (!msg || confirm(msg)) router.post(`${url}/${path}`, {}, { preserveScroll: true }); };
  const open = report.status === 'draft';
  const published = report.status === 'published';

  const subtitle = open
    ? <>مسودة لا يراها العميل. أضف أعمالك متى شئت خلال الفترة{report.autoSync ? '، وتدخلها أعمال المنصة تلقائيًا' : ''}، ثم أغلقها وانشرها.</>
    : published && report.publishedAt
      ? <>منشور في صفحة العميل منذ <DateText value={report.publishedAt} time /> — أي تعديل يظهر له مباشرة.</>
      : <>أُغلق{report.closedAt ? <> في <DateText value={report.closedAt} time /></> : null} وثبت محتواه، فلا تدخله إجراءات جديدة. راجعه ثم اعتمده للنشر.</>;

  return (
    <>
      <Head title={report.title} />
      <PageHeader crumbs={[{ label: 'التقارير الدورية', href: '/office/client-reports' }, { label: report.client.name, href: `/office/clients/${report.client.id}` }]}
        title={<span className="flex flex-wrap items-center gap-3">تحرير التقرير <StatusPill status={report.status} label={report.statusLabel} live={report.autoSync} /></span>}
        subtitle={subtitle}
        actions={<>
          {open && <>
            <Button variant="primary" onClick={() => post('close', 'إغلاق التقرير؟ يتوقف التقاط الإجراءات الجديدة ويثبت محتواه، ويمكنك إعادة فتحه لاحقًا.')}><Lock className="size-4" />إغلاق التقرير وإكماله</Button>
            <Button onClick={() => post('publish', 'إغلاق التقرير ونشره في صفحة العميل مباشرة؟')}><Send className="size-4" />إغلاق ونشر مباشرة</Button>
          </>}
          {report.status === 'closed' && <>
            <Button variant="primary" onClick={() => post('publish', 'اعتماد التقرير ونشره في صفحة العميل؟')}><Send className="size-4" />اعتماد ونشر للعميل</Button>
            <Button onClick={() => post('reopen')}><LockOpen className="size-4" />إعادة فتح</Button>
          </>}
          {published && <>
            <Button onClick={() => post('unpublish')}><Undo2 className="size-4" />سحب من صفحة العميل</Button>
            <Button onClick={() => post('reopen', 'إعادة فتح التقرير؟ يُسحب من صفحة العميل ويعود مسودة تلتقط الإجراءات الجديدة.')}><LockOpen className="size-4" />إعادة فتح</Button>
          </>}
          {!published && report.autoSync && <Button onClick={() => post('regenerate', 'إعادة بناء الإجراءات من الصفر؟ إضافاتك اليدوية تبقى، أما تعديلاتك وحذفك للإجراءات المولَّدة فتُلغى.')}><RefreshCcw className="size-4" />إعادة البناء من الصفر</Button>}
          <Button variant="danger" onClick={() => { if (confirm('حذف التقرير نهائيًا؟')) router.delete(url); }}><Trash2 className="size-4" />حذف</Button>
        </>} />
      <ReportView report={report} editable catalog={catalog} pdfHref={`${url}/pdf`} />
    </>
  );
}
