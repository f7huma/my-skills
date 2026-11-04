import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

type Result = { type: string; title: string; sub: string; href: string };

/** بحث داخلي بأرقام القضايا والطلبات والوكالات والأسماء — ضمن صلاحية المحامي على الخادم */
export function GlobalSearch() {
  const [q, setQ] = useState('');
  const [results, setResults] = useState<Result[]>([]);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const [loading, setLoading] = useState(false);
  const input = useRef<HTMLInputElement>(null);
  const listId = useId();

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === '/' && !(e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || (e.target as HTMLElement)?.isContentEditable)) {
        e.preventDefault();
        input.current?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => {
    if (q.trim().length < 2) { setResults([]); return; }
    const ctrl = new AbortController();
    setLoading(true);
    const t = setTimeout(() => {
      fetch('/office/search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal })
        .then((r) => (r.ok ? r.json() : { results: [] }))
        .then((d) => { setResults(d.results ?? []); setActive(0); setOpen(true); })
        .catch(() => {})
        .finally(() => setLoading(false));
    }, 180);
    return () => { clearTimeout(t); ctrl.abort(); };
  }, [q]);

  const go = (r: Result) => { setOpen(false); setQ(''); router.visit(r.href); };

  return (
    <div className="relative w-full max-w-[520px]">
      <label htmlFor="global-search" className="sr-only">بحث في القضايا والعملاء والطلبات</label>
      <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-faint" aria-hidden />
      <input
        ref={input} id="global-search" type="search" value={q} autoComplete="off"
        role="combobox" aria-expanded={open && results.length > 0} aria-controls={listId} aria-autocomplete="list"
        aria-activedescendant={open && results[active] ? `${listId}-${active}` : undefined}
        placeholder="ابحث برقم قضية أو طلب أو وكالة أو اسم…"
        className="input h-9 ps-9 pe-10 bg-surface"
        onChange={(e) => setQ(e.target.value)} onFocus={() => results.length && setOpen(true)} onBlur={() => setTimeout(() => setOpen(false), 150)}
        onKeyDown={(e) => {
          if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(a + 1, results.length - 1)); }
          if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)); }
          if (e.key === 'Enter' && results[active]) { e.preventDefault(); go(results[active]); }
          if (e.key === 'Escape') setOpen(false);
        }}
      />
      <kbd className="pointer-events-none absolute end-3 top-1/2 hidden -translate-y-1/2 rounded border border-line px-1.5 text-[11px] text-muted sm:block">/</kbd>
      {open && q.trim().length >= 2 && (
        <ul id={listId} role="listbox" className="absolute inset-x-0 top-[42px] z-40 max-h-[60vh] overflow-y-auto rounded-xl border border-line bg-surface p-1.5 shadow-[0_16px_40px_rgba(20,24,30,0.14)]">
          {loading && !results.length && <li className="px-3 py-2 text-[13px] text-muted">جارٍ البحث…</li>}
          {!loading && !results.length && <li className="px-3 py-2 text-[13px] text-muted">لا نتائج مطابقة.</li>}
          {results.map((r, i) => (
            <li key={r.href + i} id={`${listId}-${i}`} role="option" aria-selected={i === active}
              onMouseDown={(e) => { e.preventDefault(); go(r); }} onMouseEnter={() => setActive(i)}
              className={'flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 ' + (i === active ? 'bg-active' : '')}>
              <span className="w-12 shrink-0 text-[12px] text-muted">{r.type}</span>
              <span className="min-w-0"><span className="block truncate font-medium">{r.title}</span><span className="block truncate text-[12.5px] text-muted">{r.sub}</span></span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
