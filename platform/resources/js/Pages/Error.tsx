import { Head, Link } from '@inertiajs/react';

const messages: Record<number, [string, string]> = {
  403: ['لا تملك صلاحية لهذه الصفحة', 'إذا كنت تعتقد أن هذا خطأ فتواصل مع المكتب.'],
  404: ['الصفحة غير موجودة', 'قد يكون الرابط قديمًا أو لا يخص حسابك.'],
  419: ['انتهت صلاحية الصفحة', 'حدّث الصفحة وأعد المحاولة.'],
  429: ['محاولات كثيرة', 'انتظر قليلًا ثم أعد المحاولة.'],
  500: ['حدث خطأ غير متوقع', 'سُجّل الخطأ. أعد المحاولة بعد قليل.'],
  503: ['المنصة في صيانة', 'نعود خلال وقت قصير.'],
};

export default function ErrorPage({ status }: { status: number }) {
  const [title, body] = messages[status] ?? messages[500];
  return (
    <div className="flex min-h-dvh items-center justify-center bg-chrome px-4">
      <Head title={title} />
      <main className="sheet w-full max-w-md px-8 py-10 text-center">
        <img src="/brand/logo-mark.webp" alt="" className="mx-auto mb-5 h-14 w-auto" />
        <p className="text-[13px] text-muted"><bdi className="num">{status}</bdi></p>
        <h1 className="mt-1 text-[20px] font-bold">{title}</h1>
        <p className="mt-2 text-[14px] text-muted">{body}</p>
        <Link href="/" className="btn btn-primary mt-6">العودة للرئيسية</Link>
      </main>
    </div>
  );
}
