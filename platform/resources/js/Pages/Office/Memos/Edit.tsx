import { Head, Link, useForm } from '@inertiajs/react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { AlignCenter, AlignJustify, AlignLeft, AlignRight, Bold, History, Italic, List, ListOrdered, Printer, Quote, Save, Underline as UIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button, cx, FormError, Modal, Num, PageHeader, Pill, SelectField, TextField, UploadForm, useModal, DateText } from '@/Components/ui';
import { dateTime } from '@/lib/format';
import type { Doc, Option } from '@/types';

type Memo = { id: number; title: string; type: string; typeLabel: string; status: string; dueOn: string | null; approvedAt: string | null; filedOn: string | null; hearingId: number | null; version: number; lockVersion: number; body: string; filedDocument: Doc | null };

const statusTone: Record<string, 'neutral' | 'info' | 'accent' | 'positive'> = { draft: 'neutral', ready_for_review: 'info', approved: 'accent', filed: 'positive' };

/** محرر داخلي فقط: لا يرسل المحتوى لأي خدمة خارجية، والمسودات لا تظهر في بوابة العميل */
export default function Edit({ memo, case: c, versions, hearings, statuses, filedCandidates }: {
  memo: Memo; case: { id: number; reference: string; title: string; client: string; clientId: number }; versions: { id: number; version: number; at: string }[]; hearings: Option[]; statuses: Option[]; filedCandidates: Option[];
}) {
  const form = useForm({ title: memo.title, body: memo.body, due_on: memo.dueOn ?? '', hearing_id: memo.hearingId ? String(memo.hearingId) : '', lock_version: memo.lockVersion });
  const st = useForm({ status: memo.status, filed_on: memo.filedOn ?? '', filed_document: '', lock_version: memo.lockVersion });
  useEffect(() => { form.setData('lock_version', memo.lockVersion); st.setData('lock_version', memo.lockVersion); }, [memo.lockVersion]);
  const [dirty, setDirty] = useState(false);
  const hist = useModal();
  const [preview, setPreview] = useState<{ version: number; body: string } | null>(null);
  const editor = useEditor({
    extensions: [StarterKit.configure({ heading: { levels: [2, 3] } }), TextAlign.configure({ types: ['heading', 'paragraph'], defaultAlignment: 'right' })],
    content: memo.body || '<p></p>',
    editorProps: { attributes: { class: 'memo-editor', dir: 'rtl', 'aria-label': 'نص المذكرة', role: 'textbox', 'aria-multiline': 'true' } },
    onUpdate: ({ editor: e }) => { form.setData('body', e.getHTML()); setDirty(true); },
  });
  const save = () => form.put(`/office/memos/${memo.id}`, { preserveScroll: true, onSuccess: () => setDirty(false) });
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(); } };
    const before = (e: BeforeUnloadEvent) => { if (dirty) { e.preventDefault(); } };
    window.addEventListener('keydown', onKey); window.addEventListener('beforeunload', before);
    return () => { window.removeEventListener('keydown', onKey); window.removeEventListener('beforeunload', before); };
  });
  const tool = (label: string, Icon: typeof Bold, active: boolean, run: () => void) => (
    <button type="button" className={cx('btn btn-ghost btn-sm px-2', active && 'bg-active')} aria-label={label} aria-pressed={active} title={label} onMouseDown={(e) => e.preventDefault()} onClick={run}><Icon className="size-4" /></button>
  );
  const showVersion = (v: number) => fetch(`/office/memos/${memo.id}/versions/${v}`, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => setPreview({ version: d.version, body: d.body }));

  return (
    <>
      <Head title={memo.title} />
      <PageHeader title={memo.title} crumbs={[{ label: 'القضايا', href: '/office/cases' }, { label: c.title, href: `/office/cases/${c.id}?tab=tasks` }]}
        subtitle={<span className="flex flex-wrap items-center gap-2">{memo.typeLabel} · {c.client} · إصدار <Num>{memo.version}</Num><Pill tone={statusTone[memo.status]}>{statuses.find((s) => s.value === memo.status)?.label}</Pill>{dirty && <span className="text-[#7a4307]">تغييرات غير محفوظة</span>}</span>}
        actions={<>
          <Button size="sm" onClick={hist.show}><History className="size-4" aria-hidden />الإصدارات</Button>
          <a href={`/office/memos/${memo.id}/print`} target="_blank" rel="noopener" className="btn btn-secondary btn-sm"><Printer className="size-4" aria-hidden />طباعة</a>
          <Button size="sm" variant="primary" onClick={save} loading={form.processing}><Save className="size-4" aria-hidden />حفظ إصدار</Button>
        </>} />
      <FormError errors={form.errors as Record<string, string>} />
      <div className="grid gap-5 xl:grid-cols-[1fr_300px]">
        <div className="card overflow-hidden">
          <div className="flex flex-wrap items-center gap-0.5 border-b border-rule bg-raised px-2 py-1.5" role="toolbar" aria-label="تنسيق النص">
            {editor && <>
              {tool('عريض', Bold, editor.isActive('bold'), () => editor.chain().focus().toggleBold().run())}
              {tool('مائل', Italic, editor.isActive('italic'), () => editor.chain().focus().toggleItalic().run())}
              {tool('تسطير', UIcon, editor.isActive('underline'), () => editor.chain().focus().toggleUnderline().run())}
              <span className="mx-1 h-5 w-px bg-line" aria-hidden />
              <button type="button" className={cx('btn btn-ghost btn-sm', editor.isActive('heading', { level: 2 }) && 'bg-active')} onMouseDown={(e) => e.preventDefault()} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()} aria-pressed={editor.isActive('heading', { level: 2 })}>عنوان</button>
              <button type="button" className={cx('btn btn-ghost btn-sm', editor.isActive('heading', { level: 3 }) && 'bg-active')} onMouseDown={(e) => e.preventDefault()} onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()} aria-pressed={editor.isActive('heading', { level: 3 })}>عنوان فرعي</button>
              {tool('قائمة نقطية', List, editor.isActive('bulletList'), () => editor.chain().focus().toggleBulletList().run())}
              {tool('قائمة مرقمة', ListOrdered, editor.isActive('orderedList'), () => editor.chain().focus().toggleOrderedList().run())}
              {tool('اقتباس', Quote, editor.isActive('blockquote'), () => editor.chain().focus().toggleBlockquote().run())}
              <span className="mx-1 h-5 w-px bg-line" aria-hidden />
              {tool('محاذاة لليمين', AlignRight, editor.isActive({ textAlign: 'right' }), () => editor.chain().focus().setTextAlign('right').run())}
              {tool('توسيط', AlignCenter, editor.isActive({ textAlign: 'center' }), () => editor.chain().focus().setTextAlign('center').run())}
              {tool('محاذاة لليسار', AlignLeft, editor.isActive({ textAlign: 'left' }), () => editor.chain().focus().setTextAlign('left').run())}
              {tool('ضبط', AlignJustify, editor.isActive({ textAlign: 'justify' }), () => editor.chain().focus().setTextAlign('justify').run())}
            </>}
          </div>
          <EditorContent editor={editor} />
        </div>
        <aside className="grid grid-cols-1 content-start gap-4">
          <section className="card p-4" aria-labelledby="meta">
            <h2 id="meta" className="mb-3 font-semibold">بيانات المذكرة</h2>
            <div className="space-y-3">
              <TextField label="العنوان" value={form.data.title} onChange={(e) => { form.setData('title', e.target.value); setDirty(true); }} error={form.errors.title} />
              <TextField label="الموعد" type="date" value={form.data.due_on} onChange={(e) => { form.setData('due_on', e.target.value); setDirty(true); }} hint="يظهر في قائمة إنجاز الأسبوع." />
              <SelectField label="الجلسة المرتبطة" placeholder="بدون" options={hearings} value={form.data.hearing_id} onChange={(e) => { form.setData('hearing_id', e.target.value); setDirty(true); }} />
            </div>
          </section>
          <section className="card p-4" aria-labelledby="status">
            <h2 id="status" className="mb-3 font-semibold">الحالة</h2>
            <form onSubmit={(e) => { e.preventDefault(); st.post(`/office/memos/${memo.id}/status`, { preserveScroll: true }); }} className="space-y-3">
              <SelectField label="حالة المذكرة" options={statuses} value={st.data.status} onChange={(e) => st.setData('status', e.target.value)} />
              {st.data.status === 'filed' && <>
                <TextField label="تاريخ الإيداع" type="date" required value={st.data.filed_on} onChange={(e) => st.setData('filed_on', e.target.value)} error={st.errors.filed_on} hint="منفصل عن تاريخ الاعتماد الداخلي." />
                <SelectField label="النسخة المودعة" placeholder={filedCandidates.length ? 'بدون' : 'ارفعها أولًا من الأسفل'} options={filedCandidates} value={st.data.filed_document} onChange={(e) => st.setData('filed_document', e.target.value)} />
              </>}
              <Button type="submit" size="sm" loading={st.processing}>تحديث الحالة</Button>
            </form>
            <dl className="mt-3 space-y-1 text-[13px]">
              {memo.approvedAt && <div className="flex justify-between"><dt className="text-muted">اعتُمدت داخليًا</dt><dd><DateText value={memo.approvedAt} time /></dd></div>}
              {memo.filedOn && <div className="flex justify-between"><dt className="text-muted">أودعت</dt><dd><DateText value={memo.filedOn} /></dd></div>}
              {memo.filedDocument?.url && <div className="flex justify-between"><dt className="text-muted">النسخة المودعة</dt><dd><a href={memo.filedDocument.url} className="text-accent hover:underline">تنزيل</a></dd></div>}
            </dl>
          </section>
          <section className="card p-4" aria-labelledby="filed">
            <h2 id="filed" className="mb-2 font-semibold">إرفاق النسخة المودعة</h2>
            <UploadForm compact ownerType="case" ownerId={c.id} categories={[{ value: 'memo_filed', label: 'مذكرة مودعة' }]} defaultCategory="memo_filed" allowShare={false} />
            <p className="mt-2 text-[12.5px] text-muted">بعد الرفع اختر الملف من المرفقات عند تسجيل الإيداع.</p>
          </section>
          <p className="text-[12.5px] text-muted">اختصار الحفظ: <kbd className="num rounded border border-line px-1">Ctrl+S</kbd>. كل حفظ ينشئ إصدارًا.</p>
          <Link href={`/office/cases/${c.id}?tab=tasks`} className="text-[13px] text-accent hover:underline">العودة إلى القضية</Link>
        </aside>
      </div>
      <Modal open={hist.open} onClose={() => { hist.close(); setPreview(null); }} title="الإصدارات" wide>
        <div className="grid gap-4 sm:grid-cols-[180px_1fr]">
          <ul className="space-y-1">{versions.map((v) => (
            <li key={v.id}><button type="button" className={cx('btn btn-ghost btn-sm w-full justify-between', preview?.version === v.version && 'bg-active')} onClick={() => showVersion(v.version)}>
              <span>إصدار <Num>{v.version}</Num></span><span className="text-[11.5px] text-muted"><Num>{dateTime(v.at).slice(5)}</Num></span></button></li>
          ))}</ul>
          <div className="memo-preview min-h-[200px] rounded-lg bg-raised p-4">{preview ? <div dangerouslySetInnerHTML={{ __html: preview.body }} /> : <p className="text-muted">اختر إصدارًا لعرضه.</p>}</div>
        </div>
      </Modal>
    </>
  );
}
