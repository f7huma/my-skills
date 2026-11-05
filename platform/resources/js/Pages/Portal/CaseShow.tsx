import { Head, Link } from '@inertiajs/react';
import { caseTone, DocumentList, KeyValues, Money, Num, Pill, Timeline, DateText } from '@/Components/ui';
import { dateTime, relative } from '@/lib/format';
import type { Doc } from '@/types';

type C = {
  id: number; reference: string; title: string; stage: string | null; status: string | null; statusKey: string; clientRole: string | null; caseNumber: string | null;
  clientSummary: string | null; lastUpdate: string | null; outcome: string | null; court: string | null; circuit: string | null; filingStatus: string | null;
  nextHearing: { kind: string; at: string } | null; judgment: { outcome: string | null; pronouncedOn: string | null; deedIssued: boolean; final: boolean } | null;
  timeline: { id: number; title: string; details: string | null; at: string }[]; documents: Doc[];
  collections: { kind: string; kindKey: string; source: string; amount: string; currency: string; on: string }[];
};

export default function CaseShow({ case: c }: { case: C }) {
  return (
    <>
      <Head title={c.title} />
      <nav aria-label="مسار التنقل" className="mb-2 text-[13px] text-muted"><Link href="/portal" className="hover:text-text">ملفاتي</Link> / القضية</nav>
      <header className="mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-[22px] font-bold">{c.title}</h1>
          <Pill tone={caseTone(c.statusKey)}>{c.status}</Pill>
        </div>
        <p className="mt-1 text-[14px] text-muted">{c.stage} · مرجع المكتب <Num>{c.reference}</Num></p>
      </header>

      <section aria-labelledby="update" className="mb-7 rounded-xl bg-accent-soft px-5 py-4">
        <h2 id="update" className="text-[13.5px] font-semibold text-[#0f4640]">آخر تحديث من المكتب{c.lastUpdate && <span className="font-normal"> · {relative(c.lastUpdate)}</span>}</h2>
        <p className="mt-1 text-[15px] leading-8 text-text">{c.clientSummary || 'لا يوجد تحديث منشور بعد.'}</p>
      </section>

      <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div className="space-y-8">
          <section aria-labelledby="facts">
            <h2 id="facts" className="mb-3 text-[16px] font-semibold">بيانات القضية</h2>
            <KeyValues items={[
              ['رقم القضية', c.caseNumber && <Num>{c.caseNumber}</Num>],
              ['صفتك في الدعوى', c.clientRole],
              ['المحكمة', c.court],
              ['الدائرة', c.circuit],
              ['حالة طلب التقييد', c.filingStatus],
              ['الجلسة القادمة', c.nextHearing && <>{c.nextHearing.kind} — <DateText value={c.nextHearing.at} time /></>],
              ['النتيجة', c.judgment?.outcome ?? c.outcome],
              ['تاريخ النطق بالحكم', c.judgment?.pronouncedOn && <DateText value={c.judgment.pronouncedOn} />],
              ['صك الحكم', c.judgment ? (c.judgment.deedIssued ? 'صدر' : 'بانتظار صدوره') : null],
              ['النهائية', c.judgment?.final ? 'الحكم نهائي' : null],
            ]} />
          </section>
          <section aria-labelledby="tl">
            <h2 id="tl" className="mb-3 text-[16px] font-semibold">مسار القضية</h2>
            <Timeline events={c.timeline} />
          </section>
        </div>
        <aside className="space-y-8">
          <section aria-labelledby="money">
            <h2 id="money" className="mb-3 text-[16px] font-semibold">المبالغ</h2>
            {c.collections.length === 0 ? <p className="text-[13.5px] text-muted">لا مبالغ مسجلة.</p> : (
              <ul className="divide-y divide-rule">
                {c.collections.map((m, i) => (
                  <li key={i} className="flex items-center justify-between py-2">
                    <span><span className="block text-[14px]">{m.kind}</span><span className="text-[12px] text-muted">{m.source} · <DateText value={m.on} /></span></span>
                    <Money value={m.amount} currency={m.currency} className={m.kindKey === 'received' ? 'font-semibold text-accent' : 'font-medium'} />
                  </li>
                ))}
              </ul>
            )}
          </section>
          <section aria-labelledby="docs">
            <h2 id="docs" className="mb-2 text-[16px] font-semibold">المستندات المشاركة</h2>
            <DocumentList docs={c.documents} empty="لم يشارك المكتب مستندات بعد." />
          </section>
        </aside>
      </div>
    </>
  );
}
