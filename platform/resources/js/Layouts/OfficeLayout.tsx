import { Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import { Brand } from '@/Components/Brand';
import { Flash } from '@/Components/Flash';
import { GlobalSearch } from '@/Components/GlobalSearch';
import {
  IconCase, IconClaims, IconClients, IconDeadlines, IconExecution, IconHome, IconLogout, IconMore, IconReports, IconSettings, IconShield, IconSystem, IconTasks, IconUsers,
} from '@/Components/Icons';
import { cx, Num } from '@/Components/ui';
import type { SharedProps } from '@/types';

type Dest = { href: string; label: string; icon: (p: { size?: number; className?: string }) => ReactNode; match: RegExp; badge?: 'claims' };
type Group = { key: string; label: string; icon: Dest['icon']; items: Dest[] };

/**
 * وجهات المحامي مجمّعة بحسب الاستخدام. كل مسار سابق باقٍ بوجهته؛ التجميع للعرض فقط.
 * المجموعة المفتوحة تُبرز في الشريط العلوي، وروابطها الفرعية تظهر في سطر تحته داخل الصفحة.
 */
const groups: Group[] = [
  { key: 'home', label: 'الرئيسية', icon: IconHome, items: [{ href: '/office', label: 'الرئيسية', icon: IconHome, match: /^\/office\/?$/ }] },
  { key: 'files', label: 'الملفات', icon: IconCase, items: [
    { href: '/office/cases', label: 'القضايا', icon: IconCase, match: /^\/office\/(cases|memos)/ },
    { href: '/office/clients', label: 'العملاء والوكالات', icon: IconClients, match: /^\/office\/clients/ },
    { href: '/office/claims', label: 'المطالبات الواردة', icon: IconClaims, match: /^\/office\/claims/, badge: 'claims' },
  ] },
  { key: 'execution', label: 'التنفيذ', icon: IconExecution, items: [{ href: '/office/executions', label: 'التنفيذ', icon: IconExecution, match: /^\/office\/executions/ }] },
  { key: 'schedule', label: 'المواعيد والمهام', icon: IconDeadlines, items: [
    { href: '/office/deadlines', label: 'المواعيد والعدادات', icon: IconDeadlines, match: /^\/office\/deadlines/ },
    { href: '/office/tasks', label: 'المهام', icon: IconTasks, match: /^\/office\/tasks/ },
  ] },
  { key: 'reports', label: 'التقارير', icon: IconReports, items: [{ href: '/office/reports', label: 'التقارير', icon: IconReports, match: /^\/office\/reports/ }] },
  { key: 'admin', label: 'إدارة المكتب', icon: IconSettings, items: [
    { href: '/office/admin/users', label: 'الحسابات والصلاحيات', icon: IconUsers, match: /^\/office\/admin\/users/ },
    { href: '/office/admin/settings', label: 'الإعدادات', icon: IconSettings, match: /^\/office\/admin\/settings/ },
    { href: '/office/admin/system', label: 'حالة النظام', icon: IconSystem, match: /^\/office\/admin\/(system|audit)/ },
  ] },
];

/** الأكثر استخدامًا في شريط الجوال السفلي؛ البقية في «المزيد» */
const dock: Dest[] = [
  groups[0].items[0], groups[1].items[0],
  { href: '/office/deadlines', label: 'المواعيد', icon: IconDeadlines, match: /^\/office\/(deadlines|tasks)/ },
  groups[2].items[0],
];

function Badge({ n }: { n: number }) {
  if (!n) return null;
  return <span className="rounded-md bg-signal px-1.5 text-[11px] font-semibold leading-[18px] text-white" aria-label={`${n} مطالبة بانتظار الدراسة`}><Num>{n}</Num></span>;
}

/** قائمة «المزيد» من أسفل الشاشة على الجوال: كل الوجهات + الحساب */
function MoreSheet({ open, onClose, url, badge, user }: { open: boolean; onClose: () => void; url: string; badge: number; user?: string }) {
  const [closing, setClosing] = useState(false);
  const panel = useRef<HTMLDivElement>(null);
  const close = () => { setClosing(true); window.setTimeout(() => { setClosing(false); onClose(); }, 200); };
  useEffect(() => {
    if (!open) return;
    panel.current?.querySelector<HTMLElement>('a,button')?.focus();
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') close(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="كل الوجهات">
      <div className={cx('sheet-scrim absolute inset-0 bg-[rgba(35,42,51,0.4)]', closing && 'is-closing')} onClick={close} />
      <div ref={panel} className={cx('sheet-panel absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-[22px] bg-surface px-4 pb-[calc(16px+env(safe-area-inset-bottom,0px))] pt-2', closing && 'is-closing')}>
        <div className="mx-auto mb-3 h-1 w-10 rounded-full bg-control" aria-hidden />
        {groups.map((g) => (
          <section key={g.key} className="mb-3">
            <h2 className="mb-1 px-1 text-[12px] text-muted">{g.label}</h2>
            <ul className="grid grid-cols-2 gap-1.5">
              {g.items.map((it) => {
                const on = it.match.test(url);
                const I = it.icon;
                return (
                  <li key={it.href}>
                    <Link href={it.href} aria-current={on ? 'page' : undefined}
                      className={cx('flex min-h-12 items-center gap-2.5 rounded-[12px] px-3 text-[14px]', on ? 'bg-ink text-white' : 'bg-raised text-text active:bg-active')}>
                      <I size={20} className="shrink-0" /><span className="flex-1">{it.label}</span>{it.badge && <Badge n={badge} />}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </section>
        ))}
        <div className="mt-2 grid grid-cols-2 gap-1.5 border-t border-rule pt-3">
          <Link href="/account/security" className="flex min-h-12 items-center gap-2.5 rounded-[12px] bg-raised px-3 text-[14px]"><IconShield size={20} /><span className="truncate">{user ?? 'أمان الحساب'}</span></Link>
          <Link href="/logout" method="post" as="button" className="flex min-h-12 items-center gap-2.5 rounded-[12px] bg-raised px-3 text-[14px]"><IconLogout size={20} />تسجيل الخروج</Link>
        </div>
      </div>
    </div>
  );
}

export default function OfficeLayout({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>();
  const { auth, nav: counts, env } = page.props;
  const [more, setMore] = useState(false);
  const [account, setAccount] = useState(false);
  useEffect(() => router.on('navigate', () => { setMore(false); setAccount(false); }), []);
  useEffect(() => { document.body.style.overflow = more ? 'hidden' : ''; }, [more]);
  const accountRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!account) return;
    const off = (e: PointerEvent) => { if (!accountRef.current?.contains(e.target as Node)) setAccount(false); };
    const esc = (e: KeyboardEvent) => { if (e.key === 'Escape') setAccount(false); };
    document.addEventListener('pointerdown', off); document.addEventListener('keydown', esc);
    return () => { document.removeEventListener('pointerdown', off); document.removeEventListener('keydown', esc); };
  }, [account]);
  const badge = counts?.claimsInbox ?? 0;
  const url = page.url.split('?')[0];
  const active = groups.find((g) => g.items.some((i) => i.match.test(url))) ?? groups[0];

  return (
    <div className="min-h-dvh bg-chrome pb-[calc(64px+env(safe-area-inset-bottom,0px))] lg:pb-0">
      <a href="#main" className="skip-link">تخطَّ إلى المحتوى</a>
      {env && env !== 'local' && env !== 'testing' && (
        <div className="bg-amber px-4 py-1 text-center text-[12.5px] text-white">بيئة {env === 'preview' ? 'معاينة — بيانات اصطناعية فقط' : env}</div>
      )}

      {/* الشريط العلوي: الشعار، المجموعات، البحث، الحساب */}
      <header className="mist sticky top-0 z-30 border-b border-ink/10 no-print">
        <div className="mx-auto flex h-16 max-w-[1480px] items-center gap-3 px-3 sm:px-5">
          <Link href="/office" className="shrink-0" aria-label="الرئيسية — مكتب فيصل حميضان الحميضان">
            <span className="hidden xl:block"><Brand /></span><span className="xl:hidden"><Brand compact /></span>
          </Link>
          <nav aria-label="التنقل الرئيسي" className="hidden h-full lg:block">
            <ul className="flex h-full items-stretch gap-0.5">
              {groups.map((g) => {
                const on = g === active;
                const I = g.icon;
                return (
                  <li key={g.key} className="flex">
                    <Link href={g.items[0].href} aria-current={on ? 'page' : undefined}
                      className={cx('nav-group relative flex items-center gap-2 px-3 text-[14px] transition-colors duration-150', on ? 'font-medium text-ink' : 'text-[#3f4650] hover:text-ink')}>
                      <I size={19} className="shrink-0" />{g.label}
                      {g.key === 'files' && <Badge n={badge} />}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
          <div className="min-w-0 flex-1"><GlobalSearch /></div>
          <div ref={accountRef} className="relative hidden lg:block">
            <button type="button" onClick={() => setAccount(!account)} aria-expanded={account} aria-haspopup="menu"
              className="btn btn-ghost h-10 gap-2 px-2" aria-label={`حساب ${auth?.user.name ?? ''}`}>
              <span className="grid size-8 place-items-center rounded-full bg-ink text-[13px] font-semibold text-white">{auth?.user.name?.trim().charAt(0)}</span>
            </button>
            {account && (
              <div role="menu" className="menu-pop absolute end-0 top-12 z-40 w-60 rounded-[14px] border border-line bg-surface p-1.5 shadow-[0_12px_32px_-12px_rgba(35,42,51,.35)]">
                <p className="truncate px-3 py-2 text-[13px] text-muted">{auth?.user.name}</p>
                <Link role="menuitem" href="/account/security" className="flex items-center gap-2.5 rounded-[10px] px-3 py-2 text-[14px] hover:bg-raised"><IconShield size={18} />أمان الحساب</Link>
                <Link role="menuitem" href="/logout" method="post" as="button" className="flex w-full items-center gap-2.5 rounded-[10px] px-3 py-2 text-[14px] hover:bg-raised"><IconLogout size={18} />تسجيل الخروج</Link>
              </div>
            )}
          </div>
        </div>
        {/* الروابط الفرعية للقسم المفتوح */}
        {active.items.length > 1 && (
          <nav aria-label={`أقسام ${active.label}`} className="border-t border-ink/10 bg-chrome/70">
            <ul className="mx-auto flex max-w-[1480px] gap-1 overflow-x-auto px-3 py-1.5 sm:px-5 [scrollbar-width:none]">
              {active.items.map((it) => {
                const on = it.match.test(url);
                const I = it.icon;
                return (
                  <li key={it.href} className="shrink-0">
                    <Link href={it.href} aria-current={on ? 'page' : undefined}
                      className={cx('flex h-9 items-center gap-2 rounded-[10px] px-3 text-[13.5px] transition-colors duration-150', on ? 'bg-ink text-white' : 'text-[#3f4650] hover:bg-white')}>
                      <I size={17} />{it.label}{it.badge && <Badge n={badge} />}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
        )}
      </header>

      <main id="main" tabIndex={-1} className="page-enter mx-auto max-w-[1480px] px-0 pb-6 pt-4 outline-none sm:px-4 lg:px-5">
        {page.component === 'Office/Dashboard' ? <div className="px-4 sm:px-0">{children}</div>
          : <div className="sheet min-h-[60dvh] rounded-none border-x-0 px-4 py-6 sm:rounded-[18px] sm:border-x sm:px-7">{children}</div>}
      </main>

      {/* شريط الجوال السفلي */}
      <nav aria-label="التنقل السريع" className="fixed inset-x-0 bottom-0 z-40 border-t border-ink/10 bg-surface/95 pb-[env(safe-area-inset-bottom,0px)] backdrop-blur-md lg:hidden no-print">
        <ul className="grid grid-cols-5">
          {dock.map((d) => {
            const on = d.match.test(url);
            const I = d.icon;
            return (
              <li key={d.href}>
                <Link href={d.href} aria-current={on ? 'page' : undefined}
                  className={cx('dock-item relative flex h-16 flex-col items-center justify-center gap-1 text-[11.5px]', on ? 'font-medium text-ink' : 'text-muted')}>
                  <I size={22} />{d.label}
                </Link>
              </li>
            );
          })}
          <li>
            <button type="button" onClick={() => setMore(true)} aria-expanded={more} aria-haspopup="dialog"
              className="dock-item relative flex h-16 w-full flex-col items-center justify-center gap-1 text-[11.5px] text-muted">
              <span className="relative"><IconMore size={22} />{badge > 0 && <span className="absolute -end-1.5 -top-1 size-2.5 rounded-full bg-signal" aria-hidden />}</span>المزيد
            </button>
          </li>
        </ul>
      </nav>
      <MoreSheet open={more} onClose={() => setMore(false)} url={url} badge={badge} user={auth?.user.name} />
      <Flash />
    </div>
  );
}
