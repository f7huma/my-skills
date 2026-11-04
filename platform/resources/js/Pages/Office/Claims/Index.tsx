import { Head, Link } from '@inertiajs/react';
import { claimTone, cx, EmptyState, Money, Num, PageHeader, Pagination, Pill } from '@/Components/ui';
import { relative } from '@/lib/format';
import type { Paginated } from '@/types';

type Row = { id: number; reference: string; client: string; opponent: string; amount: string | null; currency: string; status: string; statusLabel: string; missing: boolean; submittedAt: string };

export default function Index({ claims, status }: { claims: Paginated<Row>; status: string }) {
  return (
    <>
      <Head title="المطالبات الواردة" />
      <PageHeader title="المطالبات الواردة" subtitle="من بوابة العملاء — الأقدم أولًا في الانتظار." />
      <div className="mb-4 flex flex-wrap gap-1" role="navigation" aria-label="تصفية حسب الحالة">
        {[['open', 'بانتظار الإجراء'], ['converted_case', 'حُوّلت لقضية'], ['converted_execution', 'حُوّلت لتنفيذ'], ['closed', 'مغلقة'], ['all', 'الكل']].map(([v, l]) => (
          <Link key={v} href={`/office/claims?status=${v}`} className={cx('btn btn-sm', status === v ? 'btn-primary' : 'btn-ghost')} aria-current={status === v ? 'page' : undefined}>{l}</Link>
        ))}
      </div>
      {claims.data.length === 0 ? <EmptyState title="لا مطالبات في هذه القائمة" /> : (
        <ul className="divide-y divide-rule rounded-xl border border-line">{claims.data.map((c) => (
          <li key={c.id}>
            <Link href={`/office/claims/${c.id}`} className="grid gap-1 px-4 py-3 hover:bg-raised sm:grid-cols-[1fr_auto] sm:px-5">
              <span className="min-w-0">
                <span className="block font-medium">{c.client} <span className="font-normal text-muted">ضد {c.opponent}</span></span>
                <span className="text-[12.5px] text-muted"><Num>{c.reference}</Num> · أُرسلت {relative(c.submittedAt)}</span>
              </span>
              <span className="flex flex-wrap items-center gap-2 sm:justify-end"><Money value={c.amount} currency={c.currency} className="font-semibold" />
                {c.missing && <Pill tone="negative">ناقص مستند</Pill>}<Pill tone={claimTone(c.status)}>{c.statusLabel}</Pill></span>
            </Link>
          </li>
        ))}</ul>
      )}
      <Pagination page={claims} />
    </>
  );
}
