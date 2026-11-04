import { Head, useForm, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import OfficeLayout from '@/Layouts/OfficeLayout';
import PortalLayout from '@/Layouts/PortalLayout';
import { Button, PageHeader, Pill, TextField } from '@/Components/ui';
import type { SharedProps } from '@/types';

type Props = { twoFactorEnabled: boolean; mfaRequired: boolean; setup: { qr: string; secret: string } | null; recoveryCodes: string[] | null; remainingRecoveryCodes: number };

function Section({ title, description, children }: { title: string; description: string; children: ReactNode }) {
  return (
    <section className="grid gap-4 border-b border-rule py-8 first:pt-0 last:border-0 md:grid-cols-[minmax(0,320px)_1fr] md:gap-10">
      <div>
        <h2 className="text-[16px] font-semibold">{title}</h2>
        <p className="mt-1 text-[13.5px] text-muted">{description}</p>
      </div>
      <div className="well p-4 sm:p-5">{children}</div>
    </section>
  );
}

export default function Security({ twoFactorEnabled, mfaRequired, setup, recoveryCodes, remainingRecoveryCodes }: Props) {
  const start = useForm({ password: '' });
  const confirm = useForm({ code: '' });
  const recovery = useForm({ password: '' });
  const disable = useForm({ password: '' });
  const pwd = useForm({ current_password: '', password: '', password_confirmation: '' });
  const others = useForm({ password: '' });

  return (
    <div className="mx-auto max-w-[1040px]">
      <Head title="أمان الحساب" />
      <PageHeader title="أمان الحساب" subtitle="كلمة المرور والمصادقة الثنائية والجلسات." />
      {mfaRequired && !twoFactorEnabled && (
        <p role="alert" className="mb-6 rounded-lg px-4 py-3 text-[14px] pill-warning" style={{ height: 'auto' }}>المصادقة الثنائية إلزامية لحساب المحامي في هذه البيئة. فعّلها للوصول إلى بيانات المكتب.</p>
      )}

      <Section title="المصادقة الثنائية" description="رمز من تطبيق مصادقة (مثل Microsoft Authenticator أو Google Authenticator) عند كل دخول.">
        <div className="mb-4 flex items-center gap-2">
          <span className="font-medium">الحالة:</span>
          {twoFactorEnabled ? <Pill tone="positive">مفعّلة</Pill> : <Pill tone="warning">غير مفعّلة</Pill>}
          {twoFactorEnabled && <span className="text-[13px] text-muted">رموز الاسترداد المتبقية: <bdi className="num">{remainingRecoveryCodes}</bdi></span>}
        </div>

        {recoveryCodes && (
          <div className="mb-5 rounded-lg border border-[var(--warning-border)] bg-[var(--pill-warning-bg)] p-4">
            <p className="mb-2 font-semibold text-[var(--pill-warning-ink)]">احفظ رموز الاسترداد الآن — لن تظهر مرة أخرى</p>
            <ul className="grid grid-cols-2 gap-1.5 text-[14px]" dir="ltr">
              {recoveryCodes.map((c) => <li key={c} className="num rounded bg-surface px-2 py-1 text-center">{c}</li>)}
            </ul>
          </div>
        )}

        {!twoFactorEnabled && !setup && (
          <form onSubmit={(e) => { e.preventDefault(); start.post('/account/security/two-factor', { onSuccess: () => start.reset() }); }} className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            <TextField label="كلمة المرور الحالية للتأكيد" type="password" ltr value={start.data.password} onChange={(e) => start.setData('password', e.target.value)} error={start.errors.password} autoComplete="current-password" />
            <Button variant="primary" type="submit" loading={start.processing}>بدء التفعيل</Button>
          </form>
        )}

        {!twoFactorEnabled && setup && (
          <div className="grid gap-5 sm:grid-cols-[200px_1fr]">
            <div className="rounded-lg bg-white p-2" dangerouslySetInnerHTML={{ __html: setup.qr }} aria-label="رمز QR لإضافة الحساب في تطبيق المصادقة" role="img" />
            <form onSubmit={(e) => { e.preventDefault(); confirm.post('/account/security/two-factor/confirm'); }} className="space-y-3">
              <p className="text-[13.5px] text-muted">امسح الرمز بتطبيق المصادقة، أو أدخل المفتاح يدويًا:</p>
              <p dir="ltr" className="num break-all rounded bg-surface px-2 py-1.5 text-[13px]">{setup.secret}</p>
              <TextField label="الرمز المكوّن من 6 أرقام" ltr inputMode="numeric" maxLength={6} value={confirm.data.code} onChange={(e) => confirm.setData('code', e.target.value.replace(/\D/g, ''))} error={confirm.errors.code} autoComplete="one-time-code" />
              <Button variant="primary" type="submit" loading={confirm.processing}>تأكيد التفعيل</Button>
            </form>
          </div>
        )}

        {twoFactorEnabled && (
          <div className="grid gap-4 sm:grid-cols-2">
            <form onSubmit={(e) => { e.preventDefault(); recovery.post('/account/security/two-factor/recovery-codes', { onSuccess: () => recovery.reset() }); }} className="space-y-2">
              <TextField label="كلمة المرور لتوليد رموز جديدة" type="password" ltr value={recovery.data.password} onChange={(e) => recovery.setData('password', e.target.value)} error={recovery.errors.password} autoComplete="current-password" />
              <Button type="submit" loading={recovery.processing}>توليد رموز استرداد جديدة</Button>
            </form>
            {!mfaRequired && (
              <form onSubmit={(e) => { e.preventDefault(); disable.delete('/account/security/two-factor', { onSuccess: () => disable.reset() }); }} className="space-y-2">
                <TextField label="كلمة المرور لإلغاء المصادقة الثنائية" type="password" ltr value={disable.data.password} onChange={(e) => disable.setData('password', e.target.value)} error={disable.errors.password} autoComplete="current-password" />
                <Button variant="danger" type="submit" loading={disable.processing}>إلغاء المصادقة الثنائية</Button>
              </form>
            )}
          </div>
        )}
      </Section>

      <Section title="كلمة المرور" description="تغيير كلمة المرور يُنهي جلساتك على الأجهزة الأخرى.">
        <form onSubmit={(e) => { e.preventDefault(); pwd.put('/account/security/password', { onSuccess: () => pwd.reset() }); }} className="grid gap-4 sm:grid-cols-3">
          <TextField label="الحالية" type="password" ltr value={pwd.data.current_password} onChange={(e) => pwd.setData('current_password', e.target.value)} error={pwd.errors.current_password} autoComplete="current-password" />
          <TextField label="الجديدة" type="password" ltr value={pwd.data.password} onChange={(e) => pwd.setData('password', e.target.value)} error={pwd.errors.password} autoComplete="new-password" hint="12 حرفًا على الأقل، حروف وأرقام." />
          <TextField label="تأكيد الجديدة" type="password" ltr value={pwd.data.password_confirmation} onChange={(e) => pwd.setData('password_confirmation', e.target.value)} autoComplete="new-password" />
          <div className="sm:col-span-3"><Button variant="primary" type="submit" loading={pwd.processing}>حفظ كلمة المرور</Button></div>
        </form>
      </Section>

      <Section title="الجلسات" description="إنهاء جلساتك على الأجهزة الأخرى مع إبقاء هذه الجلسة.">
        <form onSubmit={(e) => { e.preventDefault(); others.post('/account/security/sessions/logout-others', { onSuccess: () => others.reset() }); }} className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
          <TextField label="كلمة المرور للتأكيد" type="password" ltr value={others.data.password} onChange={(e) => others.setData('password', e.target.value)} error={others.errors.password} autoComplete="current-password" />
          <Button type="submit" loading={others.processing}>إنهاء الجلسات الأخرى</Button>
        </form>
      </Section>
    </div>
  );
}

Security.layout = (page: ReactNode) => <RoleLayout>{page}</RoleLayout>;

function RoleLayout({ children }: { children: ReactNode }) {
  const { auth } = usePage<SharedProps>().props;
  return auth?.user.role === 'lawyer' ? <OfficeLayout>{children}</OfficeLayout> : <PortalLayout>{children}</PortalLayout>;
}
