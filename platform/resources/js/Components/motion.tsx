import { Link } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { cx } from '@/Components/ui';

const fmt = (n: number, decimals = 0) => new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(n);

const reduced = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/**
 * رقم يعدّ من الصفر عند ظهوره أول مرة (900ms)، وعند تغيّر قيمته فعليًا يتحرك من القيمة السابقة إلى الجديدة (500ms).
 * يكتب النص مباشرة في العنصر كل إطار فلا يُعاد رسم المكوّن، ويظهر بقيمته النهائية عند تقليل الحركة.
 */
export function CountUp({ value, decimals = 0, className }: { value: number; decimals?: number; duration?: number; className?: string }) {
  const host = useRef<HTMLSpanElement>(null);
  const out = useRef<HTMLSpanElement>(null);
  const shown = useRef<number | null>(null);
  const raf = useRef(0);
  const animate = (from: number, to: number, ms: number) => {
    const node = out.current;
    if (!node) return;
    cancelAnimationFrame(raf.current);
    let start = 0;
    const tick = (t: number) => {
      if (!start) start = t;
      const p = Math.min(1, (t - start) / ms);
      const v = p < 1 ? from + (to - from) * (1 - Math.pow(2, -10 * p)) : to;
      shown.current = v;
      node.textContent = fmt(v, decimals);
      if (p < 1) raf.current = requestAnimationFrame(tick);
    };
    raf.current = requestAnimationFrame(tick);
  };
  useLayoutEffect(() => {
    const node = out.current;
    if (!node) return;
    if (reduced()) { shown.current = value; node.textContent = fmt(value, decimals); return; }
    if (shown.current === null) {
      // أول ظهور: يبدأ من الصفر ويعدّ حين يدخل مجال الرؤية
      node.textContent = fmt(0, decimals);
      shown.current = 0;
      const el = host.current;
      if (!el || !('IntersectionObserver' in window)) { animate(0, value, 900); return; }
      const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { animate(0, value, 900); io.disconnect(); } }, { threshold: 0.3 });
      io.observe(el);
      return () => io.disconnect();
    }
    if (shown.current !== value) animate(shown.current, value, 500);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, decimals]);
  useEffect(() => () => cancelAnimationFrame(raf.current), []);
  return (
    <span ref={host} className={cx('num', className)}>
      <span ref={out} aria-hidden>{fmt(value, decimals)}</span>
      <span className="sr-only">{fmt(value, decimals)}</span>
    </span>
  );
}

/** أعمدة تفاعلية: تنمو عند التحميل، وتعرض قيمتها عند المرور أو التركيز */
export function BarChart({ data, label, unit, height = 150, format = (v: number) => fmt(v) }: {
  data: { key: string; label: string; value: number }[]; label: string; unit?: string; height?: number; format?: (v: number) => string;
}) {
  const max = Math.max(1, ...data.map((d) => d.value));
  const [active, setActive] = useState(data.length - 1);
  const cur = data[active];
  return (
    <figure aria-label={label}>
      <figcaption className="flex items-baseline justify-between gap-3">
        <span className="text-[12.5px] text-muted">{cur?.label}</span>
        <span className="text-[20px] font-semibold text-text"><bdi className="num">{format(cur?.value ?? 0)}</bdi>{unit && <span className="ms-1 text-[12px] font-normal text-muted">{unit}</span>}</span>
      </figcaption>
      <div className="mt-3 flex items-end gap-2.5 border-b border-line" style={{ height }} role="list">
        {data.map((d, i) => {
          const h = Math.max(3, (d.value / max) * (height - 8));
          const on = i === active;
          return (
            <button key={d.key} type="button" role="listitem" onMouseEnter={() => setActive(i)} onFocus={() => setActive(i)} onClick={() => setActive(i)}
              aria-label={`${d.label}: ${format(d.value)}${unit ? ' ' + unit : ''}`} aria-pressed={on}
              className="group relative flex h-full flex-1 items-end focus-visible:outline-offset-4">
              <span className={cx('block w-full rounded-t-[6px] transition-[background-color,opacity] duration-200', on ? 'bg-ink' : 'bg-[#c9cfd7] group-hover:bg-[#9aa4b0]')}
                style={{ height: h }} />
            </button>
          );
        })}
      </div>
      <div className="mt-1.5 flex gap-2.5" aria-hidden>
        {data.map((d, i) => <span key={d.key} className={cx('flex-1 truncate text-center text-[11.5px]', i === active ? 'font-semibold text-text' : 'text-muted')}>{d.label.split(' ')[0]}</span>)}
      </div>
    </figure>
  );
}

// مراحل القضية بتدرّج من الفحمي إلى الفضي — ألوان الحالة (أحمر/كهرماني/أخضر) محجوزة للمعنى
const stageShades = ['#c3c9d1', '#a9b2bc', '#8f99a5', '#77818d', '#5f6976', '#4a5361', '#303741', '#232a33'];

/** مسار القضايا: شريط واحد مقسّم على المراحل، كل مقطع رابط لقائمته وتضيء مرحلته عند المرور */
export function Pipeline({ stages }: { stages: { key: string; label: string; count: number }[] }) {
  const total = stages.reduce((a, s) => a + s.count, 0);
  const [hover, setHover] = useState<string | null>(null);
  if (!total) return <p className="text-[13.5px] text-muted">لا قضايا بعد.</p>;
  const shown = stages.map((s, i) => ({ ...s, color: stageShades[i % stageShades.length] })).filter((s) => s.count > 0);
  return (
    <div>
      <div className="flex h-4 overflow-hidden rounded-full bg-rule" aria-hidden>
        {shown.map((s) => (
          <span key={s.key} className="h-full transition-opacity duration-200" style={{ width: `${(s.count / total) * 100}%`, background: s.color, opacity: hover && hover !== s.key ? 0.25 : 1 }} />
        ))}
      </div>
      <ul className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1">
        {shown.map((s) => (
          <li key={s.key}>
            <Link href={`/office/cases?stage=${s.key}`} onMouseEnter={() => setHover(s.key)} onMouseLeave={() => setHover(null)} onFocus={() => setHover(s.key)} onBlur={() => setHover(null)}
              className="flex items-center gap-2 rounded-md px-1 py-1 text-[13px] hover:bg-raised">
              <span className="diamond" style={{ color: s.color }} aria-hidden />
              <span className="flex-1 truncate">{s.label}</span>
              <bdi className="num font-semibold">{s.count}</bdi>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** مقياس تقدم أفقي ينمو عند التحميل */
export function Meter({ value, max, tone = 'ink', label }: { value: number; max: number; tone?: 'ink' | 'positive' | 'signal' | 'amber'; label: string }) {
  const pct = max > 0 ? Math.min(100, (value / max) * 100) : 0;
  const color = { ink: 'var(--ink)', positive: 'var(--positive)', signal: 'var(--signal)', amber: 'var(--amber)' }[tone];
  return (
    <div className="h-2 overflow-hidden rounded-full bg-rule" role="meter" aria-label={label} aria-valuemin={0} aria-valuemax={max} aria-valuenow={value}>
      <div className="h-full rounded-full" style={{ width: `${pct}%`, background: color }} />
    </div>
  );
}
