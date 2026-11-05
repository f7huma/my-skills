// تنسيق الأرقام والتواريخ: أرقام لاتينية، فواصل آلاف، منزلتان للمال، توقيت الرياض
const TZ = 'Asia/Riyadh';
const L = 'ar-SA-u-ca-gregory-nu-latn';

export function money(v: string | number | null | undefined, currency?: string | null): string {
  if (v === null || v === undefined || v === '') return '—';
  const n = typeof v === 'string' ? Number(v) : v;
  if (Number.isNaN(n)) return '—';
  const s = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
  return currency ? `${s} ${currency}` : s;
}

export function count(n: number): string {
  return new Intl.NumberFormat('en-US').format(n);
}

/** تاريخ محلي (YYYY-MM-DD) يُعرض كما هو دون تحويل منطقة زمنية */
export function dateOnly(d: string | null | undefined): string {
  if (!d) return '—';
  return d.slice(0, 10);
}

export function weekday(d: string): string {
  const [y, m, day] = d.slice(0, 10).split('-').map(Number);
  return new Intl.DateTimeFormat('ar-SA', { weekday: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, day)));
}

export function dayMonth(d: string): string {
  const [y, m, day] = d.slice(0, 10).split('-').map(Number);
  return new Intl.DateTimeFormat(L, { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, day)));
}

export function dateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  const date = new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(d);
  const time = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hour12: false }).format(d);
  return `${date} ${time}`;
}

export function timeOnly(iso: string): string {
  return new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso));
}

export function localDate(iso: string): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso));
}

export function relative(iso: string | null | undefined): string {
  if (!iso) return '—';
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return 'الآن';
  if (diff < 3600) return `قبل ${Math.floor(diff / 60)} د`;
  if (diff < 86400) return `قبل ${Math.floor(diff / 3600)} س`;
  const days = Math.floor(diff / 86400);
  if (days === 1) return 'أمس';
  if (days < 30) return `قبل ${days} يومًا`;
  return localDate(iso);
}

export function remainingLabel(n: number | null | undefined, dayType?: string | null): string {
  if (n === null || n === undefined) return '—';
  const unit = dayType === 'business' ? 'يوم عمل' : 'يوم';
  if (n === 0) return 'اليوم';
  if (n < 0) return `متأخر ${Math.abs(n)} ${unit}`;
  return `${n} ${unit}`;
}

export function fileSize(b: number): string {
  if (b < 1024) return `${b} B`;
  if (b < 1048576) return `${(b / 1024).toFixed(0)} KB`;
  return `${(b / 1048576).toFixed(1)} MB`;
}

export const totalsList = (t: Record<string, string> | undefined) => Object.entries(t ?? {});

/** التاريخ الهجري (تقويم أم القرى) لتاريخ محلي YYYY-MM-DD: 25/05/1448 */
export function hijri(d: string | null | undefined): string {
  if (!d) return '';
  const [y, m, day] = d.slice(0, 10).split('-').map(Number);
  if (!y || !m || !day) return '';
  try {
    return new Intl.DateTimeFormat('en-GB-u-ca-islamic-umalqura-nu-latn', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' })
      .format(new Date(Date.UTC(y, m - 1, day))).replace(/\s*AH$/, '');
  } catch {
    return '';
  }
}

export const HIJRI_MONTHS = ['محرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة', 'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة'];

const hijriFmt = (() => {
  try { return new Intl.DateTimeFormat('en-GB-u-ca-islamic-umalqura-nu-latn', { day: 'numeric', month: 'numeric', year: 'numeric', timeZone: 'UTC' }); } catch { return null; }
})();

/** أجزاء التاريخ الهجري (أم القرى) لتاريخ ميلادي YYYY-MM-DD */
export function hijriParts(d: string | null | undefined): { y: number; m: number; d: number } | null {
  if (!d || !hijriFmt) return null;
  const [y, m, day] = d.slice(0, 10).split('-').map(Number);
  if (!y || !m || !day) return null;
  const parts = hijriFmt.formatToParts(new Date(Date.UTC(y, m - 1, day)));
  const get = (t: string) => Number(parts.find((p) => p.type === t)?.value);
  return { y: get('year'), m: get('month'), d: get('day') };
}

/** تحويل تاريخ هجري (أم القرى) إلى ميلادي YYYY-MM-DD، أو null إن لم يوجد اليوم في ذلك الشهر */
export function fromHijri(hy: number, hm: number, hd: number): string | null {
  if (!hijriFmt || !hy || !hm || !hd || hm < 1 || hm > 12 || hd < 1 || hd > 30) return null;
  // تقدير أولي ثم بحث في نافذة حول التقدير
  const approx = Date.UTC(622, 6, 16) + ((hy - 1) * 354.36707 + (hm - 1) * 29.530589 + (hd - 1)) * 86400000;
  for (let off = -45; off <= 45; off++) {
    const t = new Date(approx + off * 86400000);
    const iso = t.toISOString().slice(0, 10);
    const p = hijriParts(iso);
    if (p && p.y === hy && p.m === hm && p.d === hd) return iso;
  }
  return null;
}
