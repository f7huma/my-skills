import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { Toaster, toast } from 'sonner';
import type { SharedProps } from '@/types';

export function Flash() {
  const { flash, errors } = usePage<SharedProps>().props;
  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.status) toast(flash.status);
  }, [flash?.success, flash?.error, flash?.status]);
  useEffect(() => {
    const n = Object.keys(errors ?? {}).length;
    if (n) toast.error(errors.lock_version ?? (n === 1 ? Object.values(errors)[0] : 'راجع الحقول المظللة في النموذج.'), { id: 'form-errors' });
  }, [errors]);
  return <Toaster dir="rtl" position="bottom-left" richColors closeButton toastOptions={{ style: { fontFamily: 'inherit' } }} />;
}
