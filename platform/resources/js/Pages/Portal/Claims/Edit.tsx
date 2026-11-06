import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Circle, Send, Save, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button, cx, DocumentList, FormError, Num, SelectField, TextArea, TextField, DateText } from '@/Components/ui';
import type { Doc, Option } from '@/types';
import { dateTime } from '@/lib/format';

type ClaimDoc = Doc & { categoryKey: string; party: string };
type Claim = {
  id: number; reference: string | null; status: string; statusKey: string; clientName: string; opponentType: string | null; opponentName: string | null;
  opponentIdentifier: string | null; opponentAddress: string | null; opponentPhone: string | null; amount: string | null; currency: string;
  description: string | null; missingDocumentReason: string | null; lockVersion: number; editable: boolean;
  messages: { id: number; kind: string; body: string; at: string }[]; documents: ClaimDoc[];
};

const catLabel: Record<string, string> = { invoice: 'الفواتير', statement: 'كشف الحساب', commercial_registration: 'السجل التجاري للطرف المطالب ضده' };

export default function Edit({ claim, submitKey, requiredByType, categories, parties, currencies }: {
  claim: Claim; submitKey: string; requiredByType: Record<string, string[]>; categories: Option[]; parties: Option[]; currencies: string[];
}) {
  const form = useForm({
    opponent_type: claim.opponentType ?? '', opponent_name: claim.opponentName ?? '', opponent_identifier: claim.opponentIdentifier ?? '',
    opponent_address: claim.opponentAddress ?? '', opponent_phone: claim.opponentPhone ?? '', amount: claim.amount ?? '', currency: claim.currency,
    description: claim.description ?? '', missing_document_reason: claim.missingDocumentReason ?? '', lock_version: claim.lockVersion,
  });
  const [submitting, setSubmitting] = useState(false);
  // بعد كل حفظ يعيد الخادم الإصدار الجديد للسجل
  useEffect(() => { form.setData('lock_version', claim.lockVersion); }, [claim.lockVersion]);
  const upload = useForm<{ file: File | null; category: string; party: string; label: string }>({ file: null, category: 'invoice', party: 'client', label: '' });
  const fileRef = useRef<HTMLInputElement>(null);

  const required = requiredByType[form.data.opponent_type || 'individual'] ?? [];
  const have = new Set(claim.documents.filter((d) => d.scan !== 'infected' && d.scan !== 'rejected').map((d) => d.categoryKey));
  const missing = required.filter((r) => !have.has(r));
  const completion = claim.messages.filter((m) => m.kind === 'completion_request');

  const save = (after?: () => void) => form.put(`/portal/claims/${claim.id}`, {
    preserveScroll: true,
    onSuccess: () => after?.(),
  });
  const submit = () => {
    setSubmitting(true);
    form.put(`/portal/claims/${claim.id}`, {
      preserveScroll: true,
      onSuccess: () => router.post(`/portal/claims/${claim.id}/submit`, { submit_key: submitKey }, { preserveScroll: true, onFinish: () => setSubmitting(false) }),
      onError: () => setSubmitting(false),
    });
  };
  const doUpload = (e: React.FormEvent) => {
    e.preventDefault();
    upload.post(`/portal/claims/${claim.id}/documents`, { preserveScroll: true, forceFormData: true, onSuccess: () => { upload.reset('file', 'label'); if (fileRef.current) fileRef.current.value = ''; } });
  };
  const removeDoc = (d: Doc) => { if (window.confirm('حذف هذا المرفق من المطالبة؟')) router.delete(`/portal/claims/${claim.id}/documents/${d.id}`, { preserveScroll: true }); };

  return (
    <>
      <Head title="إدراج مطالبة" />
      <nav aria-label="مسار التنقل" className="mb-2 text-[13px] text-muted"><Link href="/portal/claims" className="hover:text-text">المطالبات</Link> / {claim.opponentName ?? (claim.reference ? 'مطالبة' : 'مسودة')}</nav>
      <header className="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-[22px] font-bold">{claim.statusKey === 'needs_completion' ? 'استكمال المطالبة' : 'إدراج مطالبة قانونية'}</h1>
          <p className="mt-1 text-[14px] text-muted">احفظ المسودة في أي وقت، وأرسلها عند اكتمال البيانات والمرفقات.</p>
        </div>
      </header>

      {completion.length > 0 && (
        <section className="mb-6 rounded-xl border border-[var(--warning-border)] bg-[var(--pill-warning-bg)] px-5 py-4" aria-labelledby="req">
          <h2 id="req" className="font-semibold text-[var(--pill-warning-ink)]">طلب استكمال من المكتب</h2>
          {completion.map((m) => <p key={m.id} className="mt-1 text-[14px]">{m.body} <span className="text-[12px] text-muted">(<DateText value={m.at} time />)</span></p>)}
        </section>
      )}

      <FormError errors={form.errors as Record<string, string>} />

      <form onSubmit={(e) => { e.preventDefault(); save(); }} className="space-y-8" noValidate>
        <section aria-labelledby="s1" className="grid gap-4 md:grid-cols-[260px_1fr] md:gap-10">
          <div><h2 id="s1" className="font-semibold">موكل المكتب</h2><p className="text-[13px] text-muted">صاحب الحق الذي يمثله المكتب — تُعبأ من حسابك.</p></div>
          <div className="well px-4 py-3">
            <p className="text-[12.5px] text-muted">اسم موكل المكتب</p>
            <p className="font-semibold">{claim.clientName}</p>
          </div>
        </section>

        <section aria-labelledby="s2" className="grid gap-4 border-t border-rule pt-8 md:grid-cols-[260px_1fr] md:gap-10">
          <div><h2 id="s2" className="font-semibold">الطرف المطالب ضده</h2><p className="text-[13px] text-muted">الشخص أو المنشأة المدينة لكم بالمبلغ.</p></div>
          <div className="grid gap-4 sm:grid-cols-2">
            <fieldset className="sm:col-span-2">
              <legend className="field-label">نوع الطرف</legend>
              <div className="flex gap-2" role="radiogroup">
                {[['organization', 'منشأة'], ['individual', 'فرد']].map(([v, l]) => (
                  <label key={v} className={cx('btn btn-secondary cursor-pointer', form.data.opponent_type === v && '!border-ink !bg-active font-semibold')}>
                    <input type="radio" name="opponent_type" value={v} className="sr-only" checked={form.data.opponent_type === v} onChange={() => form.setData('opponent_type', v)} />{l}
                  </label>
                ))}
              </div>
              {form.errors.opponent_type && <p className="field-error">{form.errors.opponent_type}</p>}
            </fieldset>
            <TextField className="sm:col-span-2" label="اسم الطرف المطالب ضده" value={form.data.opponent_name} onChange={(e) => form.setData('opponent_name', e.target.value)} error={form.errors.opponent_name} maxLength={200} />
            <TextField label={form.data.opponent_type === 'organization' ? 'رقم السجل التجاري أو الرقم الموحد' : 'رقم الهوية أو الإقامة'} ltr inputMode="numeric"
              value={form.data.opponent_identifier} onChange={(e) => form.setData('opponent_identifier', e.target.value.replace(/[^\d]/g, ''))} error={form.errors.opponent_identifier} hint="أرقام فقط." />
            <TextField label="جوال الطرف (اختياري)" ltr inputMode="tel" value={form.data.opponent_phone} onChange={(e) => form.setData('opponent_phone', e.target.value)} error={form.errors.opponent_phone} />
            <TextField className="sm:col-span-2" label="العنوان (اختياري)" value={form.data.opponent_address} onChange={(e) => form.setData('opponent_address', e.target.value)} />
          </div>
        </section>

        <section aria-labelledby="s3" className="grid gap-4 border-t border-rule pt-8 md:grid-cols-[260px_1fr] md:gap-10">
          <div><h2 id="s3" className="font-semibold">المبلغ والوقائع</h2><p className="text-[13px] text-muted">إجمالي المبلغ المطالب به وعملته.</p></div>
          <div className="grid gap-4 sm:grid-cols-[1fr_140px]">
            <TextField label="مبلغ المطالبة" ltr inputMode="decimal" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value.replace(/[^\d.]/g, ''))} error={form.errors.amount} />
            <SelectField label="العملة" options={currencies} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} />
            <TextArea className="sm:col-span-2" label="وصف مختصر للمطالبة (اختياري)" rows={4} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={5000} />
          </div>
        </section>

        <div className="flex justify-end"><Button type="submit" loading={form.processing && !submitting}><Save className="size-4" aria-hidden />حفظ المسودة</Button></div>
      </form>

      <section aria-labelledby="s4" className="mt-8 grid gap-4 border-t border-rule pt-8 md:grid-cols-[260px_1fr] md:gap-10">
        <div>
          <h2 id="s4" className="font-semibold">المرفقات</h2>
          <p className="text-[13px] text-muted">المستندات الأساسية بحسب نوع الطرف:</p>
          <ul className="mt-2 space-y-1 text-[13.5px]">
            {required.map((r) => (
              <li key={r} className="flex items-center gap-1.5">
                {have.has(r) ? <CheckCircle2 className="size-4 text-positive" aria-hidden /> : <Circle className="size-4 text-faint" aria-hidden />}
                {catLabel[r] ?? r}<span className="sr-only">{have.has(r) ? ' — مرفق' : ' — غير مرفق'}</span>
              </li>
            ))}
          </ul>
          <p className="mt-2 text-[12.5px] text-muted">وأرفق سند الأمر ومطابقة الرصيد والاتفاقية إن وجدت. يمكن إرفاق أكثر من فاتورة.</p>
        </div>
        <div className="space-y-4">
          <form onSubmit={doUpload} className="well grid gap-3 p-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <label className="field-label" htmlFor="claim-file">الملف</label>
              <input ref={fileRef} id="claim-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" className="input pt-1.5" onChange={(e) => upload.setData('file', e.target.files?.[0] ?? null)} aria-describedby="claim-file-hint" />
              <p id="claim-file-hint" className="field-hint">PDF أو صورة أو Word/Excel حتى 25MB. يخضع كل ملف لفحص أمني قبل إتاحته.</p>
              {upload.errors.file && <p className="field-error" role="alert">{upload.errors.file}</p>}
            </div>
            <SelectField label="نوع المستند" options={categories} value={upload.data.category} onChange={(e) => upload.setData('category', e.target.value)} />
            <SelectField label="يخص أي طرف؟" options={parties} value={upload.data.party} onChange={(e) => upload.setData('party', e.target.value)} hint="مثال: الفاتورة صادرة منكم، السجل التجاري للطرف المطالب ضده." />
            <TextField label="وصف (مثل رقم الفاتورة)" value={upload.data.label} onChange={(e) => upload.setData('label', e.target.value)} maxLength={120} />
            <div className="flex items-end"><Button type="submit" loading={upload.processing} disabled={!upload.data.file}><Upload className="size-4" aria-hidden />إرفاق</Button></div>
          </form>
          <DocumentList docs={claim.documents} empty="لم تُرفق مستندات بعد." onDelete={removeDoc} />
          {missing.length > 0 && (
            <TextArea label="سبب عدم توفر مستند أساسي" rows={3} value={form.data.missing_document_reason} onChange={(e) => form.setData('missing_document_reason', e.target.value)}
              error={form.errors.missing_document_reason} hint={`غير مرفق: ${missing.map((m) => catLabel[m]).join('، ')}. ستُرفع المطالبة للمراجعة بعلامة «ناقص مستند».`} />
          )}
        </div>
      </section>

      <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-rule pt-6">
        <p className="text-[13px] text-muted">بعد الإرسال تظهر المطالبة للمكتب بحالة «قيد الدراسة».</p>
        <div className="flex gap-2">
          <Button onClick={() => save()} loading={form.processing && !submitting}>حفظ المسودة</Button>
          <Button variant="primary" onClick={submit} loading={submitting}><Send className="size-4" aria-hidden />إرسال المطالبة</Button>
        </div>
      </div>
    </>
  );
}
