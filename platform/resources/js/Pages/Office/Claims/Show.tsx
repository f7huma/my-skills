import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, claimTone, cx, DocumentList, KeyValues, Modal, Money, Num, PageHeader, Panel, Pill, SelectField, TextArea, TextField, useModal, DateText } from '@/Components/ui';
import { dateTime } from '@/lib/format';
import type { Doc, Option } from '@/types';

type Claim = { id: number; reference: string; status: string; statusLabel: string; client: string; clientId: number; clientName: string; submitter: string | null; opponentType: string | null;
  opponentName: string; opponentIdentifier: string | null; opponentAddress: string | null; opponentPhone: string | null; amount: string | null; currency: string; description: string | null;
  missingDocuments: boolean; missingReason: string | null; missingList: string[]; submittedAt: string; internalNotes: string | null; closedReason: string | null; caseId: number | null; executionId: number | null };

export default function Show({ claim, messages, documents, categories, instrumentTypes, categoryLabels }: {
  claim: Claim; messages: { id: number; kind: string; body: string; visible: boolean; author: string | null; at: string }[]; documents: Doc[]; categories: Option[]; instrumentTypes: Option[]; categoryLabels: Record<string, string>;
}) {
  const open = ['under_review', 'needs_completion'].includes(claim.status);
  const completion = useForm({ message: '' });
  const note = useForm({ body: '', visible_to_client: false });
  const internal = useForm({ internal_notes: claim.internalNotes ?? '' });
  const toCase = useForm({ title: `مطالبة مالية ضد ${claim.opponentName}`, category_id: '', stage: 'intake' });
  const toExec = useForm({ execution_type: 'financial', instrument_type: 'electronic_promissory_note', instrument_label: '', subject_location: '', obligation_description: '' });
  const close = useForm({ reason: '', client_message: '' });
  const m1 = useModal(); const m2 = useModal(); const m3 = useModal();
  return (
    <>
      <Head title={'مطالبة ضد ' + claim.opponentName} />
      <PageHeader title={<>مطالبة ضد {claim.opponentName}</>} crumbs={[{ label: 'المطالبات الواردة', href: '/office/claims' }]}
        subtitle={<span className="flex flex-wrap items-center gap-2">{claim.client} ضد {claim.opponentName} · أرسلها {claim.submitter} <DateText value={claim.submittedAt} time /><Pill tone={claimTone(claim.status)}>{claim.statusLabel}</Pill>{claim.missingDocuments && <Pill tone="negative">ناقص مستند</Pill>}</span>}
        actions={open ? <>
          <Button size="sm" variant="primary" onClick={m1.show}>تحويل إلى قضية</Button>
          <Button size="sm" onClick={m2.show}>تحويل إلى ملف تنفيذ</Button>
          <Button size="sm" variant="ghost" onClick={m3.show}>إغلاق بسبب</Button>
        </> : <>
          {claim.caseId && <Link href={`/office/cases/${claim.caseId}`} className="btn btn-primary btn-sm">فتح القضية الناتجة</Link>}
          {claim.executionId && <Link href={`/office/executions/${claim.executionId}`} className="btn btn-primary btn-sm">فتح ملف التنفيذ</Link>}
        </>} />
      <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
        <div className="grid grid-cols-1 content-start gap-4">
          <Panel title="بيانات المطالبة">
            <KeyValues items={[
              ['اسم موكل المكتب', claim.clientName], ['اسم الطرف المطالب ضده', claim.opponentName],
              ['نوع الطرف', claim.opponentType === 'organization' ? 'منشأة' : 'فرد'], [claim.opponentType === 'organization' ? 'السجل التجاري / الرقم الموحد' : 'رقم الهوية', claim.opponentIdentifier && <Num>{claim.opponentIdentifier}</Num>],
              ['العنوان', claim.opponentAddress], ['الجوال', claim.opponentPhone && <Num>{claim.opponentPhone}</Num>],
              ['المبلغ', <Money value={claim.amount} currency={claim.currency} className="font-semibold" />], ['الوصف', claim.description],
              ['مستندات أساسية ناقصة', claim.missingList.length > 0 && claim.missingList.map((c) => categoryLabels[c]).join('، ')],
              ['سبب عدم توفرها', claim.missingReason], ['سبب الإغلاق', claim.closedReason],
            ]} />
          </Panel>
          <Panel title="المرفقات"><DocumentList docs={documents} empty="لا مرفقات." /></Panel>
        </div>
        <div className="grid grid-cols-1 content-start gap-4">
          {open && (
            <Panel title="طلب استكمال من العميل">
              <form onSubmit={(e) => { e.preventDefault(); completion.post(`/office/claims/${claim.id}/request-completion`, { preserveScroll: true, onSuccess: () => completion.reset() }); }} className="space-y-3">
                <TextArea label="الرسالة (تظهر للعميل)" rows={3} value={completion.data.message} onChange={(e) => completion.setData('message', e.target.value)} error={completion.errors.message} />
                <Button type="submit" size="sm" loading={completion.processing} disabled={!completion.data.message}>إرسال طلب الاستكمال</Button>
              </form>
            </Panel>
          )}
          <Panel title="الرسائل والملاحظات">
            <ol className="mb-3 space-y-2">{messages.map((m) => (
              <li key={m.id} className={cx('rounded-lg p-3', m.visible ? 'bg-accent-soft' : 'well')}>
                <p className="text-[14px]">{m.body}</p>
                <p className="mt-1 text-[12px] text-muted">{m.visible ? 'ظاهرة للعميل' : 'داخلية'} · {m.author ?? '—'} · <DateText value={m.at} time /></p>
              </li>
            ))}</ol>
            <form onSubmit={(e) => { e.preventDefault(); note.post(`/office/claims/${claim.id}/note`, { preserveScroll: true, onSuccess: () => note.reset() }); }} className="space-y-2">
              <TextArea label="ملاحظة جديدة" rows={2} value={note.data.body} onChange={(e) => note.setData('body', e.target.value)} />
              <Checkbox label="اعتماد ظهورها للعميل" checked={note.data.visible_to_client} onChange={(v) => note.setData('visible_to_client', v)} />
              <Button type="submit" size="sm" loading={note.processing} disabled={!note.data.body}>إضافة</Button>
            </form>
          </Panel>
          <Panel title="بيانات داخلية">
            <form onSubmit={(e) => { e.preventDefault(); internal.put(`/office/claims/${claim.id}/internal`, { preserveScroll: true }); }} className="space-y-2">
              <TextArea label="ملاحظات المحامي (لا تظهر للعميل)" rows={3} value={internal.data.internal_notes} onChange={(e) => internal.setData('internal_notes', e.target.value)} />
              <Button type="submit" size="sm" loading={internal.processing}>حفظ</Button>
            </form>
          </Panel>
        </div>
      </div>
      <Modal open={m1.open} onClose={m1.close} title="تحويل إلى قضية">
        <form onSubmit={(e) => { e.preventDefault(); toCase.post(`/office/claims/${claim.id}/convert-case`); }} className="space-y-3">
          <TextField label="عنوان القضية" value={toCase.data.title} onChange={(e) => toCase.setData('title', e.target.value)} />
          <SelectField label="التصنيف" placeholder="بدون" options={categories} value={toCase.data.category_id} onChange={(e) => toCase.setData('category_id', e.target.value)} />
          <SelectField label="نقطة البداية" options={[{ value: 'intake', label: 'قيد الإعداد' }, { value: 'reconciliation', label: 'الصلح (تراضي)' }, { value: 'filing', label: 'طلب تقييد (ناجز)' }]} value={toCase.data.stage} onChange={(e) => toCase.setData('stage', e.target.value)} />
          <p className="text-[12.5px] text-muted">تبقى المطالبة الأصلية ومرفقاتها مرتبطة بالقضية. الضغط المتكرر لا ينشئ قضية ثانية.</p>
          <Button variant="primary" type="submit" loading={toCase.processing}>تحويل</Button>
        </form>
      </Modal>
      <Modal open={m2.open} onClose={m2.close} title="تحويل إلى ملف تنفيذ">
        <form onSubmit={(e) => { e.preventDefault(); toExec.post(`/office/claims/${claim.id}/convert-execution`); }} className="space-y-3">
          <SelectField label="نوع التنفيذ" options={[{ value: 'financial', label: 'مالي' }, { value: 'direct', label: 'مباشر' }]} value={toExec.data.execution_type} onChange={(e) => toExec.setData('execution_type', e.target.value)} />
          <SelectField label="نوع السند" options={instrumentTypes} value={toExec.data.instrument_type} onChange={(e) => toExec.setData('instrument_type', e.target.value)} />
          {toExec.data.instrument_type === 'other' && <TextField label="اسم السند" value={toExec.data.instrument_label} onChange={(e) => toExec.setData('instrument_label', e.target.value)} error={toExec.errors.instrument_label} />}
          {toExec.data.execution_type === 'direct' && <>
            <TextField label="محل التنفيذ" value={toExec.data.subject_location} onChange={(e) => toExec.setData('subject_location', e.target.value)} error={toExec.errors.subject_location} />
            <TextArea label="وصف الالتزام" value={toExec.data.obligation_description} onChange={(e) => toExec.setData('obligation_description', e.target.value)} error={toExec.errors.obligation_description} />
          </>}
          <Button variant="primary" type="submit" loading={toExec.processing}>تحويل</Button>
        </form>
      </Modal>
      <Modal open={m3.open} onClose={m3.close} title="إغلاق المطالبة">
        <form onSubmit={(e) => { e.preventDefault(); close.post(`/office/claims/${claim.id}/close`, { onSuccess: m3.close }); }} className="space-y-3">
          <TextArea label="السبب الموثق (داخلي)" required value={close.data.reason} onChange={(e) => close.setData('reason', e.target.value)} error={close.errors.reason} />
          <TextArea label="رسالة للعميل (اختيارية، تظهر في البوابة)" value={close.data.client_message} onChange={(e) => close.setData('client_message', e.target.value)} />
          <Button variant="danger" type="submit" loading={close.processing}>إغلاق</Button>
        </form>
      </Modal>
    </>
  );
}
