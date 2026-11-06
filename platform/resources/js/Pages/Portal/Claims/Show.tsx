import { Head, Link } from '@inertiajs/react';
import { ButtonLink, claimTone, DocumentList, KeyValues, Money, Num, Pill, DateText } from '@/Components/ui';
import { dateTime } from '@/lib/format';
import type { Doc } from '@/types';

type Claim = {
  id: number; reference: string | null; status: string; statusKey: string; clientName: string; opponentName: string | null; opponentType: string | null;
  opponentIdentifier: string | null; amount: string | null; currency: string; description: string | null; submittedAt: string | null; editable: boolean;
  messages: { id: number; kind: string; body: string; at: string }[]; documents: Doc[];
};

export default function Show({ claim }: { claim: Claim }) {
  return (
    <>
      <Head title={'مطالبة ' + (claim.opponentName ?? '')} />
      <nav aria-label="مسار التنقل" className="mb-2 text-[13px] text-muted"><Link href="/portal/claims" className="hover:text-text">المطالبات</Link> / {claim.opponentName ?? 'مطالبة'}</nav>
      <header className="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-3"><h1 className="text-[22px] font-bold">مطالبة ضد {claim.opponentName}</h1><Pill tone={claimTone(claim.statusKey)}>{claim.status}</Pill></div>
          <p className="mt-1 text-[14px] text-muted">{claim.submittedAt && <>أُرسلت <DateText value={claim.submittedAt} time /></>}</p>
        </div>
        {claim.editable && <ButtonLink href={`/portal/claims/${claim.id}/edit`} variant="primary">استكمال البيانات</ButtonLink>}
      </header>
      <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
        <div className="space-y-8">
          <KeyValues items={[
            ['اسم موكل المكتب', claim.clientName],
            ['اسم الطرف المطالب ضده', claim.opponentName],
            ['نوع الطرف', claim.opponentType === 'organization' ? 'منشأة' : claim.opponentType === 'individual' ? 'فرد' : null],
            ['رقم السجل أو الهوية', claim.opponentIdentifier && <Num>{claim.opponentIdentifier}</Num>],
            ['مبلغ المطالبة', <Money value={claim.amount} currency={claim.currency} />],
            ['الوصف', claim.description],
          ]} />
          <section aria-labelledby="docs"><h2 id="docs" className="mb-2 text-[16px] font-semibold">المرفقات</h2><DocumentList docs={claim.documents} /></section>
        </div>
        <section aria-labelledby="msgs">
          <h2 id="msgs" className="mb-3 text-[16px] font-semibold">رسائل المكتب</h2>
          {claim.messages.length === 0 ? <p className="text-[13.5px] text-muted">لا رسائل.</p> : (
            <ol className="space-y-3">
              {claim.messages.map((m) => (
                <li key={m.id} className={m.kind === 'completion_request' ? 'rounded-lg border border-[var(--warning-border)] bg-[var(--pill-warning-bg)] p-3' : 'well p-3'}>
                  <p className="text-[14px]">{m.body}</p>
                  <p className="mt-1 text-[12px] text-muted"><DateText value={m.at} time /></p>
                </li>
              ))}
            </ol>
          )}
        </section>
      </div>
    </>
  );
}
