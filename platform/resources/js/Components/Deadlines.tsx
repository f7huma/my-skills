import { router, useForm } from '@inertiajs/react';
import { Button, certaintyTone, cx, EmptyState, Modal, Num, Pill, SelectField, TextArea, TextField, useModal } from '@/Components/ui';
import { remainingLabel } from '@/lib/format';
import type { Option } from '@/types';
import { useState } from 'react';

export type DeadlineRow = {
  id: number; kind: string; kindLabel: string; certainty: string; certaintyLabel: string; reason: string; startOn: string | null; endOn: string | null; endHijri: string | null;
  duration: number | null; dayType: string | null; remaining: number | null; basis: string | null; source: string; status: string; extensionReason: string | null;
  rule: { name: string; version: number; source: string } | null; updatedAt: string | null; updatedBy?: string | null; caseId: number | null; executionId: number | null; ref?: string | null; client?: string | null;
};

export function DeadlineEditor({ d, kinds, certainties, onDone }: { d: DeadlineRow; kinds: Option[]; certainties: Option[]; onDone: () => void }) {
  const [mode, setMode] = useState<'end' | 'duration'>(d.duration ? 'duration' : 'end');
  const form = useForm({ kind: d.kind, certainty: d.certainty === 'needs_determination' ? 'confirmed' : d.certainty, reason: d.reason, end_on: d.endOn ?? '', start_on: d.startOn ?? '',
    duration: d.duration ? String(d.duration) : '', day_type: d.dayType ?? 'calendar', basis: '', extension_reason: d.extensionReason ?? '' });
  return (
    <form onSubmit={(e) => { e.preventDefault(); form.transform((x) => (mode === 'end' ? { ...x, start_on: '', duration: '' } : { ...x, end_on: '' })); form.put(`/office/deadlines/${d.id}`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-4 sm:grid-cols-2">
      <TextField className="sm:col-span-2" label="سبب المتابعة" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} error={form.errors.reason} />
      <SelectField label="النوع" options={kinds} value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)} />
      <SelectField label="درجة اليقين" options={certainties} value={form.data.certainty} onChange={(e) => form.setData('certainty', e.target.value)} hint="«موعد مؤكد» لا يُستبدل آليًا." />
      <fieldset className="sm:col-span-2">
        <legend className="field-label">طريقة التحديد</legend>
        <div className="flex gap-2">
          <button type="button" className={cx('btn btn-sm', mode === 'end' ? 'btn-primary' : 'btn-secondary')} onClick={() => setMode('end')} aria-pressed={mode === 'end'}>تاريخ نهاية</button>
          <button type="button" className={cx('btn btn-sm', mode === 'duration' ? 'btn-primary' : 'btn-secondary')} onClick={() => setMode('duration')} aria-pressed={mode === 'duration'}>مدة من تاريخ بداية</button>
        </div>
      </fieldset>
      {mode === 'end' ? <TextField label="تاريخ النهاية" type="date" value={form.data.end_on} onChange={(e) => form.setData('end_on', e.target.value)} error={form.errors.end_on} /> : (
        <>
          <TextField label="تاريخ البداية" type="date" value={form.data.start_on} onChange={(e) => form.setData('start_on', e.target.value)} />
          <TextField label="المدة" ltr inputMode="numeric" value={form.data.duration} onChange={(e) => form.setData('duration', e.target.value.replace(/\D/g, ''))} />
          <SelectField label="نوع الأيام" options={[{ value: 'calendar', label: 'تقويمية' }, { value: 'business', label: 'أيام عمل' }]} value={form.data.day_type} onChange={(e) => form.setData('day_type', e.target.value)} />
        </>
      )}
      <TextField className="sm:col-span-2" label="أساس الحساب (يظهر بجانب الموعد)" value={form.data.basis} onChange={(e) => form.setData('basis', e.target.value)} placeholder="مثل: من تاريخ استلام الصك عبر ناجز" />
      <TextArea className="sm:col-span-2" label="تمديد أو استثناء (السبب)" rows={2} value={form.data.extension_reason} onChange={(e) => form.setData('extension_reason', e.target.value)} />
      <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={form.processing}>حفظ الموعد</Button></div>
    </form>
  );
}

export function DeadlineList({ rows, kinds, certainties, showRef }: { rows: DeadlineRow[]; kinds: Option[]; certainties: Option[]; showRef?: boolean }) {
  const modal = useModal();
  const [edit, setEdit] = useState<DeadlineRow | null>(null);
  if (!rows.length) return <EmptyState title="لا مواعيد أو عدادات">تُنشأ آليًا عند تسجيل الحكم أو الإبلاغ، أو أضفها يدويًا.</EmptyState>;
  return (
    <>
      <ul className="divide-y divide-rule">
        {rows.map((d) => (
          <li key={d.id} id={`deadline-${d.id}`} className={cx('grid gap-2 py-3.5 sm:grid-cols-[1fr_auto]', d.status === 'closed' && 'opacity-60')}>
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-medium">{d.reason}</p>
                <Pill tone={certaintyTone(d.certainty)}>{d.certaintyLabel}</Pill>
                {d.status === 'alerted' && <Pill tone="negative">انتهى — يحتاج متابعة</Pill>}
                {d.status === 'closed' && <Pill>مغلق</Pill>}
              </div>
              <p className="mt-0.5 text-[12.5px] text-muted">
                {d.kindLabel}{showRef && d.ref && <> · <Num>{d.ref}</Num></>}{showRef && d.client && <> · {d.client}</>}
                {d.startOn && <> · البداية <Num>{d.startOn}</Num></>}
                {d.updatedAt && <> · آخر تعديل <Num>{d.updatedAt.slice(0, 10)}</Num>{d.updatedBy ? ` (${d.updatedBy})` : ''}</>}
              </p>
              {d.basis && <p className="mt-1 text-[13px] text-text/80">الأساس: {d.basis}</p>}
              {d.extensionReason && <p className="mt-0.5 text-[13px] text-muted">تمديد/استثناء: {d.extensionReason}</p>}
            </div>
            <div className="flex items-center gap-3 sm:flex-col sm:items-end">
              <div className="text-end">
                <p className="text-[15px] font-semibold"><Num>{d.endOn ?? '—'}</Num></p>
                {d.endHijri && <p className="text-[12px] text-muted">{d.endHijri}</p>}
                <p className={cx('text-[12.5px]', (d.remaining ?? 1) < 0 ? 'font-semibold text-negative' : 'text-muted')}>{remainingLabel(d.remaining, d.dayType)}</p>
              </div>
              <div className="flex gap-1">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => { setEdit(d); modal.show(); }}>{d.certainty === 'needs_determination' ? 'تحديد الموعد' : 'تعديل'}</button>
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/office/deadlines/${d.id}/toggle`, {}, { preserveScroll: true })}>{d.status === 'closed' ? 'إعادة فتح' : 'إغلاق'}</button>
              </div>
            </div>
          </li>
        ))}
      </ul>
      <Modal open={modal.open} onClose={modal.close} title="تعديل الموعد">{edit && <DeadlineEditor key={edit.id} d={edit} kinds={kinds} certainties={certainties} onDone={modal.close} />}</Modal>
    </>
  );
}
