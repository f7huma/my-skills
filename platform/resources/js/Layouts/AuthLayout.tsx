import { usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { Flash } from '@/Components/Flash';
import type { SharedProps } from '@/types';

/**
 * مشهد الهوية حول الشعار: معيّنات تتشكل وميزان كبير خافت يتأرجح ثم يستقر عند ظهور الصفحة.
 * الشعار نفسه صورة الأصل بنسبه وألوانه، ولا يُحرَّك إلا ظهوره. يتوقف كل ذلك عند تقليل الحركة.
 */
function Scene({ small = false }: { small?: boolean }) {
  return (
    <svg viewBox="0 0 400 400" className="pointer-events-none absolute inset-0 m-auto h-full max-h-[640px] w-full max-w-[640px]" aria-hidden fill="none" stroke="#303741" strokeLinecap="round" strokeLinejoin="round">
      {/* معيّنات متداخلة من زوايا الشعار */}
      <path className="scene-ring" d="M200 22 378 200 200 378 22 200Z" strokeOpacity=".10" strokeWidth="1.2" />
      <path className="scene-ring r2" d="M200 58 342 200 200 342 58 200Z" strokeOpacity=".14" strokeWidth="1.2" />
      <path className="scene-ring r3 scene-draw" style={{ ['--len' as string]: 460 }} d="M200 96 304 200 200 304 96 200Z" strokeOpacity=".18" strokeWidth="1.4" />
      {!small && (
        <g strokeOpacity=".16" strokeWidth="1.6">
          {/* ميزان كبير خلف الشعار: القائم ثابت والذراع والكفتان تتأرجح ثم تستقر */}
          <path className="scene-draw" style={{ ['--len' as string]: 300 }} d="M200 70v270M150 340h100" />
          <g className="scene-beam">
            <path d="M70 92h260" />
            <path d="M200 70l8 8-8 8-8-8Z" fill="#303741" fillOpacity=".16" />
          </g>
          <g className="scene-pan-a"><path d="M80 92 52 152M80 92l28 60M46 152a34 14 0 0 0 68 0Z" /></g>
          <g className="scene-pan-b"><path d="M320 92l-28 60M320 92l28 60M286 152a34 14 0 0 0 68 0Z" /></g>
        </g>
      )}
    </svg>
  );
}

export default function AuthLayout({ children, title }: { children: ReactNode; title: string }) {
  const { office } = usePage<SharedProps>().props;
  return (
    <>
      <div className="grid min-h-dvh bg-chrome lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <aside className="mist relative hidden overflow-hidden lg:flex lg:items-center lg:justify-center lg:p-12" aria-hidden>
          <Scene />
          <img src="/brand/logo-full.webp" alt="" width={768} height={735} className="scene-logo relative z-10 h-auto w-[min(340px,62%)]" />
        </aside>
        <main id="main" className="flex items-center justify-center px-4 py-8">
          <div className="w-full max-w-[420px]">
            <div className="mist relative mb-5 flex h-[210px] items-center justify-center overflow-hidden rounded-[22px] lg:hidden">
              <Scene small />
              <img src="/brand/logo-full.webp" alt={`شعار ${office.name}`} width={200} height={191} className="scene-logo relative h-auto w-[150px]" />
            </div>
            {/* النموذج ظاهر وقابل للاستخدام فورًا — بلا حركة دخول */}
            <div className="sheet px-6 py-8 sm:px-9">
              <h1 className="mb-6 text-[24px] font-semibold">{title}</h1>
              {children}
            </div>
            <p className="mt-5 text-center text-[12px] text-muted">{office.name}</p>
          </div>
        </main>
      </div>
      <Flash />
    </>
  );
}
