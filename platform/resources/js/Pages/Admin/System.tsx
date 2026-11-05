import { Head, Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button, cx, EmptyState, Num, PageHeader, Panel, Pill, DateText } from '@/Components/ui';
import { dateTime, fileSize } from '@/lib/format';

type Check = { key: string; label: string; status: string; message: string; checkedAt: string | null };
const tone = (s: string) => (s === 'ok' ? 'positive' : s === 'warn' ? 'warning' : s === 'fail' ? 'negative' : 'neutral');
const word: Record<string, string> = { ok: 'سليم', warn: 'تنبيه', fail: 'إخفاق', unknown: 'لم يُفحص' };

export default function System({ checks, failures, backups, environment, appUrl, scannerDriver, mfaRequired }: {
  checks: Check[]; failures: { check: string; message: string; at: string }[]; backups: { name: string; status: string; size: number | null; files: number | null; offsite: boolean; startedAt: string; verifiedAt: string | null; error: string | null }[];
  environment: string; appUrl: string; scannerDriver: string; mfaRequired: boolean;
}) {
  const [busy, setBusy] = useState(false);
  const failing = checks.filter((c) => c.status === 'fail').length;
  return (
    <>
      <Head title="حالة النظام" />
      <PageHeader title="حالة النظام" subtitle={failing ? <span className="font-medium text-negative">{failing} فحص فاشل يحتاج متابعة.</span> : 'تُشغَّل الفحوص كل ساعة آليًا.'}
        actions={<>
          <Link href="/office/admin/audit" className="btn btn-secondary btn-sm">سجل التدقيق</Link>
          <Button size="sm" variant="primary" loading={busy} onClick={() => { setBusy(true); router.post('/office/admin/system/run-checks', {}, { preserveScroll: true, onFinish: () => setBusy(false) }); }}><RefreshCw className="size-4" aria-hidden />تشغيل الفحوص الآن</Button>
        </>} />
      <section aria-label="نتائج الفحوص" className="mb-6 grid gap-px overflow-hidden rounded-xl bg-line sm:grid-cols-2 xl:grid-cols-5">
        {checks.map((c) => (
          <div key={c.key} className="bg-surface p-4">
            <div className="flex items-center justify-between gap-2"><h2 className="font-semibold">{c.label}</h2><Pill tone={tone(c.status)}>{word[c.status] ?? c.status}</Pill></div>
            <p className="mt-1.5 text-[13.5px]">{c.message}</p>
            <p className="mt-1 text-[12px] text-muted">{c.checkedAt ? <DateText value={c.checkedAt} time /> : '—'}</p>
          </div>
        ))}
      </section>
      <div className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <Panel title="النسخ الاحتياطية" description="يومية، مشفرة، لقاعدة البيانات والمرفقات معًا، مع بيان سلامة. الاستعادة تُختبر على بيئة معزولة فقط.">
          {backups.length === 0 ? <EmptyState title="لا نسخ بعد">شغّل <code dir="ltr">php artisan backup:run --verify</code> أو انتظر الجدولة اليومية.</EmptyState> : (
            <div className="overflow-x-auto"><table className="table">
              <thead><tr><th>النسخة</th><th>الحالة</th><th className="end">الملفات</th><th className="end">الحجم</th><th>نسخة منفصلة</th></tr></thead>
              <tbody>{backups.map((b) => (
                <tr key={b.name}>
                  <td><DateText value={b.startedAt} time />{b.error && <span className="block text-[12px] text-negative">{b.error}</span>}</td>
                  <td><Pill tone={b.status === 'verified' ? 'positive' : b.status === 'failed' ? 'negative' : b.status === 'pruned' ? 'neutral' : 'info'}>{({ verified: 'متحقق منها', completed: 'مكتملة', failed: 'فشلت', running: 'جارية', pruned: 'حُذفت (احتفاظ)' } as Record<string, string>)[b.status] ?? b.status}</Pill></td>
                  <td className="end"><Num>{b.files ?? '—'}</Num></td>
                  <td className="end"><Num>{b.size ? fileSize(b.size) : '—'}</Num></td>
                  <td className={cx(b.offsite ? 'text-positive' : 'text-muted')}>{b.offsite ? 'نعم' : 'لا'}</td>
                </tr>
              ))}</tbody>
            </table></div>
          )}
        </Panel>
        <div className="grid grid-cols-1 content-start gap-4">
          <Panel title="الإعداد">
            <dl className="space-y-2 text-[13.5px]">
              <div className="flex justify-between gap-3"><dt className="text-muted">البيئة</dt><dd>{environment}</dd></div>
              <div className="flex justify-between gap-3"><dt className="text-muted">عنوان الموقع</dt><dd><Num>{appUrl}</Num></dd></div>
              <div className="flex justify-between gap-3"><dt className="text-muted">محرك فحص الملفات</dt><dd>{scannerDriver}</dd></div>
              <div className="flex justify-between gap-3"><dt className="text-muted">المصادقة الثنائية للمحامي</dt><dd>{mfaRequired ? 'إلزامية' : 'اختيارية في هذه البيئة'}</dd></div>
            </dl>
          </Panel>
          <Panel title="آخر الإخفاقات">
            {failures.length === 0 ? <p className="text-[13.5px] text-muted">لا إخفاقات مسجلة.</p> : (
              <ul className="divide-y divide-rule">{failures.map((f, i) => <li key={i} className="py-2 text-[13.5px]"><span className="font-medium">{f.check}</span>: {f.message} <span className="text-[12px] text-muted"><DateText value={f.at} time /></span></li>)}</ul>
            )}
          </Panel>
        </div>
      </div>
    </>
  );
}
