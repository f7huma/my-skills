import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarPlus, CheckCircle2, Clock, FileText, Gavel, Pencil, Plus, Printer, RotateCcw, Scale } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';
import { CollectionsPanel, type CollectionRow } from '@/Components/Collections';
import { DeadlineList, type DeadlineRow } from '@/Components/Deadlines';
import { emptyTask, TaskFields, type TaskInput } from '@/Components/TaskFields';
import {
  Button, ButtonLink, caseTone, Checkbox, cx, DocumentList, EmptyState, FormError, KeyValues, Modal, Money, Num, PageHeader, Panel, Pill, SelectField,
  Tabs, TextArea, TextField, Timeline, UploadForm, useModal,
} from '@/Components/ui';
import { dateTime, relative, weekday } from '@/lib/format';
import { useTab } from '@/lib/useTab';
import type { Doc, Option, Totals } from '@/types';

type CaseT = {
  id: number; reference: string; title: string; clientId: number; client: string; categoryId: number | null; category: string | null; clientRole: string; clientRoleLabel: string;
  stage: string; stageLabel: string; status: string; statusLabel: string; outcome: string | null; outcomeLabel: string | null; claimAmount: string | null; currency: string;
  poaId: number | null; poa: { number: string; state: string } | null; clientSummary: string | null; clientSummaryUpdatedAt: string | null; internalNotes: string | null;
  lockVersion: number; sourceClaimId: number | null; createdAt: string;
};
type Recon = { id: number; number: string | null; submittedOn: string | null; status: string; statusLabel: string; outcomeOn: string | null; agreedAmount: string | null; currency: string; notes: string | null; movedToCourt: boolean; report: Doc | null };
type Filing = { id: number; requestNumber: string | null; submittedOn: string | null; status: string; statusLabel: string; caseNumber: string | null; registeredOn: string | null; referredOn: string | null; court: string | null; circuit: string | null; notes: string | null };
type Hearing = { id: number; kind: string; kindLabel: string; at: string | null; localAt: string | null; authority: string | null; meetingUrl: string | null; status: string; statusLabel: string;
  attendance: Record<string, string> | null; result: string | null; directives: string | null; changeReason: string | null; minutes: Doc | null; nextHearingId: number | null; lockVersion: number;
  reconciliationId: number | null; filingId: number | null; anchoredTasks: number };
type Task = { id: number; title: string; description: string | null; kind: string; kindLabel: string; dueMode: string; dueOn: string | null; relativeFrom: string | null; relativeEvent: string | null;
  relativeDays: number | null; relativeDayType: string | null; anchorHearingId: number | null; anchorOffset: number | null; status: string; completedAt: string | null; lockVersion: number;
  sourceHearingId: number | null; changes: { old: string | null; new: string | null; reason: string; at: string }[] };
type Memo = { id: number; title: string; type: string; status: string; statusLabel: string; dueOn: string | null; filedOn: string | null; approvedAt: string | null; version: number };
type Judgment = { id: number; outcome: string; outcomeLabel: string; pronouncedOn: string | null; pronouncedHijri: string | null; deedStatus: string; deedNumber: string | null; deedDate: string | null;
  objectionStatus: string | null; objectionFiledOn: string | null; finality: string; finalityConfirmedAt: string | null; executionDecision: string; resumedOn: string | null; notes: string | null };
type Opts = {
  stages: Option[]; statuses: Option[]; roles: Option[]; outcomes: Option[]; categories: Option[]; currencies: string[]; hearingKinds: Option[]; reconciliationStatuses: Option[];
  filingStatuses: Option[]; taskKinds: Option[]; dueModes: Option[]; memoTypes: Option[]; deedStatuses: Option[]; objectionStatuses: Option[]; executionDecisions: Option[];
  documentCategories: Option[]; collectionKinds: Option[]; collectionSources: Option[]; poas: Option[]; deadlineKinds: Option[]; certainties: Option[];
};
type Props = {
  tab: string; case: CaseT; parties: { id: number; name: string; role: string; party_type: string; identifier: string | null }[]; reconciliations: Recon[]; filings: Filing[];
  hearings: Hearing[]; tasks: Task[]; memos: Memo[]; judgment: Judgment | null; executions: { id: number; reference: string; requestNumber: string | null; type: string; instrument: string; status: string }[];
  deadlines: DeadlineRow[]; timeline: { id: number; type: string; title: string; details: string | null; at: string; clientVisible: boolean; user: string | null }[];
  documents: Doc[]; collections: CollectionRow[]; totals: Totals; options: Opts;
};

const roleLabel: Record<string, string> = { plaintiff: 'مدعٍ', defendant: 'مدعى عليه', other: 'أخرى' };
const attendLabel: Record<string, string> = { present: 'حاضر', absent: 'غائب', represented: 'بوكيل' };

// ====================================================================== الملخص
function SummaryTab({ p }: { p: Props }) {
  const c = p.case;
  const edit = useModal();
  const form = useForm({ title: c.title, category_id: c.categoryId ? String(c.categoryId) : '', client_role: c.clientRole, stage: c.stage, claim_amount: c.claimAmount ?? '', currency: c.currency,
    power_of_attorney_id: c.poaId ? String(c.poaId) : '', internal_notes: c.internalNotes ?? '', lock_version: c.lockVersion });
  useEffect(() => { form.setData('lock_version', c.lockVersion); }, [c.lockVersion]);
  const summary = useForm({ client_summary: c.clientSummary ?? '', publish_event: true });
  const party = useForm({ name: '', role: c.clientRole === 'plaintiff' ? 'defendant' : 'plaintiff', party_type: 'individual', identifier: '' });
  const nextHearing = p.hearings.find((h) => h.status === 'scheduled' && h.at && new Date(h.at) >= new Date(new Date().toDateString()));
  const openTasks = p.tasks.filter((t) => t.status === 'open');

  return (
    <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
      <div className="grid grid-cols-1 content-start gap-4">
        <Panel title="بيانات القضية" actions={<Button size="sm" variant="ghost" onClick={edit.show}><Pencil className="size-4" aria-hidden />تعديل</Button>}>
          <KeyValues items={[
            ['العميل', <Link href={`/office/clients/${c.clientId}`} className="text-accent hover:underline">{c.client}</Link>],
            ['صفة العميل', c.clientRoleLabel], ['التصنيف', c.category], ['المرحلة الإجرائية', c.stageLabel], ['حالة العمل', c.statusLabel], ['النتيجة', c.outcomeLabel],
            ['المبلغ محل الدعوى', c.claimAmount && <Money value={c.claimAmount} currency={c.currency} />],
            ['الوكالة المستخدمة', c.poa && <>وكالة <Num>{c.poa.number}</Num>{c.poa.state !== 'active' && <Pill tone={c.poa.state === 'expiring' ? 'warning' : 'negative'} className="ms-2">{c.poa.state === 'expiring' ? 'قريبة الانتهاء' : c.poa.state === 'expired' ? 'منتهية' : 'ملغاة'}</Pill>}</>],
            ['رقم القضية', p.filings.find((f) => f.caseNumber)?.caseNumber && <Num>{p.filings.find((f) => f.caseNumber)!.caseNumber}</Num>],
            ['من مطالبة', c.sourceClaimId && <Link href={`/office/claims/${c.sourceClaimId}`} className="text-accent hover:underline">المطالبة الأصلية</Link>],
            ['ملاحظات المحامي (داخلية)', c.internalNotes && <span className="whitespace-pre-line">{c.internalNotes}</span>],
          ]} />
        </Panel>
        <Panel title="الملخص الموجّه للعميل" description="منفصل عن ملاحظات المحامي ويظهر في البوابة كآخر تحديث معتمد.">
          <form onSubmit={(e) => { e.preventDefault(); summary.post(`/office/cases/${c.id}/client-summary`, { preserveScroll: true }); }} className="space-y-3">
            <TextArea label="نص التحديث" rows={3} value={summary.data.client_summary} onChange={(e) => summary.setData('client_summary', e.target.value)} maxLength={3000}
              hint={c.clientSummaryUpdatedAt ? `آخر نشر ${relative(c.clientSummaryUpdatedAt)}` : 'لم يُنشر تحديث بعد.'} />
            <Checkbox label="إضافته أيضًا إلى مسار القضية الظاهر للعميل" checked={summary.data.publish_event} onChange={(v) => summary.setData('publish_event', v)} />
            <Button type="submit" variant="primary" size="sm" loading={summary.processing}>نشر التحديث</Button>
          </form>
        </Panel>
      </div>
      <div className="grid grid-cols-1 content-start gap-4">
        <Panel title="الآن">
          <ul className="space-y-2.5 text-[14px]">
            <li className="flex items-start gap-2"><Clock className="mt-0.5 size-4 text-muted" aria-hidden />
              {nextHearing ? <span>{nextHearing.kindLabel}: <Num>{dateTime(nextHearing.at)}</Num>{nextHearing.authority && <span className="text-muted"> · {nextHearing.authority}</span>}</span> : <span className="text-muted">لا جلسة مجدولة.</span>}
            </li>
            <li className="flex items-start gap-2"><CheckCircle2 className="mt-0.5 size-4 text-muted" aria-hidden /><span><Num>{openTasks.length}</Num> مهمة مفتوحة{openTasks.filter((t) => !t.dueOn).length > 0 && <span className="text-muted"> (منها <Num>{openTasks.filter((t) => !t.dueOn).length}</Num> بلا موعد)</span>}</span></li>
            {p.deadlines.filter((d) => d.status !== 'closed').slice(0, 3).map((d) => (
              <li key={d.id} className="flex items-start gap-2"><Scale className="mt-0.5 size-4 text-muted" aria-hidden /><span>{d.reason}: <Num>{d.endOn ?? 'يحتاج تحديدًا'}</Num></span></li>
            ))}
          </ul>
        </Panel>
        <Panel title="الأطراف">
          {p.parties.length === 0 ? <p className="text-[13.5px] text-muted">لم تُسجل أطراف.</p> : (
            <ul className="divide-y divide-rule">{p.parties.map((x) => (
              <li key={x.id} className="flex items-center justify-between gap-2 py-2">
                <span><span className="font-medium">{x.name}</span> <span className="text-[12.5px] text-muted">· {roleLabel[x.role]}{x.identifier && <> · <Num>{x.identifier}</Num></>}</span></span>
                <button type="button" className="btn btn-ghost btn-sm" aria-label={'حذف ' + x.name} onClick={() => confirm('حذف الطرف؟') && router.delete(`/office/cases/${c.id}/parties/${x.id}`, { preserveScroll: true })}>حذف</button>
              </li>
            ))}</ul>
          )}
          <form onSubmit={(e) => { e.preventDefault(); party.post(`/office/cases/${c.id}/parties`, { preserveScroll: true, onSuccess: () => party.reset('name', 'identifier') }); }} className="mt-3 grid gap-2 sm:grid-cols-2">
            <TextField label="اسم الطرف" value={party.data.name} onChange={(e) => party.setData('name', e.target.value)} error={party.errors.name} />
            <SelectField label="الصفة" options={[{ value: 'plaintiff', label: 'مدعٍ' }, { value: 'defendant', label: 'مدعى عليه' }, { value: 'other', label: 'أخرى' }]} value={party.data.role} onChange={(e) => party.setData('role', e.target.value)} />
            <SelectField label="النوع" options={[{ value: 'individual', label: 'فرد' }, { value: 'organization', label: 'منشأة' }]} value={party.data.party_type} onChange={(e) => party.setData('party_type', e.target.value)} />
            <TextField label="الهوية / السجل" ltr value={party.data.identifier} onChange={(e) => party.setData('identifier', e.target.value)} />
            <div className="sm:col-span-2"><Button size="sm" type="submit" loading={party.processing} disabled={!party.data.name}><Plus className="size-4" aria-hidden />إضافة طرف</Button></div>
          </form>
        </Panel>
      </div>
      <Modal open={edit.open} onClose={edit.close} title="تعديل بيانات القضية">
        <form onSubmit={(e) => { e.preventDefault(); form.put(`/office/cases/${c.id}`, { preserveScroll: true, onSuccess: edit.close }); }} className="grid gap-4 sm:grid-cols-2">
          <FormError errors={form.errors as Record<string, string>} />
          <TextField className="sm:col-span-2" label="العنوان" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} />
          <SelectField label="المرحلة الإجرائية" options={p.options.stages} value={form.data.stage} onChange={(e) => form.setData('stage', e.target.value)} />
          <SelectField label="صفة العميل" options={p.options.roles} value={form.data.client_role} onChange={(e) => form.setData('client_role', e.target.value)} />
          <SelectField label="التصنيف" options={p.options.categories} placeholder="بدون" value={form.data.category_id} onChange={(e) => form.setData('category_id', e.target.value)} />
          <SelectField label="الوكالة" options={p.options.poas} placeholder="بدون" value={form.data.power_of_attorney_id} onChange={(e) => form.setData('power_of_attorney_id', e.target.value)} error={form.errors.power_of_attorney_id} />
          <TextField label="المبلغ" ltr value={form.data.claim_amount} onChange={(e) => form.setData('claim_amount', e.target.value.replace(/[^\d.]/g, ''))} error={form.errors.claim_amount} />
          <SelectField label="العملة" options={p.options.currencies} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} />
          <TextArea className="sm:col-span-2" label="ملاحظات المحامي (داخلية)" rows={4} value={form.data.internal_notes} onChange={(e) => form.setData('internal_notes', e.target.value)} />
          <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={form.processing}>حفظ</Button></div>
        </form>
      </Modal>
    </div>
  );
}

// ====================================================================== تراضي
function ReconciliationTab({ p }: { p: Props }) {
  const add = useForm({ request_number: '', submitted_on: '', status: 'submitted', notes: '' });
  const [outcomeFor, setOutcomeFor] = useState<Recon | null>(null);
  const modal = useModal();
  return (
    <div className="grid grid-cols-1 gap-4">
      {p.reconciliations.map((r) => (
        <Panel key={r.id} title={<>طلب صلح {r.number ? <Num>{r.number}</Num> : <span className="text-muted">بلا رقم بعد</span>}</>}
          actions={<Pill tone={r.status === 'settled' ? 'positive' : ['failed', 'other_party_absent'].includes(r.status) ? 'warning' : 'info'}>{r.statusLabel}</Pill>}>
          <KeyValues cols={3} items={[
            ['تاريخ التقديم', r.submittedOn && <Num>{r.submittedOn}</Num>], ['تاريخ النتيجة', r.outcomeOn && <Num>{r.outcomeOn}</Num>],
            ['المبلغ المتفق عليه', r.agreedAmount && <Money value={r.agreedAmount} currency={r.currency} />], ['ملاحظات', r.notes],
            ['تقرير الصلح', r.report?.url && <a href={r.report.url} className="text-accent hover:underline">تنزيل التقرير</a>],
            ['جلسات الصلح', <Num>{p.hearings.filter((h) => h.reconciliationId === r.id).length}</Num>],
          ]} />
          <div className="mt-4 flex flex-wrap gap-2">
            {['submitted', 'in_progress'].includes(r.status) && <Button size="sm" variant="primary" onClick={() => { setOutcomeFor(r); modal.show(); }}>تسجيل النتيجة</Button>}
            {['failed', 'other_party_absent'].includes(r.status) && !r.movedToCourt && (
              <Button size="sm" variant="primary" onClick={() => router.post(`/office/reconciliations/${r.id}/move-to-court`)}><Gavel className="size-4" aria-hidden />الانتقال إلى ناجز وإنشاء مهمة لائحة الدعوى</Button>
            )}
            {r.movedToCourt && <span className="text-[13px] text-muted">انتقل الملف إلى ناجز.</span>}
          </div>
        </Panel>
      ))}
      <Panel title="طلب صلح جديد (تراضي)" description="تسجيل داخلي لإجراء ينفذه المكتب في تراضي.">
        <form onSubmit={(e) => { e.preventDefault(); add.post(`/office/cases/${p.case.id}/reconciliations`, { preserveScroll: true, onSuccess: () => add.reset() }); }} className="grid gap-3 sm:grid-cols-4 sm:items-end">
          <TextField label="رقم الطلب" ltr value={add.data.request_number} onChange={(e) => add.setData('request_number', e.target.value)} />
          <TextField label="تاريخ التقديم" type="date" value={add.data.submitted_on} onChange={(e) => add.setData('submitted_on', e.target.value)} />
          <SelectField label="الحالة" options={p.options.reconciliationStatuses.slice(0, 2)} value={add.data.status} onChange={(e) => add.setData('status', e.target.value)} />
          <Button type="submit" loading={add.processing}><Plus className="size-4" aria-hidden />تسجيل</Button>
        </form>
      </Panel>
      <Modal open={modal.open} onClose={modal.close} title="نتيجة الصلح">{outcomeFor && <OutcomeForm r={outcomeFor} p={p} onDone={modal.close} />}</Modal>
    </div>
  );
}

function OutcomeForm({ r, p, onDone }: { r: Recon; p: Props; onDone: () => void }) {
  const f = useForm({ status: 'failed', outcome_on: new Date().toISOString().slice(0, 10), agreed_amount: '', currency: 'SAR', notes: '', report_document: '', close_case: true });
  const docs = p.documents.filter((d) => d.scan === 'clean');
  return (
    <form onSubmit={(e) => { e.preventDefault(); f.post(`/office/reconciliations/${r.id}/outcome`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-4 sm:grid-cols-2">
      <SelectField label="النتيجة" options={[{ value: 'settled', label: 'تم الصلح' }, { value: 'failed', label: 'تعذّر الصلح' }, { value: 'other_party_absent', label: 'غياب الطرف الآخر' }, { value: 'closed', label: 'إغلاق' }]}
        value={f.data.status} onChange={(e) => f.setData('status', e.target.value)} />
      <TextField label="التاريخ" type="date" value={f.data.outcome_on} onChange={(e) => f.setData('outcome_on', e.target.value)} />
      {f.data.status === 'settled' && (
        <>
          <TextField label="المبلغ المتفق عليه" ltr value={f.data.agreed_amount} onChange={(e) => f.setData('agreed_amount', e.target.value.replace(/[^\d.]/g, ''))} error={f.errors.agreed_amount} />
          <SelectField label="العملة" options={p.options.currencies} value={f.data.currency} onChange={(e) => f.setData('currency', e.target.value)} />
          <div className="sm:col-span-2"><Checkbox label="إغلاق مسار القضية (تُعد مكتملة صلحًا)" checked={f.data.close_case} onChange={(v) => f.setData('close_case', v)} /></div>
        </>
      )}
      <SelectField className="sm:col-span-2" label="تقرير الصلح (من مرفقات القضية)" placeholder={docs.length ? 'بدون' : 'ارفع التقرير أولًا من تبويب المرفقات'} options={docs.map((d) => ({ value: d.id, label: d.label || d.name }))} value={f.data.report_document} onChange={(e) => f.setData('report_document', e.target.value)} />
      <TextArea className="sm:col-span-2" label="ملاحظات" rows={2} value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
      {['failed', 'other_party_absent'].includes(f.data.status) && <p className="text-[13px] text-muted sm:col-span-2">بعد الحفظ يظهر خيار الانتقال إلى ناجز.</p>}
      {f.data.status === 'settled' && <p className="text-[13px] text-muted sm:col-span-2">سجّل الدفعات المقبوضة فعليًا من قسم «الحكم والتنفيذ» ← التحصيل، بمصدر «صلح».</p>}
      <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={f.processing}>حفظ النتيجة</Button></div>
    </form>
  );
}

// ====================================================================== ناجز
function CourtTab({ p }: { p: Props }) {
  const [edit, setEdit] = useState<Filing | null>(null);
  const modal = useModal();
  return (
    <div className="grid grid-cols-1 gap-4">
      {p.filings.length === 0 && <EmptyState title="لا طلبات تقييد" action={<Button variant="primary" onClick={() => { setEdit(null); modal.show(); }}>إضافة طلب تقييد أو قضية مقيدة</Button>}>أضف طلب التقييد، أو القضية المقيدة مباشرة (بما في ذلك قضايا المدعى عليه).</EmptyState>}
      {p.filings.map((f) => (
        <Panel key={f.id} title={f.caseNumber ? <>القضية رقم <Num>{f.caseNumber}</Num></> : <>طلب تقييد {f.requestNumber ? <Num>{f.requestNumber}</Num> : ''}</>}
          actions={<><Pill tone={f.status === 'registered' ? 'positive' : f.status === 'returned' ? 'warning' : f.status === 'rejected' ? 'negative' : 'info'}>{f.statusLabel}</Pill>
            <Button size="sm" variant="ghost" onClick={() => { setEdit(f); modal.show(); }}>تحديث</Button></>}>
          <KeyValues cols={3} items={[
            ['رقم طلب التقييد', f.requestNumber && <Num>{f.requestNumber}</Num>], ['تاريخ تقديم الطلب', f.submittedOn && <Num>{f.submittedOn}</Num>],
            ['رقم القضية', f.caseNumber && <Num>{f.caseNumber}</Num>], ['تاريخ القيد', f.registeredOn && <Num>{f.registeredOn}</Num>],
            ['تاريخ الإحالة', f.referredOn && <Num>{f.referredOn}</Num>], ['المحكمة', f.court], ['الدائرة', f.circuit], ['ملاحظات', f.notes],
          ]} />
        </Panel>
      ))}
      {p.filings.length > 0 && <div><Button size="sm" onClick={() => { setEdit(null); modal.show(); }}><Plus className="size-4" aria-hidden />طلب تقييد آخر</Button></div>}
      <p className="text-[12.5px] text-muted">سجل الإعادة للاستكمال وإعادة التقديم محفوظ في الخط الزمني للقضية.</p>
      <Modal open={modal.open} onClose={modal.close} title={edit ? 'تحديث طلب التقييد' : 'طلب تقييد / قضية مقيدة'}>
        <FilingForm key={edit?.id ?? 'new'} f={edit} caseId={p.case.id} statuses={p.options.filingStatuses} onDone={modal.close} />
      </Modal>
    </div>
  );
}

function FilingForm({ f, caseId, statuses, onDone }: { f: Filing | null; caseId: number; statuses: Option[]; onDone: () => void }) {
  const form = useForm({ filing_request_number: f?.requestNumber ?? '', submitted_on: f?.submittedOn ?? '', status: f?.status ?? 'submitted', case_number: f?.caseNumber ?? '',
    registered_on: f?.registeredOn ?? '', referred_on: f?.referredOn ?? '', court: f?.court ?? '', circuit: f?.circuit ?? '', notes: f?.notes ?? '', event_note: '' });
  const submit = (e: React.FormEvent) => { e.preventDefault(); const o = { preserveScroll: true, onSuccess: onDone }; f ? form.put(`/office/filings/${f.id}`, o) : form.post(`/office/cases/${caseId}/filings`, o); };
  return (
    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
      <TextField label="رقم طلب التقييد" ltr value={form.data.filing_request_number} onChange={(e) => form.setData('filing_request_number', e.target.value)} hint="يختلف عن رقم القضية." />
      <TextField label="تاريخ تقديم الطلب" type="date" value={form.data.submitted_on} onChange={(e) => form.setData('submitted_on', e.target.value)} />
      <SelectField label="الحالة" options={statuses} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} />
      <TextField label="رقم القضية (بعد القيد)" ltr value={form.data.case_number} onChange={(e) => form.setData('case_number', e.target.value)} />
      <TextField label="تاريخ القيد" type="date" value={form.data.registered_on} onChange={(e) => form.setData('registered_on', e.target.value)} />
      <TextField label="تاريخ الإحالة" type="date" value={form.data.referred_on} onChange={(e) => form.setData('referred_on', e.target.value)} />
      <TextField label="المحكمة" value={form.data.court} onChange={(e) => form.setData('court', e.target.value)} />
      <TextField label="الدائرة" value={form.data.circuit} onChange={(e) => form.setData('circuit', e.target.value)} />
      {f && <TextField className="sm:col-span-2" label="ملاحظة على هذا التحديث (تُحفظ في الخط الزمني)" value={form.data.event_note} onChange={(e) => form.setData('event_note', e.target.value)} placeholder="مثل: أعيد للاستكمال لنقص صحيفة الدعوى" />}
      <TextArea className="sm:col-span-2" label="ملاحظات" rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
      <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={form.processing}>حفظ</Button></div>
    </form>
  );
}

// ====================================================================== الجلسات
function HearingsTab({ p }: { p: Props }) {
  const add = useForm({ kind: p.case.stage === 'reconciliation' ? 'reconciliation' : 'court', scheduled_at: '', authority: '', meeting_url: '',
    reconciliation_request_id: p.reconciliations[0]?.id ? String(p.reconciliations[0].id) : '', court_filing_id: p.filings[0]?.id ? String(p.filings[0].id) : '' });
  const addModal = useModal();
  const [changing, setChanging] = useState<Hearing | null>(null);
  const [completing, setCompleting] = useState<Hearing | null>(null);
  const sorted = [...p.hearings].sort((a, b) => ((b.at ?? '') > (a.at ?? '') ? 1 : -1));
  return (
    <div className="grid grid-cols-1 gap-4">
      <div className="flex justify-end"><Button variant="primary" size="sm" onClick={addModal.show}><CalendarPlus className="size-4" aria-hidden />جدولة جلسة</Button></div>
      {sorted.length === 0 ? <EmptyState title="لا جلسات" /> : sorted.map((h) => (
        <section key={h.id} id={`hearing-${h.id}`} className={cx('card scroll-mt-20 px-5 py-4', h.status !== 'scheduled' && h.status !== 'completed' && 'bg-raised')}>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 className="font-semibold">{h.kindLabel} — <Num>{dateTime(h.at)}</Num></h3>
              <p className="text-[13px] text-muted">{h.at && weekday(h.localAt!.slice(0, 10))}{h.authority && ` · ${h.authority}`}{h.anchoredTasks > 0 && ` · ${h.anchoredTasks} مهمة مرتبطة بموعدها`}</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Pill tone={h.status === 'scheduled' ? 'accent' : h.status === 'completed' ? 'neutral' : 'warning'}>{h.statusLabel}</Pill>
              {h.meetingUrl && h.status === 'scheduled' && <a href={h.meetingUrl} target="_blank" rel="noopener noreferrer" className="btn btn-ghost btn-sm">رابط الجلسة</a>}
              {h.status === 'scheduled' && <>
                <Button size="sm" onClick={() => setChanging(h)}>تأجيل / تعديل / إلغاء</Button>
                <Button size="sm" variant="primary" onClick={() => setCompleting(h)}>إكمال الجلسة</Button>
              </>}
            </div>
          </div>
          {(h.result || h.directives || h.attendance || h.minutes || h.changeReason) && (
            <div className="mt-3 border-t border-rule pt-3">
              <KeyValues cols={3} items={[
                ['الحضور', h.attendance && Object.entries(h.attendance).filter(([, v]) => v).map(([k, v]) => `${({ client: 'العميل', opponent: 'الطرف الآخر', lawyer: 'المحامي' } as Record<string, string>)[k]}: ${attendLabel[v]}`).join('، ')],
                ['النتيجة', h.result], ['التوجيهات', h.directives], ['سبب التغيير', h.changeReason],
                ['ضبط الجلسة', h.minutes?.url && <a href={h.minutes.url} className="text-accent hover:underline">تنزيل الضبط</a>],
              ]} />
            </div>
          )}
        </section>
      ))}

      <Modal open={addModal.open} onClose={addModal.close} title="جدولة جلسة">
        <form onSubmit={(e) => { e.preventDefault(); add.post(`/office/cases/${p.case.id}/hearings`, { preserveScroll: true, onSuccess: () => { add.reset('scheduled_at', 'meeting_url'); addModal.close(); } }); }} className="grid gap-4 sm:grid-cols-2">
          <SelectField label="نوع الجلسة" options={p.options.hearingKinds} value={add.data.kind} onChange={(e) => add.setData('kind', e.target.value)} />
          <TextField label="الموعد (بتوقيت الرياض)" type="datetime-local" required value={add.data.scheduled_at} onChange={(e) => add.setData('scheduled_at', e.target.value)} error={add.errors.scheduled_at} />
          {add.data.kind === 'reconciliation' && p.reconciliations.length > 0 && <SelectField label="طلب الصلح" options={p.reconciliations.map((r) => ({ value: r.id, label: r.number ?? `طلب #${r.id}` }))} value={add.data.reconciliation_request_id} onChange={(e) => add.setData('reconciliation_request_id', e.target.value)} />}
          {add.data.kind === 'court' && p.filings.length > 0 && <SelectField label="القضية في ناجز" placeholder="—" options={p.filings.map((f) => ({ value: f.id, label: f.caseNumber ?? f.requestNumber ?? `#${f.id}` }))} value={add.data.court_filing_id} onChange={(e) => add.setData('court_filing_id', e.target.value)} />}
          <TextField label="الجهة" value={add.data.authority} onChange={(e) => add.setData('authority', e.target.value)} />
          <TextField label="رابط الجلسة (إن وجد)" ltr type="url" value={add.data.meeting_url} onChange={(e) => add.setData('meeting_url', e.target.value)} error={add.errors.meeting_url} />
          <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={add.processing}>جدولة</Button></div>
        </form>
      </Modal>
      <Modal open={!!changing} onClose={() => setChanging(null)} title="تغيير موعد الجلسة">{changing && <ChangeHearing h={changing} onDone={() => setChanging(null)} />}</Modal>
      <Modal open={!!completing} onClose={() => setCompleting(null)} title="إكمال الجلسة" wide>{completing && <CompleteHearing h={completing} p={p} onDone={() => setCompleting(null)} />}</Modal>
    </div>
  );
}

function ChangeHearing({ h, onDone }: { h: Hearing; onDone: () => void }) {
  const form = useForm({ action: 'postpone', scheduled_at: '', reason: '', lock_version: h.lockVersion });
  const [preview, setPreview] = useState<{ id: number; title: string; old: string | null; new: string | null }[] | null>(null);
  useEffect(() => {
    if (h.anchoredTasks === 0) return;
    const q = form.data.action === 'cancel' || !form.data.scheduled_at ? '' : '?scheduled_at=' + encodeURIComponent(form.data.scheduled_at);
    fetch(`/office/hearings/${h.id}/preview${q}`, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => setPreview(d.tasks)).catch(() => setPreview(null));
  }, [form.data.scheduled_at, form.data.action]);
  return (
    <form onSubmit={(e) => { e.preventDefault(); form.post(`/office/hearings/${h.id}/change`, { preserveScroll: true, onSuccess: onDone }); }} className="grid grid-cols-1 gap-4">
      <FormError errors={form.errors as Record<string, string>} />
      <fieldset>
        <legend className="field-label">الإجراء</legend>
        <div className="flex flex-wrap gap-2">
          {[['postpone', 'تأجيل لموعد جديد'], ['reschedule', 'تصحيح الموعد'], ['cancel', 'إلغاء الجلسة']].map(([v, l]) => (
            <label key={v} className={cx('btn btn-secondary cursor-pointer', form.data.action === v && '!border-ink !bg-active font-semibold')}>
              <input type="radio" className="sr-only" checked={form.data.action === v} onChange={() => form.setData('action', v)} />{l}
            </label>
          ))}
        </div>
      </fieldset>
      {form.data.action !== 'cancel' && <TextField label={form.data.action === 'postpone' ? 'الموعد الجديد (اتركه فارغًا إن لم يُحدد)' : 'الموعد الصحيح'} type="datetime-local" value={form.data.scheduled_at} onChange={(e) => form.setData('scheduled_at', e.target.value)} error={form.errors.scheduled_at} />}
      <TextField label="السبب" required value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} error={form.errors.reason} hint="يُحفظ في سجل القضية ومع كل مهمة يتغير موعدها." />
      {preview && preview.length > 0 && (
        <div className="well p-3">
          <p className="mb-2 text-[13.5px] font-semibold">أثر التغيير على المهام المرتبطة بالجلسة</p>
          <ul className="space-y-1 text-[13.5px]">{preview.map((t) => (
            <li key={t.id} className="flex flex-wrap justify-between gap-2"><span>{t.title}</span><span><Num>{t.old ?? '—'}</Num> ← <Num>{form.data.action === 'cancel' || !t.new ? 'بانتظار تحديد موعد' : t.new}</Num></span></li>
          ))}</ul>
        </div>
      )}
      <Button variant="primary" type="submit" loading={form.processing}>تأكيد</Button>
    </form>
  );
}

function CompleteHearing({ h, p, onDone }: { h: Hearing; p: Props; onDone: () => void }) {
  const form = useForm<{ attendance: Record<string, string>; result: string; directives: string; minutes_document: string; next_hearing_at: string; lock_version: number; tasks: TaskInput[] }>({
    attendance: { client: 'represented', opponent: 'present', lawyer: 'present' }, result: '', directives: '', minutes_document: '', next_hearing_at: '', lock_version: h.lockVersion, tasks: [],
  });
  const [uploadOpen, setUploadOpen] = useState(false);
  const docs = p.documents.filter((d) => d.scan === 'clean');
  const att = (k: string, label: string, opts: [string, string][]) => (
    <SelectField label={label} options={opts.map(([v, l]) => ({ value: v, label: l }))} value={form.data.attendance[k]} onChange={(e) => form.setData('attendance', { ...form.data.attendance, [k]: e.target.value })} />
  );
  return (
    <div className="grid gap-5">
    {/* رفع الضبط خارج نموذج الإكمال: لا نماذج متداخلة */}
    <div>
      <button type="button" className="text-[13px] text-accent hover:underline" onClick={() => setUploadOpen(!uploadOpen)} aria-expanded={uploadOpen}>{uploadOpen ? 'إخفاء رفع الضبط' : 'رفع ضبط الجلسة الآن'}</button>
      {uploadOpen && <div className="well mt-2 p-3"><UploadForm compact ownerType="case" ownerId={p.case.id} categories={p.options.documentCategories} defaultCategory="hearing_minutes" onDone={() => setUploadOpen(false)} /></div>}
      {uploadOpen && <p className="mt-1 text-[12.5px] text-muted">بعد اكتمال الفحص الأمني يظهر الملف في قائمة «ضبط الجلسة».</p>}
    </div>
    <form onSubmit={(e) => { e.preventDefault(); form.post(`/office/hearings/${h.id}/complete`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-5">
      <FormError errors={form.errors as Record<string, string>} />
      <div className="grid gap-3 sm:grid-cols-3">
        {att('client', 'حضور العميل', [['present', 'حاضر'], ['represented', 'بوكيل'], ['absent', 'غائب']])}
        {att('opponent', 'حضور الطرف الآخر', [['present', 'حاضر'], ['represented', 'بوكيل'], ['absent', 'غائب']])}
        {att('lawyer', 'حضور المحامي', [['present', 'حاضر'], ['absent', 'غائب']])}
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <TextArea label="النتيجة" rows={3} value={form.data.result} onChange={(e) => form.setData('result', e.target.value)} />
        <TextArea label="التوجيهات" rows={3} value={form.data.directives} onChange={(e) => form.setData('directives', e.target.value)} />
        <div>
          <SelectField label="ضبط الجلسة" placeholder={docs.length ? 'بدون' : 'لا مرفقات بعد'} options={docs.map((d) => ({ value: d.id, label: d.label || d.name }))} value={form.data.minutes_document} onChange={(e) => form.setData('minutes_document', e.target.value)} />
        </div>
        <TextField label="الجلسة القادمة (إن حُددت)" type="datetime-local" value={form.data.next_hearing_at} onChange={(e) => form.setData('next_hearing_at', e.target.value)} />
      </div>
      <fieldset className="space-y-3">
        <legend className="text-[15px] font-semibold">المهام الناتجة عن الجلسة</legend>
        {form.data.tasks.map((t, i) => (
          <div key={i} className="well relative p-3">
            <button type="button" className="btn btn-ghost btn-sm absolute end-2 top-2" aria-label="حذف المهمة" onClick={() => form.setData('tasks', form.data.tasks.filter((_, j) => j !== i))}>✕</button>
            <TaskFields value={t} prefix={`tasks.${i}.`} errors={form.errors as Record<string, string>} kinds={p.options.taskKinds} dueModes={p.options.dueModes}
              onChange={(v) => form.setData('tasks', form.data.tasks.map((x, j) => (j === i ? v : x)))} />
          </div>
        ))}
        <div className="flex flex-wrap gap-2">
          {[['submit_response', 'تقديم رد'], ['attach_document', 'إرفاق مستند'], ['prepare_memo', 'إعداد مذكرة'], ['manual', 'مهمة أخرى']].map(([k, l]) => (
            <Button key={k} type="button" size="sm" onClick={() => form.setData('tasks', [...form.data.tasks, { ...emptyTask(k), title: k === 'manual' ? '' : l, due_mode: form.data.next_hearing_at ? 'next_hearing' : 'date', anchor_offset_days: '-2' }])}>
              <Plus className="size-4" aria-hidden />{l}
            </Button>
          ))}
        </div>
      </fieldset>
      <Button variant="primary" type="submit" loading={form.processing}>حفظ وإكمال الجلسة</Button>
    </form>
    </div>
  );
}

// ====================================================================== المهام والمذكرات
function TasksTab({ p }: { p: Props }) {
  const add = useForm<TaskInput>(emptyTask());
  const memo = useForm({ type: 'response', title: '', due_on: '', hearing_id: '' });
  const memoModal = useModal();
  const [edit, setEdit] = useState<Task | null>(null);
  const toggle = (t: Task) => router.post(`/office/tasks/${t.id}/status`, { status: t.status === 'open' ? 'done' : 'open', lock_version: t.lockVersion }, { preserveScroll: true });
  return (
    <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
      <Panel title="المهام">
        {p.tasks.length === 0 ? <p className="mb-3 text-[13.5px] text-muted">لا مهام.</p> : (
          <ul className="mb-4 divide-y divide-rule">{p.tasks.map((t) => (
            <li key={t.id} id={`task-${t.id}`} className="flex items-start gap-3 py-2.5">
              <input type="checkbox" className="check mt-1" checked={t.status === 'done'} onChange={() => toggle(t)} aria-label={(t.status === 'done' ? 'إعادة فتح: ' : 'إنجاز: ') + t.title} />
              <div className="min-w-0 flex-1">
                <p className={cx('font-medium', t.status !== 'open' && 'text-muted line-through')}>{t.title}</p>
                <p className="text-[12.5px] text-muted">{t.kindLabel} · {t.dueOn ? <Num>{t.dueOn}</Num> : 'بانتظار تحديد موعد'}
                  {t.dueMode === 'next_hearing' && ` · مرتبط بالجلسة (${t.anchorOffset! >= 0 ? '+' : ''}${t.anchorOffset} يوم)`}
                  {t.dueMode === 'relative' && ` · ${t.relativeDays} ${t.relativeDayType === 'business' ? 'يوم عمل' : 'يوم'} من ${t.relativeEvent ?? 'الواقعة'}`}</p>
                {t.changes.length > 0 && <p className="text-[12px] text-muted">آخر تغيير: <Num>{t.changes[0].old ?? '—'}</Num> ← <Num>{t.changes[0].new ?? '—'}</Num> ({t.changes[0].reason})</p>}
              </div>
              {t.status === 'open' && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setEdit(t)}>تعديل</button>}
            </li>
          ))}</ul>
        )}
        <form onSubmit={(e) => { e.preventDefault(); add.post(`/office/cases/${p.case.id}/tasks`, { preserveScroll: true, onSuccess: () => add.reset() }); }} className="well space-y-3 p-4">
          <p className="font-semibold">مهمة جديدة</p>
          <TaskFields value={add.data} onChange={(v) => add.setData(v)} errors={add.errors as Record<string, string>} kinds={p.options.taskKinds} dueModes={p.options.dueModes} />
          <Button type="submit" variant="primary" size="sm" loading={add.processing}>إضافة</Button>
        </form>
      </Panel>
      <Panel title="المذكرات" actions={<Button size="sm" variant="primary" onClick={memoModal.show}><Plus className="size-4" aria-hidden />مذكرة</Button>}>
        {p.memos.length === 0 ? <EmptyState title="لا مذكرات" icon={<FileText className="size-6" strokeWidth={1.5} />}>لائحة الدعوى والمذكرات الجوابية والردود — مسودات داخلية لا تظهر للعميل.</EmptyState> : (
          <ul className="divide-y divide-rule">{p.memos.map((m) => (
            <li key={m.id} className="flex items-center justify-between gap-2 py-2.5">
              <div className="min-w-0"><Link href={`/office/memos/${m.id}`} className="block truncate font-medium hover:underline underline-offset-4">{m.title}</Link>
                <p className="text-[12.5px] text-muted">{m.type} · إصدار <Num>{m.version}</Num>{m.dueOn && <> · الموعد <Num>{m.dueOn}</Num></>}{m.filedOn && <> · أودعت <Num>{m.filedOn}</Num></>}</p></div>
              <Pill tone={m.status === 'filed' ? 'positive' : m.status === 'approved' ? 'accent' : m.status === 'ready_for_review' ? 'info' : 'neutral'}>{m.statusLabel}</Pill>
            </li>
          ))}</ul>
        )}
      </Panel>
      <Modal open={memoModal.open} onClose={memoModal.close} title="مذكرة جديدة">
        <form onSubmit={(e) => { e.preventDefault(); memo.post(`/office/cases/${p.case.id}/memos`); }} className="grid gap-4 sm:grid-cols-2">
          <SelectField label="النوع" options={p.options.memoTypes} value={memo.data.type} onChange={(e) => memo.setData('type', e.target.value)} />
          <TextField label="الموعد (اختياري)" type="date" value={memo.data.due_on} onChange={(e) => memo.setData('due_on', e.target.value)} />
          <TextField className="sm:col-span-2" label="العنوان" required value={memo.data.title} onChange={(e) => memo.setData('title', e.target.value)} error={memo.errors.title} />
          <SelectField className="sm:col-span-2" label="مرتبطة بجلسة" placeholder="بدون" options={p.hearings.map((h) => ({ value: h.id, label: `${h.kindLabel} ${h.localAt?.replace('T', ' ') ?? ''}` }))} value={memo.data.hearing_id} onChange={(e) => memo.setData('hearing_id', e.target.value)} />
          <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={memo.processing}>إنشاء وفتح المحرر</Button></div>
        </form>
      </Modal>
      <Modal open={!!edit} onClose={() => setEdit(null)} title="تعديل المهمة">{edit && <EditTask t={edit} p={p} onDone={() => setEdit(null)} />}</Modal>
    </div>
  );
}

function EditTask({ t, p, onDone }: { t: Task; p: Props; onDone: () => void }) {
  const form = useForm<TaskInput & { lock_version: number; change_reason: string }>({
    title: t.title, kind: t.kind, due_mode: t.dueMode, due_on: t.dueOn ?? '', relative_from: t.relativeFrom ?? '', relative_event: t.relativeEvent ?? '',
    relative_days: t.relativeDays !== null ? String(t.relativeDays) : '', relative_day_type: t.relativeDayType ?? 'calendar', anchor_offset_days: String(t.anchorOffset ?? 0), lock_version: t.lockVersion, change_reason: '',
  });
  return (
    <form onSubmit={(e) => { e.preventDefault(); form.put(`/office/tasks/${t.id}`, { preserveScroll: true, onSuccess: onDone }); }} className="space-y-4">
      <FormError errors={form.errors as Record<string, string>} />
      <TaskFields value={form.data} onChange={(v) => form.setData({ ...form.data, ...v })} errors={form.errors as Record<string, string>} kinds={p.options.taskKinds} dueModes={p.options.dueModes} />
      <TextField label="سبب تغيير الموعد (يُحفظ)" value={form.data.change_reason} onChange={(e) => form.setData('change_reason', e.target.value)} />
      <Button variant="primary" type="submit" loading={form.processing}>حفظ</Button>
    </form>
  );
}

// ====================================================================== الحكم والتنفيذ
function JudgmentTab({ p }: { p: Props }) {
  const j = p.judgment;
  const form = useForm({ outcome: j?.outcome ?? 'full_win', pronounced_on: j?.pronouncedOn ?? '', deed_status: j?.deedStatus ?? 'awaiting', deed_number: j?.deedNumber ?? '', deed_date: j?.deedDate ?? '',
    deed_document: '', objection_status: j?.objectionStatus ?? '', objection_filed_on: j?.objectionFiledOn ?? '', resumed_on: j?.resumedOn ?? '', notes: j?.notes ?? '' });
  const fin = useForm({ document: '', confirm: false });
  const dec = useForm({ decision: 'pursue', execution_type: 'financial' });
  const docs = p.documents.filter((d) => d.scan === 'clean');
  const resumable = ['struck_off', 'suspended'].includes(form.data.outcome);
  return (
    <div className="grid grid-cols-1 gap-4">
      <div className="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
        <Panel title="الحكم والنتيجة" description="المرحلة الإجرائية منفصلة عن حالة العمل ونتيجة الحكم.">
          <form onSubmit={(e) => { e.preventDefault(); form.post(`/office/cases/${p.case.id}/judgment`, { preserveScroll: true }); }} className="grid gap-4 sm:grid-cols-2">
            <SelectField className="sm:col-span-2" label="النتيجة" options={p.options.outcomes.filter((o) => o.value !== 'settled')} value={form.data.outcome} onChange={(e) => form.setData('outcome', e.target.value)} />
            <TextField label="تاريخ النطق" type="date" value={form.data.pronounced_on} onChange={(e) => form.setData('pronounced_on', e.target.value)} hint={j?.pronouncedHijri ?? undefined} />
            <SelectField label="الصك" options={p.options.deedStatuses} value={form.data.deed_status} onChange={(e) => form.setData('deed_status', e.target.value)} />
            {form.data.deed_status === 'issued' && <>
              <TextField label="رقم الصك" ltr value={form.data.deed_number} onChange={(e) => form.setData('deed_number', e.target.value)} />
              <TextField label="تاريخ الصك" type="date" value={form.data.deed_date} onChange={(e) => form.setData('deed_date', e.target.value)} />
              <SelectField className="sm:col-span-2" label="مرفق الصك" placeholder="بدون" options={docs.map((d) => ({ value: d.id, label: d.label || d.name }))} value={form.data.deed_document} onChange={(e) => form.setData('deed_document', e.target.value)} />
            </>}
            <SelectField label="الاعتراض" placeholder="لم يُحدد" options={p.options.objectionStatuses} value={form.data.objection_status} onChange={(e) => form.setData('objection_status', e.target.value)} />
            {form.data.objection_status === 'objected' && <TextField label="تاريخ تقديم الاعتراض" type="date" value={form.data.objection_filed_on} onChange={(e) => form.setData('objection_filed_on', e.target.value)} />}
            {resumable && <TextField label="تاريخ الاستكمال (إن استُكملت)" type="date" value={form.data.resumed_on} onChange={(e) => form.setData('resumed_on', e.target.value)} hint="الشطب والوقف قابلان للاستكمال ولا يُعدّان اكتمالًا." />}
            <TextArea className="sm:col-span-2" label="ملاحظات" rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={form.processing}>{j ? 'حفظ التحديث' : 'تسجيل الحكم'}</Button></div>
          </form>
        </Panel>
        <div className="grid grid-cols-1 content-start gap-4">
          {j && (
            <Panel title="النهائية">
              {j.finality === 'final' ? <p className="flex items-center gap-2 text-positive"><CheckCircle2 className="size-4" aria-hidden />الحكم نهائي — أكّده المحامي <Num>{dateTime(j.finalityConfirmedAt)}</Num></p> : (
                <form onSubmit={(e) => { e.preventDefault(); fin.post(`/office/cases/${p.case.id}/judgment/finality`, { preserveScroll: true }); }} className="space-y-3">
                  <p className="text-[13.5px] text-muted">تأكيد النهائية إجراء صريح منك، ولا يُفترض بانتهاء أي عداد.</p>
                  <SelectField label="مستند النهائية (اختياري)" placeholder="بدون" options={docs.map((d) => ({ value: d.id, label: d.label || d.name }))} value={fin.data.document} onChange={(e) => fin.setData('document', e.target.value)} />
                  <Checkbox label="أؤكد أن الحكم أصبح نهائيًا" checked={fin.data.confirm} onChange={(v) => fin.setData('confirm', v)} />
                  <Button type="submit" size="sm" disabled={!fin.data.confirm} loading={fin.processing}>تأكيد النهائية</Button>
                </form>
              )}
            </Panel>
          )}
          {j && (
            <Panel title="التنفيذ بعد الحكم">
              <p className="mb-3 text-[13.5px]">القرار الحالي: <strong>{p.options.executionDecisions.find((o) => o.value === j.executionDecision)?.label}</strong></p>
              <form onSubmit={(e) => { e.preventDefault(); dec.post(`/office/cases/${p.case.id}/judgment/execution`, { preserveScroll: true }); }} className="grid gap-3 sm:grid-cols-2">
                <SelectField label="القرار" options={p.options.executionDecisions.filter((o) => o.value !== 'pending')} value={dec.data.decision} onChange={(e) => dec.setData('decision', e.target.value)} />
                {dec.data.decision === 'pursue' && <SelectField label="نوع التنفيذ" options={[{ value: 'financial', label: 'مالي' }, { value: 'direct', label: 'مباشر' }]} value={dec.data.execution_type} onChange={(e) => dec.setData('execution_type', e.target.value)} />}
                <div className="sm:col-span-2"><Button type="submit" size="sm" variant="primary" loading={dec.processing}>حفظ القرار</Button></div>
              </form>
              {p.executions.length > 0 && (
                <ul className="mt-4 divide-y divide-rule border-t border-rule">{p.executions.map((e) => (
                  <li key={e.id} className="flex items-center justify-between py-2"><Link href={`/office/executions/${e.id}`} className="font-medium text-accent hover:underline">{e.type} — <Num>{e.requestNumber ?? e.reference}</Num></Link><Pill tone="info">{e.status}</Pill></li>
                ))}</ul>
              )}
            </Panel>
          )}
        </div>
      </div>
      <Panel title="المواعيد والعدادات المرتبطة" description="المتابعة التقديرية لصدور الصك مستقلة عن عداد الاعتراض. لا يُفترض أن تاريخ النطق بداية كل مهلة.">
        <DeadlineList rows={p.deadlines} kinds={p.options.deadlineKinds} certainties={p.options.certainties} />
      </Panel>
      <CollectionsPanel rows={p.collections} totals={p.totals} kinds={p.options.collectionKinds} sources={p.options.collectionSources} currencies={p.options.currencies}
        caseId={p.case.id} defaultSource={p.reconciliations.some((r) => r.status === 'settled') ? 'reconciliation' : 'execution'} documents={p.documents}
        reconciliations={p.reconciliations.map((r) => ({ value: r.id, label: r.number ?? `#${r.id}` }))} />
    </div>
  );
}

// ====================================================================== الصفحة
export default function Show(p: Props) {
  const [tab, setTab] = useTab(p.tab);
  const c = p.case;
  const status = useModal();
  const st = useForm({ work_status: c.status, note: '' });
  const showRecon = p.reconciliations.length > 0 || ['reconciliation', 'intake'].includes(c.stage);
  const showCourt = p.filings.length > 0 || !['intake', 'reconciliation'].includes(c.stage) || p.reconciliations.some((r) => r.movedToCourt);
  const missing: { label: string; tab: string }[] = [
    ...(!showRecon ? [{ label: 'طلب صلح', tab: 'reconciliation' }] : []),
    ...(!showCourt ? [{ label: 'طلب تقييد / قضية مقيدة', tab: 'court' }] : []),
    ...(!p.judgment ? [{ label: 'الحكم', tab: 'judgment' }] : []),
  ];
  const [forced, setForced] = useState<string[]>([]);
  const isShown = (k: string) => forced.includes(k) || (k === 'reconciliation' ? showRecon : k === 'court' ? showCourt : k === 'judgment' ? !!p.judgment || ['judgment', 'objection', 'execution', 'closed'].includes(c.stage) : true);
  const openTab = (k: string) => { setForced((f) => [...f, k]); setTab(k); };

  const tabContent: Record<string, ReactNode> = {
    summary: <SummaryTab p={p} />, timeline: <Panel title="الخط الزمني"><Timeline events={p.timeline} showUser /></Panel>,
    reconciliation: <ReconciliationTab p={p} />, court: <CourtTab p={p} />, hearings: <HearingsTab p={p} />, tasks: <TasksTab p={p} />,
    files: (
      <div className="grid grid-cols-1 gap-4">
        <Panel title="رفع مرفق"><UploadForm ownerType="case" ownerId={c.id} categories={p.options.documentCategories} /></Panel>
        <Panel title="مرفقات القضية" description={c.sourceClaimId ? 'تشمل مرفقات المطالبة الأصلية.' : undefined}><DocumentList docs={p.documents} manage empty="لا مرفقات بعد." /></Panel>
      </div>
    ),
    judgment: <JudgmentTab p={p} />,
  };

  return (
    <>
      <Head title={c.title} />
      <PageHeader crumbs={[{ label: 'القضايا', href: '/office/cases' }, { label: c.client, href: `/office/clients/${c.clientId}` }]} title={c.title}
        subtitle={<span className="flex flex-wrap items-center gap-2"><Num>{c.reference}</Num><span>· {c.clientRoleLabel} · {c.stageLabel}</span><Pill tone={caseTone(c.status)}>{c.statusLabel}</Pill>{c.outcomeLabel && <Pill>{c.outcomeLabel}</Pill>}</span>}
        actions={<>
          <Button size="sm" onClick={status.show}><RotateCcw className="size-4" aria-hidden />حالة العمل</Button>
          <ButtonLink size="sm" href={`/office/reports?type=case&case=${c.id}`}><Printer className="size-4" aria-hidden />تقرير</ButtonLink>
        </>} />
      <Tabs label="أقسام القضية" current={tab} onSelect={setTab} tabs={[
        { key: 'summary', label: 'الملخص' }, { key: 'timeline', label: 'الخط الزمني' },
        { key: 'reconciliation', label: 'الصلح', count: p.reconciliations.length, hidden: !isShown('reconciliation') },
        { key: 'court', label: 'القضاء', count: p.filings.length, hidden: !isShown('court') },
        { key: 'hearings', label: 'الجلسات', count: p.hearings.filter((h) => h.status === 'scheduled').length },
        { key: 'tasks', label: 'المهام والمذكرات', count: p.tasks.filter((t) => t.status === 'open').length },
        { key: 'files', label: 'المرفقات', count: p.documents.length },
        { key: 'judgment', label: 'الحكم والتنفيذ', hidden: !isShown('judgment') },
      ]} />
      {missing.filter((m) => !isShown(m.tab)).length > 0 && (
        <div className="-mt-3 mb-4 flex flex-wrap items-center gap-2 text-[13px] text-muted">
          إضافة إجراء: {missing.filter((m) => !isShown(m.tab)).map((m) => <button key={m.tab} type="button" className="btn btn-ghost btn-sm" onClick={() => openTab(m.tab)}><Plus className="size-3.5" aria-hidden />{m.label}</button>)}
        </div>
      )}
      <div role="tabpanel" id={'panel-' + tab} aria-labelledby={'tab-' + tab}>{tabContent[tab] ?? tabContent.summary}</div>
      <Modal open={status.open} onClose={status.close} title="حالة العمل">
        <form onSubmit={(e) => { e.preventDefault(); st.post(`/office/cases/${c.id}/status`, { preserveScroll: true, onSuccess: status.close }); }} className="space-y-4">
          <SelectField label="الحالة" options={p.options.statuses} value={st.data.work_status} onChange={(e) => st.setData('work_status', e.target.value)} hint="الموقوفة والمشطوبة تبقى قابلة للاستكمال ولا تُحسب مكتملة." />
          <TextField label="ملاحظة (تُحفظ في الخط الزمني)" value={st.data.note} onChange={(e) => st.setData('note', e.target.value)} />
          <Button variant="primary" type="submit" loading={st.processing}>حفظ</Button>
        </form>
      </Modal>
    </>
  );
}
