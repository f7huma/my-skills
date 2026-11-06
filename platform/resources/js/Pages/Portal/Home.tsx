import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowLeft, FilePlus2 } from 'lucide-react';
import { Button, caseTone, claimTone, EmptyState, Money, Num, Pill } from '@/Components/ui';
import { CountUp, Meter } from '@/Components/motion';
import { StageDots, stageKeyFromLabel } from '@/Components/StagePath';
import { CourtScene, DiamondRule } from '@/Components/LegalArt';
import { IconCase, IconClaims, IconClosed, IconExecution, IconFiling, IconHearing, IconIntake, IconJudgment, IconObjection, IconSettlement } from '@/Components/Icons';
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
  const recent = (iso: string | null) => !!iso && Date.now() - Date.parse(iso) < 7 * 86400000;
  const latest = [...cases, ...executions].filter((x) => x.lastUpdate).sort((a, b) => (b.lastUpdate! > a.lastUpdate! ? 1 : -1))[0];

  const stageIcon: Record<string, (p: { size?: number; className?: string }) => ReactNode> = {
    intake: IconIntake, reconciliation: IconSettlement, filing: IconFiling, court: IconHearing, judgment: IconJudgment, objection: IconObjection, execution: IconExecution, closed: IconClosed,
  };
  const latestHref = latest ? ('caseNumber' in latest ? `/portal/cases/${latest.id}` : `/portal/executions/${latest.id}`) : null;
  const latestTitle = latest ? ('title' in latest ? latest.title : `${(latest as ExecRow).type} — ${(latest as ExecRow).instrument}`) : null;

  return (
    <div className="stagger">
      <Head title="ملفاتي" />
      {/* الواجهة: ترحيب وأرقام تعدّ، وبجانبها مشهد محكمة وميزان يستقر */}
      <section className="mist relative mb-6 overflow-hidden rounded-[22px] px-5 pb-6 pt-6 sm:px-8" aria-labelledby="portal-title">
        <CourtScene className="pointer-events-none absolute -bottom-4 end-[-20px] hidden h-[340px] w-[453px] md:block" />
        <div className="relative max-w-[640px]">
          <h1 id="portal-title" className="text-[26px] font-semibold leading-tight text-ink sm:text-[32px]">ملفاتك بين أيدٍ أمينة</h1>
          <p className="mt-1.5 text-[14px] text-muted">أهلًا بك، {auth?.user.name} · {latest?.lastUpdate ? <>آخر تحديث من المكتب {relative(latest.lastUpdate)}</> : 'نتابع ملفاتك ونحدّثك بكل جديد'}</p>
          {canSubmit && <Button variant="primary" className="mt-4" onClick={() => router.post('/portal/claims')}><FilePlus2 className="size-4" aria-hidden />إدراج مطالبة جديدة</Button>}
        </div>
        {canView && (
          <dl className="relative mt-6 grid max-w-[720px] grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4">
            <div className="border-s-2 border-ink/20 ps-4"><dt className="flex items-center gap-1.5 text-[12.5px] text-muted"><IconCase size={16} />القضايا</dt><dd className="mt-1 text-[36px] font-semibold leading-none text-ink"><CountUp value={cases.length} /></dd></div>
            <div className="border-s-2 border-ink/20 ps-4"><dt className="flex items-center gap-1.5 text-[12.5px] text-muted"><IconExecution size={16} />طلبات التنفيذ</dt><dd className="mt-1 text-[36px] font-semibold leading-none text-ink"><CountUp value={executions.length} /></dd></div>
            {(received.length ? received : [['SAR', '0']]).slice(0, 2).map(([c, v]) => {
              const claimed = Number(totals.claimed[c] ?? 0);
              return (
                <div key={c} className="col-span-2 border-s-2 border-positive/40 ps-4">
                  <dt className="text-[12.5px] text-muted">المحصّل فعليًا · {c}</dt>
                  <dd className="mt-1 min-w-0 text-[clamp(26px,7.5vw,36px)] font-semibold leading-none text-positive"><CountUp value={Number(v)} decimals={2} /></dd>
                  {claimed > 0 && (
                    <div className="mt-3 max-w-[340px]">
                      <div className="meter-grow"><Meter value={Number(v)} max={claimed} tone="positive" label={`المحصّل من المطالب به (${c})`} /></div>
                      <p className="mt-1.5 text-[12px] text-muted">{Math.min(100, Math.round((Number(v) / claimed) * 100))}٪ من <bdi className="num">{new Intl.NumberFormat('en-US').format(claimed)}</bdi> مطالب بها</p>
                    </div>
                  )}
                </div>
              );
            })}
          </dl>
        )}
      </section>

      {/* آخر المستجدات: أبرز ما تغيّر في ملفاتك */}
      {canView && latest && latestHref && (
        <Link href={latestHref} className="lift group mb-6 flex items-start gap-4 rounded-[18px] border border-line bg-surface px-5 py-4 sm:px-6">
          <span className="relative mt-1 grid size-11 shrink-0 place-items-center rounded-[12px] bg-ink text-white">
            <IconCase size={22} />
            {recent(latest.lastUpdate) && <span className="pulse-dot absolute -end-1 -top-1 size-3 rounded-full bg-positive ring-2 ring-white" aria-hidden />}
          </span>
          <span className="min-w-0 flex-1">
            <span className="flex flex-wrap items-center gap-2 text-[12.5px] text-muted">آخر المستجدات{recent(latest.lastUpdate) && <span className="pill pill-positive">جديد</span>}<span>· {relative(latest.lastUpdate)}</span></span>
            <span className="mt-0.5 block font-semibold text-ink">{latestTitle}</span>
            <span className="mt-1 line-clamp-2 block text-[14px] leading-7 text-text">{latest.clientSummary || 'تم تحديث ملفك، افتحه للاطلاع.'}</span>
          </span>
          <ArrowLeft className="mt-2 size-5 shrink-0 text-muted transition-transform duration-200 group-hover:-translate-x-1" aria-hidden />
        </Link>
      )}

      {canView && Object.entries(totals.agreed).length > 0 && (
        <section aria-label="المتفق عليه صلحًا" className="card mb-6 flex flex-wrap items-center justify-between gap-3 px-5 py-4">
          <p className="flex items-center gap-2 text-[13.5px] text-muted"><IconSettlement size={18} />المتفق عليه صلحًا</p>
          <p className="flex flex-wrap gap-4 text-[18px] font-semibold">{Object.entries(totals.agreed).map(([c, v]) => <Money key={c} value={v} currency={c} />)}</p>
        </section>
      )}

      {canView && (
        <section aria-labelledby="cases-title" className="mb-8">
          <h2 id="cases-title" className="mb-1 flex items-center gap-2 text-[18px] font-semibold"><IconCase size={20} />قضاياك</h2>
          <DiamondRule className="mb-4 max-w-[220px]" />
          {cases.length === 0 ? <EmptyState title="لا توجد قضايا ظاهرة بعد" icon={<IconCase size={26} />}>تظهر هنا القضايا التي يفتحها المكتب لك.</EmptyState> : (
            <ul className="stagger grid gap-3 md:grid-cols-2">
              {cases.map((c) => {
                const key = stageKeyFromLabel(c.stage);
                const SI = key ? stageIcon[key] : IconCase;
                return (
                  <li key={c.id}>
                    <Link href={`/portal/cases/${c.id}`} className="lift flex h-full flex-col rounded-[18px] border border-line bg-surface px-5 py-4">
                      <div className="flex items-start gap-3">
                        <span className="grid size-10 shrink-0 rotate-45 place-items-center rounded-[10px] bg-mist text-ink"><span className="-rotate-45"><SI size={19} /></span></span>
                        <div className="min-w-0 flex-1">
                          <p className="font-semibold leading-snug text-text">{c.title}</p>
                          <p className="mt-0.5 text-[12.5px] text-muted">{c.stage}{c.caseNumber && <> · رقم القضية <Num>{c.caseNumber}</Num></>}</p>
                        </div>
                        <Pill tone={caseTone(c.statusKey)}>{c.status}</Pill>
                      </div>
                      <div className="mt-3"><StageDots current={key} /></div>
                      {c.clientSummary && <p className="mt-2.5 line-clamp-2 text-[13.5px] leading-7 text-text">{c.clientSummary}</p>}
                      <p className="mt-auto flex items-center justify-between pt-3 text-[12.5px] text-muted">
                        <span>{c.lastUpdate ? <>آخر تحديث {relative(c.lastUpdate)}</> : 'لا تحديث منشور بعد'}</span>
                        <span className="flex items-center gap-1 font-medium text-ink">التفاصيل<ArrowLeft className="size-4" aria-hidden /></span>
                      </p>
                    </Link>
                  </li>
                );
              })}
            </ul>
          )}
        </section>
      )}

      {canView && (
        <section aria-labelledby="exec-title" className="mb-8">
          <h2 id="exec-title" className="mb-1 flex items-center gap-2 text-[18px] font-semibold"><IconExecution size={20} />طلبات التنفيذ</h2>
          <DiamondRule className="mb-4 max-w-[220px]" />
          {executions.length === 0 ? <EmptyState title="لا توجد طلبات تنفيذ" icon={<IconExecution size={26} />}>تظهر هنا طلبات التنفيذ وحالاتها والمبالغ المحصلة.</EmptyState> : (
            <ul className="stagger grid gap-3 md:grid-cols-2">
              {executions.map((e) => (
                <li key={e.id}>
                  <Link href={`/portal/executions/${e.id}`} className="lift flex h-full flex-col rounded-[18px] border border-line bg-surface px-5 py-4">
                    <div className="flex items-start gap-3">
                      <span className="grid size-10 shrink-0 rotate-45 place-items-center rounded-[10px] bg-mist text-ink"><span className="-rotate-45"><IconExecution size={19} /></span></span>
                      <div className="min-w-0 flex-1">
                        <p className="font-semibold leading-snug">{e.type} — {e.instrument}</p>
                        {e.requestNumber && <p className="mt-0.5 text-[12.5px] text-muted">رقم الطلب <Num>{e.requestNumber}</Num></p>}
                      </div>
                      <Pill tone="info">{e.status}</Pill>
                    </div>
                    {e.clientSummary && <p className="mt-2.5 line-clamp-2 text-[13.5px] leading-7">{e.clientSummary}</p>}
                    <p className="mt-auto flex items-center justify-end gap-1 pt-3 text-[12.5px] font-medium text-ink">التفاصيل<ArrowLeft className="size-4" aria-hidden /></p>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      {canSubmit && (
        <section aria-labelledby="claims-title">
          <div className="mb-1 flex items-center justify-between">
            <h2 id="claims-title" className="flex items-center gap-2 text-[18px] font-semibold"><IconClaims size={20} />آخر المطالبات</h2>
            <Link href="/portal/claims" className="text-[13px] text-muted hover:text-text">كل المطالبات</Link>
          </div>
          <DiamondRule className="mb-4 max-w-[220px]" />
          {claims.length === 0 ? <EmptyState title="لم ترسل مطالبات بعد" icon={<IconClaims size={26} />} action={<Button onClick={() => router.post('/portal/claims')}>إدراج مطالبة</Button>} /> : (
            <ul className="card divide-y divide-rule overflow-hidden">
              {claims.map((c) => (
                <li key={c.id}>
                  <Link href={`/portal/claims/${c.id}`} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-150 hover:bg-raised active:bg-active sm:px-5">
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
    </div>
  );
}
