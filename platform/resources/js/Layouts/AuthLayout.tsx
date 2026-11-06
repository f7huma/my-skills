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
      {/* لوحة الهوية: الشعار الأصلي بنسبه وألوانه على الرمادي البارد المأخوذ من خلفيته، وخطوط هندسية خفيفة ثابتة */}
      <aside className="mist relative hidden overflow-hidden lg:flex lg:items-center lg:justify-center lg:p-12" aria-hidden>
        <img src="/brand/logo-full.webp" alt="" width={768} height={735} className="relative z-10 h-auto w-[min(380px,70%)]" />
        <span className="pointer-events-none absolute -bottom-40 -start-40 size-[420px] rotate-45 rounded-[10px] border border-ink/10" />
        <span className="pointer-events-none absolute -bottom-24 -start-24 size-[260px] rotate-45 rounded-[8px] border border-ink/10" />
        <span className="pointer-events-none absolute -top-32 -end-32 size-[300px] rotate-45 rounded-[8px] border border-ink/10" />
      </aside>
      <main id="main" className="flex items-center justify-center px-4 py-10">
        <div className="w-full max-w-[420px]">
          <div className="mb-6 flex flex-col items-center text-center lg:hidden">
            <img src="/brand/logo-full.webp" alt={`شعار ${office.name}`} width={200} height={191} className="h-auto w-[160px]" />
          </div>
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
