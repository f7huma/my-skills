import { router } from '@inertiajs/react';
import { ChevronDown, Eye, EyeOff, ImagePlus, Pencil, Plus, Trash2, X } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import { CountUp } from '@/Components/motion';
import { IconCase, IconClients, IconExecution, IconReports, IconSettlement, IconTasks, IconUsers } from '@/Components/Icons';
import { DiamondRule } from '@/Components/LegalArt';
import { StageDots } from '@/Components/StagePath';
import { Button, cx, Pill, TextArea, TextField } from '@/Components/ui';
import { money } from '@/lib/format';

export type Amount = { currency: string; amount: string };
export type Fact = { label: string; value: string };
export type WorkItem = { id: number; kind: string | null; headline: string; facts: Fact[]; notes: string | null; fields: Record<string, string>; source: 'auto' | 'manual' };
export type WorkGroup = { key: string; label: string; items: WorkItem[] };
export type ReportMatter = { key: string; kind: 'case' | 'execution'; id: number; title: string; number: string | null; court: string | null; stage: string | null; stageLabel: string | null;
  status: string | null; statusKey: string; hidden?: boolean; items: WorkItem[]; collected?: Amount[] };
export type Report = {
  id: number; title: string; period: string; periodLabel: string; start: string; end: string;
  client: { id: number; name: string; logo?: string | null }; intro: string | null; nextSteps: string | null; status: 'draft' | 'closed' | 'published'; statusLabel: string; closedAt?: string | null;
  publishedAt: string | null; lockVersion: number; autoSync: boolean;
  groups: WorkGroup[]; platform: { matters: ReportMatter[]; general: WorkItem[]; count: number };
  stats: { total: number; groups: { key: string; label: string; count: number }[]; collected: Amount[] };
};
export type CatalogField = { key: string; label: string; type: 'text' | 'select' | 'money'; required: boolean; when: string[] | null; options: { value: string; label: string }[] | null };
export type CatalogKind = { key: string; label: string; fields: CatalogField[] };
export type Catalog = { key: string; label: string; kinds: CatalogKind[] }[];

const GROUP_ICON: Record<string, typeof IconCase> = {
  conciliation: IconSettlement, litigation: IconCase, execution: IconExecution, collection: IconClients, meetings: IconUsers, other: IconTasks,
};
function GroupIcon({ g, size = 18 }: { g: string; size?: number }) { const I = GROUP_ICON[g] ?? IconTasks; return <I size={size} />; }

const cur = (c: string) => (c === 'SAR' ? 'ريال' : c);
/** المبالغ المحصّلة بعملاتها: «12,500.00 ريال» */
function Amounts({ list }: { list: Amount[] }) {
  return <>{list.map((a, i) => <span key={a.currency}>{i > 0 && ' + '}<bdi dir="ltr" className="num">{money(a.amount)}</bdi> {cur(a.currency)}</span>)}</>;
}


/** يعزل التواريخ والأرقام داخل النص العربي حتى لا ينقلب ترتيبها (2026-11-15 13:00) */
function Bidi({ text }: { text: string }) {
  const parts = text.split(/(\d[\d\-:\/.,]*(?:\s\d[\d\-:\/.,]*)*)/u);
  return <>{parts.map((p, i) => (i % 2 ? <bdi key={i} dir="ltr" className="num">{p}</bdi> : p))}</>;
}


/** نموذج عمل منظَّم: حقول النوع المختار، ويظهر من الحقول ما يخص نوع إجراء التنفيذ فقط */
function WorkForm({ reportId, kind, item, suggestions, onDone, onAdded }: {
  reportId: number; kind: CatalogKind; item?: WorkItem; suggestions: Record<string, string[]>; onDone: () => void; onAdded?: () => void;
}) {
  const [fields, setFields] = useState<Record<string, string>>(item?.fields ?? {});
  const [notes, setNotes] = useState(item?.notes ?? '');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const visible = kind.fields.filter((f) => !f.when || f.when.includes(fields.action ?? ''));
  const set = (k: string, v: string) => setFields((x) => ({ ...x, [k]: v }));
  const submit = () => {
    setBusy(true);
    const payload = { kind: kind.key, fields: Object.fromEntries(visible.map((f) => [f.key, fields[f.key] ?? ''])), notes };
    const opts = {
      preserveScroll: true, onError: (e: Record<string, string>) => setErrors(e), onFinish: () => setBusy(false),
      onSuccess: () => {
        setErrors({});
        if (item) { onDone(); return; }
        // إضافة متتالية أسرع: يبقى النوع ورقم الطلب والطرف، ويُفرَّغ الباقي
        setFields((x) => ({ ...(x.number ? { number: x.number } : {}), ...(x.party ? { party: x.party } : {}), ...(x.action ? { action: x.action } : {}) }));
        setNotes(''); onAdded?.();
      },
    };
    if (item) router.put(`/office/client-report-items/${item.id}`, payload, opts); else router.post(`/office/client-reports/${reportId}/items`, payload, opts);
  };
  return (
    <div className="detail-swap rounded-[14px] border border-ink/15 bg-white p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <p className="text-[14.5px] font-semibold text-ink">{item ? 'تعديل: ' : ''}{kind.label}</p>
        <button type="button" onClick={onDone} className="btn btn-ghost btn-sm h-8 px-2" aria-label="إغلاق النموذج"><X className="size-4" /></button>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {visible.map((f) => f.type === 'select' && f.key === 'action' ? (
          <fieldset key={f.key} className="sm:col-span-2 lg:col-span-3">
            <legend className="field-label">{f.label}<span className="text-negative"> *</span></legend>
            <div className="flex flex-wrap gap-1.5">
              {f.options!.map((o) => (
                <button key={o.value} type="button" aria-pressed={fields.action === o.value} onClick={() => set('action', o.value)}
                  className={cx('rounded-full border px-3 py-1.5 text-[13px] transition-colors duration-150', fields.action === o.value ? 'border-ink bg-ink text-white' : 'border-line bg-white hover:border-control')}>{o.label}</button>
              ))}
            </div>
            {errors['fields.action'] && <span role="alert" className="mt-1 block text-[12.5px] text-negative">{errors['fields.action']}</span>}
          </fieldset>
        ) : f.type === 'select' ? (
          <label key={f.key} className="block">
            <span className="field-label">{f.label}{f.required && <span className="text-negative"> *</span>}</span>
            <select className="input" value={fields[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)}>
              <option value="">—</option>
              {f.options!.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            {errors[`fields.${f.key}`] && <span role="alert" className="mt-1 block text-[12.5px] text-negative">{errors[`fields.${f.key}`]}</span>}
          </label>
        ) : (
          <div key={f.key}>
            <TextField label={f.label} required={f.required} value={fields[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)} error={errors[`fields.${f.key}`]}
              inputMode={f.type === 'money' ? 'decimal' : undefined} dir={f.type === 'money' || f.key === 'number' ? 'ltr' : undefined} maxLength={300}
              list={suggestions[f.key]?.length ? `sg-${f.key}` : undefined} autoComplete="off" />
            {suggestions[f.key]?.length ? <datalist id={`sg-${f.key}`}>{suggestions[f.key].map((v) => <option key={v} value={v} />)}</datalist> : null}
          </div>
        ))}
        <TextArea className="sm:col-span-2 lg:col-span-3" label="ملاحظة للعميل (اختياري)" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={3000} />
      </div>
      {errors.kind && <p role="alert" className="mt-2 text-[12.5px] text-negative">{errors.kind}</p>}
      <div className="mt-3 flex gap-2">
        <Button variant="primary" loading={busy} onClick={submit}>{item ? 'حفظ التعديل' : 'إضافة إلى التقرير'}</Button>
        <Button variant="ghost" onClick={onDone}>{item ? 'إلغاء' : 'إغلاق'}</Button>
      </div>
    </div>
  );
}

/** لوحة «إضافة عمل»: أنواع أعمال المكتب مجمّعة حسب المرحلة */
function AddPanel({ report, catalog, suggestions }: { report: Report; catalog: Catalog; suggestions: Record<string, string[]> }) {
  const [kind, setKind] = useState<CatalogKind | null>(null);
  const [added, setAdded] = useState(0);
  return (
    <section className="rounded-[16px] border border-line bg-surface px-4 py-4 sm:px-5" aria-labelledby="add-work">
      <h2 id="add-work" className="mb-3 flex items-center gap-2 text-[15px] font-semibold text-ink"><Plus className="size-4" />إضافة عمل</h2>
      <div className="grid gap-x-5 gap-y-3 md:grid-cols-2 xl:grid-cols-3">
        {catalog.map((g) => (
          <div key={g.key}>
            <p className="mb-1.5 flex items-center gap-1.5 text-[12.5px] text-muted"><GroupIcon g={g.key} size={15} />{g.label}</p>
            <div className="flex flex-wrap gap-1.5">
              {g.kinds.map((k) => (
                <button key={k.key} type="button" onClick={() => { setKind(k); setAdded(0); }} aria-pressed={kind?.key === k.key}
                  className={cx('rounded-[10px] border px-2.5 py-1.5 text-[13px] transition-colors duration-150', kind?.key === k.key ? 'border-ink bg-ink text-white' : 'border-line bg-white text-text hover:border-control')}>
                  {k.label}
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>
      {kind && (
        <div className="mt-4">
          <WorkForm key={kind.key} reportId={report.id} kind={kind} suggestions={suggestions} onDone={() => setKind(null)} onAdded={() => setAdded((n) => n + 1)} />
          {added > 0 && <p className="mt-2 text-[12.5px] text-positive" role="status">أُضيف {added === 1 ? 'عمل واحد' : `${added} أعمال`}. يمكنك إضافة عمل آخر من النوع نفسه مباشرة.</p>}
        </div>
      )}
    </section>
  );
}

/** عمل واحد: العنوان، ثم الحقائق (تسمية: قيمة)، ثم الملاحظة */
function WorkRow({ it, editable, catalog, suggestions }: { it: WorkItem; editable: boolean; catalog?: Catalog; suggestions: Record<string, string[]> }) {
  const [editing, setEditing] = useState(false);
  const kind = useMemo(() => {
    const all = (catalog ?? []).flatMap((g) => g.kinds);
    return all.find((k) => k.key === (it.source === 'auto' ? 'other' : it.kind ?? 'other'));
  }, [catalog, it]);
  if (editing && kind) {
    const fields = it.source === 'auto' ? { action_label: it.headline } : it.fields;
    return <li className="py-2"><WorkForm reportId={0} kind={kind} item={{ ...it, fields }} suggestions={suggestions} onDone={() => setEditing(false)} /></li>;
  }
  return (
    <li className="group flex items-start gap-2.5 py-2">
      <span className="diamond mt-[9px] size-1.5 shrink-0 bg-ink text-ink" aria-hidden />
      <div className="min-w-0 flex-1">
        <div className="flex items-start gap-2">
          <p className="flex-1 text-[14px] font-medium leading-7 text-text"><Bidi text={it.headline} /></p>
          {editable && (
            <span className="flex shrink-0 gap-0.5 opacity-100 transition-opacity duration-150 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
              <button type="button" className="btn btn-ghost btn-sm h-7 px-1.5" onClick={() => setEditing(true)} aria-label={`تعديل: ${it.headline}`}><Pencil className="size-3.5" /></button>
              <button type="button" className="btn btn-ghost btn-sm h-7 px-1.5 text-negative" aria-label={`حذف: ${it.headline}`}
                onClick={() => { if (confirm('حذف هذا العمل من التقرير؟')) router.delete(`/office/client-report-items/${it.id}`, { preserveScroll: true }); }}><Trash2 className="size-3.5" /></button>
            </span>
          )}
        </div>
        {it.facts.length > 0 && (
          <p className="flex flex-wrap gap-x-4 gap-y-0.5 text-[12.5px] leading-6">
            {it.facts.map((f) => <span key={f.label}><span className="text-muted">{f.label}: </span><span className="text-text"><Bidi text={f.value} /></span></span>)}
          </p>
        )}
        {it.notes && <p className="whitespace-pre-line text-[12.5px] leading-6 text-muted"><Bidi text={it.notes} /></p>}
      </div>
    </li>
  );
}

function GroupSection({ g, editable, catalog, suggestions }: { g: WorkGroup; editable: boolean; catalog?: Catalog; suggestions: Record<string, string[]> }) {
  return (
    <section className="rounded-[16px] border border-line bg-surface">
      <header className="flex items-center gap-2.5 border-b border-line px-4 py-2.5 sm:px-5">
        <span className="text-ink"><GroupIcon g={g.key} /></span>
        <h3 className="flex-1 text-[15px] font-semibold text-ink">{g.label}</h3>
        <span className="rounded-full bg-raised px-2.5 py-0.5 text-[12px] tabular-nums text-muted">{g.items.length}</span>
      </header>
      <ul className="divide-y divide-line/70 px-4 sm:px-5">
        {g.items.map((it) => <WorkRow key={it.id} it={it} editable={editable} catalog={catalog} suggestions={suggestions} />)}
      </ul>
    </section>
  );
}

/** ما عكسته المنصة تلقائيًا، مجمّعًا حسب الملف */
function MatterCard({ m, editable, hidden, onToggleHidden, catalog, suggestions }: { m: ReportMatter; editable: boolean; hidden: boolean; onToggleHidden: () => void; catalog?: Catalog; suggestions: Record<string, string[]> }) {
  const [open, setOpen] = useState(true);
  const Icon = m.kind === 'case' ? IconCase : IconExecution;
  return (
    <article className={cx('overflow-hidden rounded-[16px] border border-line bg-surface', hidden && 'opacity-55')}>
      <header className="flex flex-wrap items-center gap-x-3 gap-y-1.5 bg-ink px-4 py-2.5 text-white">
        <span className="shrink-0 text-white/70"><Icon size={17} /></span>
        <div className="min-w-0 flex-1">
          <h3 className="text-[14.5px] font-semibold leading-snug">{m.title}</h3>
          <p className="text-[12px] text-white/70">{m.number && <>رقم {m.kind === 'case' ? 'القضية' : 'الطلب'} <bdi dir="ltr">{m.number}</bdi> · </>}{m.court && <>{m.court} · </>}{m.stageLabel ?? m.status}</p>
        </div>
        <span className="rounded-md bg-white/12 px-2 py-0.5 text-[12px] text-white/90">{m.status}</span>
        {editable && (
          <button type="button" onClick={onToggleHidden} className="btn btn-sm h-8 gap-1.5 bg-white/12 px-2.5 text-white hover:bg-white/20" aria-pressed={hidden}>
            {hidden ? <><EyeOff className="size-4" />مخفي عن العميل</> : <><Eye className="size-4" />ظاهر للعميل</>}
          </button>
        )}
        <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="btn btn-sm h-8 bg-white/12 px-2 text-white hover:bg-white/20" aria-label={open ? 'طيّ' : 'إظهار'}>
          <ChevronDown className={cx('size-4 transition-transform duration-200', open && 'rotate-180')} />
        </button>
      </header>
      {open && (
        <div className="detail-swap px-4 pb-2 pt-2">
          {m.kind === 'case' && m.stage && <div className="mb-1 flex items-center gap-3 text-[12.5px] text-muted"><StageDots current={m.stage} /><span>المرحلة: {m.stageLabel}</span></div>}
          <ul className="divide-y divide-line/70">{m.items.map((it) => <WorkRow key={it.id} it={it} editable={editable} catalog={catalog} suggestions={suggestions} />)}</ul>
          {m.collected && m.collected.length > 0 && (
            <p className="mb-2 mt-1 inline-flex items-center gap-2 rounded-[10px] bg-[var(--positive-soft)] px-3 py-1.5 text-[13px] text-positive">المحصَّل خلال الفترة: <strong className="font-semibold"><Amounts list={m.collected} /></strong></p>
          )}
        </div>
      )}
    </article>
  );
}

/** مفتاح عكس أعمال المنصة: مطفأ افتراضيًا، والقرار للمحامي */
function AutoSyncSwitch({ report }: { report: Report }) {
  const on = report.autoSync;
  const toggle = () => {
    const msg = on ? 'إيقاف عكس أعمال المنصة؟ تُحذف من التقرير الأعمال التي جلبتها المنصة، وتبقى أعمالك كما هي.' : 'تشغيل عكس أعمال المنصة؟ تدخل التقرير تلقائيًا الإجراءات المسجلة على قضايا العميل وطلبات تنفيذه خلال الفترة.';
    if (confirm(msg)) router.put(`/office/client-reports/${report.id}/auto-sync`, { auto_sync: !on }, { preserveScroll: true });
  };
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 rounded-[14px] border border-line bg-surface px-4 py-3">
      <div>
        <p className="text-[14px] font-medium text-text">عكس أعمال المنصة تلقائيًا</p>
        <p className="text-[12.5px] text-muted">{on ? 'مفعّل: تدخل التقرير إجراءات قضايا العميل وطلبات تنفيذه المسجلة في المنصة.' : 'مطفأ: التقرير يحتوي ما تضيفه أنت فقط.'}</p>
      </div>
      <button type="button" role="switch" aria-checked={on} onClick={toggle} disabled={report.status === 'published'}
        className={cx('relative h-7 w-12 shrink-0 rounded-full transition-colors duration-200 disabled:opacity-50', on ? 'bg-ink' : 'bg-control')}>
        <span className={cx('absolute top-1 size-5 rounded-full bg-white shadow transition-[inset-inline-start] duration-200', on ? 'start-6' : 'start-1')} />
        <span className="sr-only">عكس أعمال المنصة</span>
      </button>
    </div>
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
 * عرض التقرير — نفس البنية للمحامي (قابل للتحرير) وللعميل (للاطلاع).
 * أعمال المكتب مجمّعة حسب المرحلة؛ وما عكسته المنصة (إن فُعِّل) في قسم مستقل حسب الملف.
 */
export function ReportView({ report, editable = false, pdfHref, toolbar, catalog }: { report: Report; editable?: boolean; pdfHref: string; toolbar?: ReactNode; catalog?: Catalog }) {
  const [hidden, setHidden] = useState<string[]>(report.platform.matters.filter((m) => m.hidden).map((m) => m.key));
  const [texts, setTexts] = useState({ title: report.title, intro: report.intro ?? '', next_steps: report.nextSteps ?? '' });
  const [dirty, setDirty] = useState(false);
  const [saving, setSaving] = useState(false);
  const save = (h = hidden) => {
    setSaving(true);
    router.put(`/office/client-reports/${report.id}`, { ...texts, hidden: h, lock_version: report.lockVersion }, { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) });
  };
  const toggle = (key: string) => { const h = hidden.includes(key) ? hidden.filter((k) => k !== key) : [...hidden, key]; setHidden(h); save(h); };
  const collected = report.stats.collected ?? [];
  // اقتراحات الإدخال: أرقام الطلبات والأطراف والجهات المستخدمة سابقًا في هذا التقرير
  const suggestions = useMemo(() => {
    const out: Record<string, Set<string>> = { number: new Set(), party: new Set(), entity: new Set(), court: new Set() };
    report.groups.flatMap((g) => g.items).forEach((i) => Object.entries(i.fields ?? {}).forEach(([k, v]) => { if (out[k] && v) out[k].add(v); }));
    return Object.fromEntries(Object.entries(out).map(([k, v]) => [k, [...v]]));
  }, [report.groups]);
  const empty = report.groups.length === 0 && report.platform.count === 0;

  return (
    <div className="stagger space-y-4">
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
          <div className="flex items-center gap-2 rounded-[12px] bg-ink px-3 py-2 text-white">
            <dd className="text-[18px] font-semibold leading-none"><CountUp value={report.stats.total} /></dd>
            <dt className="text-[12.5px] text-white/75">عملًا خلال الفترة</dt>
          </div>
          {report.stats.groups.map((g) => (
            <div key={g.key} className="flex items-center gap-2 rounded-[12px] border border-ink/10 bg-white/85 px-3 py-2">
              <span className="text-muted"><GroupIcon g={g.key} size={15} /></span>
              <dd className="text-[16px] font-semibold leading-none text-ink"><CountUp value={g.count} /></dd>
              <dt className="text-[12.5px] text-muted">{g.label}</dt>
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

      {editable && catalog && <AddPanel report={report} catalog={catalog} suggestions={suggestions} />}

      {(editable || report.intro) && (
        <Note title="ملخص الفترة" icon={<IconReports size={18} />}>
          {editable ? <TextArea label="ملخص يكتبه المكتب للعميل (اختياري)" rows={3} value={texts.intro} onChange={(e) => { setTexts({ ...texts, intro: e.target.value }); setDirty(true); }}
            placeholder="مثل: واصل المكتب خلال هذا الشهر متابعة قضاياكم..." maxLength={5000} />
            : <p className="whitespace-pre-line text-[14px] leading-7 text-text">{report.intro}</p>}
        </Note>
      )}

      {empty && editable && <p className="rounded-[14px] border border-dashed border-control px-5 py-6 text-center text-[13.5px] text-muted">لم تُضف أعمال بعد. اختر نوع العمل من «إضافة عمل» أعلاه.</p>}

      {report.groups.map((g) => <GroupSection key={g.key} g={g} editable={editable} catalog={catalog} suggestions={suggestions} />)}

      {report.platform.count > 0 && (
        <section>
          <h2 className="mb-1 flex items-center gap-2 text-[15.5px] font-semibold"><IconCase size={19} />من سجل القضايا وطلبات التنفيذ</h2>
          <DiamondRule className="mb-3 max-w-[160px]" />
          <div className="stagger grid gap-3">
            {report.platform.matters.map((m) => <MatterCard key={m.key} m={m} editable={editable} hidden={hidden.includes(m.key)} onToggleHidden={() => toggle(m.key)} catalog={catalog} suggestions={suggestions} />)}
            {report.platform.general.length > 0 && <GroupSection g={{ key: 'other', label: 'أعمال عامة', items: report.platform.general }} editable={editable} catalog={catalog} suggestions={suggestions} />}
          </div>
        </section>
      )}

      {editable && <AutoSyncSwitch report={report} />}

      {(editable || report.nextSteps) && (
        <Note title="الخطوات القادمة" icon={<IconTasks size={18} />}>
          {editable ? <TextArea label="ما سيعمل عليه المكتب في الفترة القادمة (اختياري)" rows={3} value={texts.next_steps} onChange={(e) => { setTexts({ ...texts, next_steps: e.target.value }); setDirty(true); }} maxLength={5000} />
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
export function StatusPill({ status, label, live = false }: { status: string; label: string; live?: boolean }) {
  if (status === 'draft' && live) {
    return (
      <Pill tone="warning" className="inline-flex items-center gap-1.5">
        <span className="pulse-dot inline-block size-2 rounded-full bg-current" aria-hidden />{label} — تتحدث تلقائيًا
      </Pill>
    );
  }
  return <Pill tone={status === 'published' ? 'positive' : status === 'draft' ? 'warning' : 'neutral'}>{label}</Pill>;
}

