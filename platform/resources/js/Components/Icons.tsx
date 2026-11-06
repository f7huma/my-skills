import type { SVGProps } from 'react';

/**
 * أيقونات المكتب القضائية: شبكة 24، خط 1.6 بأطراف مستديرة، والمعيّن والميزان من الشعار عنصران متكرران.
 * كل أيقونة مرتبطة بوظيفة واحدة في المنصة.
 */
type P = SVGProps<SVGSVGElement> & { size?: number };
const base = ({ size = 20, className, ...rest }: P) => ({
  width: size, height: size, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.6,
  strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, 'aria-hidden': true, className, ...rest,
});

/** الرئيسية: معيّن المكتب فوق قاعدة */
export const IconHome = (p: P) => <svg {...base(p)}><path d="M12 3.5 19 10.5 12 17.5 5 10.5Z" /><path d="M12 8.5 14 10.5 12 12.5 10 10.5Z" fill="currentColor" stroke="none" /><path d="M6.5 20.5h11" /></svg>;
/** القضايا: ميزان العدالة */
export const IconCase = (p: P) => <svg {...base(p)}><path d="M12 3v17" /><path d="M8 20.5h8" /><path d="M5 6.5h14" /><path d="m11 3.5 1-1 1 1" /><path d="M5 6.5 2.75 12a2.5 2.5 0 0 0 4.5 0Z" /><path d="M19 6.5 16.75 12a2.5 2.5 0 0 0 4.5 0Z" /></svg>;
/** العملاء: شخصان ومعيّن الصفة */
export const IconClients = (p: P) => <svg {...base(p)}><circle cx="9" cy="8" r="3.2" /><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0" /><path d="M17 6.5 19 8.5 17 10.5 15 8.5Z" /><path d="M16.5 14a4.5 4.5 0 0 1 4 4.5" /></svg>;
/** الوكالات: وثيقة بختم معيّن */
export const IconPoa = (p: P) => <svg {...base(p)}><path d="M6 3.5h8l4 4v13H6Z" /><path d="M14 3.5v4h4" /><path d="M9 11h6" /><path d="M12 14 14.5 16.5 12 19 9.5 16.5Z" /></svg>;
/** المطالبات الواردة: درج وارد */
export const IconClaims = (p: P) => <svg {...base(p)}><path d="M4 13.5 6.5 5h11l2.5 8.5" /><path d="M4 13.5h4.5l1 2h5l1-2H20v6H4Z" /><path d="m12 7.5 0 4" /><path d="m10 9.5 2 2 2-2" /></svg>;
/** التنفيذ: مطرقة القاضي */
export const IconExecution = (p: P) => <svg {...base(p)}><path d="m13 4 7 7" /><path d="m10.5 6.5 7 7" /><path d="m12 5.5-3 3 6.5 6.5 3-3" /><path d="M11 11 4 18" /><path d="M3.5 21h9" /></svg>;
/** المواعيد: تقويم بمعيّن اليوم */
export const IconDeadlines = (p: P) => <svg {...base(p)}><rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M3.5 9.5h17" /><path d="M8 3v4M16 3v4" /><path d="M12 12.5 14 14.5 12 16.5 10 14.5Z" fill="currentColor" stroke="none" /></svg>;
/** المهام: قائمة بإشارة */
export const IconTasks = (p: P) => <svg {...base(p)}><path d="m4 7 1.5 1.5L8 6" /><path d="M11 7h9" /><path d="m4 13 1.5 1.5L8 12" /><path d="M11 13h9" /><path d="M5 18.5h.01" /><path d="M11 19h9" /></svg>;
/** الجلسات: منصة القضاء */
export const IconHearing = (p: P) => <svg {...base(p)}><path d="M3.5 20.5h17" /><path d="M5 20.5v-8h14v8" /><path d="M3.5 12.5h17" /><path d="M12 3.5 15 6.5 12 9.5 9 6.5Z" /><path d="M9 16h6" /></svg>;
/** المرفقات: مشبك على ورقة */
export const IconAttachment = (p: P) => <svg {...base(p)}><path d="M15.5 7.5 9 14a2 2 0 0 0 2.8 2.8l7-7a3.6 3.6 0 0 0-5.1-5.1l-7.2 7.2a5.2 5.2 0 0 0 7.4 7.4l5.6-5.6" /></svg>;
/** التقارير: وثيقة بأعمدة */
export const IconReports = (p: P) => <svg {...base(p)}><path d="M6 3.5h8l4 4v13H6Z" /><path d="M14 3.5v4h4" /><path d="M9.5 17v-3M12 17v-5.5M14.5 17v-2" /></svg>;
/** الحسابات والصلاحيات */
export const IconUsers = (p: P) => <svg {...base(p)}><path d="M12 3.5 19 6v5.5c0 4.2-3 7.6-7 9-4-1.4-7-4.8-7-9V6Z" /><circle cx="12" cy="10" r="2.3" /><path d="M8.5 16a4 4 0 0 1 7 0" /></svg>;
/** الإعدادات */
export const IconSettings = (p: P) => <svg {...base(p)}><path d="M4 7h9M17 7h3M4 17h3M11 17h9" /><path d="M15 5 17 7 15 9 13 7Z" /><path d="M9 15 11 17 9 19 7 17Z" /></svg>;
/** حالة النظام */
export const IconSystem = (p: P) => <svg {...base(p)}><path d="M3 12h4l2-5 4 10 2-5h6" /></svg>;
/** المزيد: أربعة معيّنات */
export const IconMore = (p: P) => <svg {...base(p)}><path d="M7.5 4 10 6.5 7.5 9 5 6.5Z" /><path d="M16.5 4 19 6.5 16.5 9 14 6.5Z" /><path d="M7.5 15 10 17.5 7.5 20 5 17.5Z" /><path d="M16.5 15 19 17.5 16.5 20 14 17.5Z" /></svg>;
/** المذكرات */
export const IconMemo = (p: P) => <svg {...base(p)}><path d="M6 3.5h12v17H6Z" /><path d="M9 8h6M9 11.5h6M9 15h3.5" /></svg>;
/** الحكم */
export const IconJudgment = (p: P) => <svg {...base(p)}><path d="M5 4.5h14v5H5Z" /><path d="M8 9.5v11M16 9.5v11" /><path d="M4 20.5h16" /><path d="M12 12.5 13.5 14 12 15.5 10.5 14Z" /></svg>;
/** الصلح */
export const IconSettlement = (p: P) => <svg {...base(p)}><path d="m3 11 4-4 4 3 2-1.5" /><path d="m21 11-4-4-5 4 3 3a1.4 1.4 0 0 0 2-2" /><path d="m7 13 4 4a1.4 1.4 0 0 0 2-2" /><path d="m9 15 2 2" /></svg>;
/** فتح الملف / المسودة */
export const IconIntake = (p: P) => <svg {...base(p)}><path d="M3.5 7.5h6l1.5 2h9.5v9.5h-17Z" /><path d="M12 12.5v4M10 14.5h4" /></svg>;
/** القيد في ناجز */
export const IconFiling = (p: P) => <svg {...base(p)}><path d="M5 3.5h10l4 4v13H5Z" /><path d="M15 3.5v4h4" /><path d="m9 14 2 2 4-4.5" /></svg>;
/** الاعتراض */
export const IconObjection = (p: P) => <svg {...base(p)}><path d="M12 3.5 20.5 12 12 20.5 3.5 12Z" /><path d="M12 8v4.5" /><path d="M12 15.5h.01" /></svg>;
/** منتهية */
export const IconClosed = (p: P) => <svg {...base(p)}><path d="M12 3.5 20.5 12 12 20.5 3.5 12Z" /><path d="m8.5 12 2.5 2.5 4.5-5" /></svg>;
/** الخروج */
export const IconLogout = (p: P) => <svg {...base(p)}><path d="M10 4.5H5.5v15H10" /><path d="m14 8-4 4 4 4" /><path d="M10 12h10" /></svg>;
/** أمان الحساب */
export const IconShield = (p: P) => <svg {...base(p)}><path d="M12 3.5 19 6v5.5c0 4.2-3 7.6-7 9-4-1.4-7-4.8-7-9V6Z" /><path d="m9 12 2 2 4-4" /></svg>;
