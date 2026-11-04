import { useState } from 'react';

/** تبويب محفوظ في الرابط (?tab=) دون إعادة تحميل */
export function useTab(initial: string) {
  const [tab, setTab] = useState(initial);
  const select = (t: string) => {
    setTab(t);
    const u = new URL(window.location.href);
    u.searchParams.set('tab', t);
    window.history.replaceState(window.history.state, '', u.toString());
  };
  return [tab, select] as const;
}
