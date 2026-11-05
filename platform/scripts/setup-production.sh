#!/usr/bin/env bash
# إعداد وتشغيل المنصة الحقيقية app.alhumaydhanlaw.com.
# يسأل سؤالين فقط: بريدك الذي ستدخل به، وكلمة مرور صندوق no-reply@alhumaydhanlaw.com.
# يولّد كل المفاتيح ويحفظها في /root/humaidan-production-keys.txt (انسخها لخزنتك ثم احذف الملف).
# التشغيل:  bash /opt/humaidan-prod/platform/scripts/setup-production.sh
set -euo pipefail
DOMAIN="${DOMAIN:-app.alhumaydhanlaw.com}"
OFFICE_EMAIL="${OFFICE_EMAIL:-info@alhumaydhanlaw.com}"
MAIL_USER="${MAIL_USER:-no-reply@alhumaydhanlaw.com}"
LAWYER_NAME="${LAWYER_NAME:-فيصل حميضان الحميضان}"
KEYS_FILE=/root/humaidan-production-keys.txt
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
C="docker compose"

say "1/8 إيقاف بيئة التجربة إن كانت تعمل (لتحرير المنفذ 443)"
if [ -d /opt/humaidan/platform ] && [ -f /opt/humaidan/platform/.env ]; then
  (cd /opt/humaidan/platform && docker compose -f docker-compose.yml -f docker-compose.preview.yml stop) || true
fi

say "2/8 التحقق من ربط الدومين $DOMAIN"
MYIP="$(curl -fsS https://api.ipify.org 2>/dev/null || true)"
DNSIP="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1{print $1}')"
if [ -n "$MYIP" ] && [ "$DNSIP" != "$MYIP" ]; then
  echo "⚠️  $DOMAIN يشير إلى ${DNSIP:-لا شيء} وعنوان الخادم $MYIP — أضف سجل DNS أو انتظر ثم أعد الأمر."
  exit 1
fi

say "3/8 معلوماتك"
if [ ! -f .env.production ]; then
  read -r -p "اكتب بريدك الذي ستدخل به للمنصة ثم Enter: " LAWYER_EMAIL </dev/tty
  read -r -s -p "الصق كلمة مرور صندوق $MAIL_USER ثم Enter (لن تظهر): " MAIL_PASS </dev/tty; echo
  DB_PASS="$(pass)"; APPK="$(key)"; FK="$(key)"; BK="$(key)"
  cp .env.example .env.production
  for kv in "APP_ENV=production" "APP_DEBUG=false" "APP_URL=https://$DOMAIN" "APP_KEY=$APPK" \
            "DB_HOST=db" "DB_DATABASE=humaidan" "DB_USERNAME=humaidan" "DB_PASSWORD=$DB_PASS" \
            "SESSION_SECURE_COOKIE=true" "SESSION_ENCRYPT=true" "MFA_REQUIRED_FOR_LAWYER=true" \
            "SCANNER_DRIVER=clamav" "FILES_ENCRYPTION_KEY=$FK" "BACKUP_ENCRYPTION_KEY=$BK" \
            "OPS_ALERT_MAIL=$OFFICE_EMAIL" "LOG_CHANNEL=stderr" "LOG_LEVEL=warning" \
            "MAIL_MAILER=smtp" "MAIL_SCHEME=smtps" "MAIL_HOST=smtp.hostinger.com" "MAIL_PORT=465" \
            "MAIL_USERNAME=$MAIL_USER" "MAIL_FROM_ADDRESS=$MAIL_USER" \
            "ATTACHMENTS_ROOT=/var/lib/humaidan/files" "BACKUP_PATH=/var/lib/humaidan/backups"; do
    set_env .env.production "${kv%%=*}" "${kv#*=}"
  done
  set_env .env.production MAIL_PASSWORD "'$MAIL_PASS'"
  cat > .env <<EOF
COMPOSE_PROJECT_NAME=humaidan
APP_ENV_FILE=.env.production
APP_DOMAIN=$DOMAIN
ACME_EMAIL=$OFFICE_EMAIL
DB_PASSWORD=$DB_PASS
EOF
  echo "$LAWYER_EMAIL" > .lawyer-email
  chmod 600 .env .env.production .lawyer-email
  umask 077
  cat > "$KEYS_FILE" <<EOF
مفاتيح المنصة الحقيقية — app.alhumaydhanlaw.com
⚠️ انسخ هذا الملف كاملًا إلى خزنة كلمات المرور، واحتفظ بنسخة ثانية في مكان منفصل، ثم احذفه من الخادم.
فقدان هذه المفاتيح = فقدان الملفات والنسخ الاحتياطية.

APP_KEY=$APPK
FILES_ENCRYPTION_KEY=$FK
BACKUP_ENCRYPTION_KEY=$BK
DB_PASSWORD=$DB_PASS
EOF
else
  echo "ملفات الإعداد موجودة مسبقًا — لن تُعاد كتابتها."
  LAWYER_EMAIL="$(cat .lawyer-email 2>/dev/null || true)"
fi

say "4/8 بناء المنصة"
docker build -t humaidan-app:latest .

say "5/8 تشغيل قاعدة البيانات وفاحص الفيروسات"
$C up -d db clamav
for i in $(seq 1 60); do $C exec -T db pg_isready -U humaidan >/dev/null 2>&1 && break; sleep 2; done

say "6/8 إنشاء الجداول والبيانات المرجعية فقط (لا بيانات وهمية)"
$C run --rm app migrate
$C run --rm app php artisan db:seed --class='Database\Seeders\ReferenceDataSeeder' --force

say "7/8 تشغيل كل الخدمات"
$C up -d
sleep 10
$C ps

say "8/8 حساب المحامي الأول"
if [ -n "${LAWYER_EMAIL:-}" ] && [ ! -f .lawyer-invited ]; then
  $C exec -T app php artisan office:invite-lawyer "$LAWYER_EMAIL" "$LAWYER_NAME" && touch .lawyer-invited
fi

printf '\n\033[1;32m✅ انتهى إعداد المنصة الحقيقية.\033[0m\n'
echo "1) افتح الرابط الطويل الظاهر أعلاه (يبدأ بـ https://$DOMAIN/invitation/) في متصفحك لتعيين كلمة المرور."
echo "2) اعرض مفاتيحك بالأمر:  cat $KEYS_FILE   ثم انسخها لخزنتك."
