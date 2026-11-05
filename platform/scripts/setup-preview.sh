#!/usr/bin/env bash
# إعداد وتشغيل بيئة التجربة preview.alhumaydhanlaw.com ببيانات وهمية — بلا أي إدخال يدوي.
# يولّد المفاتيح وكلمات المرور ويكتب ملفات الإعداد ويشغّل الخدمات ويحفظ بيانات الدخول في:
#   /root/humaidan-preview-credentials.txt
# التشغيل:  bash /opt/humaidan/platform/scripts/setup-preview.sh
set -euo pipefail
DOMAIN="${DOMAIN:-preview.alhumaydhanlaw.com}"
ACME_EMAIL="${ACME_EMAIL:-info@alhumaydhanlaw.com}"
CRED_FILE=/root/humaidan-preview-credentials.txt
cd "$(dirname "$0")/.."

say() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
key() { echo "base64:$(openssl rand -base64 32)"; }
pass() { openssl rand -base64 24 | tr -d '/+=' | cut -c1-24; }
set_env() { python3 - "$1" "$2" "$3" <<'PY'
import sys, re
path, k, v = sys.argv[1:4]
lines = open(path, encoding='utf-8').read().splitlines()
out, done = [], False
for l in lines:
    if re.match(r'^' + re.escape(k) + r'=', l):
        out.append(f'{k}={v}'); done = True
    else:
        out.append(l)
if not done: out.append(f'{k}={v}')
open(path, 'w', encoding='utf-8').write('\n'.join(out) + '\n')
PY
}

C="docker compose -f docker-compose.yml -f docker-compose.preview.yml"

say "1/7 التحقق من ربط الدومين $DOMAIN"
MYIP="$(curl -fsS https://api.ipify.org 2>/dev/null || true)"
DNSIP="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1{print $1}')"
if [ -n "$MYIP" ] && [ "$DNSIP" != "$MYIP" ]; then
  echo "⚠️  الدومين $DOMAIN يشير حاليًا إلى: ${DNSIP:-لا شيء} بينما عنوان هذا الخادم: $MYIP"
  echo "    أضف سجل DNS أو انتظر انتشاره، ثم أعد تشغيل هذا الأمر."
  exit 1
fi
echo "الدومين يشير إلى هذا الخادم ($MYIP) ✓"

say "2/7 توليد المفاتيح وكتابة ملفات الإعداد"
if [ ! -f .env.preview ]; then
  DB_PASS="$(pass)"; GATE_PASS="$(pass)"
  cp .env.example .env.preview
  set_env .env.preview APP_ENV preview
  set_env .env.preview APP_DEBUG false
  set_env .env.preview APP_URL "https://$DOMAIN"
  set_env .env.preview APP_KEY "$(key)"
  set_env .env.preview APP_NOINDEX true
  set_env .env.preview SYNTHETIC_DATA_ONLY true
  set_env .env.preview DB_HOST db
  set_env .env.preview DB_DATABASE humaidan
  set_env .env.preview DB_USERNAME humaidan
  set_env .env.preview DB_PASSWORD "$DB_PASS"
  set_env .env.preview SESSION_SECURE_COOKIE true
  set_env .env.preview SCANNER_DRIVER clamav
  set_env .env.preview FILES_ENCRYPTION_KEY "$(key)"
  set_env .env.preview BACKUP_ENCRYPTION_KEY "$(key)"
  set_env .env.preview LOG_CHANNEL stderr
  set_env .env.preview ATTACHMENTS_ROOT /var/lib/humaidan/files
  set_env .env.preview BACKUP_PATH /var/lib/humaidan/backups
  HASH="$(docker run --rm caddy:2.10-alpine caddy hash-password -p "$GATE_PASS")"
  cat > .env <<EOF
COMPOSE_PROJECT_NAME=humaidan-preview
APP_ENV_FILE=.env.preview
APP_DOMAIN=$DOMAIN
ACME_EMAIL=$ACME_EMAIL
DB_PASSWORD=$DB_PASS
PREVIEW_BASIC_USER=preview
PREVIEW_BASIC_HASH='$HASH'
EOF
  chmod 600 .env .env.preview
  umask 077
  cat > "$CRED_FILE" <<EOF
بيئة التجربة — مكتب فيصل حميضان الحميضان
العنوان: https://$DOMAIN

1) نافذة الحماية قبل الموقع:
   اسم المستخدم: preview
   كلمة المرور: $GATE_PASS

2) حسابات تجريبية داخل المنصة (كلمة المرور لكلها: Demo-Pass-2026):
   المحامي:                     lawyer@example.test
   عميل شركة (متابعة ومطالبات): cfo@example.test
   عميل شركة (متابعة فقط):      viewer@example.test
   عميل فرد:                    salem@example.test
EOF
  echo "حُفظت بيانات الدخول في $CRED_FILE"
else
  echo "ملفات الإعداد موجودة مسبقًا — لن تُعاد كتابتها."
fi

say "3/7 تشغيل قاعدة البيانات وفاحص الفيروسات"
$C up -d db clamav
for i in $(seq 1 60); do $C exec -T db pg_isready -U humaidan >/dev/null 2>&1 && break; sleep 2; done

say "4/7 إنشاء الجداول"
$C run --rm app migrate

say "5/7 إضافة البيانات الوهمية (مرة واحدة)"
if [ ! -f .preview-seeded ]; then
  $C run --rm app php artisan db:seed --force && touch .preview-seeded
else
  echo "البيانات الوهمية مضافة مسبقًا."
fi

say "6/7 تشغيل كل الخدمات"
$C up -d
sleep 10
$C ps

say "7/7 انتظار فاحص الفيروسات ثم فحص ونسخة احتياطية (حتى 15 دقيقة في أول تشغيل)"
OK=0
for i in $(seq 1 45); do
  if $C exec -T app php artisan ops:scanner-selftest >/dev/null 2>&1; then OK=1; break; fi
  sleep 20
done
$C exec -T app php artisan ops:scanner-selftest || true
$C exec -T app php artisan backup:run --verify || true

printf '\n\033[1;32m✅ انتهى إعداد بيئة التجربة.\033[0m\n'
[ "$OK" = 1 ] || echo "⚠️  فاحص الفيروسات لم يجهز بعد؛ أعد الأمر بعد 10 دقائق."
echo
cat "$CRED_FILE"
echo
echo "افتح الآن في المتصفح: https://$DOMAIN"
