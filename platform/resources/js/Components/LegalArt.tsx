import { CSSProperties, useEffect, useRef, useState } from 'react';

/**
 * رسوم قانونية خطية بلون الشعار، تفاعلية:
 * - تتحرك طبقاتها مع مؤشر الفأرة بعمق مختلف لكل طبقة (على الأجهزة ذات المؤشر الدقيق فقط).
 * - الضغط على الرسم يعيد حركته (ضربة المطرقة / تأرجح الميزان).
 * زخرفية (aria-hidden)، وتتوقف حركتها كلها عند تقليل الحركة.
 */
const motionOk = () => typeof window !== 'undefined'
  && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/** عمق بالبكسل لكل طبقة [data-depth]: يكتب transform مباشرة دون إعادة رسم React */
function useParallax<T extends HTMLElement>() {
  const ref = useRef<T>(null);
  useEffect(() => {
    const host = ref.current?.closest('[data-parallax-host]') as HTMLElement | null ?? ref.current;
    if (!host || !motionOk()) return;
    const layers = () => Array.from(ref.current?.querySelectorAll<SVGGElement>('[data-depth]') ?? []);
    let raf = 0; let x = 0; let y = 0;
    const apply = () => {
      raf = 0;
      for (const l of layers()) { const d = Number(l.dataset.depth); l.style.transform = `translate(${(x * d).toFixed(2)}px, ${(y * d).toFixed(2)}px)`; }
    };
    const move = (e: PointerEvent) => {
      const r = host.getBoundingClientRect();
      x = ((e.clientX - r.left) / r.width - 0.5) * 2; y = ((e.clientY - r.top) / r.height - 0.5) * 2;
      if (!raf) raf = requestAnimationFrame(apply);
    };
    const leave = () => { x = 0; y = 0; for (const l of layers()) l.style.transition = 'transform 500ms cubic-bezier(0.23, 1, 0.32, 1)'; apply(); setTimeout(() => layers().forEach((l) => { l.style.transition = ''; }), 520); };
    host.addEventListener('pointermove', move); host.addEventListener('pointerleave', leave);
    return () => { host.removeEventListener('pointermove', move); host.removeEventListener('pointerleave', leave); cancelAnimationFrame(raf); };
  }, []);
  return ref;
}

const len = (n: number) => ({ ['--len' as string]: n } as CSSProperties);

/** واجهة محكمة وميزان أمامها — صفحة العميل */
export function CourtScene({ className }: { className?: string }) {
  const ref = useParallax<HTMLDivElement>();
  const [run, setRun] = useState(0);
  return (
    <div ref={ref} className={className} aria-hidden onClick={() => setRun((n) => n + 1)} style={{ cursor: 'pointer' }}>
      <svg key={run} viewBox="0 0 400 300" className="h-full w-full" fill="none" stroke="#303741" strokeLinecap="round" strokeLinejoin="round">
        <g data-depth="3">
          <path className="scene-ring" d="M200 6 334 140 200 274 66 140Z" strokeOpacity=".08" strokeWidth="1.2" />
          <path className="scene-ring r2" d="M200 40 300 140 200 240 100 140Z" strokeOpacity=".1" strokeWidth="1.2" />
        </g>
        <g data-depth="6">
          <g className="art-rise" strokeOpacity=".38" strokeWidth="1.6">
            <path d="M110 112 200 62 290 112Z" />
            <path d="M104 118h192M104 124h192" />
            <path d="M126 130v96M150 130v96M250 130v96M274 130v96" />
            <path d="M122 130h8M146 130h8M246 130h8M270 130h8M122 226h8M146 226h8M246 226h8M270 226h8" />
            <path d="M120 232h160M110 242h180M100 252h200" />
            <path d="M200 80l7 7-7 7-7-7Z" fill="#303741" fillOpacity=".25" />
          </g>
        </g>
        <g data-depth="12" strokeWidth="2" strokeOpacity=".8">
          <path className="scene-draw" style={len(180)} d="M200 120v104M178 226h44" />
          <g className="art-beam">
            <path d="M150 128h100" />
            <path d="M200 116l6 6-6 6-6-6Z" fill="#303741" />
          </g>
          <g className="art-pan-a"><path d="M156 128 144 158M156 128l12 30M138 158a18 7 0 0 0 36 0Z" fill="#e1e5e6" /></g>
          <g className="art-pan-b"><path d="M244 128 232 158M244 128l12 30M226 158a18 7 0 0 0 36 0Z" fill="#e1e5e6" /></g>
        </g>
      </svg>
    </div>
  );
}

/** مطرقة القاضي تضرب قاعدتها، وملفات القضايا خلفها وميزان صغير — صفحة المحامي */
export function GavelScene({ className }: { className?: string }) {
  const ref = useParallax<HTMLDivElement>();
  const [run, setRun] = useState(0);
  return (
    <div ref={ref} className={className} aria-hidden onClick={() => setRun((n) => n + 1)} style={{ cursor: 'pointer' }}>
      <svg key={run} viewBox="0 0 400 300" className="h-full w-full" fill="none" stroke="#303741" strokeLinecap="round" strokeLinejoin="round">
        {/* معيّنات خلفية من زوايا الشعار */}
        <g data-depth="3">
          <path className="scene-ring" d="M220 20 340 140 220 260 100 140Z" strokeOpacity=".08" strokeWidth="1.2" />
          <path className="scene-ring r2" d="M220 60 300 140 220 220 140 140Z" strokeOpacity=".1" strokeWidth="1.2" />
        </g>
        {/* ملفات القضايا */}
        <g data-depth="5" className="art-rise" strokeOpacity=".4" strokeWidth="1.6">
          <path d="M44 150h92a6 6 0 0 1 6 6v84a6 6 0 0 1-6 6H44a6 6 0 0 1-6-6v-84a6 6 0 0 1 6-6Z" fill="#f4f5f4" />
          <path d="M54 140h92a6 6 0 0 1 6 6v84a6 6 0 0 1-6 6H54a6 6 0 0 1-6-6v-84a6 6 0 0 1 6-6Z" fill="#f4f5f4" />
          <path d="M64 130h40l8 8h46a6 6 0 0 1 6 6v82a6 6 0 0 1-6 6H64a6 6 0 0 1-6-6v-90a6 6 0 0 1 6-6Z" fill="#ffffff" />
          <path d="M78 164h60M78 178h60M78 192h40" strokeOpacity=".7" />
          <path d="M140 204l6 6-6 6-6-6Z" fill="#303741" fillOpacity=".5" />
        </g>
        {/* ميزان صغير */}
        <g data-depth="10" strokeWidth="1.6" strokeOpacity=".55" className="art-rise">
          <path d="M330 60v64M316 126h28" />
          <g className="art-beam-sm"><path d="M302 70h56" /><path d="M330 62l5 5-5 5-5-5Z" fill="#303741" /></g>
          <path d="M308 70l-8 18M308 70l8 18M296 88a12 5 0 0 0 24 0Z" fill="#e1e5e6" />
          <path d="M352 70l-8 18M352 70l8 18M340 88a12 5 0 0 0 24 0Z" fill="#e1e5e6" />
        </g>
        {/* قاعدة الطَّرق وتموجات الضربة */}
        <g data-depth="7">
          <g className="gavel-ripples" strokeOpacity=".35" strokeWidth="1.4">
            <path className="ripple" d="M220 214 250 236 220 258 190 236Z" />
            <path className="ripple r2" d="M220 214 250 236 220 258 190 236Z" />
          </g>
          <path d="M172 236h96a6 6 0 0 1 6 6v8a6 6 0 0 1-6 6h-96a6 6 0 0 1-6-6v-8a6 6 0 0 1 6-6Z" fill="#303741" fillOpacity=".9" stroke="none" />
          <path d="M160 260h120" strokeOpacity=".35" strokeWidth="1.6" />
        </g>
        {/* المطرقة: تدور حول نهاية مقبضها */}
        <g data-depth="9">
          <g className="gavel-arm">
            <g transform="translate(214 196) rotate(-34)">
              <path d="M18 0H132" strokeWidth="9" />
              <path d="M18 0H132" strokeWidth="3.5" stroke="#5f6976" />
              <path d="M126 -6h10a4 4 0 0 1 4 4v4a4 4 0 0 1-4 4h-10Z" fill="#303741" stroke="none" />
              <rect x="-18" y="-40" width="36" height="80" rx="8" fill="#303741" stroke="none" />
              <path d="M-18 -26H18M-18 26H18" stroke="#e1e5e6" strokeWidth="2.4" strokeOpacity=".85" />
            </g>
          </g>
        </g>
      </svg>
    </div>
  );
}

/** فاصل زخرفي: خطان ومعيّن في الوسط */
export function DiamondRule({ className }: { className?: string }) {
  return (
    <div className={className} aria-hidden>
      <div className="flex items-center gap-3 text-control">
        <span className="h-px flex-1 bg-current" /><span className="diamond text-ink/40" /><span className="h-px flex-1 bg-current" />
      </div>
    </div>
  );
}
