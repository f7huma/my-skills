import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button, TextField } from '@/Components/ui';

export default function TwoFactorChallenge() {
  const [recovery, setRecovery] = useState(false);
  const form = useForm({ code: '', recovery_code: '' });
  return (
    <AuthLayout title="التحقق بخطوتين">
      <Head title="التحقق بخطوتين" />
      <form onSubmit={(e) => { e.preventDefault(); form.post('/two-factor'); }} className="space-y-4">
        {recovery ? (
          <TextField key="r" label="رمز الاسترداد" ltr autoComplete="one-time-code" value={form.data.recovery_code} onChange={(e) => form.setData('recovery_code', e.target.value)} error={form.errors.code} autoFocus hint="أحد رموز الاسترداد المحفوظة، ويُستخدم مرة واحدة." />
        ) : (
          <TextField key="c" label="رمز التحقق من تطبيق المصادقة" ltr inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={form.data.code} onChange={(e) => form.setData('code', e.target.value.replace(/\D/g, ''))} error={form.errors.code} autoFocus />
        )}
        <Button variant="primary" type="submit" className="w-full" loading={form.processing}>تحقق</Button>
        <button type="button" className="btn btn-ghost w-full" onClick={() => { setRecovery(!recovery); form.reset(); form.clearErrors(); }}>
          {recovery ? 'استخدام رمز التطبيق' : 'استخدام رمز استرداد'}
        </button>
      </form>
    </AuthLayout>
  );
}
