#!/usr/bin/env bash
# نقل المنصة الحقيقية إلى دومين آخر، مع تحويل الدومين السابق و www تلقائيًا إلى الجديد (تبقى الروابط القديمة تعمل).
# التشغيل:  bash /opt/humaidan-prod/platform/scripts/change-domain.sh alhumaydhanlaw.com
# للرجوع:   bash /opt/humaidan-prod/platform/scripts/change-domain.sh app.alhumaydhanlaw.com
set -euo pipefail
NEW="${1:?اكتب الدومين الجديد بعد اسم الأمر}"
NEW="$(echo "$NEW" | tr 'A-Z' 'a-z' | sed -e 's#^https\?://##' -e 's#/.*$##')"
cd "$(dirname "$0")/.."
[ -f .env ] && [ -f .env.production ] || { echo "المنصة الحقيقية غير مُعدّة في هذا المجلد"; exit 1; }
OLD="$(grep -E '^APP_DOMAIN=' .env | cut -d= -f2-)"

say() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
set_kv() { python3 - "$1" "$2" "$3" <<'PY'
import sys, re
path, k, v = sys.argv[1:4]
s = open(path, encoding='utf-8').read()
s, n = re.subn(r'(?m)^' + re.escape(k) + r'=.*$', lambda m: f'{k}={v}', s)
if n == 0: s = s.rstrip('\n') + f'\n{k}={v}\n'
open(path, 'w', encoding='utf-8').write(s)
PY
}

say "1/4 التحقق من ربط $NEW بهذا الخادم"
MYIP="$(curl -fsS https://api.ipify.org 2>/dev/null || true)"
DNSIP="$(getent ahostsv4 "$NEW" 2>/dev/null | awk 'NR==1{print $1}')"
if [ -n "$MYIP" ] && [ "$DNSIP" != "$MYIP" ]; then
  echo "⚠️  $NEW يشير إلى ${DNSIP:-لا شيء} وعنوان هذا الخادم $MYIP."
  echo "    عدّل سجل A في DNS أو انتظر انتشاره (حتى ساعة)، ثم أعد الأمر نفسه. لم يتغير شيء."
  exit 1
fi
V6="$(getent ahostsv6 "$NEW" 2>/dev/null | awk '$1 !~ /^::ffff:/ {print $1; exit}')"
MY6="$(curl -6 -fsS https://api64.ipify.org 2>/dev/null || true)"
if [ -n "$V6" ] && [ "$V6" != "$MY6" ]; then
  echo "⚠️  يوجد سجل AAAA للدومين $NEW يشير إلى $V6 (ليس هذا الخادم). احذف سجل AAAA من DNS ثم أعد الأمر. لم يتغير شيء."
  exit 1
fi
echo "الدومين يشير إلى هذا الخادم ✓"

say "2/4 تحديث الإعدادات (من $OLD إلى $NEW)"
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"; cp .env.production ".env.production.bak.$(date +%Y%m%d%H%M%S)"
set_kv .env APP_DOMAIN "$NEW"
set_kv .env.production APP_URL "https://$NEW"

say "3/4 تحويل الدومين السابق و www إلى الجديد"
mkdir -p docker/caddy/extra
{
  echo "# مولَّد بواسطة change-domain.sh — لا تعدّله يدويًا"
  SRC=""
  case "$NEW" in www.*) ;; *.*.*) ;; *) SRC="www.$NEW" ;; esac
  if [ -n "$OLD" ] && [ "$OLD" != "$NEW" ]; then SRC="${SRC:+$SRC, }$OLD"; fi
  if [ -n "$SRC" ]; then
    echo "$SRC {"
    echo "	redir https://$NEW{uri} permanent"
    echo "}"
  fi
} > docker/caddy/extra/redirects.caddy
cat docker/caddy/extra/redirects.caddy

say "4/4 إعادة تشغيل الخدمات"
docker compose up -d --force-recreate app worker scheduler caddy
sleep 15
CODE="$(curl -s -o /dev/null -w '%{http_code}' "https://$NEW/login" || true)"
docker compose ps
if [ "$CODE" = "200" ]; then
  printf '\n\033[1;32m✅ المنصة تعمل الآن على https://%s\033[0m\n' "$NEW"
else
  echo "⚠️  لم تستجب https://$NEW/login بعد (الرمز: $CODE). قد تحتاج الشهادة دقيقة؛ افتح الرابط بعد دقيقتين."
  echo "    إن استمرت المشكلة أرسل آخر 30 سطرًا من:  docker compose logs --tail 30 caddy"
fi
echo "الروابط القديمة على $OLD تتحول تلقائيًا إلى $NEW."
