import { Head, Link, router } from '@inertiajs/react';
import { Check, CalendarX2, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ButtonLink, certaintyTone, cx, EmptyState, Money, Num, Panel, Pill, poaLabel, poaTone, DateText } from '@/Components/ui';
import { dayMonth, relative, timeOnly, weekday, dateOnly, count, countNoun, hijri, hijriParts } from '@/lib/format';
import { BarChart, CountUp, Meter, Pipeline } from '@/Components/motion';

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
  insights: { months: { month: string; total: number }[]; collectedThisMonth: number; stages: { key: string; label: string; count: number }[] };
};

const typeMark: Record<Item['type'], string> = {
  hearing: 'text-ink', task: 'text-[#9aa4b0]', memo: 'text-[#7d8794]', execution: 'text-[#5f6976]', deadline: 'text-ink',
};

// أسماء أيام مختصرة مقروءة (الأحد = 0) للشاشات الضيقة
const SHORT_DAYS = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

function addDays(d: string, n: number) {
  const [y, m, day] = d.split('-').map(Number);
  const t = new Date(Date.UTC(y, m - 1, day + n));
  return t.toISOString().slice(0, 10);
}

/** شريط الأسبوع: سبع عُقد ماسية حجمها بقدر حمل اليوم؛ اليوم مضاء، والضغط على يوم يحصر القائمة فيه */
function WeekRibbon({ agenda, selected, onSelect }: { agenda: Agenda; selected: string | null; onSelect: (d: string | null) => void }) {
  const days = Array.from({ length: 7 }, (_, i) => addDays(agenda.range.from, i));
  const all = [...agenda.today, ...agenda.week];
  const load = days.map((d) => {
    const items = all.filter((i) => i.date === d);
    return { d, n: items.length, hearings: items.filter((i) => i.type === 'hearing').length, deadline: items.some((i) => i.type === 'deadline') };
  });
  const max = Math.max(1, ...load.map((l) => l.n));
  return (
    <div className="relative mt-7">
      <span aria-hidden className="absolute inset-x-[7%] top-[26px] h-px bg-ink/15" />
      <ol className="relative grid grid-cols-7" aria-label="أيام الأسبوع — اختر يومًا لعرض بنوده">
        {load.map(({ d, n, hearings, deadline }, i) => {
          const today = i === 0;
          const off = [5, 6].includes(new Date(d + 'T12:00:00Z').getUTCDay());
          const on = selected === d;
          const size = n ? 14 + (n / max) * 18 : 10;
          return (
            <li key={d} className="flex justify-center">
              <button type="button" onClick={() => onSelect(on ? null : d)} aria-pressed={on}
                aria-label={`${weekday(d)} ${d} (${hijri(d)}هـ): ${n} بند${hearings ? `، منها ${hearings} جلسة` : ''}${deadline ? '، وفيه موعد نظامي' : ''}`}
                className={cx('group flex w-full flex-col items-center rounded-xl pb-2 pt-1 transition-colors duration-200', on ? 'bg-white' : 'hover:bg-white/70')}>
                <span className="grid h-[44px] place-items-center">
                  <span className={cx('rounded-[4px] transition-[filter] duration-200 group-hover:brightness-125', today ? 'bg-ink' : n ? 'bg-[#8b95a1]' : 'border border-ink/30 bg-transparent', deadline && 'ring-2 ring-amber ring-offset-2 ring-offset-[#e1e5e6]')}
                    style={{ width: size, height: size, transform: 'rotate(45deg)' }} />
                </span>
                <span className={cx('mt-1 text-[10.5px] sm:text-[12.5px]', today ? 'font-semibold text-ink' : off ? 'text-faint' : 'text-muted')}>
                  {today ? 'اليوم' : <><span className="sm:hidden" aria-hidden>{SHORT_DAYS[new Date(d + 'T12:00:00Z').getUTCDay()]}</span><span className="hidden sm:inline">{weekday(d).replace('ال', '')}</span></>}
                </span>
                <span className="num text-[11.5px] text-muted">{hijriParts(d)?.d}<span className="text-muted">هـ</span></span>
                <span className="num text-[10.5px] text-muted">{d.slice(8)}/{d.slice(5, 7)}</span>
                <span className={cx('num mt-0.5 text-[12px]', n ? 'text-ink' : 'text-faint')}>{n ? n : '—'}</span>
              </button>
            </li>
          );
        })}
      </ol>
    </div>
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
      <span aria-hidden className={cx('diamond mt-2.5', typeMark[it.type])} />
      <div className="min-w-0 flex-1">
        <Link href={it.href} className="block font-medium text-text hover:underline underline-offset-4">{it.title}</Link>
        <p className="flex flex-wrap gap-x-2 text-[12.5px] text-muted [&>span]:min-w-0 [&>span]:line-clamp-1">
          <span className="text-text/80">{it.label}</span>
          {it.ref && <span>· <Num>{it.ref}</Num></span>}
          {it.client && <span>· {it.client}</span>}
          {it.type === 'deadline' && it.certainty && <Pill tone={certaintyTone(it.certainty)} className="!h-5">{it.label}</Pill>}
        </p>
      </div>
      <div className="shrink-0 text-end text-[13px]">
        {it.date && (it.time ? <div className="text-text"><Num>{it.time}</Num></div> : (
          <div className="leading-tight"><span className="num block text-text">{dateOnly(it.date).slice(8)}/{dateOnly(it.date).slice(5, 7)}</span><span className="num block text-[11.5px] text-muted">{hijri(it.date).slice(0, 5)}هـ</span></div>
        ))}
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
  // يوم مزدحم: تُعرض أول ٨ بنود ويُكشف الباقي عند الطلب حتى لا تطول الصفحة
  const [all, setAll] = useState(false);
  const shown = all ? items : items.slice(0, 8);
  return (
    <section aria-labelledby={id} className="py-1">
      <h3 id={id} className={cx('flex items-center gap-2 pt-3 text-[13.5px] font-semibold', tone === 'negative' ? 'text-negative' : 'text-text')}>
        {title}<span className="font-normal text-muted"><Num>{items.length}</Num></span>
      </h3>
      {items.length ? <ul className="divide-y divide-rule">{shown.map((i) => <AgendaRow key={i.key} it={i} />)}</ul>
        : <p className="py-2 text-[13px] text-muted">{emptyText}</p>}
      {items.length > 8 && (
        <button type="button" className="btn btn-ghost btn-sm mt-1" onClick={() => setAll(!all)} aria-expanded={all}>
          {all ? 'عرض أقل' : <>عرض البنود الباقية (<Num>{items.length - 8}</Num>)</>}
        </button>
      )}
    </section>
  );
}

function daysUntil(from: string, to: string | null) {
  if (!to) return null;
  const a = Date.parse(from + 'T00:00:00Z'); const b = Date.parse(to + 'T00:00:00Z');
  return Math.round((b - a) / 86400000);
}
const monthLabel = (m: string) => new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { month: 'long', timeZone: 'UTC' }).format(new Date(m + '-15T12:00:00Z'));

export default function Dashboard(p: Props) {
  const { agenda, insights } = p;
  const [clientQ, setClientQ] = useState('');
  const [day, setDay] = useState<string | null>(null);
  const byDay = useMemo(() => {
    const m = new Map<string, Item[]>();
    agenda.week.forEach((i) => { if (i.date) m.set(i.date, [...(m.get(i.date) ?? []), i]); });
    return [...m.entries()];
  }, [agenda.week]);
  const hearingsToday = agenda.today.filter((i) => i.type === 'hearing').length;
  const weekDeadlines = [...agenda.today, ...agenda.week].filter((i) => i.type === 'deadline').length;
  const reviewing = p.claims.filter((c) => c.status === 'under_review').length;
  const finding = [
    agenda.overdue.length ? countNoun(agenda.overdue.length, { one: 'بند متأخر', two: 'بندان متأخران', few: 'بنود متأخرة', many: 'بندًا متأخرًا' }) : null,
    reviewing ? countNoun(reviewing, { one: 'مطالبة واحدة', two: 'مطالبتان', few: 'مطالبات', many: 'مطالبة' }) + ' بانتظار الدراسة' : null,
    agenda.unscheduled.length ? countNoun(agenda.unscheduled.length, { one: 'مهمة واحدة', two: 'مهمتان', few: 'مهام', many: 'مهمة' }) + ' بانتظار تحديد موعد' : null,
  ].filter(Boolean).join(' · ');
  const inDays = (n: number) => (n < 0 ? 'انتهى' : n === 0 ? 'اليوم' : n === 1 ? 'غدًا' : n === 2 ? 'بعد يومين' : `بعد ${n} ${n % 100 >= 3 && n % 100 <= 10 ? 'أيام' : 'يومًا'}`);
  const clients = p.clients.filter((c) => !clientQ || c.name.includes(clientQ) || c.reference.toLowerCase().includes(clientQ.toLowerCase()));
  const nearest = p.deadlines.find((d) => d.end);
  const left = nearest ? daysUntil(agenda.range.from, nearest.end) : null;
  const dayItems = day ? [...agenda.today, ...agenda.week].filter((i) => i.date === day) : [];
  const stats: [string, number, string?, number?][] = [
    ['جلسات اليوم', hearingsToday],
    ['مواعيد نظامية هذا الأسبوع', weekDeadlines, weekDeadlines ? 'text-signal' : undefined],
    ['قضايا سارية', p.counters.activeCases],
    ['المقبوض هذا الشهر · ر.س', insights.collectedThisMonth, undefined, 0],
  ];

  return (
    <>
      <Head title="أسبوع المكتب" />
      {/* الشريط الفحمي: الأرقام تعدّ، وشريط الأسبوع تحتها */}
      <section className="mist relative overflow-hidden rounded-[22px] px-5 pb-4 pt-6 sm:px-8" aria-labelledby="week-title">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0 flex-1 basis-[22rem]">
            <h1 id="week-title" className="text-[26px] font-semibold leading-tight text-ink sm:text-[30px]">أسبوع المكتب</h1>
            <p className="mt-1 text-[14px] text-muted">{weekday(agenda.range.from)} {dayMonth(agenda.range.from)} · {agenda.range.fromHijri}</p>
            {finding && <p className={cx('mt-1.5 text-[13.5px] font-medium', agenda.overdue.length ? 'text-signal' : 'text-muted')}>{finding}</p>}
            {nearest && left !== null && (
              <Link href={`/office/deadlines#deadline-${nearest.id}`} className="mt-1.5 flex max-w-full items-start gap-2 text-[13.5px] text-ink hover:underline underline-offset-4 lg:max-w-[60ch]">
                <span className={cx('diamond', left <= 3 ? 'text-signal' : left <= 10 ? 'text-amber' : 'text-[#8b95a1]')} aria-hidden />
                <span className="line-clamp-2">أقرب موعد نظامي: <b>{inDays(left)}</b> — {nearest.reason}</span>
              </Link>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            <ButtonLink href="/office/cases/create" variant="primary" size="sm"><Plus className="size-4" aria-hidden />قضية جديدة</ButtonLink>
            <ButtonLink href="/office/executions/create" variant="ghost" size="sm" className="hidden sm:inline-flex">طلب تنفيذ</ButtonLink>
            <ButtonLink href="/office/clients/create" variant="ghost" size="sm" className="hidden sm:inline-flex">عميل جديد</ButtonLink>
          </div>
        </div>
        <dl className="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 lg:grid-cols-4">
          {stats.map(([label, value, tone, dec], i) => (
            <div key={label} className={cx('min-w-0 border-s border-ink/15 ps-4', i === 3 && 'col-span-2 lg:col-span-1')}>
              <dt className="text-[12.5px] text-muted">{label}</dt>
              <dd className={cx('mt-1 font-semibold leading-none text-ink', i === 3 ? 'text-[clamp(26px,7.5vw,40px)]' : 'text-[34px] sm:text-[40px]', tone)}><CountUp value={value} decimals={dec ?? 0} /></dd>
            </div>
          ))}
        </dl>
        <WeekRibbon agenda={agenda} selected={day} onSelect={setDay} />
      </section>

      <div className="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section className="card px-5 pb-4 pt-4" aria-labelledby="agenda-title">
          <div className="flex items-center justify-between gap-3">
            <h2 id="agenda-title" className="text-[16px] font-semibold">{day ? `بنود ${weekday(day)}` : 'قائمة الإنجاز'}</h2>
            {day ? <button type="button" className="btn btn-ghost btn-sm" onClick={() => setDay(null)}>عرض الأسبوع كاملًا</button>
              : <Link href="/office/tasks" className="text-[13px] text-muted hover:text-text">كل المهام</Link>}
          </div>
          {day ? (
            dayItems.length ? <ul className="divide-y divide-rule">{dayItems.map((i) => <AgendaRow key={i.key} it={i} />)}</ul>
              : <p className="py-6 text-center text-[13.5px] text-muted">لا بنود في هذا اليوم.</p>
          ) : (
            <div>
              {agenda.overdue.length > 0 && <Group id="g-overdue" title="متأخر" tone="negative" items={agenda.overdue} emptyText="" />}
              <Group id="g-today" title="اليوم" items={agenda.today} emptyText="لا جلسات ولا مهام مستحقة اليوم." />
              <section aria-labelledby="g-week" className="py-1">
                <h3 id="g-week" className="pt-3 text-[13.5px] font-semibold">بقية الأسبوع <span className="font-normal text-muted"><Num>{agenda.week.length}</Num></span></h3>
                {byDay.length === 0 && <p className="py-2 text-[13px] text-muted">لا بنود في بقية الأسبوع.</p>}
                {byDay.map(([d, items]) => (
                  <div key={d} id={`day-${d}`} className="scroll-mt-20">
                    <p className="mt-2 text-[12.5px] text-muted">{weekday(d)} <DateText value={d} /></p>
                    <ul className="divide-y divide-rule">{items.map((i) => <AgendaRow key={i.key} it={i} />)}</ul>
                  </div>
                ))}
              </section>
              <Group id="g-unscheduled" title="بانتظار تحديد موعد" items={agenda.unscheduled} emptyText="كل المهام لها مواعيد." />
            </div>
          )}
        </section>

        <div className="grid grid-cols-1 content-start gap-4">
          <Panel id="nearest" title="الموعد النظامي الأقرب" actions={<Link href="/office/deadlines" className="text-[13px] text-muted hover:text-text">كل المواعيد</Link>}>
            {!nearest || left === null ? <p className="text-[13.5px] text-muted">لا مواعيد نظامية مفتوحة.</p> : (
              <>
                <div className="flex items-center gap-4">
                  <span className={cx('num text-[64px] font-semibold leading-none', left <= 3 ? 'text-signal' : left <= 10 ? 'text-amber' : 'text-ink')}>
                    {Math.max(0, left)}
                  </span>
                  <div className="min-w-0">
                    <p className="font-semibold">{left < 0 ? 'انتهى الموعد' : left === 0 ? 'ينتهي اليوم' : left === 1 ? 'يوم واحد متبقٍ' : left === 2 ? 'يومان متبقيان' : left <= 10 ? 'أيام متبقية' : 'يومًا متبقيًا'}</p>
                    <Link href={`/office/deadlines#deadline-${nearest.id}`} className="block truncate text-[13px] text-muted hover:text-text">{nearest.reason}{nearest.ref && <> · <Num>{nearest.ref}</Num></>}</Link>
                  </div>
                </div>
                {(p.counters.alerted > 0 || p.counters.needsDetermination > 0) && (
                  <p className="mt-2 text-[12.5px] text-muted">
                    {p.counters.alerted > 0 && <><b className="text-signal">{countNoun(p.counters.alerted, { one: 'عداد واحد', two: 'عدادان', few: 'عدادات', many: 'عدادًا' })}</b> انتهت مهلته · </>}
                    {p.counters.needsDetermination > 0 && <><b className="text-amber">{countNoun(p.counters.needsDetermination, { one: 'موعد واحد', two: 'موعدان', few: 'مواعيد', many: 'موعدًا' })}</b> بانتظار تحديد البداية</>}
                  </p>
                )}
                <div className="mt-3"><Meter value={Math.max(0, 30 - Math.max(0, left))} max={30} tone={left <= 3 ? 'signal' : left <= 10 ? 'amber' : 'ink'} label="قرب الموعد خلال ٣٠ يومًا" /></div>
                <ul className="mt-3 divide-y divide-rule">
                  {p.deadlines.filter((d) => d.id !== nearest.id).slice(0, 3).map((d) => (
                    <li key={d.id} className="flex items-center justify-between gap-3 py-2">
                      <Link href={`/office/deadlines#deadline-${d.id}`} className="min-w-0 truncate text-[13.5px] hover:underline underline-offset-4">{d.reason}</Link>
                      <span className="flex shrink-0 items-center gap-2">
                        <Pill tone={d.alerted ? 'negative' : certaintyTone(d.certainty)}>{d.alerted ? 'انتهى' : d.certaintyLabel}</Pill>
                        <span className="text-end text-[12.5px]"><DateText value={d.end} /></span>
                      </span>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </Panel>

          <Panel id="collected" title="المقبوض فعليًا" description="آخر ستة أشهر بالريال — مرّر على الشهر لقراءته">
            <BarChart label="المقبوض شهريًا" unit="ر.س" data={insights.months.map((m) => ({ key: m.month, label: monthLabel(m.month), value: m.total }))}
              format={(v) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v)} />
          </Panel>

          <Panel id="pipeline" title="القضايا حسب المرحلة" description={<>المجموع <Num>{count(insights.stages.reduce((a, s) => a + s.count, 0))}</Num> — اضغط مرحلة لفتح قائمتها</>}>
            <Pipeline stages={insights.stages} />
          </Panel>

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

          <Panel id="poas" title="وكالات قريبة من الانتهاء">
            {p.poas.length === 0 ? <p className="text-[13.5px] text-muted">لا وكالات تنتهي خلال نافذة التنبيه.</p> : (
              <ul className="divide-y divide-rule">
                {p.poas.map((v) => (
                  <li key={v.id} className="flex items-center justify-between gap-3 py-2">
                    <div className="min-w-0">
                      <Link href={`/office/clients/${v.clientId}?tab=poa`} className="block truncate font-medium hover:underline underline-offset-4">{v.client}</Link>
                      <p className="text-[12.5px] text-muted">وكالة <Num>{v.number}</Num> · تنتهي <DateText value={v.expires} /></p>
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
