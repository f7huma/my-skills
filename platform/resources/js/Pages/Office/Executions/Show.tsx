import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CollectionsPanel, type CollectionRow } from '@/Components/Collections';
import { DeadlineList, type DeadlineRow } from '@/Components/Deadlines';
import { ExecutionFields } from '@/Components/ExecutionFields';
import { emptyTask, TaskFields, type TaskInput } from '@/Components/TaskFields';
import { Button, cx, DocumentList, EmptyState, FormError, KeyValues, Modal, Money, Num, PageHeader, Panel, Pill, SelectField, Tabs, TextArea, TextField, Timeline, UploadForm, useModal, DateText } from '@/Components/ui';
import { useTab } from '@/lib/useTab';
import type { Doc, Option, Totals } from '@/types';

type Exec = Record<string, any> & { id: number; reference: string; client: string; clientId: number; case: { id: number; reference: string; title: string } | null; lockVersion: number; weeklyDue: string | null };
type Action = { id: number; type: string; typeId: number; submittedOn: string | null; status: string; statusLabel: string; result: string | null; eligibleOn: string | null; nextFollowupOn: string | null; notes: string | null };
type Props = {
  execution: Exec; parties: { id: number; name: string; role: string; party_type: string; identifier: string | null }[];
  decisions: { id: number; label: string; number: string | null; decidedOn: string | null; notes: string | null; document: Doc | null }[]; actions: Action[];
  tasks: { id: number; title: string; kindLabel: string; dueOn: string | null; status: string; lockVersion: number }[]; deadlines: DeadlineRow[];
  timeline: { id: number; title: string; details: string | null; at: string; clientVisible: boolean; user: string | null }[]; documents: Doc[]; collections: CollectionRow[]; totals: Totals;
  options: { executionTypes: Option[]; instrumentTypes: Option[]; statuses: Option[]; actionTypes: (Option & { interval: number | null; dayType: string; fromLast: boolean; condition: string | null })[];
    actionStatuses: Option[]; decisionLabels: string[]; currencies: string[]; documentCategories: Option[]; collectionKinds: Option[]; collectionSources: Option[]; taskKinds: Option[]; dueModes: Option[]; deadlineKinds: Option[]; certainties: Option[] };
};
const label = (opts: Option[], v: string) => opts.find((o) => o.value === v)?.label ?? v;

export default function Show(p: Props) {
  const e = p.execution;
  const [tab, setTab] = useTab(new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('tab') ?? 'summary');
  const edit = useModal();
  const form = useForm<Record<string, any>>({ ...Object.fromEntries(Object.entries(e).filter(([k]) => /^[a-z_]+$/.test(k)).map(([k, v]) => [k, v ?? ''])), lock_version: e.lockVersion });
  useEffect(() => { form.setData('lock_version', e.lockVersion); }, [e.lockVersion]);
  const action = useForm({ action_type_id: String(p.options.actionTypes[0]?.value ?? ''), submitted_on: '', status: 'submitted', result: '', next_followup_on: '', notes: '' });
  const decision = useForm({ label: 'قرار 34', number: '', decided_on: '', notes: '', document: '' });
  const task = useForm<TaskInput>(emptyTask('execution_followup'));
  const [labelMode, setLabelMode] = useState('قرار 34');
  const [actEdit, setActEdit] = useState<Action | null>(null);
  const selType = p.options.actionTypes.find((t) => String(t.value) === action.data.action_type_id);
  const docs = p.documents.filter((d) => d.scan === 'clean');
  const instrument = e.instrument_type === 'other' ? e.instrument_label : label(p.options.instrumentTypes, e.instrument_type);

  return (
    <>
      <Head title={'تنفيذ ' + (e.request_number ?? e.reference)} />
      <PageHeader title={<>طلب تنفيذ {e.request_number ? <Num>{e.request_number}</Num> : <span className="text-muted">بلا رقم بعد</span>}</>}
        crumbs={[{ label: 'التنفيذ', href: '/office/executions' }, { label: e.client, href: `/office/clients/${e.clientId}` }]}
        subtitle={<span className="flex flex-wrap items-center gap-2"><Num>{e.reference}</Num> · {label(p.options.executionTypes, e.execution_type)} · {instrument}<Pill tone="info">{label(p.options.statuses, e.status)}</Pill>
          {e.case && <>· <Link href={`/office/cases/${e.case.id}?tab=judgment`} className="text-accent hover:underline">القضية <Num>{e.case.reference}</Num></Link></>}</span>}
        actions={<>
          {e.weekly_followup && <Button size="sm" onClick={() => router.post(`/office/executions/${e.id}/followup`, {}, { preserveScroll: true })}>تسجيل متابعة أسبوعية{e.weeklyDue && <> (<DateText value={e.weeklyDue} />)</>}</Button>}
          <Button size="sm" variant="primary" onClick={edit.show}>تحديث الطلب</Button>
        </>} />
      <Tabs label="أقسام طلب التنفيذ" current={tab} onSelect={setTab} tabs={[
        { key: 'summary', label: 'الملخص' }, { key: 'actions', label: 'طلبات الإجراء', count: p.actions.length }, { key: 'decisions', label: 'القرارات', count: p.decisions.length },
        { key: 'deadlines', label: 'المواعيد والمهام', count: p.tasks.filter((t) => t.status === 'open').length + p.deadlines.filter((d) => d.status !== 'closed').length },
        { key: 'money', label: 'التحصيل' }, { key: 'files', label: 'المرفقات', count: p.documents.length }, { key: 'timeline', label: 'الخط الزمني' },
      ]} />
      <div role="tabpanel" id={'panel-' + tab} aria-labelledby={'tab-' + tab}>
        {tab === 'summary' && (
          <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <Panel title="بيانات الطلب">
              <KeyValues cols={3} items={[
                ['نوع التنفيذ', label(p.options.executionTypes, e.execution_type)], ['نوع السند', instrument],
                ['رقم الصك', e.instrument_type === 'judgment_deed' && e.deed_number && <Num>{e.deed_number}</Num>], ['تاريخ الصك', e.instrument_type === 'judgment_deed' && e.deed_date && <DateText value={e.deed_date} />],
                ['رقم السند', e.instrument_type === 'electronic_promissory_note' && e.note_number && <Num>{e.note_number}</Num>],
                ['قيمة السند', e.instrument_type === 'electronic_promissory_note' && e.note_amount && <Money value={e.note_amount} currency={e.note_currency} />],
                ['استحقاق السند', e.note_due_on && <DateText value={e.note_due_on} />],
                ['محل التنفيذ', e.execution_type === 'direct' && e.subject_location], ['وصف الالتزام', e.execution_type === 'direct' && e.obligation_description],
                ['المبلغ المطلوب', e.amount_claimed && <Money value={e.amount_claimed} currency={e.currency} />],
                ['تقديم الطلب', e.submitted_on && <DateText value={e.submitted_on} />], ['القبول', e.accepted_on && <DateText value={e.accepted_on} />],
                ['صدور أمر التنفيذ', e.order_issued_on && <DateText value={e.order_issued_on} />],
                [e.notification_method === 'publication' ? 'الإعلان' : 'التبليغ', e.notified_on && <DateText value={e.notified_on} />],
                ['بدء الإجراءات الجبرية', e.enforcement_started_on && <DateText value={e.enforcement_started_on} />],
                ['ملخص للعميل', e.client_summary], ['ملاحظات داخلية', e.internal_notes],
              ]} />
            </Panel>
            <Panel title="الأطراف">
              {p.parties.length === 0 ? <p className="text-[13.5px] text-muted">لا أطراف.</p> : <ul className="divide-y divide-rule">{p.parties.map((x) => (
                <li key={x.id} className="py-2"><span className="font-medium">{x.name}</span> <span className="text-[12.5px] text-muted">· {x.role === 'respondent' ? 'منفذ ضده' : 'طالب تنفيذ'}{x.identifier && <> · <Num>{x.identifier}</Num></>}</span></li>
              ))}</ul>}
            </Panel>
          </div>
        )}

        {tab === 'actions' && (
          <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <Panel title="طلبات الإجراء" description="الإفصاح، تحويل المبلغ المحجوز، إدراج ممثل نظامي، طلب اتخاذ أمر أو إجراء، وغيرها.">
              {p.actions.length === 0 ? <EmptyState title="لا طلبات إجراء" /> : (
                <ul className="divide-y divide-rule">{p.actions.map((a) => (
                  <li key={a.id} id={`action-${a.id}`} className="grid gap-1 py-3 sm:grid-cols-[1fr_auto]">
                    <div>
                      <p className="font-medium">{a.type} <Pill tone={a.status === 'rejected' ? 'negative' : a.status === 'completed' ? 'positive' : 'info'}>{a.statusLabel}</Pill></p>
                      <p className="text-[12.5px] text-muted">{a.submittedOn && <>قُدّم <DateText value={a.submittedOn} /></>}{a.eligibleOn && <> · يجوز إعادة الطلب من <DateText value={a.eligibleOn} /> (شرط الخدمة)</>}</p>
                      {a.result && <p className="text-[13px]">{a.result}</p>}
                    </div>
                    <div className="flex items-center gap-2 sm:flex-col sm:items-end">
                      {a.nextFollowupOn && <span className="text-[13px]">متابعة: <DateText value={a.nextFollowupOn} /></span>}
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => setActEdit(a)}>تحديث</button>
                    </div>
                  </li>
                ))}</ul>
              )}
            </Panel>
            <Panel title="تسجيل طلب إجراء">
              <form onSubmit={(ev) => { ev.preventDefault(); action.post(`/office/executions/${e.id}/actions`, { preserveScroll: true, onSuccess: () => action.reset('submitted_on', 'result', 'next_followup_on', 'notes') }); }} className="space-y-3">
                <SelectField label="نوع الإجراء" options={p.options.actionTypes} value={action.data.action_type_id} onChange={(ev) => action.setData('action_type_id', ev.target.value)}
                  hint={selType?.interval ? `مهلة إعادة الطلب: ${selType.interval} ${selType.dayType === 'business' ? 'يوم عمل' : 'يوم'}${selType.fromLast ? ' من آخر طلب من النوع نفسه' : ''}.` : (selType?.condition ?? 'لا مهلة مضبوطة لهذه الخدمة — اضبطها من الإعدادات.')} />
                <TextField label="تاريخ التقديم" type="date" value={action.data.submitted_on} onChange={(ev) => action.setData('submitted_on', ev.target.value)} />
                <SelectField label="الحالة" options={p.options.actionStatuses} value={action.data.status} onChange={(ev) => action.setData('status', ev.target.value)} />
                <TextField label="أقرب موعد متابعة" type="date" value={action.data.next_followup_on} onChange={(ev) => action.setData('next_followup_on', ev.target.value)} hint="يظهر في قائمة إنجاز الأسبوع." />
                <TextArea label="النتيجة" rows={2} value={action.data.result} onChange={(ev) => action.setData('result', ev.target.value)} />
                <Button variant="primary" type="submit" loading={action.processing}>تسجيل</Button>
              </form>
            </Panel>
            <Modal open={!!actEdit} onClose={() => setActEdit(null)} title="تحديث طلب الإجراء">{actEdit && <ActionEdit a={actEdit} statuses={p.options.actionStatuses} onDone={() => setActEdit(null)} />}</Modal>
          </div>
        )}

        {tab === 'decisions' && (
          <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <Panel title="القرارات المسجلة" description="تُسجّل من واقع المستند أو ما ظهر فعليًا في ناجز. لا يُنشأ قرار ولا يثبت صدوره بانتهاء المهلة.">
              {p.decisions.length === 0 ? <EmptyState title="لا قرارات مسجلة" /> : (
                <ul className="divide-y divide-rule">{p.decisions.map((d) => (
                  <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <span><span className="font-medium">{d.label}</span>{d.number && <> — <Num>{d.number}</Num></>} <span className="text-[12.5px] text-muted">· <DateText value={d.decidedOn} />{d.notes && ` · ${d.notes}`}</span></span>
                    {d.document?.url && <a href={d.document.url} className="btn btn-ghost btn-sm">المرفق</a>}
                  </li>
                ))}</ul>
              )}
            </Panel>
            <Panel title="تسجيل قرار">
              <form onSubmit={(ev) => { ev.preventDefault(); decision.post(`/office/executions/${e.id}/decisions`, { preserveScroll: true, onSuccess: () => decision.reset('number', 'notes', 'document') }); }} className="space-y-3">
                <SelectField label="التسمية" options={[...p.options.decisionLabels, 'تسمية أخرى كما ظهرت']} value={labelMode} onChange={(ev) => { setLabelMode(ev.target.value); decision.setData('label', ev.target.value === 'تسمية أخرى كما ظهرت' ? '' : ev.target.value); }} />
                {labelMode === 'تسمية أخرى كما ظهرت' && <TextField label="التسمية كما ظهرت فعليًا" value={decision.data.label} onChange={(ev) => decision.setData('label', ev.target.value)} error={decision.errors.label} />}
                <TextField label="الرقم" ltr value={decision.data.number} onChange={(ev) => decision.setData('number', ev.target.value)} />
                <TextField label="التاريخ" type="date" required value={decision.data.decided_on} onChange={(ev) => decision.setData('decided_on', ev.target.value)} error={decision.errors.decided_on} />
                <SelectField label="المرفق" placeholder={docs.length ? 'بدون' : 'ارفع المستند من تبويب المرفقات'} options={docs.map((d) => ({ value: d.id, label: d.label || d.name }))} value={decision.data.document} onChange={(ev) => decision.setData('document', ev.target.value)} />
                <Button variant="primary" type="submit" loading={decision.processing}>تسجيل القرار</Button>
              </form>
            </Panel>
          </div>
        )}

        {tab === 'deadlines' && (
          <div className="grid grid-cols-1 gap-4">
            <Panel title="المواعيد والعدادات" description="مهلة المنفذ ضده تُربط بالإبلاغ أو الإعلان الموثق، ولا تعمل إلا بقاعدة معتمدة. خلاف ذلك: «يحتاج تحديدًا».">
              <DeadlineList rows={p.deadlines} kinds={p.options.deadlineKinds} certainties={p.options.certainties} />
            </Panel>
            <Panel title="المهام">
              {p.tasks.length > 0 && <ul className="mb-4 divide-y divide-rule">{p.tasks.map((t) => (
                <li key={t.id} id={`task-${t.id}`} className="flex items-center gap-3 py-2">
                  <input type="checkbox" className="check" checked={t.status === 'done'} aria-label={'إنجاز ' + t.title} onChange={() => router.post(`/office/tasks/${t.id}/status`, { status: t.status === 'open' ? 'done' : 'open', lock_version: t.lockVersion }, { preserveScroll: true })} />
                  <span className={cx('flex-1', t.status !== 'open' && 'text-muted line-through')}>{t.title}</span><span className="text-[13px] text-muted"><DateText value={t.dueOn} fallback="بلا موعد" /></span>
                </li>
              ))}</ul>}
              <form onSubmit={(ev) => { ev.preventDefault(); task.post(`/office/executions/${e.id}/tasks`, { preserveScroll: true, onSuccess: () => task.reset() }); }} className="well space-y-3 p-4">
                <TaskFields value={task.data} onChange={(v) => task.setData(v)} kinds={p.options.taskKinds} dueModes={p.options.dueModes} errors={task.errors as Record<string, string>} />
                <Button type="submit" size="sm" variant="primary" loading={task.processing}><Plus className="size-4" aria-hidden />إضافة مهمة</Button>
              </form>
            </Panel>
          </div>
        )}

        {tab === 'money' && <CollectionsPanel rows={p.collections} totals={p.totals} kinds={p.options.collectionKinds} sources={p.options.collectionSources} currencies={p.options.currencies} executionId={e.id} caseId={e.case?.id} documents={p.documents} />}
        {tab === 'files' && (
          <div className="grid grid-cols-1 gap-4">
            <Panel title="رفع مرفق"><UploadForm ownerType="execution" ownerId={e.id} categories={p.options.documentCategories} defaultCategory="execution" /></Panel>
            <Panel title="المرفقات"><DocumentList docs={p.documents} manage empty="لا مرفقات." /></Panel>
          </div>
        )}
        {tab === 'timeline' && <Panel title="الخط الزمني"><Timeline events={p.timeline} showUser /></Panel>}
      </div>

      <Modal open={edit.open} onClose={edit.close} title="تحديث طلب التنفيذ" wide>
        <form onSubmit={(ev) => { ev.preventDefault(); form.put(`/office/executions/${e.id}`, { preserveScroll: true, onSuccess: edit.close }); }} className="space-y-5">
          <FormError errors={form.errors as Record<string, string>} />
          <ExecutionFields data={form.data} set={(k, v) => form.setData(k, v)} errors={form.errors as Record<string, string>} options={p.options} />
          <Button variant="primary" type="submit" loading={form.processing}>حفظ</Button>
        </form>
      </Modal>
    </>
  );
}

function ActionEdit({ a, statuses, onDone }: { a: Action; statuses: Option[]; onDone: () => void }) {
  const f = useForm({ status: a.status, result: a.result ?? '', next_followup_on: a.nextFollowupOn ?? '', notes: a.notes ?? '' });
  return (
    <form onSubmit={(e) => { e.preventDefault(); f.put(`/office/execution-actions/${a.id}`, { preserveScroll: true, onSuccess: onDone }); }} className="space-y-3">
      <SelectField label="الحالة" options={statuses} value={f.data.status} onChange={(e) => f.setData('status', e.target.value)} />
      <TextField label="أقرب موعد متابعة" type="date" value={f.data.next_followup_on} onChange={(e) => f.setData('next_followup_on', e.target.value)} />
      <TextArea label="النتيجة" rows={2} value={f.data.result} onChange={(e) => f.setData('result', e.target.value)} />
      <Button variant="primary" type="submit" loading={f.processing}>حفظ</Button>
    </form>
  );
}
