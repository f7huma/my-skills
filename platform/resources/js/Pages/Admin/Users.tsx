import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button, Checkbox, cx, EmptyState, Modal, Num, PageHeader, Panel, Pill, SelectField, TextField, useModal, DateText } from '@/Components/ui';
import { dateTime } from '@/lib/format';
import type { Option, SharedProps } from '@/types';

type U = { id: number; name: string; email: string; username: string | null; role: string; active: boolean; twoFactor: boolean; lastLogin: string | null; memberships: { id: number; client: string; clientId: number; canView: boolean; canSubmitClaims: boolean; revoked: boolean }[] };

export default function Users({ users, invitations, clients }: { users: U[]; invitations: { id: number; name: string; email: string; role: string; client: string | null; expiresAt: string }[]; clients: Option[] }) {
  const { auth, flash } = usePage<SharedProps>().props;
  const modal = useModal();
  const form = useForm({ name: '', email: '', role: 'client', client_id: '', can_view: true, can_submit_claims: false });
  const act = (url: string, msg: string) => { if (confirm(msg)) router.post(url, {}, { preserveScroll: true }); };
  return (
    <>
      <Head title="الحسابات والصلاحيات" />
      <PageHeader title="الحسابات والصلاحيات" subtitle="الحسابات تُنشأ بالدعوة فقط. تعطيل الحساب يُنهي جلساته فورًا." actions={<Button variant="primary" onClick={modal.show}><Plus className="size-4" aria-hidden />دعوة</Button>} />
      {flash.inviteUrl && <p className="mb-4 break-all rounded-lg px-4 py-3 text-[13.5px] pill-info" style={{ height: 'auto' }}>الرابط (يظهر مرة واحدة، أحادي الاستخدام): <bdi dir="ltr" className="select-all">{flash.inviteUrl}</bdi></p>}
      <Panel title="المستخدمون">
        <ul className="divide-y divide-rule">{users.map((u) => (
          <li key={u.id} className="grid gap-2 py-3 lg:grid-cols-[1fr_auto]">
            <div className="min-w-0">
              <p className="font-medium">{u.name} <span className="text-[12.5px] font-normal text-muted"><Num>{u.email}</Num>{u.username && <> · اسم المستخدم: <bdi>{u.username}</bdi></>}</span></p>
              <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[12.5px] text-muted">
                <Pill tone={u.role === 'lawyer' ? 'accent' : 'neutral'}>{u.role === 'lawyer' ? 'محامٍ' : 'عميل'}</Pill>
                {!u.active && <Pill tone="negative">معطّل</Pill>}
                {u.role === 'lawyer' && <Pill tone={u.twoFactor ? 'positive' : 'warning'}>{u.twoFactor ? 'مصادقة ثنائية' : 'بلا مصادقة ثنائية'}</Pill>}
                آخر دخول {u.lastLogin ? <DateText value={u.lastLogin} time /> : '—'}
              </p>
              {u.memberships.length > 0 && <p className="mt-1 text-[13px]">{u.memberships.map((m) => (
                <span key={m.id} className={cx('me-3 inline-block', m.revoked && 'text-muted line-through')}>{m.client}: {[m.canView && 'متابعة', m.canSubmitClaims && 'مطالبات'].filter(Boolean).join(' + ') || 'بلا صلاحية'}</span>
              ))}</p>}
            </div>
            {u.id !== auth?.user.id && (
              <div className="flex flex-wrap items-center gap-1.5">
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => act(`/office/admin/users/${u.id}/revoke-sessions`, 'إنهاء جميع جلسات هذا المستخدم؟')}>إنهاء الجلسات</button>
                {u.active && <button type="button" className="btn btn-ghost btn-sm" onClick={() => act(`/office/admin/users/${u.id}/reset-password`, 'إنشاء رابط إعادة تعيين كلمة المرور؟ تُنهى جلساته الحالية.')}>إعادة تعيين كلمة المرور</button>}
                {u.active ? <button type="button" className="btn btn-danger btn-sm" onClick={() => act(`/office/admin/users/${u.id}/deactivate`, `تعطيل حساب ${u.name}؟ يُمنع وصوله فورًا.`)}>تعطيل</button>
                  : <button type="button" className="btn btn-secondary btn-sm" onClick={() => act(`/office/admin/users/${u.id}/reactivate`, 'إعادة تفعيل الحساب؟')}>إعادة التفعيل</button>}
              </div>
            )}
          </li>
        ))}</ul>
      </Panel>
      <Panel title="دعوات معلقة" className="mt-4">
        {invitations.length === 0 ? <EmptyState title="لا دعوات معلقة" /> : (
          <ul className="divide-y divide-rule">{invitations.map((i) => (
            <li key={i.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
              <span>{i.name} <span className="text-[12.5px] text-muted"><Num>{i.email}</Num> · {i.role === 'lawyer' ? 'محامٍ' : i.client}</span></span>
              <span className="flex items-center gap-2 text-[12.5px] text-muted">تنتهي <DateText value={i.expiresAt} time />
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.delete(`/office/admin/invitations/${i.id}`, { preserveScroll: true })}>إلغاء</button></span>
            </li>
          ))}</ul>
        )}
      </Panel>
      <Modal open={modal.open} onClose={modal.close} title="دعوة مستخدم">
        <form onSubmit={(e) => { e.preventDefault(); form.post('/office/admin/invitations', { preserveScroll: true, onSuccess: () => { modal.close(); form.reset(); } }); }} className="grid grid-cols-1 gap-4">
          <SelectField label="الدور" options={[{ value: 'client', label: 'مستخدم بوابة عميل' }, { value: 'lawyer', label: 'محامٍ (صلاحيات كاملة)' }]} value={form.data.role} onChange={(e) => form.setData('role', e.target.value)} />
          {form.data.role === 'client' && <SelectField label="العميل" options={clients} placeholder="اختر العميل" value={form.data.client_id} onChange={(e) => form.setData('client_id', e.target.value)} error={form.errors.client_id} />}
          <TextField label="الاسم" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
          <TextField label="البريد الإلكتروني" type="email" ltr required value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
          {form.data.role === 'client' && <>
            <Checkbox label="متابعة القضايا والتنفيذ والمبالغ" checked={form.data.can_view} onChange={(v) => form.setData('can_view', v)} />
            <Checkbox label="تقديم المطالبات" checked={form.data.can_submit_claims} onChange={(v) => form.setData('can_submit_claims', v)} />
            {form.errors.can_view && <p className="field-error">{form.errors.can_view}</p>}
          </>}
          <Button variant="primary" type="submit" loading={form.processing}>إنشاء الدعوة</Button>
        </form>
      </Modal>
    </>
  );
}
