import { ReactNode, useId, useState } from 'react';
import { IconCase, IconClosed, IconExecution, IconFiling, IconHearing, IconIntake, IconJudgment, IconObjection, IconSettlement } from '@/Components/Icons';
import { cx } from '@/Components/ui';

/** مراحل القضية بترتيبها في المنصة (المفاتيح نفسها المخزنة في الخادم) */
export const STAGE_ORDER = ['intake', 'reconciliation', 'filing', 'court', 'judgment', 'objection', 'execution', 'closed'] as const;
export const STAGE_LABELS: Record<string, string> = {
  intake: 'قيد الإعداد', reconciliation: 'الصلح (تراضي)', filing: 'طلب تقييد (ناجز)', court: 'منظورة أمام المحكمة',
  judgment: 'صدر الحكم', objection: 'الاعتراض', execution: 'التنفيذ', closed: 'منتهية',
};
const SHORT: Record<string, string> = { intake: 'الإعداد', reconciliation: 'الصلح', filing: 'القيد', court: 'المحكمة', judgment: 'الحكم', objection: 'الاعتراض', execution: 'التنفيذ', closed: 'منتهية' };
const ICON: Record<string, (p: { size?: number; className?: string }) => ReactNode> = {
  intake: IconIntake, reconciliation: IconSettlement, filing: IconFiling, court: IconHearing, judgment: IconJudgment, objection: IconObjection, execution: IconExecution, closed: IconClosed,
};
/** مفتاح المرحلة من اسمها المعروض (البوابة تستقبل الاسم فقط) */
export const stageKeyFromLabel = (label: string | null) => Object.keys(STAGE_LABELS).find((k) => STAGE_LABELS[k] === label) ?? null;

/**
 * مسار بصري لمراحل القضية: المراحل السابقة مكتملة، والحالية بارزة، واللاحقة مخطّطة.
 * الضغط على مرحلة يعرض تفاصيلها للاطلاع فقط؛ تغيير الحالة يبقى في أزرار الصفحة وتبويباتها.
 */
export function StagePath({ current, details, label = 'مسار القضية' }: { current: string; details: (key: string) => ReactNode; label?: string }) {
  const idx = Math.max(0, STAGE_ORDER.indexOf(current as typeof STAGE_ORDER[number]));
  const [open, setOpen] = useState<string>(current);
  const panelId = useId();
  const progress = STAGE_ORDER.length > 1 ? idx / (STAGE_ORDER.length - 1) : 0;
  return (
    <section aria-label={label} className="mb-5">
      <div className="-mx-1 overflow-x-auto px-1 pb-1 [scrollbar-width:none]">
        <div className="relative min-w-[640px]">
          {/* المسار: خط أساس ثم خط تقدم يمتد حتى المرحلة الحالية */}
          <span aria-hidden className="absolute inset-x-[6.25%] top-[19px] h-[2px] rounded bg-line" />
          <span aria-hidden className="path-fill absolute start-[6.25%] top-[19px] h-[2px] rounded bg-ink" style={{ width: `${progress * 87.5}%` }} />
          <ol className="relative grid grid-cols-8">
            {STAGE_ORDER.map((k, i) => {
              const state = i < idx ? 'done' : i === idx ? 'current' : 'next';
              const Icon = ICON[k];
              const sel = open === k;
              return (
                <li key={k} className="flex justify-center">
                  <button type="button" onClick={() => setOpen(k)} aria-pressed={sel} aria-controls={panelId}
                    aria-label={`${STAGE_LABELS[k]} — ${state === 'done' ? 'مرحلة سابقة' : state === 'current' ? 'المرحلة الحالية' : 'مرحلة لاحقة'}`}
                    className="group flex w-full flex-col items-center gap-1.5 rounded-xl px-1 pb-1.5 transition-colors duration-150 hover:bg-raised">
                    <span className={cx('grid size-10 place-items-center rounded-[10px] border transition-[background-color,border-color,color] duration-200 rotate-45',
                      state === 'done' && 'border-ink bg-ink text-white', state === 'current' && 'stage-current border-ink bg-white text-ink ring-2 ring-ink ring-offset-2 ring-offset-white',
                      state === 'next' && 'border-dashed border-control bg-white text-faint', sel && state !== 'current' && 'border-solid border-ink')}>
                      <span className="-rotate-45"><Icon size={18} /></span>
                    </span>
                    <span className={cx('mt-1 text-[12.5px] leading-tight', state === 'current' ? 'font-semibold text-ink' : state === 'done' ? 'text-text' : 'text-muted', sel && 'underline decoration-2 underline-offset-[6px]')}>{SHORT[k]}</span>
                  </button>
                </li>
              );
            })}
          </ol>
        </div>
      </div>
      <div id={panelId} role="region" aria-live="polite" aria-label={`تفاصيل مرحلة ${STAGE_LABELS[open]}`} key={open}
        className="detail-swap mt-2 rounded-[14px] border border-line bg-raised px-4 py-3">
        <p className="flex items-center gap-2 text-[13px] font-semibold text-ink"><IconCase size={16} />{STAGE_LABELS[open]}
          <span className="font-normal text-muted">{open === current ? '· المرحلة الحالية' : STAGE_ORDER.indexOf(open as typeof STAGE_ORDER[number]) < idx ? '· سابقة' : '· لم تبدأ بعد'}</span></p>
        <div className="mt-1.5 text-[13.5px] text-text">{details(open)}</div>
      </div>
    </section>
  );
}

/** مؤشر مراحل مختصر للقوائم: ثمانية معيّنات صغيرة، الحالية أكبر */
export function StageDots({ current }: { current: string | null }) {
  const idx = current ? STAGE_ORDER.indexOf(current as typeof STAGE_ORDER[number]) : -1;
  if (idx < 0) return null;
  return (
    <span className="inline-flex items-center gap-1.5" aria-label={`المرحلة ${idx + 1} من ${STAGE_ORDER.length}: ${STAGE_LABELS[current!]}`} role="img">
      {STAGE_ORDER.map((k, i) => (
        <span key={k} className={cx('rotate-45 rounded-[1.5px]', i === idx ? 'size-2.5 bg-ink' : i < idx ? 'size-1.5 bg-ink/70' : 'size-1.5 border border-control')} />
      ))}
    </span>
  );
}
