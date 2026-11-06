import { Head, Link, router } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';
import { Button, claimTone, EmptyState, Money, Num, PageHeader, Pill } from '@/Components/ui';
import { relative } from '@/lib/format';

type Row = { id: number; reference: string | null; status: string; statusKey: string; opponentName: string | null; amount: string | null; currency: string; updatedAt: string; submittedAt: string | null };

export default function Index({ claims }: { claims: Row[] }) {
  return (
    <>
      <Head title="المطالبات" />
      <PageHeader title="المطالبات" subtitle="المطالبات القانونية المرسلة للمكتب وحالاتها." actions={<Button variant="primary" onClick={() => router.post('/portal/claims')}><FilePlus2 className="size-4" aria-hidden />مطالبة جديدة</Button>} />
      {claims.length === 0 ? <EmptyState title="لا توجد مطالبات">أنشئ مطالبة واحفظها كمسودة حتى تكتمل بياناتها ومرفقاتها.</EmptyState> : (
        <ul className="divide-y divide-rule rounded-xl border border-line">
          {claims.map((c) => (
            <li key={c.id}>
              <Link href={`/portal/claims/${c.id}`} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5 hover:bg-raised sm:px-5">
                <span className="min-w-0">
                  <span className="block font-medium">{c.opponentName || 'مسودة بلا طرف بعد'}</span>
                  <span className="text-[12.5px] text-muted">{c.reference ? 'مُرسلة' : 'مسودة'} · عُدلت {relative(c.updatedAt)}</span>
                </span>
                <span className="flex items-center gap-3"><Money value={c.amount} currency={c.currency} /><Pill tone={claimTone(c.statusKey)}>{c.status}</Pill></span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
