import { router } from '@inertiajs/react';
import { ChevronDown, Eye, EyeOff, Pencil, Plus, Trash2 } from 'lucide-react';
import { ReactNode, useState } from 'react';
import { CountUp } from '@/Components/motion';
import { IconCase, IconExecution, IconHearing, IconReports, IconTasks } from '@/Components/Icons';
import { DiamondRule } from '@/Components/LegalArt';
import { StageDots } from '@/Components/StagePath';
import { Button, cx, DateText, Num, TextArea, TextField } from '@/Components/ui';

export type ReportItem = { id: number; on: string; hijri: string; title: string; details: string | null; source: 'auto' | 'manual'; matter: string };
export type ReportMatter = { key: string; kind: 'case' | 'execution'; id: number; title: string; number: string | null; court: string | null; stage: string | null; stageLabel: string | null;
  status: string | null; statusKey: string; hidden?: boolean; items: ReportItem[] };
export type Report = {
  id: number; title: string; period: string; periodLabel: string; start: string; end: string; startHijri: string; endHijri: string;
  client: { id: number; name: string }; intro: string | null; nextSteps: string | null; status: 'draft' | 'published'; statusLabel: string;
  publishedAt: string | null; lockVersion: number; matters: ReportMatter[]; general: ReportItem[];
  stats: { cases: number; executions: number; actions: number; hearings: number };
};

/** يعزل التواريخ والأرقام داخل النص العربي حتى لا ينقلب ترتيبها (2026-11-15 13:00) */
function Bidi({ text }: { text: string }) {
  const parts = text.split(/(\d[\d\-:\/.,]*(?:\s\d[\d\-:\/.,]*)*)/u);
  return <>{parts.map((p, i) => (i % 2 ? <bdi key={i} dir="ltr" className="num">{p}</bdi> : p))}</>;
}

/** نموذج إضافة/تعديل إجراء — للمحامي فقط */
function ItemForm({ reportId, item, matter, matters, onDone, start, end }: { reportId: number; item?: ReportItem; matter: string; matters: ReportMatter[]; onDone: () => void; start: string; end: string }) {
  const [d, setD] = useState({ matter_key: item?.matter ?? matter, occurred_on: item?.on ?? end, title: item?.title ?? '', details: item?.details ?? '' });
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
      <TextField label="التاريخ" type="date" value={d.occurred_on} onChange={(e) => setD({ ...d, occurred_on: e.target.value })} error={errors.occurred_on} hint={`الفترة: ${start} — ${end}`} />
      <label className="block">
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

/** خط زمني للإجراءات: تاريخ ميلادي وهجري، عقدة معيّن على مسار، والعنوان والتفاصيل */
function Timeline({ items, editable, onEdit }: { items: ReportItem[]; editable: boolean; onEdit: (i: ReportItem) => void }) {
  return (
    <ol className="relative">
      <span aria-hidden className="absolute bottom-3 top-3 start-[118px] w-px bg-line sm:start-[142px]" />
      {items.map((it, idx) => (
        <li key={it.id} className="group relative grid grid-cols-[104px_28px_1fr] items-start gap-x-1 py-2.5 sm:grid-cols-[128px_28px_1fr]" style={{ animationDelay: `${idx * 40}ms` }}>
          <span className="pt-0.5 text-[12.5px] leading-tight">
            <bdi dir="ltr" className="num block font-semibold text-ink">{it.on}</bdi>
            <span className="block text-[11.5px] text-muted">{it.hijri}</span>
          </span>
          <span className="flex justify-center pt-1.5"><span className="diamond size-2.5 bg-ink text-ink ring-4 ring-surface" aria-hidden /></span>
          <span className="min-w-0">
            <span className="flex items-start gap-2">
              <span className="flex-1 font-medium text-text"><Bidi text={it.title} /></span>
              {editable && (
                <span className="flex shrink-0 gap-0.5 opacity-100 transition-opacity duration-150 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                  {it.source === 'manual' && <span className="pill pill-info !h-5">يدوي</span>}
                  <button type="button" className="btn btn-ghost btn-sm h-7 px-1.5" onClick={() => onEdit(it)} aria-label={`تعديل: ${it.title}`}><Pencil className="size-3.5" /></button>
                  <button type="button" className="btn btn-ghost btn-sm h-7 px-1.5 text-negative" aria-label={`حذف: ${it.title}`}
                    onClick={() => { if (confirm('حذف هذا الإجراء من التقرير؟')) router.delete(`/office/client-report-items/${it.id}`, { preserveScroll: true }); }}><Trash2 className="size-3.5" /></button>
                </span>
              )}
            </span>
            {it.details && <span className="mt-0.5 block whitespace-pre-line text-[13.5px] leading-7 text-muted"><Bidi text={it.details} /></span>}
          </span>
        </li>
      ))}
    </ol>
  );
}

function MatterCard({ m, editable, report, hidden, onToggleHidden }: { m: ReportMatter; editable: boolean; report: Report; hidden: boolean; onToggleHidden: () => void }) {
  const [open, setOpen] = useState(true);
  const [form, setForm] = useState<ReportItem | 'new' | null>(null);
  const Icon = m.kind === 'case' ? IconCase : IconExecution;
  return (
    <article className={cx('lift overflow-hidden rounded-[18px] border border-line bg-surface', hidden && 'opacity-55')}>
      <header className="flex flex-wrap items-center gap-3 bg-ink px-5 py-3.5 text-white">
        <span className="grid size-9 shrink-0 rotate-45 place-items-center rounded-[9px] bg-white/12 ring-1 ring-white/25"><span className="-rotate-45"><Icon size={18} /></span></span>
        <div className="min-w-0 flex-1">
          <h3 className="font-semibold leading-snug">{m.title}</h3>
          <p className="text-[12.5px] text-white/75">
            {m.number && <>رقم {m.kind === 'case' ? 'القضية' : 'الطلب'} <Num>{m.number}</Num> · </>}{m.court && <>{m.court} · </>}{m.stageLabel ?? m.status}
          </p>
        </div>
        <span className="rounded-md bg-white px-2 py-0.5 text-[12px] font-medium text-ink">{m.status}</span>
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
        <div className="detail-swap px-5 pb-4 pt-3">
          {m.kind === 'case' && m.stage && <div className="mb-2 flex items-center gap-3 text-[12.5px] text-muted"><StageDots current={m.stage} /><span>المرحلة: {m.stageLabel}</span></div>}
          {m.items.length ? <Timeline items={m.items} editable={editable} onEdit={(i) => setForm(i)} />
            : <p className="py-3 text-[13.5px] text-muted">لا إجراءات جديدة خلال هذه الفترة — الملف في مرحلة «{m.stageLabel ?? m.status}».</p>}
          {editable && (form ? <ItemForm reportId={report.id} item={form === 'new' ? undefined : form} matter={m.key} matters={report.matters} start={report.start} end={report.end} onDone={() => setForm(null)} />
            : <button type="button" className="btn btn-ghost btn-sm mt-1" onClick={() => setForm('new')}><Plus className="size-4" />إضافة إجراء لهذا الملف</button>)}
        </div>
      )}
    </article>
  );
}

function Note({ title, icon, children }: { title: string; icon: ReactNode; children: ReactNode }) {
  return (
    <section className="rounded-[18px] border border-line bg-surface p-5">
      <h2 className="mb-2 flex items-center gap-2 text-[16px] font-semibold text-ink">{icon}{title}</h2>
      {children}
    </section>
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
  const stats: [string, number, ReactNode][] = [
    ['قضايا مشمولة', report.stats.cases, <IconCase key="c" size={18} />], ['طلبات تنفيذ', report.stats.executions, <IconExecution key="e" size={18} />],
    ['إجراءات خلال الفترة', report.stats.actions, <IconTasks key="t" size={18} />], ['جلسات', report.stats.hearings, <IconHearing key="h" size={18} />],
  ];

  return (
    <div className="stagger space-y-5">
      {/* الواجهة: هوية المكتب، عنوان التقرير والفترة، والأرقام */}
      <section className="mist relative overflow-hidden rounded-[22px] px-5 pb-6 pt-6 sm:px-8">
        <div className="flex flex-wrap items-start justify-between gap-5">
          <div className="min-w-0 flex-1 basis-[24rem]">
            <p className="flex items-center gap-2 text-[13px] text-muted"><img src="/brand/logo-mark.webp" alt="" className="h-7 w-auto" />تقرير {report.periodLabel} عن سير الأعمال</p>
            {editable ? (
              <input aria-label="عنوان التقرير" className="mt-2 w-full rounded-[10px] border border-transparent bg-transparent px-1 text-[26px] font-semibold leading-tight text-ink outline-none transition-colors hover:border-control focus:border-ink focus:bg-white sm:text-[30px]"
                value={texts.title} onChange={(e) => { setTexts({ ...texts, title: e.target.value }); setDirty(true); }} maxLength={200} />
            ) : <h1 className="mt-2 text-[26px] font-semibold leading-tight text-ink sm:text-[30px]">{report.title}</h1>}
            <p className="mt-2 text-[14px] text-text">الفترة من <DateText value={report.start} /> إلى <DateText value={report.end} /></p>
          </div>
          <div className="w-full rounded-[16px] border border-ink/10 bg-white/80 px-4 py-3 sm:w-auto sm:min-w-[240px]">
            <p className="text-[12px] text-muted">مقدَّم إلى</p>
            <p className="font-semibold text-ink">{report.client.name}</p>
            <p className="mt-1.5 text-[12px] text-muted">من</p>
            <p className="text-[13.5px] font-medium text-ink">مكتب فيصل حميضان الحميضان للمحاماة</p>
          </div>
        </div>
        <dl className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
          {stats.map(([l, v, ic]) => (
            <div key={l} className="rounded-[14px] border border-ink/10 bg-white/85 px-4 py-3">
              <dt className="flex items-center gap-1.5 text-[12.5px] text-muted">{ic}{l}</dt>
              <dd className="mt-1 text-[30px] font-semibold leading-none text-ink"><CountUp value={v} /></dd>
            </div>
          ))}
        </dl>
        <div className="mt-5 flex flex-wrap gap-2">
          <a href={pdfHref} className="btn btn-primary"><IconReports size={18} />تنزيل PDF</a>
          {toolbar}
        </div>
      </section>

      {(editable || report.intro) && (
        <Note title="ملخص الفترة" icon={<IconReports size={19} />}>
          {editable ? <TextArea label="ملخص يكتبه المكتب للعميل" rows={4} value={texts.intro} onChange={(e) => { setTexts({ ...texts, intro: e.target.value }); setDirty(true); }}
            placeholder="مثل: واصل المكتب خلال هذا الشهر متابعة قضاياكم... " maxLength={5000} />
            : <p className="whitespace-pre-line text-[15px] leading-8 text-text">{report.intro}</p>}
        </Note>
      )}

      {[['القضايا', cases, IconCase], ['طلبات التنفيذ', execs, IconExecution]].map(([label, list, Ic]) => (list as ReportMatter[]).length > 0 && (
        <section key={label as string}>
          <h2 className="mb-1 flex items-center gap-2 text-[18px] font-semibold">{(() => { const I = Ic as typeof IconCase; return <I size={20} />; })()}{label as string}</h2>
          <DiamondRule className="mb-4 max-w-[220px]" />
          <div className="stagger grid gap-4">
            {(list as ReportMatter[]).map((m) => <MatterCard key={m.key} m={m} report={report} editable={editable} hidden={hidden.includes(m.key)} onToggleHidden={() => toggle(m.key)} />)}
          </div>
        </section>
      ))}

      {(report.general.length > 0 || editable) && (
        <Note title="أعمال أخرى لصالحكم" icon={<IconTasks size={19} />}>
          {report.general.length ? <Timeline items={report.general} editable={editable} onEdit={(i) => setGeneralForm(i)} /> : <p className="text-[13.5px] text-muted">أضف هنا أي عمل أنجزته للعميل خارج المنصة أو خارج ملف محدد.</p>}
          {editable && (generalForm ? <ItemForm reportId={report.id} item={generalForm === 'new' ? undefined : generalForm} matter="general" matters={report.matters} start={report.start} end={report.end} onDone={() => setGeneralForm(null)} />
            : <button type="button" className="btn btn-ghost btn-sm mt-1" onClick={() => setGeneralForm('new')}><Plus className="size-4" />إضافة عمل</button>)}
        </Note>
      )}

      {(editable || report.nextSteps) && (
        <Note title="الخطوات القادمة" icon={<IconTasks size={19} />}>
          {editable ? <TextArea label="ما سيعمل عليه المكتب في الفترة القادمة" rows={3} value={texts.next_steps} onChange={(e) => { setTexts({ ...texts, next_steps: e.target.value }); setDirty(true); }} maxLength={5000} />
            : <p className="whitespace-pre-line text-[15px] leading-8 text-text">{report.nextSteps}</p>}
        </Note>
      )}

      <footer className="flex items-center justify-between gap-4 rounded-[18px] border border-line bg-surface px-5 py-4">
        <div><p className="font-semibold text-ink">مكتب فيصل حميضان الحميضان للمحاماة والاستشارات القانونية</p><p className="text-[13px] text-muted">نسعد بالرد على استفساراتكم حول أي إجراء ورد في هذا التقرير.</p></div>
        <img src="/brand/logo-mark.webp" alt="" className="h-12 w-auto opacity-90" />
      </footer>

      {editable && dirty && (
        <div className="sheet-panel fixed inset-x-0 bottom-[calc(64px+env(safe-area-inset-bottom,0px))] z-40 mx-auto flex w-[min(560px,calc(100%-24px))] items-center justify-between gap-3 rounded-[16px] bg-ink px-4 py-3 text-white shadow-[0_16px_40px_-16px_rgba(35,42,51,.6)] lg:bottom-6">
          <span className="text-[14px]">لديك تعديلات غير محفوظة على النصوص</span>
          <Button variant="secondary" size="sm" loading={saving} onClick={() => save()}>حفظ</Button>
        </div>
      )}
    </div>
  );
}
