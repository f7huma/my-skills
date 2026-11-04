#!/bin/sh
set -eu
role="${1:-web}"

# التحقق من الإعدادات الحرجة قبل التشغيل — يفشل الحاوي بدل العمل بإعداد ناقص
for v in APP_KEY APP_URL DB_PASSWORD FILES_ENCRYPTION_KEY BACKUP_ENCRYPTION_KEY; do
  eval "val=\${$v:-}"
  if [ -z "$val" ]; then echo "missing required env: $v" >&2; exit 64; fi
done
if [ "${SCANNER_DRIVER:-clamav}" = "fake" ]; then echo "SCANNER_DRIVER=fake is not allowed in containers" >&2; exit 64; fi

# ذاكرات مؤقتة تُبنى من متغيرات البيئة الحالية: تغيير APP_URL أو SESSION_DOMAIN يتطلب إعادة تشغيل فقط
php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null
php artisan event:cache >/dev/null

case "$role" in
  web)
    exec frankenphp run --config /etc/frankenphp/Caddyfile ;;
  worker)
    exec php artisan queue:work --queue=scans,default --sleep=2 --tries=5 --backoff=30 --max-time=3600 --memory=256 ;;
  scheduler)
    exec php artisan schedule:work ;;
  migrate)
    exec php artisan migrate --force ;;
  *)
    exec "$@" ;;
esac
