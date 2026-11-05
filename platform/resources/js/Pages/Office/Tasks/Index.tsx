import { Head, Link, router, useForm } from '@inertiajs/react';
import { emptyTask, TaskFields, type TaskInput } from '@/Components/TaskFields';
import { Button, cx, EmptyState, Num, PageHeader, Panel, DateText } from '@/Components/ui';
import type { Option } from '@/types';

type T = { id: number; title: string; kindLabel: string; dueMode: string; dueOn: string | null; status: string; lockVersion: number; case: { id: number; reference: string; title: string } | null; execution: { id: number; reference: string } | null };

export default function Index({ tasks, status, kinds, dueModes }: { tasks: T[]; status: string; kinds: Option[]; dueModes: Option[] }) {
  const form = useForm<TaskInput>(emptyTask());
  const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Riyadh' });
  return (
    <>
      <Head title="المهام" />
      <PageHeader title="المهام" subtitle="المهام من القضايا والجلسات والتنفيذ، والمهام اليدوية." />
      <div className="mb-4 flex gap-1">
        {[['open', 'المفتوحة'], ['done', 'المنجزة'], ['all', 'الكل']].map(([v, l]) => (
          <Link key={v} href={`/office/tasks?status=${v}`} className={cx('btn btn-sm', status === v ? 'btn-primary' : 'btn-ghost')} aria-current={status === v ? 'page' : undefined}>{l}</Link>
        ))}
      </div>
      <div className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <section className="card px-5 py-3" aria-label="قائمة المهام">
          {tasks.length === 0 ? <EmptyState title="لا مهام" /> : (
            <ul className="divide-y divide-rule">{tasks.map((t) => (
              <li key={t.id} id={`task-${t.id}`} className="flex items-start gap-3 py-2.5">
                <input type="checkbox" className="check mt-1" checked={t.status === 'done'} aria-label={'إنجاز ' + t.title}
                  onChange={() => router.post(`/office/tasks/${t.id}/status`, { status: t.status === 'open' ? 'done' : 'open', lock_version: t.lockVersion }, { preserveScroll: true })} />
                <div className="min-w-0 flex-1">
                  <p className={cx('font-medium', t.status !== 'open' && 'text-muted line-through')}>{t.title}</p>
                  <p className="text-[12.5px] text-muted">{t.kindLabel}
                    {t.case && <> · <Link href={`/office/cases/${t.case.id}?tab=tasks`} className="hover:text-text"><Num>{t.case.reference}</Num> {t.case.title}</Link></>}
                    {t.execution && <> · <Link href={`/office/executions/${t.execution.id}`} className="hover:text-text"><Num>{t.execution.reference}</Num></Link></>}</p>
                </div>
                <span className={cx('shrink-0 text-[13px]', t.dueOn && t.dueOn < today && t.status === 'open' ? 'font-semibold text-negative' : 'text-muted')}>{t.dueOn ? <DateText value={t.dueOn} /> : 'بلا موعد'}</span>
              </li>
            ))}</ul>
          )}
        </section>
        <Panel title="مهمة يدوية">
          <form onSubmit={(e) => { e.preventDefault(); form.post('/office/tasks', { preserveScroll: true, onSuccess: () => form.reset() }); }} className="space-y-3">
            <TaskFields value={form.data} onChange={(v) => form.setData(v)} kinds={kinds} dueModes={dueModes} errors={form.errors as Record<string, string>} />
            <Button type="submit" variant="primary" loading={form.processing}>إضافة</Button>
          </form>
        </Panel>
      </div>
    </>
  );
}
