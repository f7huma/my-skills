import { usePage } from '@inertiajs/react';
import { cx } from '@/Components/ui';
import type { SharedProps } from '@/types';

/** الشعار الأصلي (العلامة) مع اسم المكتب نصيًا ورقم الترخيص. light للأسطح الفحمية */
export function Brand({ compact = false, light = false }: { compact?: boolean; light?: boolean }) {
  const { office } = usePage<SharedProps>().props;
  return (
    <div className="flex items-center gap-3">
      <img src={light ? '/brand/logo-mark.webp' : '/brand/logo-mark.webp'} alt="" width={30} height={38} className="h-[40px] w-auto shrink-0" />
      {!compact && (
        <div className="min-w-0 leading-tight">
          <p className={cx('text-[15px] font-semibold', light ? 'text-ink' : 'truncate text-ink')}>{office.shortName}</p>
          <p className={cx('truncate text-[11.5px]', light ? 'text-muted' : 'text-muted')}>للمحاماة · ترخيص <bdi className="num">{office.license}</bdi></p>
        </div>
      )}
    </div>
  );
}
