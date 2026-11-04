import { Head, Link, router, usePage } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';
import { Button, caseTone, claimTone, EmptyState, Money, Num, Pill } from '@/Components/ui';
import { relative } from '@/lib/format';
import type { SharedProps } from '@/types';

type CaseRow = { id: number; reference: string; title: string; stage: string | null; status: string | null; statusKey: string; caseNumber: string | null; clientSummary: string | null; lastUpdate: string | null };
type ExecRow = { id: number; reference: string; requestNumber: string | null; type: string; instrument: string; status: string; statusKey: string; clientSummary: string | null; lastUpdate: string | null };
type ClaimRow = { id: number; reference: string | null; status: string; statusKey: string; opponentName: string | null; amount: string | null; currency: string };

export default function Home({ cases, executions, totals, claims, canView, canSubmit }: {
  cases: CaseRow[]; executions: ExecRow[]; totals: { received: Record<string, string>; agreed: Record<string, string>; claimed: Record<string, string> };
  claims: ClaimRow[]; canView: boolean; canSubmit: boolean;
}) {
  const { auth } = usePage<SharedProps>().props;
  const received = Object.entries(totals.received);
  const latest = [...cases, ...executions].filter((x) => x.lastUpdate).sort((a, b) => (b.lastUpdate! > a.lastUpdate! ? 1 : -1))[0];

  return (
    <>
      <Head title="ملفاتي" />
      <header className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-[22px] font-bold sm:text-[24px]">ملفاتك لدى المكتب</h1>
          <p className="mt-1 text-[14px] text-muted">
            {auth?.user.name} · <Num>{cases.length}</Num> قضية · <Num>{executions.length}</Num> طلب تنفيذ
            {latest?.lastUpdate && <> · آخر تحديث {relative(latest.lastUpdate)}</>}
          </p>
        </div>
        {canSubmit && <Button variant="primary" onClick={() => router.post('/portal/claims')}><FilePlus2 className="size-4" aria-hidden />إدراج مطالبة جديدة</Button>}
      </header>

      {canView && (
        <section aria-labelledby="money-title" className="mb-8 grid gap-px overflow-hidden rounded-xl bg-line sm:grid-cols-3">
          <h2 id="money-title" className="sr-only">المبالغ</h2>
          <div className="bg-surface px-5 py-4 sm:col-span-1">
            <p className="text-[13px] text-muted">المحصّل فعليًا</p>
            {received.length ? received.map(([c, v]) => <p key={c} className="mt-1 text-[26px] font-bold leading-tight text-accent"><Money value={v} currency={c} /></p>)
              : <p className="mt-1 text-[26px] font-bold leading-tight text-faint"><Num>0.00</Num></p>}
          </div>
          <div className="bg-surface px-5 py-4">
            <p className="text-[13px] text-muted">المتفق عليه صلحًا</p>
            {Object.entries(totals.agreed).length ? Object.entries(totals.agreed).map(([c, v]) => <p key={c} className="mt-1 text-[18px] font-semibold"><Money value={v} currency={c} /></p>) : <p className="mt-1 text-[18px] text-faint">—</p>}
          </div>
          <div className="bg-surface px-5 py-4">
            <p className="text-[13px] text-muted">المبالغ المطالب بها</p>
            {Object.entries(totals.claimed).length ? Object.entries(totals.claimed).map(([c, v]) => <p key={c} className="mt-1 text-[18px] font-semibold"><Money value={v} currency={c} /></p>) : <p className="mt-1 text-[18px] text-faint">—</p>}
          </div>
        </section>
      )}

      {canView && (
        <section aria-labelledby="cases-title" className="mb-8">
          <h2 id="cases-title" className="mb-3 text-[17px] font-semibold">القضايا</h2>
          {cases.length === 0 ? <EmptyState title="لا توجد قضايا ظاهرة بعد">تظهر هنا القضايا التي يفتحها المكتب لك.</EmptyState> : (
            <ul className="divide-y divide-rule rounded-xl border border-line">
              {cases.map((c) => (
                <li key={c.id}>
                  <Link href={`/portal/cases/${c.id}`} className="block px-4 py-4 transition-colors hover:bg-raised sm:px-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="font-semibold text-text">{c.title}</p>
                      <Pill tone={caseTone(c.statusKey)}>{c.status}</Pill>
                    </div>
                    <p className="mt-0.5 text-[13px] text-muted">
                      {c.stage}{c.caseNumber && <> · رقم القضية <Num>{c.caseNumber}</Num></>} · مرجع المكتب <Num>{c.reference}</Num>
                    </p>
                    {c.clientSummary && <p className="mt-2 text-[14px] leading-7 text-text">{c.clientSummary}</p>}
                    {c.lastUpdate && <p className="mt-1 text-[12.5px] text-muted">آخر تحديث {relative(c.lastUpdate)}</p>}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      {canView && (
        <section aria-labelledby="exec-title" className="mb-8">
          <h2 id="exec-title" className="mb-3 text-[17px] font-semibold">طلبات التنفيذ</h2>
          {executions.length === 0 ? <EmptyState title="لا توجد طلبات تنفيذ">تظهر هنا طلبات التنفيذ وحالاتها والمبالغ المحصلة.</EmptyState> : (
            <ul className="divide-y divide-rule rounded-xl border border-line">
              {executions.map((e) => (
                <li key={e.id}>
                  <Link href={`/portal/executions/${e.id}`} className="block px-4 py-4 transition-colors hover:bg-raised sm:px-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="font-semibold">{e.type} — {e.instrument}</p>
                      <Pill tone="info">{e.status}</Pill>
                    </div>
                    <p className="mt-0.5 text-[13px] text-muted">{e.requestNumber ? <>رقم الطلب <Num>{e.requestNumber}</Num> · </> : null}مرجع المكتب <Num>{e.reference}</Num></p>
                    {e.clientSummary && <p className="mt-2 text-[14px] leading-7">{e.clientSummary}</p>}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      {canSubmit && (
        <section aria-labelledby="claims-title">
          <div className="mb-3 flex items-center justify-between">
            <h2 id="claims-title" className="text-[17px] font-semibold">آخر المطالبات</h2>
            <Link href="/portal/claims" className="text-[13px] text-muted hover:text-text">كل المطالبات</Link>
          </div>
          {claims.length === 0 ? <EmptyState title="لم ترسل مطالبات بعد" action={<Button onClick={() => router.post('/portal/claims')}>إدراج مطالبة</Button>} /> : (
            <ul className="divide-y divide-rule rounded-xl border border-line">
              {claims.map((c) => (
                <li key={c.id}>
                  <Link href={`/portal/claims/${c.id}`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-raised sm:px-5">
                    <span className="min-w-0">
                      <span className="block font-medium">{c.opponentName || 'مسودة بلا طرف بعد'}</span>
                      <span className="text-[12.5px] text-muted">{c.reference ? <Num>{c.reference}</Num> : 'لم تُرسل'}</span>
                    </span>
                    <span className="flex items-center gap-3"><Money value={c.amount} currency={c.currency} /><Pill tone={claimTone(c.statusKey)}>{c.status}</Pill></span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}
      {!canView && !canSubmit && <EmptyState title="لا صلاحيات مفعّلة على حسابك">تواصل مع المكتب لتفعيل صلاحية المتابعة.</EmptyState>}
    </>
  );
}
