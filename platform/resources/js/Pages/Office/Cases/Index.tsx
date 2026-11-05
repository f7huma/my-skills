import { Head, Link, router } from '@inertiajs/react';
import { Bookmark, X } from 'lucide-react';
import { useState } from 'react';
import { ButtonLink, caseTone, EmptyState, Num, PageHeader, Pagination, Pill, DateText } from '@/Components/ui';
import { dateTime } from '@/lib/format';
import type { Option, Paginated } from '@/types';

type Row = { id: number; reference: string; title: string; client: string; clientId: number; caseNumber: string | null; stage: string; status: string; statusLabel: string; category: string | null; role: string; nextHearing: string | null; updatedAt: string };
type F = { q?: string; status?: string; stage?: string; category?: string | number; role?: string; client?: number };

export default function Index({ cases, filters, savedFilters, options }: { cases: Paginated<Row>; filters: F; savedFilters: { id: number; name: string; query: F }[]; options: { stages: Option[]; statuses: Option[]; roles: Option[]; categories: Option[] } }) {
  const [f, setF] = useState<F>(filters);
  const apply = (next: F) => { setF(next); router.get('/office/cases', Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '' && v !== undefined && v !== null)), { preserveState: true, replace: true }); };
  const active = Object.values(filters).some((v) => v);
  const saveFilter = () => {
    const name = window.prompt('اسم الفلتر المحفوظ:');
    if (name) router.post('/office/filters', { name, page: 'cases', query: filters }, { preserveScroll: true });
  };
  const sel = (key: keyof F, label: string, opts: Option[]) => (
    <div>
      <label htmlFor={'f-' + key} className="sr-only">{label}</label>
      <select id={'f-' + key} className="input h-9 w-auto min-w-[140px]" value={String(f[key] ?? '')} onChange={(e) => apply({ ...f, [key]: e.target.value || undefined })}>
        <option value="">{label}: الكل</option>
        {opts.map((o) => <option key={String(o.value)} value={String(o.value)}>{o.label}</option>)}
      </select>
    </div>
  );
  return (
    <>
      <Head title="القضايا" />
      <PageHeader title="القضايا" subtitle={<><Num>{cases.total}</Num> قضية{active && ' مطابقة للفلاتر'}</>} actions={<ButtonLink href="/office/cases/create" variant="primary">قضية جديدة</ButtonLink>} />
      {savedFilters.length > 0 && (
        <div className="mb-3 flex flex-wrap items-center gap-1.5">
          <span className="text-[12.5px] text-muted">فلاتر محفوظة:</span>
          {savedFilters.map((s) => (
            <span key={s.id} className="inline-flex items-center rounded-lg bg-raised">
              <button type="button" className="btn btn-ghost btn-sm" onClick={() => apply(s.query)}>{s.name}</button>
              <button type="button" className="btn btn-ghost btn-sm px-1.5" aria-label={'حذف الفلتر ' + s.name} onClick={() => router.delete(`/office/filters/${s.id}`, { preserveScroll: true })}><X className="size-3.5" /></button>
            </span>
          ))}
        </div>
      )}
      <form onSubmit={(e) => { e.preventDefault(); apply(f); }} className="mb-4 flex flex-wrap items-center gap-2">
        <label htmlFor="f-q" className="sr-only">بحث</label>
        <input id="f-q" type="search" className="input h-9 w-full max-w-[300px]" placeholder="رقم القضية، الطلب، العنوان، الطرف" value={f.q ?? ''} onChange={(e) => setF({ ...f, q: e.target.value })} />
        {sel('status', 'الحالة', [{ value: 'open', label: 'غير المكتملة' }, ...options.statuses])}
        {sel('stage', 'المرحلة', options.stages)}
        {sel('category', 'التصنيف', options.categories)}
        {sel('role', 'صفة العميل', options.roles)}
        {active && <button type="button" className="btn btn-ghost btn-sm" onClick={() => apply({})}>مسح</button>}
        {active && <button type="button" className="btn btn-ghost btn-sm" onClick={saveFilter}><Bookmark className="size-4" aria-hidden />حفظ الفلتر</button>}
      </form>
      {cases.data.length === 0 ? <EmptyState title={active ? 'لا قضايا مطابقة' : 'لا قضايا بعد'} action={<ButtonLink href="/office/cases/create">قضية جديدة</ButtonLink>} /> : (
        <>
          <div className="hidden overflow-x-auto md:block">
            <table className="table">
              <thead><tr><th>القضية</th><th>العميل</th><th>رقم القضية</th><th>المرحلة</th><th>الحالة</th><th className="end">الجلسة القادمة</th></tr></thead>
              <tbody>{cases.data.map((c) => (
                <tr key={c.id}>
                  <td className="max-w-[320px]"><Link href={`/office/cases/${c.id}`} className="block truncate font-medium hover:underline underline-offset-4">{c.title}</Link><span className="text-[12.5px] text-muted"><Num>{c.reference}</Num> · {c.role}{c.category ? ' · ' + c.category : ''}</span></td>
                  <td className="text-muted"><Link href={`/office/clients/${c.clientId}`} className="hover:text-text">{c.client}</Link></td>
                  <td>{c.caseNumber ? <Num>{c.caseNumber}</Num> : <span className="text-faint">—</span>}</td>
                  <td className="text-muted">{c.stage}</td>
                  <td><Pill tone={caseTone(c.status)}>{c.statusLabel}</Pill></td>
                  <td className="end">{c.nextHearing ? <DateText value={c.nextHearing} time /> : <span className="text-faint">—</span>}</td>
                </tr>
              ))}</tbody>
            </table>
          </div>
          <ul className="divide-y divide-rule md:hidden">{cases.data.map((c) => (
            <li key={c.id} className="py-3">
              <Link href={`/office/cases/${c.id}`} className="block">
                <span className="flex items-start justify-between gap-2"><span className="font-medium">{c.title}</span><Pill tone={caseTone(c.status)}>{c.statusLabel}</Pill></span>
                <span className="block text-[13px] text-muted">{c.client} · {c.stage}</span>
                <span className="block text-[12.5px] text-muted"><Num>{c.caseNumber ?? c.reference}</Num>{c.nextHearing && <> · الجلسة <DateText value={c.nextHearing} time /></>}</span>
              </Link>
            </li>
          ))}</ul>
        </>
      )}
      <Pagination page={cases} />
    </>
  );
}
