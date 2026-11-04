import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

/** الشعار الأصلي (العلامة) مع اسم المكتب نصيًا ورقم الترخيص */
export function Brand({ compact = false, light = false }: { compact?: boolean; light?: boolean }) {
  const { office } = usePage<SharedProps>().props;
  return (
    <div className="flex items-center gap-2.5">
      <img src={light ? '/brand/logo-mark-light.webp' : '/brand/logo-mark.webp'} alt="" width={30} height={38} className="h-[38px] w-auto shrink-0" />
      {!compact && (
        <div className="min-w-0 leading-tight">
          <p className="truncate text-[14px] font-bold text-ink">{office.shortName}</p>
          <p className="truncate text-[11.5px] text-muted">للمحاماة · ترخيص <bdi className="num">{office.license}</bdi></p>
        </div>
      )}
    </div>
  );
}
