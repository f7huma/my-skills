#!/usr/bin/env bash
# تجهيز خادم Ubuntu جديد لمنصة مكتب الحميضان: تحديث، جدار ناري، Docker، تنزيل المنصة وبناؤها.
# يُشغَّل مرة واحدة بصلاحية root:
#   curl -fsSL https://raw.githubusercontent.com/f7huma/my-skills/add-design-skills/platform/scripts/server-bootstrap.sh | bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
BRANCH="add-design-skills"
REPO="https://github.com/f7huma/my-skills.git"

say() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }

[ "$(id -u)" -eq 0 ] || { echo "شغّل هذا الأمر بحساب root"; exit 1; }

say "1/5 تحديث النظام (قد يستغرق دقائق)"
apt-get update -y
apt-get -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" upgrade
apt-get install -y git ufw unattended-upgrades curl ca-certificates openssl

say "2/5 الجدار الناري: السماح بـ SSH و HTTP و HTTPS فقط"
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable

say "3/5 تثبيت Docker"
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker >/dev/null 2>&1 || true
docker --version
docker compose version

say "4/5 تنزيل المنصة إلى /opt/humaidan"
if [ -d /opt/humaidan/.git ]; then
  git -C /opt/humaidan fetch origin "$BRANCH"
  git -C /opt/humaidan checkout -B "$BRANCH" "origin/$BRANCH"
else
  git clone --branch "$BRANCH" "$REPO" /opt/humaidan
fi

say "5/5 بناء المنصة (3-10 دقائق)"
cd /opt/humaidan/platform
docker build -t humaidan-app:latest .
docker pull -q caddy:2.10-alpine >/dev/null
docker pull -q postgres:17-alpine >/dev/null
docker pull -q clamav/clamav:1.4 >/dev/null

IP="$(curl -fsS https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')"
printf '\n\033[1;32m✅ اكتمل تجهيز الخادم.\033[0m\n'
printf 'عنوان IP لهذا الخادم: \033[1m%s\033[0m\n' "$IP"
printf 'الخطوة التالية: أضف سجلات DNS كما في الدليل، ثم شغّل أمر بيئة التجربة.\n'
