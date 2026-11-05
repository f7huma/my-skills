import { Link, router, useForm } from '@inertiajs/react';
import { FileText, Download, ShieldAlert, ShieldCheck, Loader2, Eye, EyeOff, Trash2, Upload, Inbox } from 'lucide-react';
import { ReactNode, useEffect, useId, useRef, useState, forwardRef, InputHTMLAttributes, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import type { Doc, Option, Paginated } from '@/types';
import { dateTime, fileSize, hijri, localDate, money } from '@/lib/format';

export function cx(...c: (string | false | null | undefined)[]) {
  return c.filter(Boolean).join(' ');
}

/** رقم أو مرجع معزول الاتجاه داخل النص العربي */
export function Num({ children, className }: { children: ReactNode; className?: string }) {
  return <bdi dir="ltr" className={cx('num', className)}>{children}</bdi>;
}

/** تاريخ ميلادي مع مقابله الهجري. يقبل YYYY-MM-DD أو وقتًا ISO (يُعرض بتوقيت الرياض مع الساعة عند time) */
export function DateText({ value, time = false, fallback = '—', className }: { value: string | null | undefined; time?: boolean; fallback?: string; className?: string }) {
  if (!value) return <span className={cx('text-faint', className)}>{fallback}</span>;
  const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value);
  if (!isDateOnly && Number.isNaN(Date.parse(value))) return <span className={className}>{value}</span>;
  const g = isDateOnly ? value : (time ? dateTime(value) : localDate(value));
  const h = hijri(isDateOnly ? value : localDate(value));
  return (
    <span className={cx('whitespace-nowrap', className)}>
      <bdi dir="ltr" className="num">{g}</bdi>
      {h && <span className="ms-1.5 text-[0.88em] text-muted"><bdi dir="ltr" className="num">{h}</bdi>هـ</span>}
    </span>
  );
}

export function Money({ value, currency, className }: { value: string | number | null | undefined; currency?: string | null; className?: string }) {
  if (value === null || value === undefined || value === '') return <span className="text-faint">—</span>;
  return (
    <span className={cx('whitespace-nowrap', className)}>
      <bdi dir="ltr" className="num">{money(value)}</bdi>
      {currency && <span className="text-muted text-[12px] ms-1">{currency}</span>}
    </span>
  );
}

// ------------------------------------------------------------------ الأزرار
type BtnProps = { variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'light'; size?: 'sm' | 'md'; loading?: boolean; className?: string; children: ReactNode };

export function Button({ variant = 'secondary', size = 'md', loading, className, children, ...rest }: BtnProps & React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button {...rest} disabled={rest.disabled || loading} aria-busy={loading || undefined} className={cx('btn', `btn-${variant}`, size === 'sm' && 'btn-sm', className)}>
      {loading && <Loader2 className="size-4 animate-spin" aria-hidden />}
      {children}
    </button>
  );
}

export function ButtonLink({ href, variant = 'secondary', size = 'md', className, children }: BtnProps & { href: string }) {
  return <Link href={href} className={cx('btn', `btn-${variant}`, size === 'sm' && 'btn-sm', className)}>{children}</Link>;
}

// ------------------------------------------------------------------ الحقول
type FieldWrap = { label: string; error?: string; hint?: ReactNode; className?: string; required?: boolean };

function useFieldIds(error?: string, hint?: ReactNode) {
  const id = useId();
  const describedBy = [error ? id + '-err' : '', hint ? id + '-hint' : ''].filter(Boolean).join(' ') || undefined;
  return { id, describedBy };
}

function FieldShell({ id, label, error, hint, className, required, children }: FieldWrap & { id: string; children: ReactNode }) {
  return (
    <div className={className}>
      <label htmlFor={id} className="field-label">{label}{required && <span className="text-negative" aria-hidden> *</span>}</label>
      {children}
      {hint && !error && <p id={id + '-hint'} className="field-hint">{hint}</p>}
      {error && <p id={id + '-err'} className="field-error" role="alert">{error}</p>}
    </div>
  );
}

export const TextField = forwardRef<HTMLInputElement, FieldWrap & InputHTMLAttributes<HTMLInputElement> & { ltr?: boolean }>(
  function TextField({ label, error, hint, className, ltr, required, ...rest }, ref) {
    const h = rest.type === 'date' && typeof rest.value === 'string' ? hijri(rest.value) : '';
    const fullHint = h ? <>{hint}{hint ? ' · ' : ''}يوافق <bdi dir="ltr" className="num">{h}</bdi>هـ</> : hint;
    const { id, describedBy } = useFieldIds(error, fullHint);
    return (
      <FieldShell id={id} label={label} error={error} hint={fullHint} className={className} required={required}>
        <input ref={ref} id={id} required={required} aria-invalid={error ? true : undefined} aria-describedby={describedBy} {...rest} className={cx('input', ltr && 'input-ltr num')} />
      </FieldShell>
    );
  },
);

export function SelectField({ label, error, hint, className, options, placeholder, required, ...rest }: FieldWrap & SelectHTMLAttributes<HTMLSelectElement> & { options: Option[] | string[]; placeholder?: string }) {
  const { id, describedBy } = useFieldIds(error, hint);
  return (
    <FieldShell id={id} label={label} error={error} hint={hint} className={className} required={required}>
      <select id={id} required={required} aria-invalid={error ? true : undefined} aria-describedby={describedBy} {...rest} className="input">
        {placeholder !== undefined && <option value="">{placeholder}</option>}
        {options.map((o) => typeof o === 'string'
          ? <option key={o} value={o}>{o}</option>
          : <option key={String(o.value)} value={String(o.value)}>{o.label}</option>)}
      </select>
    </FieldShell>
  );
}

export function TextArea({ label, error, hint, className, required, ...rest }: FieldWrap & TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const { id, describedBy } = useFieldIds(error, hint);
  return (
    <FieldShell id={id} label={label} error={error} hint={hint} className={className} required={required}>
      <textarea id={id} required={required} aria-invalid={error ? true : undefined} aria-describedby={describedBy} {...rest} className="input" />
    </FieldShell>
  );
}

export function Checkbox({ label, hint, checked, onChange, name }: { label: string; hint?: string; checked: boolean; onChange: (v: boolean) => void; name?: string }) {
  const id = useId();
  return (
    <div className="flex items-start gap-2.5">
      <input id={id} name={name} type="checkbox" className="check mt-1" checked={checked} onChange={(e) => onChange(e.target.checked)} />
      <label htmlFor={id} className="text-[14px] leading-6">
        {label}
        {hint && <span className="block text-[12.5px] text-muted">{hint}</span>}
      </label>
    </div>
  );
}

export function FormError({ errors }: { errors: Record<string, string> }) {
  const general = errors.lock_version || errors.claim;
  if (!general) return null;
  return <div role="alert" className="rounded-lg px-3 py-2.5 text-[13.5px] pill-negative" style={{ height: 'auto' }}>{general}</div>;
}

// ------------------------------------------------------------------ الحالة
export type Tone = 'neutral' | 'positive' | 'negative' | 'warning' | 'info' | 'accent';

export function Pill({ tone = 'neutral', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
  return <span className={cx('pill', `pill-${tone}`, className)}>{children}</span>;
}

export const caseTone = (s: string): Tone => ({ active: 'accent', completed: 'neutral', suspended: 'warning', struck_off: 'warning', draft: 'neutral' } as Record<string, Tone>)[s] ?? 'neutral';
export const claimTone = (s: string): Tone => ({ under_review: 'info', needs_completion: 'warning', converted_case: 'positive', converted_execution: 'positive', closed: 'neutral', draft: 'neutral' } as Record<string, Tone>)[s] ?? 'neutral';
export const certaintyTone = (s: string): Tone => ({ estimated: 'info', needs_review: 'warning', confirmed: 'positive', needs_determination: 'negative' } as Record<string, Tone>)[s] ?? 'neutral';
export const poaTone = (s: string): Tone => ({ active: 'positive', expiring: 'warning', expired: 'negative', revoked: 'neutral' } as Record<string, Tone>)[s] ?? 'neutral';
export const poaLabel: Record<string, string> = { active: 'سارية', expiring: 'قريبة الانتهاء', expired: 'منتهية', revoked: 'ملغاة' };

// ------------------------------------------------------------------ الصفحة واللوحات
export function PageHeader({ title, subtitle, actions, crumbs }: { title: ReactNode; subtitle?: ReactNode; actions?: ReactNode; crumbs?: { label: string; href: string }[] }) {
  return (
    <header className="mb-6 flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
      <div className="min-w-0">
        {crumbs && crumbs.length > 0 && (
          <nav aria-label="مسار التنقل" className="mb-1 text-[13px] text-muted">
            {crumbs.map((c, i) => (
              <span key={c.href}>
                <Link href={c.href} className="hover:text-text hover:underline underline-offset-4">{c.label}</Link>
                {i < crumbs.length - 1 && <span aria-hidden className="mx-1.5">/</span>}
              </span>
            ))}
          </nav>
        )}
        <h1 className="text-[24px] font-semibold leading-snug text-text sm:text-[28px]">{title}</h1>
        {subtitle && <div className="mt-1 text-[14px] text-muted">{subtitle}</div>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </header>
  );
}

export function Panel({ title, actions, children, className, bodyClassName, id, description }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; className?: string; bodyClassName?: string; id?: string; description?: ReactNode }) {
  return (
    <section id={id} className={cx('card', className)} aria-labelledby={title && id ? id + '-title' : undefined}>
      {(title || actions) && (
        <div className="flex items-center justify-between gap-3 px-5 pt-4 pb-2">
          <div className="min-w-0">
            {title && <h2 id={id ? id + "-title" : undefined} className="text-[16px] font-semibold text-text">{title}</h2>}
            {description && <p className="text-[13px] text-muted">{description}</p>}
          </div>
          {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
      )}
      <div className={cx('px-5 pb-5', !title && !actions && 'pt-5', bodyClassName)}>{children}</div>
    </section>
  );
}

/** قائمة مفتاح/قيمة تُخفي الحقول الفارغة */
export function KeyValues({ items, cols = 2 }: { items: [string, ReactNode][]; cols?: 1 | 2 | 3 }) {
  const visible = items.filter(([, v]) => v !== null && v !== undefined && v !== '' && v !== false);
  if (!visible.length) return <p className="text-[13.5px] text-muted">لا توجد بيانات مسجلة بعد.</p>;
  return (
    <dl className={cx('grid gap-x-8 gap-y-3', cols === 2 && 'sm:grid-cols-2', cols === 3 && 'sm:grid-cols-2 lg:grid-cols-3')}>
      {visible.map(([k, v]) => (
        <div key={k} className="min-w-0">
          <dt className="text-[12.5px] text-muted">{k}</dt>
          <dd className="mt-0.5 break-words text-[14px] text-text">{v}</dd>
        </div>
      ))}
    </dl>
  );
}

export function EmptyState({ title, children, action, icon }: { title: string; children?: ReactNode; action?: ReactNode; icon?: ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-lg px-6 py-10 text-center well">
      <div className="mb-2 text-faint" aria-hidden>{icon ?? <Inbox className="size-6" strokeWidth={1.5} />}</div>
      <p className="font-semibold text-text">{title}</p>
      {children && <div className="mt-1 max-w-md text-[13.5px] text-muted">{children}</div>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function Tabs({ tabs, current, onSelect, label }: { tabs: { key: string; label: string; count?: number; hidden?: boolean }[]; current: string; onSelect: (k: string) => void; label: string }) {
  const refs = useRef<Record<string, HTMLButtonElement | null>>({});
  const visible = tabs.filter((t) => !t.hidden);
  const onKey = (e: React.KeyboardEvent, i: number) => {
    // RTL: السهم الأيسر يتقدم
    const next = e.key === 'ArrowLeft' ? i + 1 : e.key === 'ArrowRight' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? visible.length - 1 : null;
    if (next === null) return;
    e.preventDefault();
    const t = visible[(next + visible.length) % visible.length];
    onSelect(t.key);
    refs.current[t.key]?.focus();
  };
  return (
    <div role="tablist" aria-label={label} className="tabs mb-5">
      {visible.map((t, i) => (
        <button key={t.key} ref={(el) => { refs.current[t.key] = el; }} role="tab" type="button" id={'tab-' + t.key} aria-controls={'panel-' + t.key}
          aria-selected={current === t.key} tabIndex={current === t.key ? 0 : -1} className="tab" onClick={() => onSelect(t.key)} onKeyDown={(e) => onKey(e, i)}>
          {t.label}
          {t.count !== undefined && t.count > 0 && <span className="ms-1.5 text-[12px] font-normal text-muted"><Num>{t.count}</Num></span>}
        </button>
      ))}
    </div>
  );
}

// ------------------------------------------------------------------ نافذة حوار (dialog أصلي: حبس التركيز وEsc)
export function Modal({ open, onClose, title, children, wide }: { open: boolean; onClose: () => void; title: string; children: ReactNode; wide?: boolean }) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) d.showModal();
    if (!open && d.open) d.close();
  }, [open]);
  return (
    <dialog ref={ref} className="modal" style={wide ? { width: 'min(860px, calc(100vw - 24px))' } : undefined} onClose={onClose} onCancel={onClose} aria-labelledby={title ? 'dlg-title' : undefined}>
      {open && (
        <div className="flex max-h-[calc(100dvh-32px)] flex-col">
          <div className="flex items-center justify-between border-b border-rule px-5 py-3.5">
            <h2 id="dlg-title" className="text-[16px] font-semibold">{title}</h2>
            <button type="button" onClick={onClose} className="btn btn-ghost btn-sm" aria-label="إغلاق">✕</button>
          </div>
          <div className="overflow-y-auto px-5 py-4">{children}</div>
        </div>
      )}
    </dialog>
  );
}

export function useModal() {
  const [open, setOpen] = useState(false);
  return { open, show: () => setOpen(true), close: () => setOpen(false) };
}

// ------------------------------------------------------------------ الخط الزمني
export function Timeline({ events, showUser }: { events: { id: number; title: string; details?: string | null; at: string; clientVisible?: boolean; user?: string | null }[]; showUser?: boolean }) {
  if (!events.length) return <EmptyState title="لا توجد أحداث بعد">تظهر هنا الإجراءات المسجلة بحسب تاريخها.</EmptyState>;
  return (
    <ol className="relative ms-1.5 border-s border-line">
      {events.map((e) => (
        <li key={e.id} className="relative ps-5 pb-5 last:pb-0">
          <span aria-hidden className={cx('absolute -start-[5px] top-2 size-[9px] rounded-full border-2 border-surface', e.clientVisible ? 'bg-accent' : 'bg-control')} />
          <div className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
            <p className="font-medium text-text">{e.title}</p>
            <span className="text-[12.5px] text-muted"><DateText value={e.at} time /></span>
            {showUser && e.user && <span className="text-[12.5px] text-muted">· {e.user}</span>}
            {e.clientVisible !== undefined && e.clientVisible && <span className="text-[12px] text-accent">ظاهر للعميل</span>}
          </div>
          {e.details && <p className="mt-0.5 whitespace-pre-line text-[13.5px] text-muted">{e.details}</p>}
        </li>
      ))}
    </ol>
  );
}

// ------------------------------------------------------------------ المستندات
export function ScanBadge({ scan }: { scan: string }) {
  if (scan === 'clean') return <span className="inline-flex items-center gap-1 text-[12px] text-positive"><ShieldCheck className="size-3.5" aria-hidden />سليم</span>;
  if (scan === 'pending') return <span className="inline-flex items-center gap-1 text-[12px] text-muted"><Loader2 className="size-3.5 animate-spin" aria-hidden />قيد الفحص</span>;
  if (scan === 'error') return <span className="inline-flex items-center gap-1 text-[12px] text-[#7a4307]"><ShieldAlert className="size-3.5" aria-hidden />محجوب حتى يكتمل الفحص</span>;
  return <span className="inline-flex items-center gap-1 text-[12px] text-negative"><ShieldAlert className="size-3.5" aria-hidden />مرفوض</span>;
}

export function DocumentList({ docs, manage, empty = 'لا توجد مستندات.', onDelete }: { docs: Doc[]; manage?: boolean; empty?: string; onDelete?: (d: Doc) => void }) {
  const [busy, setBusy] = useState<string | null>(null);
  if (!docs.length) return <EmptyState title={empty} icon={<FileText className="size-6" strokeWidth={1.5} />} />;
  const share = (d: Doc) => {
    setBusy(d.id);
    router.post(`/office/documents/${d.id}/share`, { shared: !d.shared }, { preserveScroll: true, onFinish: () => setBusy(null) });
  };
  const remove = (d: Doc) => {
    if (onDelete) return onDelete(d);
    const reason = window.prompt('سبب حذف المستند (يُحفظ في سجل التدقيق):');
    if (!reason) return;
    router.delete(`/office/documents/${d.id}`, { data: { reason }, preserveScroll: true });
  };
  return (
    <ul className="divide-y divide-rule">
      {docs.map((d) => (
        <li key={d.id} className="flex flex-wrap items-center gap-x-4 gap-y-1.5 py-3">
          <FileText className="size-5 shrink-0 text-faint" strokeWidth={1.5} aria-hidden />
          <div className="min-w-0 flex-1">
            <p className="truncate font-medium text-text" title={d.name}>{d.label || d.name}</p>
            <p className="flex flex-wrap gap-x-2 text-[12.5px] text-muted">
              {d.categoryLabel && <span>{d.categoryLabel}</span>}
              {d.partyLabel && <span>· {d.partyLabel}</span>}
              <span>· <Num>{fileSize(d.size)}</Num></span>
              {d.uploadedAt && <span>· <DateText value={d.uploadedAt} time /></span>}
            </p>
          </div>
          <ScanBadge scan={d.scan} />
          {manage && d.shared !== undefined && (
            <button type="button" className={cx('btn btn-sm', d.shared ? 'btn-secondary' : 'btn-ghost')} onClick={() => share(d)} disabled={busy === d.id} aria-pressed={d.shared}
              title={d.shared ? 'ظاهر في بوابة العميل — اضغط للإخفاء' : 'غير ظاهر للعميل — اضغط للمشاركة'}>
              {d.shared ? <Eye className="size-4" aria-hidden /> : <EyeOff className="size-4" aria-hidden />}
              {d.shared ? 'ظاهر للعميل' : 'داخلي'}
            </button>
          )}
          {d.url ? (
            <a href={d.url} className="btn btn-ghost btn-sm" aria-label={'تنزيل ' + (d.label || d.name)}><Download className="size-4" aria-hidden />تنزيل</a>
          ) : (
            <span className="btn btn-ghost btn-sm opacity-60" aria-disabled>غير متاح</span>
          )}
          {(manage || onDelete) && (
            <button type="button" className="btn btn-ghost btn-sm text-negative" onClick={() => remove(d)} aria-label={'حذف ' + (d.label || d.name)}><Trash2 className="size-4" aria-hidden /></button>
          )}
        </li>
      ))}
    </ul>
  );
}

/** رفع مستند للمحامي: يرتبط بمالك (عميل/قضية/تنفيذ/مطالبة) */
export function UploadForm({ ownerType, ownerId, categories, defaultCategory = 'other', allowShare = true, onDone, compact }: {
  ownerType: 'client' | 'case' | 'execution' | 'claim'; ownerId: number; categories: Option[]; defaultCategory?: string; allowShare?: boolean; onDone?: () => void; compact?: boolean;
}) {
  const form = useForm<{ owner_type: string; owner_id: number; file: File | null; category: string; label: string; shared_with_client: boolean; party: string }>({
    owner_type: ownerType, owner_id: ownerId, file: null, category: defaultCategory, label: '', shared_with_client: false, party: 'office',
  });
  const fileRef = useRef<HTMLInputElement>(null);
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post('/office/documents', { preserveScroll: true, forceFormData: true, onSuccess: () => { form.reset('file', 'label'); if (fileRef.current) fileRef.current.value = ''; onDone?.(); } });
  };
  return (
    <form onSubmit={submit} className={cx('grid gap-3', !compact && 'sm:grid-cols-[1.2fr_1fr_1fr_auto] sm:items-end')}>
      <div>
        <label className="field-label" htmlFor={`up-${ownerType}-${ownerId}`}>الملف</label>
        <input ref={fileRef} id={`up-${ownerType}-${ownerId}`} type="file" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" className="input pt-1.5"
          onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} aria-describedby={`up-hint-${ownerId}`} aria-invalid={form.errors.file ? true : undefined} />
        <p id={`up-hint-${ownerId}`} className="field-hint">PDF أو JPG أو PNG أو Word/Excel، حتى 25MB. يُتاح بعد الفحص الأمني.</p>
        {form.errors.file && <p className="field-error" role="alert">{form.errors.file}</p>}
      </div>
      <SelectField label="نوع المستند" options={categories} value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} error={form.errors.category} />
      <TextField label="وصف مختصر (اختياري)" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} maxLength={120} />
      <div className="flex flex-col gap-2 sm:pb-[22px]">
        {allowShare && <Checkbox label="مشاركة مع العميل" checked={form.data.shared_with_client} onChange={(v) => form.setData('shared_with_client', v)} />}
        <Button variant="primary" type="submit" loading={form.processing} disabled={!form.data.file}><Upload className="size-4" aria-hidden />رفع</Button>
      </div>
    </form>
  );
}

export function Pagination<T>({ page }: { page: Paginated<T> }) {
  if (page.last_page <= 1) return page.total ? <p className="mt-3 text-[13px] text-muted"><Num>{page.total}</Num> سجل</p> : null;
  return (
    <nav aria-label="الصفحات" className="mt-4 flex flex-wrap items-center justify-between gap-3 text-[13px]">
      <p className="text-muted"><Num>{`${page.from}–${page.to}`}</Num> من <Num>{page.total}</Num></p>
      <div className="flex flex-wrap gap-1">
        {page.links.map((l, i) => {
          const label = l.label.includes('Previous') || l.label.includes('&laquo;') ? 'السابق' : l.label.includes('Next') || l.label.includes('&raquo;') ? 'التالي' : l.label;
          return l.url ? (
            <Link key={i} href={l.url} preserveScroll className={cx('btn btn-sm', l.active ? 'btn-primary' : 'btn-ghost')} aria-current={l.active ? 'page' : undefined}>{label}</Link>
          ) : (
            <span key={i} className="btn btn-sm btn-ghost opacity-50">{label}</span>
          );
        })}
      </div>
    </nav>
  );
}

export function TotalsGrid({ totals, labels, emptyText = 'لا توجد مبالغ مسجلة.' }: { totals: Record<string, Record<string, string>>; labels: Record<string, string>; emptyText?: string }) {
  const keys = Object.keys(labels).filter((k) => totals[k] && Object.keys(totals[k]).length);
  if (!keys.length) return <p className="text-[13.5px] text-muted">{emptyText}</p>;
  return (
    <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-line sm:grid-cols-4">
      {Object.keys(labels).map((k) => (
        <div key={k} className="bg-surface px-4 py-3">
          <dt className="text-[12.5px] text-muted">{labels[k]}</dt>
          <dd className="mt-1 space-y-0.5">
            {totals[k] && Object.keys(totals[k]).length
              ? Object.entries(totals[k]).map(([cur, v]) => <div key={cur} className="text-[17px] font-semibold"><Money value={v} currency={cur} /></div>)
              : <span className="text-[17px] text-faint">—</span>}
          </dd>
        </div>
      ))}
    </dl>
  );
}

export function money2(v: string | null | undefined, c?: string) { return money(v, c); }
