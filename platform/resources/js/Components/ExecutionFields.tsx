import { SelectField, TextArea, TextField, Checkbox, cx } from '@/Components/ui';
import type { Option } from '@/types';

export type ExecData = Record<string, string | boolean>;

/** حقول التنفيذ: نوع التنفيذ منفصل عن نوع السند، وتظهر حقول كل نوع بحسب الحاجة */
export function ExecutionFields({ data, set, errors, options }: { data: ExecData; set: (k: string, v: string | boolean) => void; errors: Record<string, string>; options: { executionTypes: Option[]; instrumentTypes: Option[]; statuses: Option[]; currencies: string[]; cases?: Option[] } }) {
  const v = (k: string) => String(data[k] ?? '');
  const text = (k: string, label: string, props: Record<string, unknown> = {}) => <TextField label={label} value={v(k)} onChange={(e) => set(k, e.target.value)} error={errors[k]} {...props} />;
  const date = (k: string, label: string, hint?: string) => <TextField label={label} type="date" value={v(k)} onChange={(e) => set(k, e.target.value)} error={errors[k]} hint={hint} />;
  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-3">
        <fieldset className="sm:col-span-1">
          <legend className="field-label">نوع التنفيذ</legend>
          <div className="flex gap-2">{options.executionTypes.map((o) => (
            <label key={String(o.value)} className={cx('btn btn-secondary cursor-pointer', v('execution_type') === o.value && '!border-ink !bg-active font-semibold')}>
              <input type="radio" className="sr-only" checked={v('execution_type') === o.value} onChange={() => set('execution_type', String(o.value))} />{o.label}</label>
          ))}</div>
        </fieldset>
        <SelectField label="نوع السند" options={options.instrumentTypes} value={v('instrument_type')} onChange={(e) => set('instrument_type', e.target.value)} />
        {v('instrument_type') === 'other' ? text('instrument_label', 'اسم نوع السند', { required: true }) : <div className="hidden sm:block" />}
      </div>

      {v('instrument_type') === 'judgment_deed' && (
        <fieldset className="grid gap-4 sm:grid-cols-3"><legend className="mb-2 font-semibold">بيانات الصك</legend>{text('deed_number', 'رقم الصك', { ltr: true })}{date('deed_date', 'تاريخ الصك')}</fieldset>
      )}
      {v('instrument_type') === 'electronic_promissory_note' && (
        <fieldset className="grid gap-4 sm:grid-cols-3"><legend className="mb-2 font-semibold">بيانات السند لأمر</legend>
          {text('note_number', 'رقم السند', { ltr: true })}
          <div className="grid grid-cols-[1fr_100px] gap-2">{text('note_amount', 'قيمة السند', { ltr: true, inputMode: 'decimal' })}<SelectField label="العملة" options={options.currencies} placeholder="—" value={v('note_currency')} onChange={(e) => set('note_currency', e.target.value)} /></div>
          {date('note_issued_on', 'تاريخ الإصدار')}{date('note_due_on', 'تاريخ الاستحقاق')}
        </fieldset>
      )}
      {v('execution_type') === 'direct' && (
        <fieldset className="grid gap-4 sm:grid-cols-2"><legend className="mb-2 font-semibold">التنفيذ المباشر</legend>
          {text('subject_location', 'محل التنفيذ', { required: true })}
          <TextArea className="sm:col-span-2" label="وصف الالتزام" rows={3} value={v('obligation_description')} onChange={(e) => set('obligation_description', e.target.value)} error={errors.obligation_description} required />
        </fieldset>
      )}

      <fieldset className="grid gap-4 sm:grid-cols-3"><legend className="mb-2 font-semibold">الطلب ووقائعه</legend>
        {text('request_number', 'رقم طلب التنفيذ', { ltr: true })}
        <SelectField label="الحالة" options={options.statuses} value={v('status')} onChange={(e) => set('status', e.target.value)} />
        <div className="grid grid-cols-[1fr_100px] gap-2">{text('amount_claimed', 'المبلغ المطلوب', { ltr: true, inputMode: 'decimal' })}<SelectField label="العملة" options={options.currencies} value={v('currency')} onChange={(e) => set('currency', e.target.value)} /></div>
        {date('submitted_on', 'تاريخ تقديم الطلب')}
        {options.cases && (
          <SelectField className="sm:col-span-2" label="القضية المرتبطة (اختياري)" placeholder={options.cases.length ? 'بدون ربط بقضية' : 'لا قضايا لهذا العميل'} options={options.cases}
            value={v('legal_case_id')} onChange={(e) => set('legal_case_id', e.target.value)} error={errors.legal_case_id}
            hint="عند الربط تُحتسب مبالغ هذا الطلب ومواعيده ضمن القضية، دون تكرار." />
        )}
      </fieldset>
      <div className="grid gap-4 sm:grid-cols-2">
        <TextArea label="ملخص موجه للعميل" rows={3} value={v('client_summary')} onChange={(e) => set('client_summary', e.target.value)} hint="يظهر في البوابة كآخر تحديث." />
        <TextArea label="ملاحظات داخلية" rows={3} value={v('internal_notes')} onChange={(e) => set('internal_notes', e.target.value)} />
      </div>
      <Checkbox label="متابعة داخلية كل سبعة أيام لهذا الملف" hint="تذكير داخلي في قائمة الإنجاز — منفصل عن شروط قبول الطلبات في ناجز." checked={Boolean(data.weekly_followup)} onChange={(b) => set('weekly_followup', b)} />
    </div>
  );
}
