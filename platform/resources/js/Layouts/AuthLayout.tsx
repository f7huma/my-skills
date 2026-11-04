import { usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { Flash } from '@/Components/Flash';
import type { SharedProps } from '@/types';

export default function AuthLayout({ children, title }: { children: ReactNode; title: string }) {
  const { office } = usePage<SharedProps>().props;
  return (
    <div className="flex min-h-dvh items-center justify-center bg-chrome px-4 py-10">
      <main id="main" className="w-full max-w-[420px]">
        <div className="mb-6 flex flex-col items-center text-center">
          <img src="/brand/logo-full.webp" alt={`شعار ${office.name}`} width={200} height={191} className="h-auto w-[176px]" />
        </div>
        <div className="sheet px-6 py-7 sm:px-8">
          <h1 className="mb-5 text-[20px] font-bold">{title}</h1>
          {children}
        </div>
        <p className="mt-5 text-center text-[12px] text-muted">{office.name}</p>
      </main>
      <Flash />
    </div>
  );
}
