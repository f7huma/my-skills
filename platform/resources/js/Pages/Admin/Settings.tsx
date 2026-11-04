import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button, Checkbox, cx, EmptyState, Modal, Num, PageHeader, Pill, SelectField, TextArea, TextField, useModal } from '@/Components/ui';

type Rule = { id: number; code: string; version: number; name: string; kind: string; duration: number | null; dayType: string; startEvent: string | null; effectiveFrom: string; effectiveTo: string | null; source: string; status: string; notes: string | null; reviewedAt: string | null };
type AT = { id: number; name: string; resubmission_interval_days: number | null; interval_day_type: string; interval_from_last_same_type: boolean; condition_note: string | null; is_active: boolean };
type Props = { tab: string; poaWarningDays: number; categories: { id: number; name: string; is_active: boolean }[]; actionTypes: AT[]; year: number;
  holidays: { id: number; date: string; hijri: string; name: string; kind: string; reviewed: boolean }[]; rules: Rule[] };

const tabs = [['general', 'عام'], ['holidays', 'تقويم الإجازات'], ['rules', 'قواعد المواعيد'], ['actions', 'أنواع طلبات الإجراء'], ['categories', 'تصنيفات القضايا']];

function Row({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return (
    <section className="grid gap-4 border-b border-rule py-7 first:pt-0 last:border-0 md:grid-cols-[minmax(0,300px)_1fr] md:gap-10">
      <div><h2 className="font-semibold">{title}</h2><p className="mt-1 text-[13.5px] text-muted">{description}</p></div>
      <div>{children}</div>
    </section>
  );
}

export default function Settings(p: Props) {
  const gen = useForm({ poa_expiry_warning_days: String(p.poaWarningDays) });
  const hol = useForm({ date: '', name: '', kind: 'official' });
  const cat = useForm({ name: '' });
  const rule = useForm({ code: 'execution_compliance', name: '', deadline_kind: 'execution_compliance', duration: '', day_type: 'business', start_event: 'notified_on', effective_from: '', effective_to: '', source_reference: '', notes: '' });
  const ruleModal = useModal();
  const [at, setAt] = useState<AT | null>(null);
  const atModal = useModal();
  return (
    <>
      <Head title="الإعدادات" />
      <PageHeader title="الإعدادات" />
      <nav className="tabs mb-6" aria-label="أقسام الإعدادات">
        {tabs.map(([k, l]) => <Link key={k} href={`/office/admin/settings?tab=${k}`} className="tab" aria-current={p.tab === k ? 'page' : undefined} aria-selected={p.tab === k}>{l}</Link>)}
      </nav>

      {p.tab === 'general' && (
        <Row title="نافذة تنبيه الوكالات" description="عدد الأيام قبل انتهاء الوكالة لرفعها إلى أعلى القائمة.">
          <form onSubmit={(e) => { e.preventDefault(); gen.put('/office/admin/settings/general', { preserveScroll: true }); }} className="well flex flex-wrap items-end gap-3 p-4">
            <TextField label="الأيام" ltr className="w-32" value={gen.data.poa_expiry_warning_days} onChange={(e) => gen.setData('poa_expiry_warning_days', e.target.value.replace(/\D/g, ''))} error={gen.errors.poa_expiry_warning_days} />
            <Button type="submit" variant="primary" loading={gen.processing}>حفظ</Button>
          </form>
        </Row>
      )}

      {p.tab === 'holidays' && (
        <Row title={`إجازات ${p.year}`} description="أيام العمل تستبعد الجمعة والسبت وهذه الأيام. راجع كل يوم وفق الإعلان الرسمي؛ غير المراجَع مميز.">
          <div className="mb-3 flex gap-2">
            <Link href={`/office/admin/settings?tab=holidays&year=${p.year - 1}`} className="btn btn-ghost btn-sm"><Num>{p.year - 1}</Num></Link>
            <Link href={`/office/admin/settings?tab=holidays&year=${p.year + 1}`} className="btn btn-ghost btn-sm"><Num>{p.year + 1}</Num></Link>
          </div>
          {p.holidays.length === 0 ? <EmptyState title="لا إجازات مسجلة لهذه السنة" /> : (
            <ul className="mb-4 divide-y divide-rule">{p.holidays.map((h) => (
              <li key={h.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <span><Num>{h.date}</Num> <span className="text-[12.5px] text-muted">({h.hijri})</span> — {h.name}</span>
                <span className="flex items-center gap-2">
                  {!h.reviewed && <Pill tone="warning">لم يُراجع</Pill>}
                  {!h.reviewed && <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.put(`/office/admin/holidays/${h.id}`, { name: h.name, reviewed: true }, { preserveScroll: true })}>اعتماد</button>}
                  <button type="button" className="btn btn-ghost btn-sm text-negative" onClick={() => confirm('حذف هذا اليوم؟') && router.delete(`/office/admin/holidays/${h.id}`, { preserveScroll: true })}>حذف</button>
                </span>
              </li>
            ))}</ul>
          )}
          <form onSubmit={(e) => { e.preventDefault(); hol.post('/office/admin/holidays', { preserveScroll: true, onSuccess: () => hol.reset() }); }} className="well grid gap-3 p-4 sm:grid-cols-[160px_1fr_140px_auto] sm:items-end">
            <TextField label="التاريخ" type="date" value={hol.data.date} onChange={(e) => hol.setData('date', e.target.value)} error={hol.errors.date} />
            <TextField label="الاسم" value={hol.data.name} onChange={(e) => hol.setData('name', e.target.value)} error={hol.errors.name} />
            <SelectField label="النوع" options={[{ value: 'official', label: 'رسمية' }, { value: 'office', label: 'إجازة المكتب' }]} value={hol.data.kind} onChange={(e) => hol.setData('kind', e.target.value)} />
            <Button type="submit" loading={hol.processing}>إضافة</Button>
          </form>
        </Row>
      )}

      {p.tab === 'rules' && (
        <Row title="قواعد المواعيد" description="إصدارات مرتبطة بتاريخ السريان والنوع والمصدر الرسمي. لا تُطبّق قاعدة إلا بعد مراجعتك واعتمادها. التعديل يكون بإصدار جديد.">
          <div className="mb-3"><Button size="sm" variant="primary" onClick={ruleModal.show}><Plus className="size-4" aria-hidden />إصدار جديد</Button></div>
          <ul className="divide-y divide-rule">{p.rules.map((r) => (
            <li key={r.id} className="py-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{r.name} <span className="text-[12.5px] text-muted">· <Num>{r.code}</Num> · إصدار <Num>{r.version}</Num></span></p>
                <span className="flex items-center gap-2">
                  <Pill tone={r.status === 'active' ? 'positive' : r.status === 'draft' ? 'warning' : 'neutral'}>{r.status === 'active' ? 'معتمدة' : r.status === 'draft' ? 'مسودة — غير مطبقة' : 'متقاعدة'}</Pill>
                  {r.status !== 'active' && <button type="button" className="btn btn-secondary btn-sm" onClick={() => confirm('أؤكد أنني راجعت النص النظامي ونقطة البداية والمدة لهذه القاعدة قبل اعتمادها.') && router.post(`/office/admin/rules/${r.id}/status`, { status: 'active', confirm_review: 1 }, { preserveScroll: true })}>اعتماد بعد المراجعة</button>}
                  {r.status === 'active' && <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/office/admin/rules/${r.id}/status`, { status: 'retired' }, { preserveScroll: true })}>إيقاف</button>}
                </span>
              </div>
              <p className="text-[13px] text-muted">{r.duration !== null && <><Num>{r.duration}</Num> {r.dayType === 'business' ? 'أيام عمل' : 'أيام'} · </>}البداية: {r.startEvent ?? '—'} · سارية من <Num>{r.effectiveFrom}</Num>{r.effectiveTo && <> إلى <Num>{r.effectiveTo}</Num></>}</p>
              <p className="text-[13px]">المصدر: {r.source}</p>
              {r.notes && <p className="text-[12.5px] text-muted">{r.notes}</p>}
            </li>
          ))}</ul>
          <Modal open={ruleModal.open} onClose={ruleModal.close} title="إصدار قاعدة جديد">
            <form onSubmit={(e) => { e.preventDefault(); rule.post('/office/admin/rules', { preserveScroll: true, onSuccess: ruleModal.close }); }} className="grid gap-3 sm:grid-cols-2">
              <TextField label="الرمز" ltr value={rule.data.code} onChange={(e) => rule.setData('code', e.target.value)} error={rule.errors.code} hint="execution_compliance أو objection أو رمز جديد." />
              <TextField label="الاسم" value={rule.data.name} onChange={(e) => rule.setData('name', e.target.value)} error={rule.errors.name} />
              <TextField label="المدة" ltr value={rule.data.duration} onChange={(e) => rule.setData('duration', e.target.value.replace(/\D/g, ''))} />
              <SelectField label="نوع الأيام" options={[{ value: 'calendar', label: 'تقويمية' }, { value: 'business', label: 'أيام عمل' }]} value={rule.data.day_type} onChange={(e) => rule.setData('day_type', e.target.value)} />
              <SelectField label="واقعة البداية" options={[{ value: 'notified_on', label: 'الإبلاغ / الإعلان' }, { value: 'deed_date', label: 'تاريخ الصك' }]} value={rule.data.start_event} onChange={(e) => rule.setData('start_event', e.target.value)} />
              <TextField label="نوع الموعد" ltr value={rule.data.deadline_kind} onChange={(e) => rule.setData('deadline_kind', e.target.value)} />
              <TextField label="سارية من" type="date" value={rule.data.effective_from} onChange={(e) => rule.setData('effective_from', e.target.value)} error={rule.errors.effective_from} />
              <TextField label="حتى (اختياري)" type="date" value={rule.data.effective_to} onChange={(e) => rule.setData('effective_to', e.target.value)} />
              <TextArea className="sm:col-span-2" label="المصدر الرسمي (المادة واللائحة)" value={rule.data.source_reference} onChange={(e) => rule.setData('source_reference', e.target.value)} error={rule.errors.source_reference} />
              <TextArea className="sm:col-span-2" label="ملاحظات" value={rule.data.notes} onChange={(e) => rule.setData('notes', e.target.value)} />
              <div className="sm:col-span-2"><Button type="submit" variant="primary" loading={rule.processing}>إنشاء كمسودة</Button></div>
            </form>
          </Modal>
        </Row>
      )}

      {p.tab === 'actions' && (
        <Row title="أنواع طلبات الإجراء" description="مهلة إعادة الطلب قابلة للضبط لكل خدمة، ويمكن احتسابها من آخر طلب من النوع نفسه. هذه شروط قبول، منفصلة عن المتابعة الأسبوعية الداخلية.">
          <ul className="mb-3 divide-y divide-rule">{p.actionTypes.map((t) => (
            <li key={t.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
              <span><span className={cx('font-medium', !t.is_active && 'text-muted line-through')}>{t.name}</span> <span className="text-[12.5px] text-muted">· {t.resubmission_interval_days ? <><Num>{t.resubmission_interval_days}</Num> {t.interval_day_type === 'business' ? 'يوم عمل' : 'يوم'}{t.interval_from_last_same_type ? ' من آخر طلب مماثل' : ''}</> : 'بلا مهلة مضبوطة'}</span>
                {t.condition_note && <span className="block text-[12.5px] text-muted">{t.condition_note}</span>}</span>
              <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setAt(t); atModal.show(); }}>تعديل</button>
            </li>
          ))}</ul>
          <Button size="sm" onClick={() => { setAt(null); atModal.show(); }}><Plus className="size-4" aria-hidden />نوع جديد</Button>
          <Modal open={atModal.open} onClose={atModal.close} title={at ? 'تعديل نوع الإجراء' : 'نوع إجراء جديد'}><ActionTypeForm key={at?.id ?? 'n'} t={at} onDone={atModal.close} /></Modal>
        </Row>
      )}

      {p.tab === 'categories' && (
        <Row title="تصنيفات القضايا" description="قائمة قابلة للإدارة. التعطيل يخفي التصنيف من الاختيارات الجديدة فقط.">
          <ul className="mb-3 divide-y divide-rule">{p.categories.map((c) => (
            <li key={c.id} className="flex items-center justify-between py-2">
              <span className={cx(!c.is_active && 'text-muted line-through')}>{c.name}</span>
              <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.put(`/office/admin/categories/${c.id}`, { name: c.name, is_active: !c.is_active }, { preserveScroll: true })}>{c.is_active ? 'تعطيل' : 'تفعيل'}</button>
            </li>
          ))}</ul>
          <form onSubmit={(e) => { e.preventDefault(); cat.post('/office/admin/categories', { preserveScroll: true, onSuccess: () => cat.reset() }); }} className="flex items-end gap-2">
            <TextField label="تصنيف جديد" value={cat.data.name} onChange={(e) => cat.setData('name', e.target.value)} error={cat.errors.name} />
            <Button type="submit" loading={cat.processing}>إضافة</Button>
          </form>
        </Row>
      )}
    </>
  );
}

function ActionTypeForm({ t, onDone }: { t: AT | null; onDone: () => void }) {
  const f = useForm({ name: t?.name ?? '', resubmission_interval_days: t?.resubmission_interval_days ? String(t.resubmission_interval_days) : '', interval_day_type: t?.interval_day_type ?? 'calendar',
    interval_from_last_same_type: t?.interval_from_last_same_type ?? true, condition_note: t?.condition_note ?? '', is_active: t?.is_active ?? true });
  return (
    <form onSubmit={(e) => { e.preventDefault(); t ? f.put(`/office/admin/action-types/${t.id}`, { preserveScroll: true, onSuccess: onDone }) : f.post('/office/admin/action-types', { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-3">
      <TextField label="الاسم" value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} error={f.errors.name} />
      <div className="grid grid-cols-2 gap-3">
        <TextField label="مهلة إعادة الطلب (أيام)" ltr value={f.data.resubmission_interval_days} onChange={(e) => f.setData('resubmission_interval_days', e.target.value.replace(/\D/g, ''))} hint="فارغ = لا مهلة." />
        <SelectField label="نوع الأيام" options={[{ value: 'calendar', label: 'تقويمية' }, { value: 'business', label: 'أيام عمل' }]} value={f.data.interval_day_type} onChange={(e) => f.setData('interval_day_type', e.target.value)} />
      </div>
      <Checkbox label="تُحتسب من آخر طلب من النوع نفسه" checked={f.data.interval_from_last_same_type} onChange={(v) => f.setData('interval_from_last_same_type', v)} />
      <TextField label="شرط الخدمة / مصدرها" value={f.data.condition_note} onChange={(e) => f.setData('condition_note', e.target.value)} />
      <Checkbox label="مفعّل" checked={f.data.is_active} onChange={(v) => f.setData('is_active', v)} />
      <Button type="submit" variant="primary" loading={f.processing}>حفظ</Button>
    </form>
  );
}
