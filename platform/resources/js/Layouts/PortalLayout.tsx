import { Link, usePage } from '@inertiajs/react';
import { LogOut, ShieldCheck } from 'lucide-react';
import { ReactNode } from 'react';
import { Brand } from '@/Components/Brand';
import { Flash } from '@/Components/Flash';
import { cx } from '@/Components/ui';
import type { SharedProps } from '@/types';

/** بوابة العميل: وجهات قليلة — شريط علوي فوق عمود متوسط بلا شريط جانبي */
export default function PortalLayout({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>();
  const { auth, portal } = page.props;
  const canSubmit = portal?.clients.some((c) => c.canSubmitClaims);
  const links = [
    { href: '/portal', label: 'ملفاتي', active: /^\/portal(\/(cases|executions).*)?\/?$/.test(page.url.split('?')[0]) },
    ...(canSubmit ? [{ href: '/portal/claims', label: 'المطالبات', active: page.url.startsWith('/portal/claims') }] : []),
  ];
  return (
    <div className="min-h-dvh bg-chrome">
      <a href="#main" className="skip-link">تخطَّ إلى المحتوى</a>
      <header className="mist no-print">
        <div className="mx-auto flex max-w-[1120px] flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3.5 sm:px-6">
          <Link href="/portal" aria-label="الصفحة الرئيسية للبوابة"><Brand /></Link>
          <nav aria-label="التنقل" className="order-3 flex w-full gap-1 sm:order-none sm:w-auto">
            {links.map((l) => (
              <Link key={l.href} href={l.href} aria-current={l.active ? 'page' : undefined}
                className={cx('rounded-[10px] px-3.5 py-1.5 text-[14px] transition-colors', l.active ? 'bg-white shadow-[0_1px_2px_rgba(48,55,65,.08)] font-medium text-ink' : 'text-muted hover:bg-white/70 hover:text-text')}>{l.label}</Link>
            ))}
          </nav>
          <div className="ms-auto flex items-center gap-1">
            <Link href="/account/security" className="btn btn-ghost btn-sm" aria-label="أمان الحساب"><ShieldCheck className="size-4" aria-hidden /><span className="hidden sm:inline">{auth?.user.name}</span></Link>
            <Link href="/logout" method="post" as="button" className="btn btn-ghost btn-sm" aria-label="تسجيل الخروج"><LogOut className="size-4" aria-hidden /></Link>
          </div>
        </div>
      </header>
      <main id="main" tabIndex={-1} className="mx-auto max-w-[1120px] px-0 pb-8 pt-5 outline-none sm:px-6">
        {page.component === 'Portal/Home' ? <div className="px-4 sm:px-0">{children}</div>
          : <div className="sheet min-h-[60vh] rounded-none border-x-0 px-4 py-6 sm:rounded-[18px] sm:border-x sm:px-8">{children}</div>}
        <p className="px-4 pt-4 text-center text-[12px] text-muted">{page.props.office.name} — ترخيص رقم <bdi className="num">{page.props.office.license}</bdi></p>
      </main>
      <Flash />
    </div>
  );
}
