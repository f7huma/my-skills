import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button, EmptyState, Modal, Money, Num, Panel, SelectField, TextField, TotalsGrid, useModal, Checkbox } from '@/Components/ui';
import type { Doc, Option, Totals } from '@/types';

export type CollectionRow = { id: number; kind: string; kindLabel: string; source: string; sourceLabel: string; amount: string; currency: string; on: string; description: string | null; visible: boolean; document: Doc | null; executionId: number | null };

const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, () => Math.floor(Math.random() * 16).toString(16)));

/** سجل التحصيل: عمليات مستقلة بمفتاح فريد لكل إدخال؛ المجاميع لكل عملة على حدة */
export function CollectionsPanel({ rows, totals, kinds, sources, currencies, caseId, executionId, reconciliations = [], defaultSource = 'execution', documents = [] }: {
  rows: CollectionRow[]; totals: Totals; kinds: Option[]; sources: Option[]; currencies: string[]; caseId?: number; executionId?: number;
  reconciliations?: Option[]; defaultSource?: string; documents?: Doc[];
}) {
  const modal = useModal();
  const form = useForm({
    entry_key: uuid(), legal_case_id: caseId ?? '', execution_request_id: executionId ?? '', reconciliation_request_id: '',
    kind: 'received', source: defaultSource, amount: '', currency: 'SAR', occurred_on: new Date().toISOString().slice(0, 10), description: '', visible_to_client: true, document: '',
  });
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post('/office/collections', { preserveScroll: true, onSuccess: () => { modal.close(); form.reset(); form.setData('entry_key', uuid()); } });
  };
  const remove = (r: CollectionRow) => {
    const reason = window.prompt('سبب حذف العملية (يُحفظ في سجل التدقيق):');
    if (reason) router.delete(`/office/collections/${r.id}`, { data: { reason }, preserveScroll: true });
  };
  return (
    <Panel title="التحصيل والمبالغ" description="كل دفعة عملية مستقلة. الدفعة المسجلة في التنفيذ تظهر في القضية المرتبطة دون تكرار."
      actions={<Button size="sm" variant="primary" onClick={modal.show}><Plus className="size-4" aria-hidden />عملية مالية</Button>}>
      <TotalsGrid totals={totals} labels={{ claimed: 'المطلوب', agreed: 'المتفق عليه', seized: 'المحجوز', received: 'المقبوض فعليًا' }} />
      <div className="mt-4">
        {rows.length === 0 ? <EmptyState title="لا عمليات مالية بعد" /> : (
          <div className="overflow-x-auto"><table className="table">
            <thead><tr><th>البند</th><th>المصدر</th><th>التاريخ</th><th>الوصف</th><th className="end">المبلغ</th><th><span className="sr-only">إجراءات</span></th></tr></thead>
            <tbody>{rows.map((r) => (
              <tr key={r.id}>
                <td className="font-medium">{r.kindLabel}{!r.visible && <span className="ms-2 text-[12px] text-muted">(داخلي)</span>}</td>
                <td className="text-muted">{r.sourceLabel}{r.executionId && !executionId ? ' — ملف تنفيذ' : ''}</td>
                <td><Num>{r.on}</Num></td>
                <td className="max-w-[260px] truncate text-muted">{r.description ?? '—'}{r.document?.url && <> · <a href={r.document.url} className="text-accent hover:underline">المستند</a></>}</td>
                <td className="end"><Money value={r.amount} currency={r.currency} className={r.kind === 'received' ? 'font-semibold text-accent' : ''} /></td>
                <td className="end"><button type="button" className="btn btn-ghost btn-sm" aria-label="حذف العملية" onClick={() => remove(r)}><Trash2 className="size-4" /></button></td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
      <Modal open={modal.open} onClose={modal.close} title="تسجيل عملية مالية">
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <SelectField label="نوع البند" options={kinds} value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)} error={form.errors.kind} />
          <SelectField label="المصدر" options={sources} value={form.data.source} onChange={(e) => form.setData('source', e.target.value)} />
          {form.data.source === 'reconciliation' && reconciliations.length > 0 && (
            <SelectField className="sm:col-span-2" label="طلب الصلح المرتبط" options={reconciliations} placeholder="—" value={form.data.reconciliation_request_id} onChange={(e) => form.setData('reconciliation_request_id', e.target.value)} />
          )}
          <TextField label="المبلغ" ltr inputMode="decimal" required value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value.replace(/[^\d.]/g, ''))} error={form.errors.amount} />
          <SelectField label="العملة" options={currencies} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} />
          <TextField label="التاريخ" type="date" value={form.data.occurred_on} onChange={(e) => form.setData('occurred_on', e.target.value)} error={form.errors.occurred_on} />
          <TextField label="الوصف" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={250} />
          {documents.length > 0 && <SelectField className="sm:col-span-2" label="المستند المؤيد (اختياري)" placeholder="بدون مستند" options={documents.filter((d) => d.scan === 'clean').map((d) => ({ value: d.id, label: d.label || d.name }))} value={form.data.document} onChange={(e) => form.setData('document', e.target.value)} />}
          <div className="sm:col-span-2"><Checkbox label="يظهر للعميل في البوابة" checked={form.data.visible_to_client} onChange={(v) => form.setData('visible_to_client', v)} /></div>
          <div className="sm:col-span-2"><Button variant="primary" type="submit" loading={form.processing}>تسجيل</Button></div>
        </form>
      </Modal>
    </Panel>
  );
}
