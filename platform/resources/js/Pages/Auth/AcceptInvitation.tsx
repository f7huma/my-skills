import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button, TextField } from '@/Components/ui';

export default function AcceptInvitation({ token, invitation }: { token: string; invitation: { name: string; email: string; existingAccount: boolean; reset?: boolean } }) {
  const form = useForm({ username: '', password: '', password_confirmation: '' });
  const title = invitation.reset ? 'تعيين كلمة مرور جديدة' : invitation.existingAccount ? 'ربط الدعوة بحسابك' : 'إنشاء الحساب';
  return (
    <AuthLayout title={title}>
      <Head title={title} />
      <p className="mb-4 text-[14px] text-muted">مرحبًا {invitation.name}. الحساب: <bdi className="num">{invitation.email}</bdi></p>
      <form onSubmit={(e) => { e.preventDefault(); form.post(`/invitation/${token}`); }} className="space-y-4">
        {!invitation.reset && (
          <TextField label="اسم المستخدم (اختياري)" dir="auto" autoComplete="username" autoCapitalize="none" spellCheck={false} value={form.data.username}
            onChange={(e) => form.setData('username', e.target.value)} error={form.errors.username} maxLength={60}
            hint="تدخل به بدل البريد. يقبل الحروف والأرقام والمسافات، مثل: فيصل الحميضان" />
        )}
        <TextField label={invitation.existingAccount ? 'كلمة المرور الحالية' : 'كلمة المرور'} type="password" ltr autoComplete={invitation.existingAccount ? 'current-password' : 'new-password'}
          value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password}
          hint={invitation.existingAccount ? undefined : '4 أحرف أو أرقام على الأقل.'} required />
        <TextField label="تأكيد كلمة المرور" type="password" ltr autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} required />
        <Button variant="primary" type="submit" className="w-full" loading={form.processing}>متابعة</Button>
      </form>
      <p className="mt-4 text-[12.5px] text-muted">الرابط صالح لمرة واحدة ولمدة محدودة.</p>
    </AuthLayout>
  );
}
