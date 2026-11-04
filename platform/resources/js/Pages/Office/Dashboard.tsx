import { Head, Link, router } from '@inertiajs/react';
import { Check, CalendarX2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ButtonLink, certaintyTone, cx, EmptyState, Money, Num, Panel, Pill, poaLabel, poaTone } from '@/Components/ui';
import { dayMonth, relative, timeOnly, weekday, dateOnly, count } from '@/lib/format';

type Item = {
  key: string; type: 'hearing' | 'task' | 'memo' | 'execution' | 'deadline'; label: string; title: string; date: string | null; time?: string;
  ref?: string | null; client?: string | null; href: string; taskId?: number; lockVersion?: number; certainty?: string; alerted?: boolean; dueMode?: string;
};
type Agenda = { range: { from: string; to: string; fromHijri: string }; overdue: Item[]; today: Item[]; week: Item[]; unscheduled: Item[] };
type Props = {
  agenda: Agenda;
  claims: { id: number; reference: string; client: string; opponent: string; amount: string | null; currency: string; status: string; statusLabel: string; missing: boolean; submittedAt: string }[];
  hearings: { id: number; kind: string; at: string; authority: string | null; caseId: number; ref: string; title: string; client: string }[];
  deadlines: { id: number; reason: string; end: string | null; certainty: string; certaintyLabel: string; alerted: boolean; ref: string | null }[];
  poas: { id: number; number: string; client: string; clientId: number; expires: string; state: string }[];
  counters: { activeCases: number; resumable: number; alerted: number; needsDetermination: number };
  clients: { id: number; name: string; type: string; reference: string; active: number }[];
};

const typeMark: Record<Item['type'], string> = {
  hearing: 'bg-accent', task: 'bg-ink', memo: 'bg-[#5b6b85]', execution: 'bg-[#8a6a1f]', deadline: 'bg-negative',
};

function addDays(d: string, n: number) {
  const [y, m, day] = d.split('-').map(Number);
  const t = new Date(Date.UTC(y, m - 1, day + n));
  return t.toISOString().slice(0, 10);
}

/** شريط الأيام السبعة: عدد البنود لكل يوم، واليوم معلَّم بلون البيانات */
function WeekStrip({ agenda }: { agenda: Agenda }) {
  const days = Array.from({ length: 7 }, (_, i) => addDays(agenda.range.from, i));
  const all = [...agenda.today, ...agenda.week];
  const counts = days.map((d) => ({ d, hearings: all.filter((i) => i.date === d && i.type === 'hearing').length, other: all.filter((i) => i.date === d && i.type !== 'hearing').length }));
  return (
    <ol className="grid grid-cols-7 gap-1.5" aria-label="توزيع بنود الأسبوع على الأيام">
      {counts.map(({ d, hearings, other }, i) => {
        const today = i === 0;
        const fri = [5, 6].includes(new Date(d + 'T12:00:00Z').getUTCDay());
        return (
          <li key={d} className={cx('rounded-lg px-2 py-2 text-center', today ? 'bg-accent-soft ring-1 ring-accent' : fri ? 'bg-[#eceef1]' : 'bg-raised')}>
            <a href={`#day-${d}`} className="block focus-visible:outline-none" aria-label={`${weekday(d)} ${d}: ${hearings} جلسة و${other} بند آخر`}>
              <span className={cx('block text-[12px]', today ? 'font-semibold text-[#0f4640]' : 'text-muted')}>{today ? 'اليوم' : weekday(d).replace('ال', '')}</span>
              <span className="block text-[11.5px] text-muted"><Num>{d.slice(5).replace('-', '/')}</Num></span>
              <span className="mt-1 flex items-center justify-center gap-1 text-[13px]">
                {hearings > 0 && <span className="font-semibold text-accent" title="جلسات"><Num>{hearings}</Num>ج</span>}
                {other > 0 && <span className="text-text" title="مهام ومواعيد"><Num>{other}</Num></span>}
                {hearings + other === 0 && <span className="text-faint">—</span>}
              </span>
            </a>
          </li>
        );
      })}
    </ol>
  );
}

function AgendaRow({ it }: { it: Item }) {
  const [done, setDone] = useState(false);
  const complete = () => {
    if (!it.taskId) return;
    setDone(true);
    router.post(`/office/tasks/${it.taskId}/status`, { status: 'done', lock_version: it.lockVersion }, { preserveScroll: true, onError: () => setDone(false) });
  };
  return (
    <li className={cx('group flex items-start gap-3 py-2.5 transition-opacity duration-200', done && 'opacity-40')}>
      <span aria-hidden className={cx('mt-2 size-2 shrink-0 rounded-full', typeMark[it.type])} />
      <div className="min-w-0 flex-1">
        <Link href={it.href} className="block font-medium text-text hover:underline underline-offset-4">{it.title}</Link>
        <p className="flex flex-wrap gap-x-2 text-[12.5px] text-muted">
          <span className="text-text/80">{it.label}</span>
          {it.ref && <span>· <Num>{it.ref}</Num></span>}
          {it.client && <span>· {it.client}</span>}
          {it.type === 'deadline' && it.certainty && <Pill tone={certaintyTone(it.certainty)} className="!h-5">{it.label}</Pill>}
        </p>
      </div>
      <div className="shrink-0 text-end text-[13px]">
        {it.date && <div className="text-text"><Num>{it.time ?? dateOnly(it.date).slice(5).replace('-', '/')}</Num></div>}
        {it.taskId && !done && (
          <button type="button" onClick={complete} className="btn btn-ghost btn-sm mt-0.5 h-7 px-2 text-[12.5px]" aria-label={`إنجاز: ${it.title}`}>
            <Check className="size-3.5" aria-hidden />تم
          </button>
        )}
      </div>
    </li>
  );
}

function Group({ title, items, tone, id, emptyText }: { title: string; items: Item[]; tone?: 'negative'; id: string; emptyText: string }) {
  return (
    <section aria-labelledby={id} className="py-1">
      <h3 id={id} className={cx('flex items-center gap-2 pt-3 text-[13.5px] font-semibold', tone === 'negative' ? 'text-negative' : 'text-text')}>
        {title}<span className="font-normal text-muted"><Num>{items.length}</Num></span>
      </h3>
      {items.length ? <ul className="divide-y divide-rule">{items.map((i) => <AgendaRow key={i.key} it={i} />)}</ul>
        : <p className="py-2 text-[13px] text-muted">{emptyText}</p>}
    </section>
  );
}

export default function Dashboard(p: Props) {
  const { agenda } = p;
  const [clientQ, setClientQ] = useState('');
  const byDay = useMemo(() => {
    const m = new Map<string, Item[]>();
    agenda.week.forEach((i) => { if (i.date) m.set(i.date, [...(m.get(i.date) ?? []), i]); });
    return [...m.entries()];
  }, [agenda.week]);
  const hearingsToday = agenda.today.filter((i) => i.type === 'hearing').length;
  const finding = [
    agenda.overdue.length ? `${agenda.overdue.length} بند متأخر` : null,
    hearingsToday ? `${hearingsToday} ${hearingsToday === 1 ? 'جلسة' : 'جلسات'} اليوم` : null,
    p.claims.filter((c) => c.status === 'under_review').length ? `${p.claims.filter((c) => c.status === 'under_review').length} مطالبة بانتظار الدراسة` : null,
    agenda.unscheduled.length ? `${agenda.unscheduled.length} مهمة تنتظر تحديد موعد` : null,
  ].filter(Boolean).join(' · ') || 'لا توجد بنود عاجلة هذا الأسبوع';
  const clients = p.clients.filter((c) => !clientQ || c.name.includes(clientQ) || c.reference.toLowerCase().includes(clientQ.toLowerCase()));

  return (
    <>
      <Head title="إنجاز الأسبوع" />
      <header className="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-[22px] font-bold sm:text-[24px]">إنجاز الأسبوع</h1>
          <p className="mt-0.5 text-[14px] text-muted">
            {weekday(agenda.range.from)} {dayMonth(agenda.range.from)} ({agenda.range.fromHijri}) — حتى {dayMonth(agenda.range.to)}
          </p>
          <p className={cx('mt-1 text-[14px] font-medium', agenda.overdue.length ? 'text-negative' : 'text-text')}>{finding}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ButtonLink href="/office/tasks" size="sm">كل المهام</ButtonLink>
          <ButtonLink href="/office/clients/create" size="sm">عميل جديد</ButtonLink>
          <ButtonLink href="/office/executions/create" size="sm">طلب تنفيذ</ButtonLink>
        </div>
      </header>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
        {/* قائمة الإنجاز */}
        <section className="card px-5 pb-4 pt-4" aria-labelledby="agenda-title">
          <h2 id="agenda-title" className="sr-only">قائمة إنجاز الأسبوع</h2>
          <WeekStrip agenda={agenda} />
          <div className="mt-2">
            {agenda.overdue.length > 0 && <Group id="g-overdue" title="متأخر" tone="negative" items={agenda.overdue} emptyText="" />}
            <Group id="g-today" title="اليوم" items={agenda.today} emptyText="لا جلسات ولا مهام مستحقة اليوم." />
            <section aria-labelledby="g-week" className="py-1">
              <h3 id="g-week" className="pt-3 text-[13.5px] font-semibold">بقية الأسبوع <span className="font-normal text-muted"><Num>{agenda.week.length}</Num></span></h3>
              {byDay.length === 0 && <p className="py-2 text-[13px] text-muted">لا بنود في بقية الأسبوع.</p>}
              {byDay.map(([d, items]) => (
                <div key={d} id={`day-${d}`} className="scroll-mt-20">
                  <p className="mt-2 text-[12.5px] text-muted">{weekday(d)} <Num>{d}</Num></p>
                  <ul className="divide-y divide-rule">{items.map((i) => <AgendaRow key={i.key} it={i} />)}</ul>
                </div>
              ))}
            </section>
            <Group id="g-unscheduled" title="بانتظار تحديد موعد" items={agenda.unscheduled} emptyText="كل المهام لها مواعيد." />
          </div>
        </section>

        <div className="grid grid-cols-1 content-start gap-4">
          <Panel id="claims" title="المطالبات الواردة" actions={<Link href="/office/claims" className="text-[13px] text-muted hover:text-text">عرض الكل</Link>}>
            {p.claims.length === 0 ? <p className="text-[13.5px] text-muted">لا مطالبات بانتظار الدراسة.</p> : (
              <ul className="divide-y divide-rule">
                {p.claims.slice(0, 5).map((c) => (
                  <li key={c.id} className="flex items-start justify-between gap-3 py-2.5">
                    <div className="min-w-0">
                      <Link href={`/office/claims/${c.id}`} className="block truncate font-medium hover:underline underline-offset-4">{c.client}</Link>
                      <p className="truncate text-[12.5px] text-muted">ضد {c.opponent} · <Num>{c.reference}</Num></p>
                      <div className="mt-1 flex gap-1.5">
                        <Pill tone={c.status === 'needs_completion' ? 'warning' : 'info'}>{c.statusLabel}</Pill>
                        {c.missing && <Pill tone="negative">ناقص مستند</Pill>}
                      </div>
                    </div>
                    <div className="shrink-0 text-end">
                      <Money value={c.amount} currency={c.currency} className="font-semibold" />
                      <p className="text-[12px] text-muted">{relative(c.submittedAt)}</p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          <Panel id="hearings" title="الجلسات القادمة" actions={<Link href="/office/cases" className="text-[13px] text-muted hover:text-text">القضايا</Link>}>
            {p.hearings.length === 0 ? <p className="text-[13.5px] text-muted">لا جلسات مجدولة.</p> : (
              <ul className="divide-y divide-rule">
                {p.hearings.map((h) => (
                  <li key={h.id} className="flex items-start gap-3 py-2.5">
                    <div className="w-[64px] shrink-0 text-[12.5px] leading-5">
                      <div className="font-semibold text-text"><Num>{h.at.slice(5, 10).replace('-', '/')}</Num></div>
                      <div className="text-muted"><Num>{timeOnly(h.at)}</Num></div>
                    </div>
                    <div className="min-w-0">
                      <Link href={`/office/cases/${h.caseId}?tab=hearings`} className="block truncate font-medium hover:underline underline-offset-4">{h.title}</Link>
                      <p className="truncate text-[12.5px] text-muted">{h.kind} · {h.client}{h.authority ? ` · ${h.authority}` : ''}</p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          <Panel id="counters" title="المواعيد والعدادات" actions={<Link href="/office/deadlines" className="text-[13px] text-muted hover:text-text">الشاشة الموحدة</Link>}>
            <dl className="mb-3 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-line">
              {[
                ['قضايا سارية', p.counters.activeCases, false],
                ['موقوفة/مشطوبة قابلة للاستكمال', p.counters.resumable, false],
                ['عدادات انتهت (تنبيه)', p.counters.alerted, p.counters.alerted > 0],
                ['تحتاج تحديدًا', p.counters.needsDetermination, p.counters.needsDetermination > 0],
              ].map(([l, v, warn]) => (
                <div key={String(l)} className="bg-surface px-3 py-2.5">
                  <dt className="text-[12px] text-muted">{l}</dt>
                  <dd className={cx('text-[20px] font-semibold', warn ? 'text-negative' : 'text-text')}><Num>{count(Number(v))}</Num></dd>
                </div>
              ))}
            </dl>
            <ul className="divide-y divide-rule">
              {p.deadlines.slice(0, 4).map((d) => (
                <li key={d.id} className="flex items-center justify-between gap-3 py-2">
                  <div className="min-w-0">
                    <Link href={`/office/deadlines#deadline-${d.id}`} className="block truncate text-[13.5px] font-medium hover:underline underline-offset-4">{d.reason}</Link>
                    <p className="text-[12px] text-muted">{d.ref && <Num>{d.ref}</Num>}</p>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <Pill tone={d.alerted ? 'negative' : certaintyTone(d.certainty)}>{d.alerted ? 'انتهى' : d.certaintyLabel}</Pill>
                    <span className="w-[74px] text-end text-[12.5px]"><Num>{d.end ?? '—'}</Num></span>
                  </div>
                </li>
              ))}
            </ul>
          </Panel>

          <Panel id="poas" title="وكالات قريبة من الانتهاء">
            {p.poas.length === 0 ? <p className="text-[13.5px] text-muted">لا وكالات تنتهي خلال نافذة التنبيه.</p> : (
              <ul className="divide-y divide-rule">
                {p.poas.map((v) => (
                  <li key={v.id} className="flex items-center justify-between gap-3 py-2">
                    <div className="min-w-0">
                      <Link href={`/office/clients/${v.clientId}?tab=poa`} className="block truncate font-medium hover:underline underline-offset-4">{v.client}</Link>
                      <p className="text-[12.5px] text-muted">وكالة <Num>{v.number}</Num> · تنتهي <Num>{v.expires}</Num></p>
                    </div>
                    <Pill tone={poaTone(v.state)}>{poaLabel[v.state]}</Pill>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          <Panel id="clients" title="العملاء" actions={<Link href="/office/clients" className="text-[13px] text-muted hover:text-text">الكل</Link>}>
            <label htmlFor="client-filter" className="sr-only">بحث سريع في العملاء</label>
            <input id="client-filter" type="search" className="input mb-2 h-9" placeholder="بحث سريع بالاسم أو المرجع" value={clientQ} onChange={(e) => setClientQ(e.target.value)} />
            {clients.length === 0 ? <EmptyState title="لا نتائج" icon={<CalendarX2 className="size-6" strokeWidth={1.5} />} /> : (
              <ul className="divide-y divide-rule">
                {clients.slice(0, 6).map((c) => (
                  <li key={c.id} className="flex items-center justify-between py-2">
                    <Link href={`/office/clients/${c.id}`} className="truncate font-medium hover:underline underline-offset-4">{c.name}</Link>
                    <span className="shrink-0 text-[12.5px] text-muted"><Num>{c.active}</Num> سارية</span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </div>
      </div>
    </>
  );
}
