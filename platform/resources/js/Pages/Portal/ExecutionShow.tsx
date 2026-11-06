import { Head, Link } from '@inertiajs/react';
import { DocumentList, KeyValues, Money, Num, Pill, Timeline, DateText } from '@/Components/ui';
import { relative } from '@/lib/format';
import type { Doc } from '@/types';

type E = {
  id: number; reference: string; requestNumber: string | null; type: string; instrument: string; status: string; clientSummary: string | null; lastUpdate: string | null;
  amountClaimed: string | null; currency: string; submittedOn: string | null; acceptedOn: string | null; orderIssuedOn: string | null;
  timeline: { id: number; title: string; details: string | null; at: string }[]; documents: Doc[];
  collections: { kind: string; kindKey: string; source: string; amount: string; currency: string; on: string }[];
};

export default function ExecutionShow({ execution: e }: { execution: E }) {
  const received = e.collections.filter((c) => c.kindKey === 'received');
  return (
    <>
      <Head title={'طلب تنفيذ ' + (e.requestNumber ?? e.reference)} />
      <nav aria-label="مسار التنقل" className="mb-2 text-[13px] text-muted"><Link href="/portal" className="hover:text-text">ملفاتي</Link> / طلب تنفيذ</nav>
      <header className="mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-[22px] font-bold">{e.type} — {e.instrument}</h1>
          <Pill tone="info">{e.status}</Pill>
        </div>
        <p className="mt-1 text-[14px] text-muted">{e.requestNumber && <>رقم الطلب <Num>{e.requestNumber}</Num> · </>}مرجع المكتب <Num>{e.reference}</Num></p>
      </header>
      <section className="mb-7 rounded-xl bg-accent-soft px-5 py-4" aria-labelledby="upd">
        <h2 id="upd" className="text-[13.5px] font-semibold text-ink">آخر تحديث من المكتب{e.lastUpdate && <span className="font-normal"> · {relative(e.lastUpdate)}</span>}</h2>
        <p className="mt-1 text-[15px] leading-8">{e.clientSummary || 'لا يوجد تحديث منشور بعد.'}</p>
      </section>
      <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div className="space-y-8">
          <KeyValues items={[
            ['المبلغ المطلوب', e.amountClaimed && <Money value={e.amountClaimed} currency={e.currency} />],
            ['تاريخ التقديم', e.submittedOn && <DateText value={e.submittedOn} />],
            ['تاريخ القبول', e.acceptedOn && <DateText value={e.acceptedOn} />],
            ['صدور أمر التنفيذ', e.orderIssuedOn && <DateText value={e.orderIssuedOn} />],
          ]} />
          <section aria-labelledby="tl"><h2 id="tl" className="mb-3 text-[16px] font-semibold">المسار</h2><Timeline events={e.timeline} /></section>
        </div>
        <aside className="space-y-8">
          <section aria-labelledby="col">
            <h2 id="col" className="mb-3 text-[16px] font-semibold">المحصّل</h2>
            {received.length === 0 ? <p className="text-[13.5px] text-muted">لم يُحصّل مبلغ بعد.</p> : (
              <ul className="divide-y divide-rule">{received.map((m, i) => (
                <li key={i} className="flex items-center justify-between py-2"><span className="text-[13px] text-muted"><DateText value={m.on} /></span><Money value={m.amount} currency={m.currency} className="font-semibold text-accent" /></li>
              ))}</ul>
            )}
          </section>
          <section aria-labelledby="docs"><h2 id="docs" className="mb-2 text-[16px] font-semibold">المستندات المشاركة</h2><DocumentList docs={e.documents} empty="لا مستندات مشاركة." /></section>
        </aside>
      </div>
    </>
  );
}
