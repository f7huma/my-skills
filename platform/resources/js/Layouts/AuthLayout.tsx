import { usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { Flash } from '@/Components/Flash';
import type { SharedProps } from '@/types';

/** الدخول: لوحة فحمية بشعار المكتب وشبكة الماس، والنموذج على ورقة بيضاء */
export default function AuthLayout({ children, title }: { children: ReactNode; title: string }) {
  const { office } = usePage<SharedProps>().props;
  return (
    <>
    <div className="grid min-h-dvh bg-chrome lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <aside className="night relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12" aria-hidden>
        <div className="rise">
          <img src="/brand/logo-mark-light.webp" alt="" width={132} height={166} className="h-[166px] w-auto" />
        </div>
        <div className="rise" style={{ animationDelay: '120ms' }}>
          <p className="max-w-[18ch] text-[34px] font-semibold leading-[1.35] text-white">{office.name}</p>
          <p className="mt-3 text-[14px] text-[#a9b2bd]">ترخيص رقم <bdi className="num">{office.license}</bdi></p>
        </div>
        <span className="pointer-events-none absolute -bottom-40 -start-40 size-[420px] rotate-45 rounded-[48px] border border-white/10" />
        <span className="pointer-events-none absolute -bottom-24 -start-24 size-[260px] rotate-45 rounded-[32px] border border-white/10" />
      </aside>
      <main id="main" className="flex items-center justify-center px-4 py-10">
        <div className="w-full max-w-[420px]">
          <div className="mb-6 flex flex-col items-center text-center lg:hidden">
            <img src="/brand/logo-full.webp" alt={`شعار ${office.name}`} width={200} height={191} className="h-auto w-[160px]" />
          </div>
          <div className="sheet rise px-6 py-8 sm:px-9">
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
