import { Link, router, usePage } from '@inertiajs/react';
import { CalendarClock, ClipboardList, FileBarChart, Gavel, Home, Inbox, LogOut, Menu, Scale, Settings, ShieldCheck, Users, UserCog, Activity, X } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';
import { Brand } from '@/Components/Brand';
import { Flash } from '@/Components/Flash';
import { GlobalSearch } from '@/Components/GlobalSearch';
import { cx, Num } from '@/Components/ui';
import type { SharedProps } from '@/types';

const nav = [
  { href: '/office', label: 'الرئيسية', icon: Home, match: /^\/office\/?$/ },
  { href: '/office/claims', label: 'المطالبات الواردة', icon: Inbox, match: /^\/office\/claims/, badge: 'claims' },
  { href: '/office/cases', label: 'القضايا', icon: Scale, match: /^\/office\/(cases|memos)/ },
  { href: '/office/executions', label: 'التنفيذ', icon: Gavel, match: /^\/office\/executions/ },
  { href: '/office/deadlines', label: 'المواعيد والعدادات', icon: CalendarClock, match: /^\/office\/deadlines/ },
  { href: '/office/tasks', label: 'المهام', icon: ClipboardList, match: /^\/office\/tasks/ },
  { href: '/office/clients', label: 'العملاء والوكالات', icon: Users, match: /^\/office\/clients/ },
  { href: '/office/reports', label: 'التقارير', icon: FileBarChart, match: /^\/office\/reports/ },
];
const admin = [
  { href: '/office/admin/users', label: 'الحسابات والصلاحيات', icon: UserCog, match: /^\/office\/admin\/users/ },
  { href: '/office/admin/settings', label: 'الإعدادات', icon: Settings, match: /^\/office\/admin\/settings/ },
  { href: '/office/admin/system', label: 'حالة النظام', icon: Activity, match: /^\/office\/admin\/(system|audit)/ },
];

function NavList({ items, url, badge }: { items: typeof nav; url: string; badge: number }) {
  return (
    <ul className="space-y-0.5">
      {items.map((it) => {
        const active = it.match.test(url.split('?')[0]);
        const Icon = it.icon;
        return (
          <li key={it.href}>
            <Link href={it.href} aria-current={active ? 'page' : undefined}
              className={cx('relative flex h-10 items-center gap-3 rounded-[10px] px-3 text-[14px] transition-colors duration-150',
                active ? 'bg-white shadow-[0_1px_2px_rgba(48,55,65,.08)] font-medium text-ink' : 'text-[#3f4650] hover:bg-white/70 hover:text-text')}>
              {active && <span aria-hidden className="diamond absolute -start-[3px] text-ink" />}
              <Icon className="size-[18px] shrink-0" strokeWidth={1.5} aria-hidden />
              <span className="flex-1 truncate">{it.label}</span>
              {'badge' in it && it.badge === 'claims' && badge > 0 && (
                <span className="rounded-md bg-signal px-1.5 text-[11.5px] font-semibold leading-5 text-white" aria-label={`${badge} مطالبة بانتظار الدراسة`}><Num>{badge}</Num></span>
              )}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}

export default function OfficeLayout({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>();
  const { auth, nav: counts, env } = page.props;
  const [menu, setMenu] = useState(false);
  useEffect(() => router.on('navigate', () => setMenu(false)), []);
  useEffect(() => {
    document.body.style.overflow = menu ? 'hidden' : '';
  }, [menu]);
  const badge = counts?.claimsInbox ?? 0;

  const sidebar = (
    <div className="mist flex h-full flex-col gap-6 px-3 pb-4 pt-5">
      <Link href="/office" className="px-2" aria-label="الرئيسية — مكتب فيصل حميضان الحميضان"><Brand /></Link>
      <nav aria-label="التنقل الرئيسي" className="flex-1 space-y-6 overflow-y-auto">
        <NavList items={nav} url={page.url} badge={badge} />
        <div>
          <p className="mb-1.5 px-3 text-[12px] text-muted">إدارة المكتب</p>
          <NavList items={admin} url={page.url} badge={0} />
        </div>
      </nav>
      <div className="space-y-1 border-t border-ink/15 pt-3">
        <Link href="/account/security" className="flex items-center gap-2.5 rounded-[10px] px-3 py-1.5 text-[13.5px] text-ink hover:bg-white/70">
          <span className="grid size-8 shrink-0 place-items-center rounded-full bg-white text-[13px] font-semibold">{auth?.user.name?.trim().charAt(0)}</span>
          <span className="min-w-0">
            <span className="block truncate font-medium">{auth?.user.name}</span>
            <span className="flex items-center gap-1 text-[12px] text-muted"><ShieldCheck className="size-3.5" aria-hidden />أمان الحساب</span>
          </span>
        </Link>
        <Link href="/logout" method="post" as="button" className="flex w-full items-center gap-2.5 rounded-[10px] px-3 py-1.5 text-[13.5px] text-[#3f4650] hover:bg-white/70 hover:text-text">
          <LogOut className="size-4" aria-hidden />تسجيل الخروج
        </Link>
      </div>
    </div>
  );

  return (
    <div className="min-h-dvh bg-chrome">
      <a href="#main" className="skip-link">تخطَّ إلى المحتوى</a>
      {env && env !== 'local' && env !== 'testing' && (
        <div className="bg-amber px-4 py-1 text-center text-[12.5px] text-white">بيئة {env === 'preview' ? 'معاينة — بيانات اصطناعية فقط' : env}</div>
      )}
      {/* الإطار: شريط جانبي وشريط علوي على أرضية واحدة بلا فاصل */}
      <aside className="fixed inset-y-0 start-0 hidden w-[256px] lg:block no-print" aria-label="القائمة">{sidebar}</aside>

      {menu && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="القائمة">
          <div className="drawer-scrim absolute inset-0 bg-[rgba(20,24,30,0.38)]" onClick={() => setMenu(false)} />
          <div className="drawer-panel mist absolute inset-y-0 start-0 w-[min(300px,86vw)] shadow-xl">
            <button type="button" onClick={() => setMenu(false)} className="btn btn-ghost btn-sm absolute end-2 top-4 z-10" aria-label="إغلاق القائمة"><X className="size-4" /></button>
            {sidebar}
          </div>
        </div>
      )}

      <div className="lg:ps-[256px]">
        <div className="sticky top-0 z-30 flex h-14 items-center gap-3 bg-chrome/90 px-3 backdrop-blur-md sm:px-5 no-print lg:h-16">
          <button type="button" className="btn btn-ghost btn-sm lg:hidden" onClick={() => setMenu(true)} aria-label="فتح القائمة" aria-expanded={menu}><Menu className="size-5" /></button>
          <div className="lg:hidden"><Brand compact /></div>
          <div className="flex flex-1 justify-center"><GlobalSearch /></div>
        </div>
        <main id="main" tabIndex={-1} className="min-h-[calc(100dvh-64px)] px-0 pb-6 outline-none sm:px-4 lg:pe-4 lg:ps-2">
          {/* اللوحة الرئيسية ترسم أسطحها بنفسها؛ بقية الصفحات على ورقة بيضاء واحدة */}
          {page.component === 'Office/Dashboard' ? <div className="px-4 sm:px-0">{children}</div>
            : <div className="sheet min-h-[calc(100dvh-88px)] rounded-none border-x-0 px-4 py-6 sm:rounded-[18px] sm:border-x sm:px-7">{children}</div>}
        </main>
      </div>
      <Flash />
    </div>
  );
}
