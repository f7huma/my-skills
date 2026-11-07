import { Head, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Checkbox, TextField } from '@/Components/ui';
import type { SharedProps } from '@/types';

export default function Login() {
  const { flash } = usePage<SharedProps>().props;
  const form = useForm({ email: '', password: '', remember: false });
  return (
    <AuthLayout title="تسجيل الدخول">
      <Head title="تسجيل الدخول" />
      {flash.status && <p className="mb-4 rounded-lg px-3 py-2 text-[13.5px] pill-info" style={{ height: 'auto' }}>{flash.status}</p>}
      <form onSubmit={(e) => { e.preventDefault(); form.post('/login', { onFinish: () => form.reset('password') }); }} className="space-y-4" noValidate>
        <TextField label="اسم المستخدم أو البريد الإلكتروني" type="text" dir="auto" autoComplete="username" autoCapitalize="none" autoCorrect="off" spellCheck={false} required value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} autoFocus />
        <TextField label="كلمة المرور" type="password" autoComplete="current-password" ltr required value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password} />
        <Checkbox label="تذكرني على هذا الجهاز" checked={form.data.remember} onChange={(v) => form.setData('remember', v)} />
        <Button variant="primary" type="submit" className="w-full" loading={form.processing}>دخول</Button>
      </form>
      <p className="mt-5 text-[12.5px] leading-6 text-muted">الحسابات تُنشأ بدعوة من المكتب فقط. إذا نسيت كلمة المرور فتواصل مع المكتب لإرسال رابط إعادة التعيين.</p>
    </AuthLayout>
  );
}
