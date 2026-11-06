import { IconAttachment, IconCase, IconClaims, IconExecution, IconPoa } from '@/Components/Icons';
import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Archive, Pencil, Plus } from 'lucide-react';
import { Button, ButtonLink, caseTone, Checkbox, claimTone, DocumentList, EmptyState, KeyValues, Modal, Money, Num, PageHeader, Panel, Pill, poaLabel, poaTone, Tabs, TextArea, TextField, TotalsGrid, UploadForm, useModal, DateText } from '@/Components/ui';
import { dateTime } from '@/lib/format';
import { useTab } from '@/lib/useTab';
import type { Doc, Option, SharedProps, Totals } from '@/types';

type Poa = { id: number; number: string; issued: string | null; expires: string | null; state: string; notes: string | null; document: Doc | null };
type Props = {
  tab: string;
  client: { id: number; reference: string; type: string; typeLabel: string; name: string; nationalId: string | null; unifiedNumber: string | null; commercialRegistration: string | null; address: string | null; nationalAddress: string | null; phone: string | null; email: string | null; internalNotes: string | null; archived: boolean };
  counts: { total: number; active: number; completed: number; resumable: number };
  poas: Poa[];
  cases: { id: number; reference: string; title: string; stage: string; status: string; statusLabel: string; category: string | null; role: string; outcome: string | null; closedOn: string | null }[];
  executions: { id: number; reference: string; requestNumber: string | null; type: string; status: string; caseId: number | null }[];
  claims: { id: number; reference: string; opponent: string; status: string; statusLabel: string; amount: string | null; currency: string }[];
  documents: Doc[]; allDocumentsCount: number; totals: Totals;
  members: { id: number; userId: number; name: string; email: string; active: boolean; canView: boolean; canSubmitClaims: boolean; revoked: boolean; lastLogin: string | null }[];
  pendingInvitations: { id: number; name: string; email: string; expires_at: string; can_view: boolean; can_submit_claims: boolean }[];
  categories: Option[];
};

function PoaForm({ clientId, poa, onDone }: { clientId: number; poa?: Poa; onDone: () => void }) {
  const form = useForm({ number: poa?.number ?? '', issued_on: poa?.issued ?? '', expires_on: poa?.expires ?? '', notes: poa?.notes ?? '', status: poa?.state === 'revoked' ? 'revoked' : 'active', document_id: '' });
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const opts = { preserveScroll: true, onSuccess: onDone };
    poa ? form.put(`/office/clients/${clientId}/poas/${poa.id}`, opts) : form.post(`/office/clients/${clientId}/poas`, opts);
  };
  return (
    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
      <TextField label="رقم الوكالة" ltr required value={form.data.number} onChange={(e) => form.setData('number', e.target.value)} error={form.errors.number} />
      {poa && (
        <div>
          <label className="field-label" htmlFor="poa-status">الحالة</label>
          <select id="poa-status" className="input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}><option value="active">سارية</option><option value="revoked">ملغاة</option></select>
        </div>
      )}
      <TextField label="تاريخ الإصدار" type="date" value={form.data.issued_on} onChange={(e) => form.setData('issued_on', e.target.value)} error={form.errors.issued_on} />
      <TextField label="تاريخ الانتهاء" type="date" value={form.data.expires_on} onChange={(e) => form.setData('expires_on', e.target.value)} error={form.errors.expires_on} />
      <TextArea className="sm:col-span-2" label="ملاحظات" rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
      {!poa && <p className="text-[12.5px] text-muted sm:col-span-2">لإرفاق صورة الوكالة: ارفعها من تبويب الملفات بنوع «الوكالة». تسجيل وكالة جديدة لا يستبدل السجل القديم.</p>}
      <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={form.processing}>{poa ? 'حفظ' : 'تسجيل الوكالة'}</Button></div>
    </form>
  );
}

function InviteForm({ clientId, onDone }: { clientId: number; onDone: () => void }) {
  const form = useForm({ name: '', email: '', role: 'client', client_id: clientId, can_view: true, can_submit_claims: false });
  return (
    <form onSubmit={(e) => { e.preventDefault(); form.post('/office/admin/invitations', { preserveScroll: true, onSuccess: onDone }); }} className="grid grid-cols-1 gap-4">
      <TextField label="اسم المستخدم" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
      <TextField label="البريد الإلكتروني" type="email" ltr required value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
      <Checkbox label="متابعة القضايا والتنفيذ والمبالغ" checked={form.data.can_view} onChange={(v) => form.setData('can_view', v)} />
      <Checkbox label="تقديم المطالبات" hint="منفصلة عن صلاحية المتابعة." checked={form.data.can_submit_claims} onChange={(v) => form.setData('can_submit_claims', v)} />
      {form.errors.can_view && <p className="field-error">{form.errors.can_view}</p>}
      <Button variant="primary" type="submit" loading={form.processing}>إرسال الدعوة</Button>
      <p className="text-[12.5px] text-muted">رابط أحادي الاستخدام صالح 72 ساعة.</p>
    </form>
  );
}

export default function Show(p: Props) {
  const [tab, setTab] = useTab(p.tab);
  const c = p.client;
  const poaModal = useModal();
  const inviteModal = useModal();
  const editPoa = usePoaState();
  const inviteUrl = usePage<SharedProps>().props.flash.inviteUrl;
  return (
    <>
      <Head title={c.name} />
      <PageHeader title={c.name} crumbs={[{ label: 'العملاء', href: '/office/clients' }]}
        subtitle={<>{c.typeLabel}{c.archived && <> · <Pill>مؤرشف</Pill></>}</>}
        actions={<>
          <ButtonLink href={`/office/cases/create?client=${c.id}`} variant="primary" size="sm"><Plus className="size-4" aria-hidden />قضية</ButtonLink>
          <ButtonLink href={`/office/executions/create?client=${c.id}`} size="sm">طلب تنفيذ</ButtonLink>
          <ButtonLink href={`/office/reports?type=client&client=${c.id}`} size="sm">تقرير</ButtonLink>
          <ButtonLink href={`/office/clients/${c.id}/edit`} size="sm" variant="ghost"><Pencil className="size-4" aria-hidden />تعديل</ButtonLink>
        </>} />

      <Tabs label="أقسام ملف العميل" current={tab} onSelect={setTab} tabs={[
        { key: 'overview', label: 'الملخص' }, { key: 'poa', label: 'الوكالات', icon: <IconPoa size={17} />, count: p.poas.length }, { key: 'files', label: 'الملفات', icon: <IconAttachment size={17} />, count: p.documents.length },
        { key: 'cases', label: 'القضايا', icon: <IconCase size={17} />, count: p.cases.length }, { key: 'executions', label: 'التنفيذ', icon: <IconExecution size={17} />, count: p.executions.length },
        { key: 'claims', label: 'المطالبات', icon: <IconClaims size={17} />, count: p.claims.length }, { key: 'access', label: 'حسابات البوابة', count: p.members.length },
      ]} />

      <div role="tabpanel" id={'panel-' + tab} aria-labelledby={'tab-' + tab}>
        {tab === 'overview' && (
          <div className="grid gap-4 lg:grid-cols-[1.3fr_1fr]">
            <Panel title="بيانات العميل">
              <KeyValues items={[
                ['النوع', c.typeLabel],
                ['رقم الهوية', c.type === 'individual' && c.nationalId && <Num>{c.nationalId}</Num>],
                ['الرقم الوطني الموحد', c.type === 'organization' && c.unifiedNumber && <Num>{c.unifiedNumber}</Num>],
                ['السجل التجاري', c.type === 'organization' && c.commercialRegistration && <Num>{c.commercialRegistration}</Num>],
                ['الجوال', c.phone && <Num>{c.phone}</Num>], ['البريد', c.email && <Num>{c.email}</Num>],
                ['العنوان الوطني', c.nationalAddress && <Num>{c.nationalAddress}</Num>], ['العنوان', c.address],
                ['ملاحظات داخلية', c.internalNotes],
              ]} />
            </Panel>
            <div className="grid grid-cols-1 content-start gap-4">
              <Panel title="القضايا">
                <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-line sm:grid-cols-4">
                  {[['الإجمالي', p.counts.total], ['السارية', p.counts.active], ['المكتملة', p.counts.completed], ['موقوفة/مشطوبة', p.counts.resumable]].map(([l, v]) => (
                    <div key={String(l)} className="bg-surface px-3 py-2.5"><dt className="text-[12px] text-muted">{l}</dt><dd className="text-[20px] font-semibold"><Num>{v}</Num></dd></div>
                  ))}
                </dl>
                {p.counts.resumable > 0 && <p className="mt-2 text-[12.5px] text-muted">الموقوفة والمشطوبة قابلة للاستكمال ولا تُحسب مكتملة.</p>}
              </Panel>
              <Panel title="الوكالات">
                {p.poas.length === 0 ? <p className="text-[13.5px] text-muted">لا وكالات مسجلة.</p> : (
                  <ul className="divide-y divide-rule">{p.poas.slice(0, 3).map((v) => (
                    <li key={v.id} className="flex items-center justify-between py-2"><span>وكالة <Num>{v.number}</Num> <span className="text-[12.5px] text-muted">· تنتهي <DateText value={v.expires} /></span></span><Pill tone={poaTone(v.state)}>{poaLabel[v.state]}</Pill></li>
                  ))}</ul>
                )}
              </Panel>
            </div>
            <Panel title="المبالغ" className="lg:col-span-2">
              <TotalsGrid totals={p.totals} labels={{ claimed: 'المطلوب', agreed: 'المتفق عليه', seized: 'المحجوز', received: 'المقبوض فعليًا' }} />
            </Panel>
          </div>
        )}

        {tab === 'poa' && (
          <Panel title="الوكالات" description="ترتب القريبة من الانتهاء أولًا. المنتهية والملغاة مميزة ولا تُحذف."
            actions={<Button size="sm" variant="primary" onClick={() => { editPoa.set(null); poaModal.show(); }}><Plus className="size-4" aria-hidden />وكالة جديدة</Button>}>
            {p.poas.length === 0 ? <EmptyState title="لا وكالات مسجلة" /> : (
              <div className="overflow-x-auto"><table className="table">
                <thead><tr><th>الرقم</th><th>الإصدار</th><th>الانتهاء</th><th>الحالة</th><th>المرفق</th><th><span className="sr-only">إجراءات</span></th></tr></thead>
                <tbody>{p.poas.map((v) => (
                  <tr key={v.id}>
                    <td className="font-medium"><Num>{v.number}</Num></td><td><DateText value={v.issued} /></td><td><DateText value={v.expires} /></td>
                    <td><Pill tone={poaTone(v.state)}>{poaLabel[v.state]}</Pill></td>
                    <td>{v.document?.url ? <a className="text-accent hover:underline" href={v.document.url}>تنزيل</a> : <span className="text-faint">—</span>}</td>
                    <td className="end"><button type="button" className="btn btn-ghost btn-sm" onClick={() => { editPoa.set(v); poaModal.show(); }}>تعديل</button></td>
                  </tr>
                ))}</tbody>
              </table></div>
            )}
            <Modal open={poaModal.open} onClose={poaModal.close} title={editPoa.value ? 'تعديل الوكالة' : 'وكالة جديدة'}>
              <PoaForm key={editPoa.value?.id ?? 'new'} clientId={c.id} poa={editPoa.value ?? undefined} onDone={poaModal.close} />
            </Modal>
          </Panel>
        )}

        {tab === 'files' && (
          <div className="grid grid-cols-1 gap-4">
            <Panel title="رفع مستند للعميل" description="عقد الأتعاب، الوكالة، الهوية أو السجل التجاري، عقد التأسيس، العنوان الوطني، وغيرها.">
              <UploadForm ownerType="client" ownerId={c.id} categories={p.categories} defaultCategory="fee_contract" />
            </Panel>
            <Panel title="ملفات العميل" description={p.allDocumentsCount > p.documents.length ? `يظهر هنا ما رُفع على ملف العميل؛ مستندات القضايا والتنفيذ في صفحاتها (${p.allDocumentsCount} إجمالًا).` : undefined}>
              <DocumentList docs={p.documents} manage empty="لا ملفات على مستوى العميل بعد." />
            </Panel>
          </div>
        )}

        {tab === 'cases' && (
          <Panel title="القضايا" actions={<><ButtonLink href={`/office/cases/create?client=${c.id}&archived=1`} size="sm">إضافة قضية منتهية</ButtonLink><ButtonLink href={`/office/cases/create?client=${c.id}`} size="sm" variant="primary">قضية جديدة</ButtonLink></>}>
            {p.cases.length === 0 ? <EmptyState title="لا قضايا لهذا العميل" /> : (
              <div className="overflow-x-auto"><table className="table">
                <thead><tr><th>القضية</th><th>الصفة</th><th>المرحلة</th><th>الحالة</th><th>النتيجة / الانتهاء</th></tr></thead>
                <tbody>{p.cases.map((x) => (
                  <tr key={x.id}><td className="min-w-[240px] max-w-[420px] font-medium"><Link href={`/office/cases/${x.id}`} title={x.title} className="line-clamp-2 hover:underline underline-offset-4">{x.title}</Link></td>
                    <td className="text-muted">{x.role}</td><td className="text-muted">{x.stage}</td><td><Pill tone={caseTone(x.status)}>{x.statusLabel}</Pill></td>
                    <td className="text-[13px] text-muted">{x.outcome && <span className="block">{x.outcome}</span>}{x.closedOn && <DateText value={x.closedOn} />}{!x.outcome && !x.closedOn && '—'}</td></tr>
                ))}</tbody>
              </table></div>
            )}
          </Panel>
        )}

        {tab === 'executions' && (
          <Panel title="التنفيذ" actions={<><ButtonLink href={`/office/executions/create?client=${c.id}&status=completed`} size="sm">إضافة تنفيذ منتهٍ</ButtonLink><ButtonLink href={`/office/executions/create?client=${c.id}`} size="sm" variant="primary">طلب تنفيذ</ButtonLink></>}>
            {p.executions.length === 0 ? <EmptyState title="لا طلبات تنفيذ" /> : (
              <ul className="divide-y divide-rule">{p.executions.map((e) => (
                <li key={e.id} className="flex items-center justify-between py-2.5"><Link href={`/office/executions/${e.id}`} className="font-medium hover:underline underline-offset-4">{e.type}{e.requestNumber && <> — <Num>{e.requestNumber}</Num></>}</Link><Pill tone="info">{e.status}</Pill></li>
              ))}</ul>
            )}
          </Panel>
        )}

        {tab === 'claims' && (
          <Panel title="المطالبات المرتبطة">
            {p.claims.length === 0 ? <EmptyState title="لا مطالبات مرسلة" /> : (
              <ul className="divide-y divide-rule">{p.claims.map((x) => (
                <li key={x.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5"><Link href={`/office/claims/${x.id}`} className="font-medium hover:underline underline-offset-4">مطالبة ضد {x.opponent}</Link>
                  <span className="flex items-center gap-3"><Money value={x.amount} currency={x.currency} /><Pill tone={claimTone(x.status)}>{x.statusLabel}</Pill></span></li>
              ))}</ul>
            )}
          </Panel>
        )}

        {tab === 'access' && (
          <Panel title="حسابات بوابة العميل" description="يمكن للمنشأة أن يكون لها أكثر من مستخدم. صلاحية المتابعة منفصلة عن تقديم المطالبات."
            actions={<Button size="sm" variant="primary" onClick={inviteModal.show}><Plus className="size-4" aria-hidden />دعوة مستخدم</Button>}>
            {inviteUrl && <p className="mb-3 break-all rounded-lg px-3 py-2 text-[13px] pill-info" style={{ height: 'auto' }}>رابط الدعوة (يظهر مرة واحدة): <bdi dir="ltr">{inviteUrl}</bdi></p>}
            {p.members.length === 0 && p.pendingInvitations.length === 0 ? <EmptyState title="لا مستخدمين لهذا العميل" /> : (
              <ul className="divide-y divide-rule">
                {p.members.map((m) => <MemberRow key={m.id} m={m} />)}
                {p.pendingInvitations.map((i) => (
                  <li key={'i' + i.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                    <span><span className="font-medium">{i.name}</span> <span className="text-[12.5px] text-muted"><Num>{i.email}</Num></span></span>
                    <span className="flex items-center gap-2 text-[12.5px] text-muted"><Pill tone="warning">دعوة معلقة</Pill>تنتهي <DateText value={i.expires_at} time />
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.delete(`/office/admin/invitations/${i.id}`, { preserveScroll: true })}>إلغاء</button></span>
                  </li>
                ))}
              </ul>
            )}
            <Modal open={inviteModal.open} onClose={inviteModal.close} title="دعوة مستخدم للبوابة"><InviteForm clientId={c.id} onDone={inviteModal.close} /></Modal>
          </Panel>
        )}
      </div>
      {!c.archived && tab === 'overview' && (
        <div className="mt-8 border-t border-rule pt-5">
          <Button variant="ghost" size="sm" onClick={() => { if (confirm('أرشفة العميل؟ يتوقف وصول مستخدمي البوابة لملفاته.')) router.post(`/office/clients/${c.id}/archive`); }}><Archive className="size-4" aria-hidden />أرشفة العميل</Button>
        </div>
      )}
    </>
  );
}

function MemberRow({ m }: { m: Props['members'][number] }) {
  const save = (data: { can_view: boolean; can_submit_claims: boolean; revoked: boolean }) => router.put(`/office/admin/memberships/${m.id}`, data, { preserveScroll: true });
  return (
    <li className="flex flex-wrap items-center justify-between gap-3 py-3">
      <div className="min-w-0">
        <p className="font-medium">{m.name} {!m.active && <Pill tone="negative">معطّل</Pill>} {m.revoked && <Pill>عضوية ملغاة</Pill>}</p>
        <p className="text-[12.5px] text-muted"><Num>{m.email}</Num> · آخر دخول {m.lastLogin ? <DateText value={m.lastLogin} time /> : 'لم يدخل بعد'}</p>
      </div>
      <div className="flex flex-wrap items-center gap-4 text-[13.5px]">
        <label className="flex items-center gap-1.5"><input type="checkbox" className="check" checked={m.canView} disabled={m.revoked} onChange={(e) => save({ can_view: e.target.checked, can_submit_claims: m.canSubmitClaims, revoked: m.revoked })} />متابعة</label>
        <label className="flex items-center gap-1.5"><input type="checkbox" className="check" checked={m.canSubmitClaims} disabled={m.revoked} onChange={(e) => save({ can_view: m.canView, can_submit_claims: e.target.checked, revoked: m.revoked })} />مطالبات</label>
        <button type="button" className={m.revoked ? 'btn btn-secondary btn-sm' : 'btn btn-danger btn-sm'} onClick={() => save({ can_view: m.canView, can_submit_claims: m.canSubmitClaims, revoked: !m.revoked })}>{m.revoked ? 'إعادة العضوية' : 'إلغاء العضوية'}</button>
      </div>
    </li>
  );
}

function usePoaState() {
  const [value, set] = useState<Poa | null>(null);
  return { value, set };
}
