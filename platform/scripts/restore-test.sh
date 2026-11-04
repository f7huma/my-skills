#!/usr/bin/env bash
# اختبار استعادة على بيئة معزولة (لا يمس قاعدة الإنتاج):
# 1) نسخ آخر حزمة نسخ إلى وحدة تخزين بيئة الاختبار، 2) الاستعادة والتحقق الآلي، 3) تجربة الدخول يدويًا عبر نفق SSH.
set -euo pipefail
NAME="${1:?اسم النسخة (php artisan backup:list)}"
ENV_FILE="${ENV_FILE:-.env.production}"
docker compose --env-file "$ENV_FILE" cp "app:/var/lib/humaidan/backups/$NAME.tar.enc" "/tmp/$NAME.tar.enc"
docker compose --env-file "$ENV_FILE" cp "app:/var/lib/humaidan/backups/$NAME.tar.enc.sha256" "/tmp/$NAME.tar.enc.sha256"
RT="docker compose -f docker-compose.restore-test.yml"
$RT up -d db
sleep 5
$RT run --rm --no-deps -v "/tmp/$NAME.tar.enc:/var/lib/humaidan/backups/$NAME.tar.enc:ro" -v "/tmp/$NAME.tar.enc.sha256:/var/lib/humaidan/backups/$NAME.tar.enc.sha256:ro" \
  app php artisan backup:restore-test "$NAME" --database=humaidan_restored --files=/var/lib/humaidan/files
echo "==> للتجربة اليدوية لتسجيل الدخول وفتح المرفقات: اضبط DB_DATABASE=humaidan_restored في .env.restore-test ثم:"
echo "    $RT up -d app && ssh -L 18080:127.0.0.1:18080 <server>  ثم افتح http://127.0.0.1:18080"
echo "==> بعد الانتهاء: $RT down -v"
