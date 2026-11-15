import { router } from '@inertiajs/react';
import { ChevronDown, Eye, EyeOff, ImagePlus, Pencil, Plus, Trash2 } from 'lucide-react';
import { ReactNode, useState } from 'react';
import { CountUp } from '@/Components/motion';
import { IconCase, IconExecution, IconHearing, IconReports, IconTasks } from '@/Components/Icons';
import { DiamondRule } from '@/Components/LegalArt';
import { StageDots } from '@/Components/StagePath';
import { Button, cx, DateText, Num, Pill, TextArea, TextField } from '@/Components/ui';
import { money } from '@/lib/format';

const cur = (c: string) => (c === 'SAR' ? 'ريال' : c);
/** المبالغ المحصّلة بعملاتها: «12,500.00 ريال» */
function Amounts({ list }: { list: Amount[] }) {
  return <>{list.map((a, i) => <span key={a.currency}>{i > 0 && ' + '}<bdi dir="ltr" className="num">{money(a.amount)}</bdi> {cur(a.currency)}</span>)}</>;
}

export type ReportItem = { id: number; on: string; title: string; details: string | null; source: 'auto' | 'manual'; matter: string };
export type ReportMatter = { key: string; kind: 'case' | 'execution'; id: number; title: string; number: string | null; court: string | null; stage: string | null; stageLabel: string | null;
  status: string | null; statusKey: string; hidden?: boolean; items: ReportItem[]; collected?: Amount[] };
export type Amount = { currency: string; amount: string };
export type Report = {
  id: number; title: string; period: string; periodLabel: string; start: string; end: string;
  client: { id: number; name: string; logo?: string | null }; intro: string | null; nextSteps: string | null; status: 'draft' | 'closed' | 'published'; statusLabel: string; closedAt?: string | null;
  publishedAt: string | null; lockVersion: number; matters: ReportMatter[]; general: ReportItem[];
  stats: { cases: number; executions: number; actions: number; hearings: number; collected?: Amount[] };
};

/** يعزل التواريخ والأرقام داخل النص العربي حتى لا ينقلب ترتيبها (2026-11-15 13:00) */
function Bidi({ text }: { text: string }) {
  const parts = text.split(/(\d[\d\-:\/.,]*(?:\s\d[\d\-:\/.,]*)*)/u);
  return <>{parts.map((p, i) => (i % 2 ? <bdi key={i} dir="ltr" className="num">{p}</bdi> : p))}</>;
}

/** نموذج إضافة/تعديل إجراء — للمحامي فقط */
function ItemForm({ reportId, item, matter, matters, onDone }: { reportId: number; item?: ReportItem; matter: string; matters: ReportMatter[]; onDone: () => void }) {
  const [d, setD] = useState({ matter_key: item?.matter ?? matter, title: item?.title ?? '', details: item?.details ?? '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const submit = () => {
    setBusy(true);
    const opts = { preserveScroll: true, onSuccess: onDone, onError: (e: Record<string, string>) => setErrors(e), onFinish: () => setBusy(false) };
    if (item) router.put(`/office/client-report-items/${item.id}`, d, opts); else router.post(`/office/client-reports/${reportId}/items`, d, opts);
  };
  return (
    <div className="detail-swap grid gap-3 rounded-[14px] border border-line bg-raised p-4 sm:grid-cols-2">
      <TextField className="sm:col-span-2" label="الإجراء" required value={d.title} onChange={(e) => setD({ ...d, title: e.target.value })} error={errors.title} placeholder="مثل: حضور اجتماع تسوية مع الطرف الآخر" maxLength={300} />
      <label className="block sm:col-span-2">
        <span className="field-label">يتبع</span>
        <select className="input" value={d.matter_key} onChange={(e) => setD({ ...d, matter_key: e.target.value })}>
          {matters.map((m) => <option key={m.key} value={m.key}>{m.title}</option>)}
          <option value="general">أعمال أخرى لصالح العميل</option>
        </select>
      </label>
      <TextArea className="sm:col-span-2" label="تفاصيل للعميل (اختياري)" rows={2} value={d.details} onChange={(e) => setD({ ...d, details: e.target.value })} maxLength={3000} />
      <div className="flex gap-2 sm:col-span-2">
        <Button variant="primary" loading={busy} onClick={submit}>{item ? 'حفظ التعديل' : 'إضافة الإجراء'}</Button>
        <Button variant="ghost" onClick={onDone}>إلغاء</Button>
      </div>
    </div>
  );
}

/** قائمة الأعمال المنجزة خلال الفترة — بلا تواريخ، معيّن صغير لكل عمل */
function WorkList({ items, editable, onEdit }: { items: ReportItem[]; editable: boolean; onEdit: (i: ReportItem) => void }) {
  return (
    <ul className="divide-y divide-line/70">
      {items.map((it) => (
        <li key={it.id} className="group flex items-start gap-2.5 py-1.5">
          <span className="diamond mt-[9px] size-1.5 shrink-0 bg-ink text-ink" aria-hidden />
          <span className="min-w-0 flex-1">
            <span className="flex items-start gap-2">
              <span className="flex-1 text-[14px] leading-7 text-text"><Bidi text={it.title} /></span>
              {editable && (
                <span className="flex shrink-0 gap-0.5 opacity-100 transition-opacity duration-150 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                  {it.source === 'manual' && <span className="pill pill-info !h-5">يدوي</span>}
                  <button type="button" className="btn btn-ghost btn-sm h-7 px-1.5" onClick={() => onEdit(it)} aria-label={`تعديل: ${it.title}`}><Pencil className="size-3.5" /></button>
                  <button type="button" className="btn btn-ghost btn-sm h-7 px-1.5 text-negative" aria-label={`حذف: ${it.title}`}
                    onClick={() => { if (confirm('حذف هذا العمل من التقرير؟')) router.delete(`/office/client-report-items/${it.id}`, { preserveScroll: true }); }}><Trash2 className="size-3.5" /></button>
                </span>
              )}
            </span>
            {it.details && <span className="block whitespace-pre-line text-[12.5px] leading-6 text-muted"><Bidi text={it.details} /></span>}
          </span>
        </li>
      ))}
    </ul>
  );
}

function MatterCard({ m, editable, report, hidden, onToggleHidden }: { m: ReportMatter; editable: boolean; report: Report; hidden: boolean; onToggleHidden: () => void }) {
  const [open, setOpen] = useState(true);
  const [form, setForm] = useState<ReportItem | 'new' | null>(null);
  const Icon = m.kind === 'case' ? IconCase : IconExecution;
  return (
    <article className={cx('overflow-hidden rounded-[16px] border border-line bg-surface', hidden && 'opacity-55')}>
      <header className="flex flex-wrap items-center gap-x-3 gap-y-1.5 bg-ink px-4 py-2.5 text-white">
        <span className="shrink-0 text-white/70"><Icon size={17} /></span>
        <div className="min-w-0 flex-1">
          <h3 className="text-[14.5px] font-semibold leading-snug">{m.title}</h3>
          <p className="text-[12px] text-white/70">
            {m.number && <>رقم {m.kind === 'case' ? 'القضية' : 'الطلب'} <Num>{m.number}</Num> · </>}{m.court && <>{m.court} · </>}{m.stageLabel ?? m.status}
          </p>
        </div>
        <span className="rounded-md bg-white/12 px-2 py-0.5 text-[12px] text-white/90">{m.status}</span>
        {editable && (
          <button type="button" onClick={onToggleHidden} className="btn btn-sm h-8 gap-1.5 bg-white/12 px-2.5 text-white hover:bg-white/20" aria-pressed={hidden}>
            {hidden ? <><EyeOff className="size-4" />مخفي عن العميل</> : <><Eye className="size-4" />ظاهر للعميل</>}
          </button>
        )}
        <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="btn btn-sm h-8 bg-white/12 px-2 text-white hover:bg-white/20" aria-label={open ? 'طيّ الإجراءات' : 'إظهار الإجراءات'}>
          <ChevronDown className={cx('size-4 transition-transform duration-200', open && 'rotate-180')} />
        </button>
      </header>
      {open && (
        <div className="detail-swap px-4 pb-3 pt-2.5">
          {m.kind === 'case' && m.stage && <div className="mb-2 flex items-center gap-3 text-[12.5px] text-muted"><StageDots current={m.stage} /><span>المرحلة: {m.stageLabel}</span></div>}
          <WorkList items={m.items} editable={editable} onEdit={(i) => setForm(i)} />
          {m.collected && m.collected.length > 0 && (
            <p className="mt-2 inline-flex items-center gap-2 rounded-[10px] bg-[var(--positive-soft)] px-3 py-1.5 text-[13.5px] text-positive">المحصَّل خلال الفترة: <strong className="font-semibold"><Amounts list={m.collected} /></strong></p>
          )}
          {editable && (form ? <ItemForm reportId={report.id} item={form === 'new' ? undefined : form} matter={m.key} matters={report.matters} onDone={() => setForm(null)} />
            : <button type="button" className="btn btn-ghost btn-sm mt-1" onClick={() => setForm('new')}><Plus className="size-4" />إضافة عمل لهذا الملف</button>)}
        </div>
      )}
    </article>
  );
}

function Note({ title, icon, children }: { title: string; icon: ReactNode; children: ReactNode }) {
  return (
    <section className="rounded-[16px] border border-line bg-surface px-5 py-4">
      <h2 className="mb-2 flex items-center gap-2 text-[15px] font-semibold text-ink">{icon}{title}</h2>
      {children}
    </section>
  );
}

/** الفترة: تاريخ ميلادي فقط، وللمحامي تعديل البداية والنهاية */
function PeriodLine({ report, editable }: { report: Report; editable: boolean }) {
  const [open, setOpen] = useState(false);
  const [d, setD] = useState({ period: report.period, start: report.start, end: report.end });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const save = () => {
    setBusy(true);
    router.put(`/office/client-reports/${report.id}/period`, d, { preserveScroll: true, onSuccess: () => { setOpen(false); setErrors({}); }, onError: setErrors, onFinish: () => setBusy(false) });
  };
  const types: [string, string][] = [['weekly', 'أسبوعي'], ['monthly', 'شهري'], ['yearly', 'سنوي'], ['custom', 'فترة محددة']];
  return (
    <div className="mt-1.5">
      <p className="flex flex-wrap items-center gap-x-2 text-[13.5px] text-text">
        <span>الفترة من <bdi dir="ltr" className="num font-medium">{report.start}</bdi> إلى <bdi dir="ltr" className="num font-medium">{report.end}</bdi></span>
        {editable && !open && <button type="button" className="btn btn-ghost btn-sm h-7 px-2 text-[12.5px]" onClick={() => setOpen(true)}><Pencil className="size-3.5" />تعديل الفترة</button>}
      </p>
      {open && (
        <div className="detail-swap mt-3 grid gap-3 rounded-[14px] border border-line bg-white p-4 sm:grid-cols-[auto_auto_auto] sm:items-end">
          <label className="block sm:col-span-3">
            <span className="field-label">نوع التقرير</span>
            <select className="input max-w-[220px]" value={d.period} onChange={(e) => setD({ ...d, period: e.target.value })}>
              {types.map(([k, l]) => <option key={k} value={k}>{l}</option>)}
            </select>
          </label>
          <TextField label="من تاريخ" type="date" value={d.start} onChange={(e) => setD({ ...d, start: e.target.value })} error={errors.start} />
          <TextField label="إلى تاريخ" type="date" value={d.end} onChange={(e) => setD({ ...d, end: e.target.value })} error={errors.end} />
          <div className="flex gap-2">
            <Button variant="primary" loading={busy} onClick={save}>حفظ الفترة</Button>
            <Button variant="ghost" onClick={() => setOpen(false)}>إلغاء</Button>
          </div>
          <p className="text-[12.5px] text-muted sm:col-span-3">تُحذف الأعمال المولَّدة التي تخرج عن الفترة الجديدة، وتدخل أعمال الأيام المضافة تلقائيًا. إضافاتك اليدوية تبقى.</p>
        </div>
      )}
    </div>
  );
}

/** العميل: اسمه وشعاره. للمحامي رفع الشعار أو تغييره أو إزالته (يُحفظ على خادم المكتب فقط) */
function ClientMark({ report, editable }: { report: Report; editable: boolean }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const url = `/office/clients/${report.client.id}/logo`;
  const upload = (f: File | undefined) => {
    if (!f) return;
    setBusy(true); setError('');
    router.post(url, { logo: f }, { forceFormData: true, preserveScroll: true, onError: (e) => setError(e.logo ?? 'تعذّر رفع الشعار.'), onFinish: () => setBusy(false) });
  };
  const logo = report.client.logo;
  return (
    <div className="flex items-center gap-3">
      {logo ? <img src={logo} alt={`شعار ${report.client.name}`} className="h-14 max-w-[150px] rounded-[10px] bg-white object-contain p-1.5 ring-1 ring-ink/10" /> : null}
      <div className="min-w-0">
        <p className="text-[15px] font-semibold leading-snug text-ink">{report.client.name}</p>
        {editable && (
          <div className="mt-1 flex flex-wrap items-center gap-1">
            <label className={cx('btn btn-ghost btn-sm h-7 cursor-pointer px-2 text-[12.5px]', busy && 'pointer-events-none opacity-60')}>
              <ImagePlus className="size-3.5" />{busy ? 'جارٍ الرفع…' : logo ? 'تغيير الشعار' : 'إضافة شعار العميل'}
              <input type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => { upload(e.target.files?.[0]); e.target.value = ''; }} />
            </label>
            {logo && <button type="button" className="btn btn-ghost btn-sm h-7 px-2 text-[12.5px] text-negative" onClick={() => { if (confirm('إزالة شعار العميل؟')) router.delete(url, { preserveScroll: true }); }}>إزالة</button>}
          </div>
        )}
        {error && <p role="alert" className="mt-1 text-[12.5px] text-negative">{error}</p>}
      </div>
    </div>
  );
}

/**
 * عرض التقرير التفاعلي — نفس البنية للمحامي (قابل للتحرير) وللعميل (للاطلاع).
 * لا يُعرض للعميل إلا ما اعتمده المحامي، والملفات المخفية لا تصل إليه أصلًا.
 */
export function ReportView({ report, editable = false, pdfHref, toolbar }: { report: Report; editable?: boolean; pdfHref: string; toolbar?: ReactNode }) {
  const [hidden, setHidden] = useState<string[]>(report.matters.filter((m) => m.hidden).map((m) => m.key));
  const [texts, setTexts] = useState({ title: report.title, intro: report.intro ?? '', next_steps: report.nextSteps ?? '' });
  const [dirty, setDirty] = useState(false);
  const [saving, setSaving] = useState(false);
  const [generalForm, setGeneralForm] = useState<ReportItem | 'new' | null>(null);
  const save = (h = hidden) => {
    setSaving(true);
    router.put(`/office/client-reports/${report.id}`, { ...texts, hidden: h, lock_version: report.lockVersion }, { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) });
  };
  const toggle = (key: string) => { const h = hidden.includes(key) ? hidden.filter((k) => k !== key) : [...hidden, key]; setHidden(h); save(h); };
  const cases = report.matters.filter((m) => m.kind === 'case');
  const execs = report.matters.filter((m) => m.kind === 'execution');
  const collected = report.stats.collected ?? [];
  const stats: [string, number, ReactNode][] = [
    ['قضايا', report.stats.cases, <IconCase key="c" size={16} />], ['طلبات تنفيذ', report.stats.executions, <IconExecution key="e" size={16} />],
    ['أعمال منجزة', report.stats.actions, <IconTasks key="t" size={16} />], ['جلسات', report.stats.hearings, <IconHearing key="h" size={16} />],
  ];

  return (
    <div className="stagger space-y-4">
      {/* الواجهة: العنوان والفترة، والعميل بشعاره، والأرقام في شريط واحد */}
      <section className="mist relative overflow-hidden rounded-[20px] px-5 py-5 sm:px-7">
        <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
          <div className="min-w-0 flex-1 basis-[22rem]">
            <p className="text-[12.5px] text-muted">{report.period === 'custom' ? 'تقرير سير الأعمال' : `تقرير ${report.periodLabel} عن سير الأعمال`}</p>
            {editable ? (
              <input aria-label="عنوان التقرير" className="-mx-1 mt-1 w-full rounded-[8px] border border-transparent bg-transparent px-1 text-[20px] font-semibold leading-snug text-ink outline-none transition-colors hover:border-control focus:border-ink focus:bg-white sm:text-[22px]"
                value={texts.title} onChange={(e) => { setTexts({ ...texts, title: e.target.value }); setDirty(true); }} maxLength={200} />
            ) : <h1 className="mt-1 text-[20px] font-semibold leading-snug text-ink sm:text-[22px]">{report.title}</h1>}
            <PeriodLine report={report} editable={editable && report.status !== 'published'} />
          </div>
          <ClientMark report={report} editable={editable} />
        </div>
        <dl className="mt-4 flex flex-wrap items-stretch gap-2">
          {stats.map(([l, v, ic]) => (
            <div key={l} className="flex items-center gap-2 rounded-[12px] border border-ink/10 bg-white/85 px-3 py-2">
              <span className="text-muted">{ic}</span>
              <dd className="text-[18px] font-semibold leading-none text-ink"><CountUp value={v} /></dd>
              <dt className="text-[12.5px] text-muted">{l}</dt>
            </div>
          ))}
          {collected.length > 0 && (
            <div className="flex items-center gap-2 rounded-[12px] border border-positive/25 bg-[var(--positive-soft)] px-3 py-2">
              <dt className="text-[12.5px] text-muted">المحصَّل</dt>
              <dd className="text-[15px] font-semibold leading-none text-positive"><Amounts list={collected} /></dd>
            </div>
          )}
          <div className="ms-auto flex items-center gap-2">
            <a href={pdfHref} className="btn btn-primary btn-sm"><IconReports size={16} />تنزيل PDF</a>
            {toolbar}
          </div>
        </dl>
      </section>

      {(editable || report.intro) && (
        <Note title="ملخص الفترة" icon={<IconReports size={19} />}>
          {editable ? <TextArea label="ملخص يكتبه المكتب للعميل" rows={4} value={texts.intro} onChange={(e) => { setTexts({ ...texts, intro: e.target.value }); setDirty(true); }}
            placeholder="مثل: واصل المكتب خلال هذا الشهر متابعة قضاياكم... " maxLength={5000} />
            : <p className="whitespace-pre-line text-[14px] leading-7 text-text">{report.intro}</p>}
        </Note>
      )}

      {[['القضايا', cases, IconCase], ['طلبات التنفيذ', execs, IconExecution]].map(([label, list, Ic]) => (list as ReportMatter[]).length > 0 && (
        <section key={label as string}>
          <h2 className="mb-1 flex items-center gap-2 text-[15.5px] font-semibold">{(() => { const I = Ic as typeof IconCase; return <I size={20} />; })()}{label as string}</h2>
          <DiamondRule className="mb-3 max-w-[160px]" />
          <div className="stagger grid gap-3">
            {(list as ReportMatter[]).map((m) => <MatterCard key={m.key} m={m} report={report} editable={editable} hidden={hidden.includes(m.key)} onToggleHidden={() => toggle(m.key)} />)}
          </div>
        </section>
      ))}

      {(report.general.length > 0 || editable) && (
        <Note title="أعمال أخرى" icon={<IconTasks size={19} />}>
          {report.general.length ? <WorkList items={report.general} editable={editable} onEdit={(i) => setGeneralForm(i)} /> : <p className="text-[13.5px] text-muted">أضف هنا أي عمل أنجزته للعميل خارج المنصة أو خارج ملف محدد.</p>}
          {editable && (generalForm ? <ItemForm reportId={report.id} item={generalForm === 'new' ? undefined : generalForm} matter="general" matters={report.matters} onDone={() => setGeneralForm(null)} />
            : <button type="button" className="btn btn-ghost btn-sm mt-1" onClick={() => setGeneralForm('new')}><Plus className="size-4" />إضافة عمل</button>)}
        </Note>
      )}

      {(editable || report.nextSteps) && (
        <Note title="الخطوات القادمة" icon={<IconTasks size={19} />}>
          {editable ? <TextArea label="ما سيعمل عليه المكتب في الفترة القادمة" rows={3} value={texts.next_steps} onChange={(e) => { setTexts({ ...texts, next_steps: e.target.value }); setDirty(true); }} maxLength={5000} />
            : <p className="whitespace-pre-line text-[14px] leading-7 text-text">{report.nextSteps}</p>}
        </Note>
      )}


      {editable && dirty && (
        <div className="sheet-panel fixed inset-x-0 bottom-[calc(64px+env(safe-area-inset-bottom,0px))] z-40 mx-auto flex w-[min(560px,calc(100%-24px))] items-center justify-between gap-3 rounded-[16px] bg-ink px-4 py-3 text-white shadow-[0_16px_40px_-16px_rgba(35,42,51,.6)] lg:bottom-6">
          <span className="text-[14px]">لديك تعديلات غير محفوظة على النصوص</span>
          <Button variant="secondary" size="sm" loading={saving} onClick={() => save()}>حفظ</Button>
        </div>
      )}
    </div>
  );
}

/** شارة الحالة: المسودة المفتوحة تنبض بنقطة حية لأنها تتحدث تلقائيًا */
export function StatusPill({ status, label }: { status: string; label: string }) {
  if (status === 'draft') {
    return (
      <Pill tone="warning" className="inline-flex items-center gap-1.5">
        <span className="pulse-dot inline-block size-2 rounded-full bg-current" aria-hidden />{label} — تتحدث تلقائيًا
      </Pill>
    );
  }
  return <Pill tone={status === 'published' ? 'positive' : 'neutral'}>{label}</Pill>;
}

