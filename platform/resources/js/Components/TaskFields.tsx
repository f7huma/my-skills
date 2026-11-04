import { SelectField, TextField } from '@/Components/ui';
import type { Option } from '@/types';

export type TaskInput = {
  title: string; kind: string; due_mode: string; due_on: string; relative_from: string; relative_event: string;
  relative_days: string; relative_day_type: string; anchor_offset_days: string; description?: string;
};

export const emptyTask = (kind = 'manual'): TaskInput => ({
  title: '', kind, due_mode: 'date', due_on: '', relative_from: '', relative_event: '', relative_days: '', relative_day_type: 'calendar', anchor_offset_days: '0',
});

/** الاستحقاق: تاريخ صريح، أو مدة من واقعة معلومة، أو مرتبط بالجلسة القادمة، أو بانتظار تحديد موعد */
export function TaskFields({ value, onChange, kinds, dueModes, errors = {}, prefix = '' }: {
  value: TaskInput; onChange: (v: TaskInput) => void; kinds: Option[]; dueModes: Option[]; errors?: Record<string, string>; prefix?: string;
}) {
  const set = (k: keyof TaskInput, v: string) => onChange({ ...value, [k]: v });
  const err = (k: string) => errors[prefix + k];
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <TextField className="sm:col-span-2" label="المهمة" value={value.title} onChange={(e) => set('title', e.target.value)} error={err('title')} maxLength={250} required />
      <SelectField label="النوع" options={kinds} value={value.kind} onChange={(e) => set('kind', e.target.value)} />
      <SelectField label="الاستحقاق" options={dueModes} value={value.due_mode} onChange={(e) => set('due_mode', e.target.value)} error={err('due_mode')} />
      {value.due_mode === 'date' && <TextField label="تاريخ الاستحقاق" type="date" value={value.due_on} onChange={(e) => set('due_on', e.target.value)} error={err('due_on')} />}
      {value.due_mode === 'relative' && (
        <>
          <TextField label="الواقعة المعلومة" placeholder="مثل: تاريخ تبليغ الحكم" value={value.relative_event} onChange={(e) => set('relative_event', e.target.value)} />
          <TextField label="تاريخ الواقعة" type="date" value={value.relative_from} onChange={(e) => set('relative_from', e.target.value)} error={err('relative_from')} />
          <TextField label="المدة (أيام)" ltr inputMode="numeric" value={value.relative_days} onChange={(e) => set('relative_days', e.target.value.replace(/[^\d-]/g, ''))} error={err('relative_days')} />
          <SelectField label="نوع الأيام" options={[{ value: 'calendar', label: 'أيام تقويمية' }, { value: 'business', label: 'أيام عمل (تستبعد الجمعة والسبت والإجازات)' }]} value={value.relative_day_type} onChange={(e) => set('relative_day_type', e.target.value)} />
        </>
      )}
      {value.due_mode === 'next_hearing' && (
        <TextField label="قبل الجلسة القادمة أو بعدها بـ (أيام)" ltr inputMode="numeric" hint="سالب = قبل الجلسة. يتحدث الموعد تلقائيًا عند تغيّر الجلسة."
          value={value.anchor_offset_days} onChange={(e) => set('anchor_offset_days', e.target.value.replace(/[^\d-]/g, ''))} error={err('anchor_offset_days')} />
      )}
      {value.due_mode === 'unscheduled' && <p className="self-end pb-2 text-[13px] text-muted">تظهر في قائمة «بانتظار تحديد موعد».</p>}
    </div>
  );
}
