#!/usr/bin/env bash
# التراجع إلى الصورة السابقة. إن كانت الهجرة الجديدة غير متوافقة فاستعد نسخة ما قبل النشر (انظر الدليل).
set -euo pipefail
ENV_FILE="${ENV_FILE:-.env.production}"
COMPOSE="docker compose --env-file $ENV_FILE"
docker image inspect humaidan-app:previous >/dev/null 2>&1 || { echo "لا توجد صورة سابقة"; exit 1; }
docker tag humaidan-app:previous humaidan-app:latest
$COMPOSE up -d --no-build app worker scheduler
$COMPOSE exec -T app php artisan queue:restart || true
$COMPOSE exec -T app php artisan ops:check
echo "==> عاد التطبيق إلى الصورة السابقة."
