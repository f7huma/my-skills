import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ButtonLink, EmptyState, Num, PageHeader, Pagination, Pill } from '@/Components/ui';
import type { Paginated } from '@/types';

type Row = { id: number; reference: string; name: string; type: string; typeLabel: string; phone: string | null; cases: number; active: number; archived: boolean };

export default function Index({ clients, filters }: { clients: Paginated<Row>; filters: { q: string; type: string | null; archived: boolean } }) {
  const [q, setQ] = useState(filters.q ?? '');
  const apply = (over: Record<string, unknown>) => router.get('/office/clients', { q, type: filters.type, archived: filters.archived ? 1 : undefined, ...over }, { preserveState: true, replace: true });
  return (
    <>
      <Head title="العملاء" />
      <PageHeader title="العملاء والوكالات" subtitle={<><Num>{clients.total}</Num> عميل</>} actions={<ButtonLink href="/office/clients/create" variant="primary">عميل جديد</ButtonLink>} />
      <form onSubmit={(e) => { e.preventDefault(); apply({}); }} className="mb-4 flex flex-wrap items-center gap-2">
        <label htmlFor="cq" className="sr-only">بحث</label>
        <input id="cq" type="search" className="input h-9 w-full max-w-[320px]" placeholder="الاسم، المرجع، الهوية، السجل التجاري" value={q} onChange={(e) => setQ(e.target.value)} />
        {[['', 'الكل'], ['individual', 'أفراد'], ['organization', 'منشآت']].map(([v, l]) => (
          <button key={v} type="button" className={'btn btn-sm ' + ((filters.type ?? '') === v ? 'btn-primary' : 'btn-ghost')} aria-pressed={(filters.type ?? '') === v} onClick={() => apply({ type: v || undefined })}>{l}</button>
        ))}
        <label className="ms-auto flex items-center gap-2 text-[13.5px]"><input type="checkbox" className="check" checked={filters.archived} onChange={(e) => apply({ archived: e.target.checked ? 1 : undefined })} />إظهار المؤرشفين</label>
      </form>
      {clients.data.length === 0 ? <EmptyState title="لا عملاء مطابقون" action={<ButtonLink href="/office/clients/create">إضافة عميل</ButtonLink>} /> : (
        <div className="overflow-x-auto">
          <table className="table">
            <thead><tr><th>العميل</th><th>النوع</th><th>المرجع</th><th className="end">القضايا السارية</th><th className="end">إجمالي القضايا</th><th>الجوال</th></tr></thead>
            <tbody>
              {clients.data.map((c) => (
                <tr key={c.id}>
                  <td className="font-medium"><Link href={`/office/clients/${c.id}`} className="hover:underline underline-offset-4">{c.name}</Link>{c.archived && <Pill className="ms-2">مؤرشف</Pill>}</td>
                  <td className="text-muted">{c.typeLabel}</td>
                  <td><Num>{c.reference}</Num></td>
                  <td className="end"><Num>{c.active}</Num></td>
                  <td className="end text-muted"><Num>{c.cases}</Num></td>
                  <td className="text-muted">{c.phone ? <Num>{c.phone}</Num> : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Pagination page={clients} />
    </>
  );
}
