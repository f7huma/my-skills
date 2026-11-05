import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Num, PageHeader, Pagination, DateText } from '@/Components/ui';
import { dateTime } from '@/lib/format';
import type { Paginated } from '@/types';

type Row = { id: number; action: string; user: string | null; subject: string | null; meta: Record<string, unknown> | null; ip: string | null; at: string };

export default function Audit({ logs, filters }: { logs: Paginated<Row>; filters: { action?: string } }) {
  const [a, setA] = useState(filters.action ?? '');
  return (
    <>
      <Head title="سجل التدقيق" />
      <PageHeader title="سجل التدقيق" subtitle="للإضافة فقط: الدخول وتغييرات الصلاحيات والحالات والمواعيد والملفات والتقارير والتحصيل. لا يحفظ محتوى المستندات أو الأسرار." crumbs={[{ label: 'حالة النظام', href: '/office/admin/system' }]} />
      <form onSubmit={(e) => { e.preventDefault(); router.get('/office/admin/audit', { action: a || undefined }, { preserveState: true }); }} className="mb-4 flex gap-2">
        <label htmlFor="aa" className="sr-only">تصفية بالإجراء</label>
        <input id="aa" className="input h-9 max-w-[280px]" dir="ltr" placeholder="auth. / file. / case." value={a} onChange={(e) => setA(e.target.value)} />
        <button className="btn btn-secondary btn-sm h-9" type="submit">تصفية</button>
      </form>
      <div className="overflow-x-auto"><table className="table text-[13px]">
        <thead><tr><th>الوقت</th><th>الإجراء</th><th>المستخدم</th><th>السجل</th><th>تفاصيل</th><th>IP</th></tr></thead>
        <tbody>{logs.data.map((l) => (
          <tr key={l.id}>
            <td className="whitespace-nowrap"><DateText value={l.at} time /></td>
            <td><Num>{l.action}</Num></td>
            <td>{l.user ?? <span className="text-faint">النظام</span>}</td>
            <td className="text-muted"><Num>{l.subject ?? '—'}</Num></td>
            <td className="max-w-[320px] truncate text-muted" dir="ltr">{l.meta ? JSON.stringify(l.meta) : '—'}</td>
            <td className="text-muted"><Num>{l.ip ?? '—'}</Num></td>
          </tr>
        ))}</tbody>
      </table></div>
      <Pagination page={logs} />
    </>
  );
}
