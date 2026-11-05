#!/usr/bin/env bash
# تغيير كلمة مرور نافذة الحماية لبيئة التجربة (اسم المستخدم يبقى preview).
# التشغيل:  bash /opt/humaidan/platform/scripts/rotate-preview-password.sh
set -euo pipefail
CRED_FILE="${CRED_FILE:-/root/humaidan-preview-credentials.txt}"
cd "$(dirname "$0")/.."
[ -f .env ] || { echo "بيئة التجربة غير مُعدّة بعد"; exit 1; }

NEW="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
HASH="$(docker run --rm caddy:2.10-alpine caddy hash-password -p "$NEW")"

python3 - .env "$CRED_FILE" "$HASH" "$NEW" <<'PY'
import sys, re, os
env, cred, h, new = sys.argv[1:5]
s = open(env, encoding='utf-8').read()
s, n = re.subn(r"(?m)^PREVIEW_BASIC_HASH=.*$", lambda m: f"PREVIEW_BASIC_HASH='{h}'", s)
if n != 1: sys.exit("PREVIEW_BASIC_HASH غير موجود في .env")
open(env, 'w', encoding='utf-8').write(s)
if os.path.exists(cred):
    c = open(cred, encoding='utf-8').read()
    c = re.sub(r"(?m)^(\s*كلمة المرور: ).*$", lambda m: m.group(1) + new, c, count=1)
    open(cred, 'w', encoding='utf-8').write(c)
PY

docker compose -f docker-compose.yml -f docker-compose.preview.yml up -d --force-recreate caddy
printf '\n\033[1;32m✅ تغيّرت كلمة مرور نافذة الحماية.\033[0m\n'
echo "اسم المستخدم: preview"
echo "كلمة المرور الجديدة: $NEW"
echo "(محفوظة أيضًا في $CRED_FILE)"
