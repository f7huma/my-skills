import { countNoun } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ButtonLink, EmptyState, Money, Num, PageHeader, Pagination, Pill } from '@/Components/ui';
import type { Option, Paginated } from '@/types';

type Row = { id: number; reference: string; requestNumber: string | null; client: string; type: string; instrument: string; status: string; statusLabel: string; case: string | null; amount: string | null; currency: string; received: { currency: string; total: string }[] };

export default function Index({ executions, filters, statuses }: { executions: Paginated<Row>; filters: { q?: string; type?: string; status?: string }; statuses: Option[] }) {
  const [q, setQ] = useState(filters.q ?? '');
  const apply = (o: Record<string, string | undefined>) => router.get('/office/executions', { ...filters, q, ...o }, { preserveState: true, replace: true });
  return (
    <>
      <Head title="التنفيذ" />
      <PageHeader title="التنفيذ" subtitle={countNoun(executions.total, { one: 'طلب تنفيذ واحد', two: 'طلبا تنفيذ', few: 'طلبات تنفيذ', many: 'طلب تنفيذ' })} actions={<ButtonLink href="/office/executions/create" variant="primary">طلب تنفيذ مستقل</ButtonLink>} />
      <form onSubmit={(e) => { e.preventDefault(); apply({}); }} className="mb-4 flex flex-wrap gap-2">
        <label htmlFor="eq" className="sr-only">بحث</label>
        <input id="eq" type="search" className="input h-9 max-w-[300px]" placeholder="رقم الطلب، العميل، الطرف" value={q} onChange={(e) => setQ(e.target.value)} />
        <label htmlFor="et" className="sr-only">النوع</label>
        <select id="et" className="input h-9 w-auto" value={filters.type ?? ''} onChange={(e) => apply({ type: e.target.value || undefined })}><option value="">كل الأنواع</option><option value="financial">مالي</option><option value="direct">مباشر</option></select>
        <label htmlFor="es" className="sr-only">الحالة</label>
        <select id="es" className="input h-9 w-auto" value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value || undefined })}><option value="">كل الحالات</option>{statuses.map((s) => <option key={String(s.value)} value={String(s.value)}>{s.label}</option>)}</select>
      </form>
      {executions.data.length === 0 ? <EmptyState title="لا طلبات تنفيذ" /> : (
        <div className="overflow-x-auto"><table className="table">
          <thead><tr><th>الطلب</th><th>العميل</th><th>النوع والسند</th><th>الحالة</th><th className="end">المطلوب</th><th className="end">المقبوض</th></tr></thead>
          <tbody>{executions.data.map((e) => (
            <tr key={e.id}>
              <td><Link href={`/office/executions/${e.id}`} className="font-medium hover:underline underline-offset-4">{e.requestNumber ? <Num>{e.requestNumber}</Num> : 'طلب تنفيذ بلا رقم بعد'}</Link>{e.case && <span className="block text-[12.5px] text-muted">مرتبط بقضية</span>}</td>
              <td className="text-muted">{e.client}</td>
              <td className="text-muted">{e.type} · {e.instrument}</td>
              <td><Pill tone="info">{e.statusLabel}</Pill></td>
              <td className="end"><Money value={e.amount} currency={e.currency} /></td>
              <td className="end">{e.received.length ? e.received.map((r) => <div key={r.currency}><Money value={r.total} currency={r.currency} className="font-semibold text-accent" /></div>) : <span className="text-faint">—</span>}</td>
            </tr>
          ))}</tbody>
        </table></div>
      )}
      <Pagination page={executions} />
    </>
  );
}
