import { Head, Link } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';

export default function InvitationInvalid() {
  return (
    <AuthLayout title="الرابط غير صالح">
      <Head title="رابط غير صالح" />
      <p className="text-[14px] text-muted">انتهت صلاحية رابط الدعوة أو سبق استخدامه أو أُلغي. اطلب من المكتب إرسال رابط جديد.</p>
      <Link href="/login" className="btn btn-secondary mt-5 w-full">العودة لتسجيل الدخول</Link>
    </AuthLayout>
  );
}
