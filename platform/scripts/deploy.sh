#!/usr/bin/env bash
# نشر إصدار جديد على الخادم مع نقطة تراجع.
# الاستخدام: ./scripts/deploy.sh <git-ref>   (من مجلد المشروع على الخادم)
set -euo pipefail
REF="${1:?حدد الوسم أو الفرع المراد نشره}"
ENV_FILE="${ENV_FILE:-.env.production}"
COMPOSE="docker compose --env-file $ENV_FILE"

echo "==> التأكد من نجاح بوابات CI للإصدار $REF قبل النشر (راجع GitHub Actions: ready-to-deploy)."
current_image="$(docker image inspect humaidan-app:latest --format '{{.Id}}' 2>/dev/null || true)"
if [ -n "$current_image" ]; then
  docker tag humaidan-app:latest humaidan-app:previous
  echo "==> حُفظت الصورة الحالية باسم humaidan-app:previous للتراجع"
fi

echo "==> نسخة احتياطية قبل النشر"
$COMPOSE exec -T app php artisan backup:run --verify || { echo "فشل النسخ الاحتياطي — أُوقف النشر"; exit 1; }

git fetch --tags origin
git checkout --detach "$REF"
$COMPOSE build app
$COMPOSE run --rm app migrate
$COMPOSE up -d app worker scheduler caddy
$COMPOSE exec -T app php artisan queue:restart || true
sleep 5
$COMPOSE exec -T app php artisan ops:check
echo "==> تم النشر. تحقق من /up ومن صفحة حالة النظام."
