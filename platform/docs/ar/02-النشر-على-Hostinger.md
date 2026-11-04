# النشر على Hostinger VPS

> **تنبيه صادق:** لم يُتح لي الوصول إلى صفحات Hostinger المرجعية من بيئة العمل (حُجب الاتصال)، ولم أشترِ باقة أو أنشر شيئًا. الخطوات أدناه عامة لأي VPS بنظام Ubuntu وDocker، ويجب التحقق من خصائص باقتك في لوحة Hostinger قبل الاعتماد عليها. كذلك لم يكتمل بناء صورة Docker داخل بيئة العمل لأن مرايا حزم Debian محجوبة فيها؛ اكتمل بناء الواجهة واعتماديات PHP، ويجب تشغيل `docker compose build` على الخادم والتحقق من نجاحه.

## 1. قبل البدء: تحقق من الباقة
في لوحة Hostinger VPS تحقق ووثّق بنفسك:
- نظام التشغيل Ubuntu 22.04/24.04 (أو قالب Docker إن توفر)، وحجم القرص والذاكرة (يُنصح بـ 4GB RAM على الأقل لأن ClamAV يستهلك نحو 1–1.5GB).
- **موقع مركز البيانات**: اختر موقعًا يتوافق مع متطلبات المكتب ونظام حماية البيانات الشخصية. إن كان خارج المملكة فراجع لائحة نقل البيانات الشخصية إلى خارج المملكة (SDAIA) قبل إدخال أي بيانات حقيقية.
- النسخ الاحتياطي الذي توفره Hostinger (تكراره، مدة الاحتفاظ، هل الاستعادة تستبدل الخادم كاملًا). اعتبره **طبقة إضافية** فقط؛ النسخ المشفرة للتطبيق هي الأساس.
- جدار الحماية: افتح 22 (يفضّل من عناوينك فقط) و80 و443 فقط.

## 2. تهيئة الخادم (مرة واحدة)
```bash
ssh root@<IP>
adduser deploy && usermod -aG sudo deploy
# مفاتيح SSH فقط، وأوقف الدخول بكلمة مرور وroot في /etc/ssh/sshd_config
apt update && apt -y upgrade && apt -y install ca-certificates curl git ufw unattended-upgrades
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw enable
curl -fsSL https://get.docker.com | sh && usermod -aG docker deploy
```

## 3. الدومين المؤقت والدائم
- عنوان الموقع متغير إعداد: `APP_URL` في ملف البيئة و`APP_DOMAIN` لدى الوكيل.
- للمعاينة: استخدم نطاقًا فرعيًا مؤقتًا يتبع لك (مثل `preview.<دومين المكتب>`) أو النطاق المؤقت الذي تمنحه Hostinger إن كان يقبل شهادة Let's Encrypt صحيحة (تحقق أن السجل A يشير إلى IP الخادم). يصدر Caddy الشهادة تلقائيًا.
- الانتقال للدائم **دون إعادة بناء**: عدّل `APP_URL` و`APP_DOMAIN` و`SESSION_DOMAIN` (فارغ أو الدومين الجديد) ثم:
  ```bash
  docker compose --env-file .env.production up -d   # يعيد إنشاء الحاويات بالمتغيرات الجديدة؛ config:cache يُبنى عند الإقلاع
  ```
  روابط الدعوات القديمة تشير للدومين القديم: ألغها وأعد إرسالها.

## 4. أول نشر للإنتاج
```bash
su - deploy
git clone <المستودع> humaidan && cd humaidan/platform
cp .env.example .env.production && chmod 600 .env.production
```
اضبط في `.env.production` على الأقل:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<الدومين>
APP_KEY=base64:...                 # docker run --rm humaidan-app:latest php artisan key:generate --show
DB_HOST=db  DB_DATABASE=humaidan  DB_USERNAME=humaidan  DB_PASSWORD=<قوية>
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
MFA_REQUIRED_FOR_LAWYER=true
SCANNER_DRIVER=clamav  CLAMAV_HOST=clamav
FILES_ENCRYPTION_KEY=...           # مختلف عن APP_KEY
BACKUP_ENCRYPTION_KEY=...          # مختلف عن السابقين
BACKUP_OFFSITE_PATH=/mnt/offsite   # بعد تركيب وجهة منفصلة (انظر دليل النسخ)
OPS_ALERT_MAIL=<بريد المكتب>      # مع إعداد MAIL_* لخادم بريد المكتب
```
وفي ملف `.env` بجوار `docker-compose.yml` (لمتغيرات Compose):
```
APP_DOMAIN=<الدومين>
ACME_EMAIL=<بريد المكتب>
DB_PASSWORD=<نفس كلمة مرور القاعدة>
APP_ENV_FILE=.env.production
```
ثم:
```bash
docker compose build
docker compose up -d db clamav
docker compose run --rm app migrate
docker compose run --rm app php artisan db:seed --class=Database\\Seeders\\ReferenceDataSeeder --force
docker compose up -d
docker compose exec app php artisan ops:security-check
```
**احفظ** `APP_KEY` و`FILES_ENCRYPTION_KEY` و`BACKUP_ENCRYPTION_KEY` في خزنة كلمات مرور المكتب خارج الخادم. فقدانها يعني فقدان الملفات والنسخ.

## 5. إنشاء حساب المحامي الأول
```bash
docker compose exec app php artisan office:invite-lawyer <البريد> "<الاسم>"
```
افتح الرابط (صالح مرة واحدة 72 ساعة)، عيّن كلمة مرور قوية، ثم فعّل المصادقة الثنائية فورًا (إلزامية في الإنتاج) واحفظ رموز الاسترداد.

## 6. بيئة المعاينة
مشروع Compose منفصل بقاعدة ووحدات تخزين ومفاتيح مختلفة، وبيانات اصطناعية فقط:
```bash
cp .env.example .env.preview   # APP_ENV=preview, APP_NOINDEX=true, SYNTHETIC_DATA_ONLY=true, مفاتيح جديدة
caddy hash-password             # أو: docker run --rm caddy:2.10-alpine caddy hash-password -p '<كلمة>'
# في .env للمعاينة: PREVIEW_BASIC_USER و PREVIEW_BASIC_HASH و APP_DOMAIN=<الدومين المؤقت> و APP_ENV_FILE=.env.preview
docker compose -p humaidan-preview -f docker-compose.yml -f docker-compose.preview.yml up -d
docker compose -p humaidan-preview run --rm app migrate
docker compose -p humaidan-preview run --rm app php artisan db:seed --force   # بيانات اصطناعية (APP_ENV ≠ production)
docker compose -p humaidan-preview exec app php artisan preview:assert-synthetic
```
التقييد قبل شاشة الدخول طبقتان: Basic Auth لدى Caddy، و`PREVIEW_GATE_*` داخل التطبيق اختياريًا. الفهرسة ممنوعة بـ `X-Robots-Tag` و`robots.txt`.
> إن شغّلت المعاينة والإنتاج على الخادم نفسه فلا يمكن لوكيلين حجز المنفذ 443: شغّل وكيلًا واحدًا بموقعين (انسخ كتلة الموقع في Caddyfile) ووجّه كل موقع إلى حاوية التطبيق الخاصة به.

## 7. التحديث والتراجع
```bash
./scripts/deploy.sh v1.2.0     # نسخة احتياطية متحقق منها، بناء، هجرة، إعادة تشغيل، فحص
./scripts/rollback.sh          # العودة للصورة السابقة
```
- انشر فقط إصدارًا نجحت فيه وظيفة `ready-to-deploy` في GitHub Actions.
- إذا أضافت الهجرة تغييرًا غير متوافق وتعثر النشر: أوقف التطبيق، استعد نسخة ما قبل النشر على بيئة معزولة للتحقق، ثم استعدها للإنتاج وفق دليل النسخ (خطوة تتطلب قرارًا بشريًا).
- التطبيق لا يعدّل كوده ولا يثبت تحديثات تلقائيًا: نظام الملفات للقراءة فقط داخل الحاوية، والتحديث يمر بالاختبارات والمراجعة.
