# خطة التشغيل لموقع alhumaydhanlaw.com

خطة مخصّصة لوضعك الحالي:
- دومين **alhumaydhanlaw.com** على Hostinger.
- خادم **VPS KVM 2** على Hostinger.

نفّذ الخطوات بالترتيب. لا تنتقل لخطوة قبل أن ترى «✅ علامة النجاح» في الخطوة التي قبلها. وإذا ظهر أي خطأ، أرسل آخر 30 سطرًا منه.

## العناوين التي سنستخدمها
| الاستخدام | العنوان |
|---|---|
| بيئة التجربة (بيانات وهمية) | `preview.alhumaydhanlaw.com` |
| المنصة الحقيقية | `app.alhumaydhanlaw.com` |
| الدومين الرئيسي | `alhumaydhanlaw.com` يبقى كما هو، لموقع تعريفي للمكتب مستقبلًا |

> إن أردت أن تعمل المنصة على الدومين الرئيسي نفسه بدل `app.`، أخبرني قبل المرحلة ج.

## قبل أن تبدأ
- **خزنة كلمات مرور:** مثل Bitwarden أو 1Password، أو ملف مشفّر عندك. ستحفظ فيها عدة قيم سرية خلال الخطوات.
- **بريد للمكتب على الدومين:** مثل `info@alhumaydhanlaw.com`. منه تُرسل الدعوات وتنبيهات النظام.
  - المكان: hPanel ← **Emails** ← دومينك ← أنشئ صندوقًا.
  - وأنشئ أيضًا صندوقًا ثانيًا للإرسال الآلي: `no-reply@alhumaydhanlaw.com`.

---

# المرحلة أ: تجهيز الخادم (≈ 45 دقيقة)

### أ1. نظام التشغيل وعنوان IP
1. **المكان:** hpanel.hostinger.com ← **VPS** ← **Manage** بجانب الخادم.
2. إن لم يكن نظام التشغيل مثبتًا: ادخل **OS & Panel** (أو **Operating System**) ← اختر **Ubuntu 24.04** «نظيفًا» بلا لوحة تحكم.
3. عيّن **كلمة مرور root** قوية، واحفظها في الخزنة.
4. انسخ **عنوان IP** للخادم (IPv4) من صفحة الخادم.

✅ **علامة النجاح:** عندك عنوان IP وكلمة مرور root.

### أ2. سجلات DNS
1. **المكان:** hPanel ← **Domains** ← `alhumaydhanlaw.com` ← **DNS / Nameservers** ← **DNS records**.
2. أضف سجلين من نوع **A**، وكلاهما يشير إلى **عنوان IP الخادم**:
   | Type | Name | Points to | TTL |
   |---|---|---|---|
   | A | `preview` | عنوان IP الخادم | الافتراضي |
   | A | `app` | عنوان IP الخادم | الافتراضي |
3. **لا تحذف أو تعدّل** أي سجل موجود، خصوصًا سجلات `@` و`www` و`MX` الخاصة بالبريد.

✅ **علامة النجاح:** بعد 5–30 دقيقة، نفّذ على **جهازك**:
```bash
ping preview.alhumaydhanlaw.com
```
يجب أن يظهر عنوان IP الخادم.

### أ3. الدخول للخادم
1. افتح الطرفية على جهازك:
   - **ويندوز:** قائمة ابدأ ← `PowerShell`.
   - **ماك:** تطبيق **Terminal**.
2. نفّذ (ضع عنوان IP الحقيقي):
   ```bash
   ssh root@عنوان-IP
   ```
3. إذا سألك `Are you sure...?` اكتب `yes`.
4. اكتب كلمة مرور root. **لن تظهر الأحرف أثناء الكتابة**، وهذا طبيعي.

✅ **علامة النجاح:** يظهر سطر ينتهي بـ `:~#`.

> كل الأوامر التالية تُلصق في هذه النافذة (بالزر الأيمن للفأرة)، ثم Enter.

### أ4. التحديث والجدار الناري وDocker
```bash
apt update && apt -y upgrade
apt -y install git ufw unattended-upgrades
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw --force enable
curl -fsSL https://get.docker.com | sh
docker --version && docker compose version
```
- إن ظهرت نافذة وردية تسأل عن ملفات إعداد، اضغط Enter.
- إن كان في hPanel قسم **Firewall** للخادم: إما ألا تفعّله، أو تسمح فيه بالمنافذ 22 و80 و443.

✅ **علامة النجاح:** يظهر رقم إصدار Docker ورقم إصدار Docker Compose.

### أ5. رمز وصول GitHub (قراءة فقط)
1. **المكان:** github.com ← صورتك ← **Settings** ← **Developer settings** ← **Personal access tokens** ← **Fine-grained tokens** ← **Generate new token**.
2. اضبط:
   - **Name:** `humaidan-server`
   - **Expiration:** 90 يومًا
   - **Repository access:** Only select ← `my-skills`
   - **Permissions** ← Repository ← **Contents: Read-only**
3. اضغط **Generate**، وانسخ الرمز الذي يبدأ بـ `github_pat_` واحفظه في الخزنة.

### أ6. تنزيل المنصة وبنائها
```bash
cd /opt && git clone https://github.com/f7huma/my-skills.git humaidan
```
- **Username:** `f7huma`
- **Password:** الصق الرمز من أ5. لن يظهر عند اللصق.

```bash
cd /opt/humaidan && git checkout add-design-skills && cd platform
docker compose build
```
✅ **علامة النجاح:** ينتهي البناء بدون `ERROR`. قد يأخذ 3–10 دقائق.

---

# المرحلة ب: بيئة التجربة preview (≈ 30 دقيقة + أسبوع تجربة)

### ب1. توليد المفاتيح
نفّذ هذا الأمر **5 مرات**، واحفظ النواتج في الخزنة تحت عنوان «تجربة»:
```bash
echo "base64:$(openssl rand -base64 32)"
```
- **1:** APP_KEY
- **2:** FILES_ENCRYPTION_KEY
- **3:** BACKUP_ENCRYPTION_KEY
- **4:** كلمة مرور القاعدة (احذف منه `base64:`)
- **5:** كلمة حماية الموقع (احذف منه `base64:`)

ثم ولّد بصمة كلمة الحماية. ضع الناتج 5 مكان النص العربي، مع إبقاء علامتي `'` حوله:
```bash
docker run --rm caddy:2.10-alpine caddy hash-password -p 'الناتج-5'
```
احفظ البصمة الناتجة (تبدأ بـ `$2a$`).

### ب2. ملف إعداد التجربة
```bash
cp .env.example .env.preview && nano .env.preview
```
عدّل هذه الأسطر (تنقّل بالأسهم). الأسطر غير المذكورة اتركها كما هي:
```
APP_ENV=preview
APP_DEBUG=false
APP_URL=https://preview.alhumaydhanlaw.com
APP_KEY=الناتج-1
APP_NOINDEX=true
SYNTHETIC_DATA_ONLY=true
DB_HOST=db
DB_DATABASE=humaidan
DB_USERNAME=humaidan
DB_PASSWORD=الناتج-4
SESSION_SECURE_COOKIE=true
SCANNER_DRIVER=clamav
FILES_ENCRYPTION_KEY=الناتج-2
BACKUP_ENCRYPTION_KEY=الناتج-3
```
احفظ بـ `Ctrl+O` ثم Enter، واخرج بـ `Ctrl+X`.

### ب3. ملف متغيرات التشغيل
```bash
nano .env
```
الملف جديد؛ اكتب فيه:
```
COMPOSE_PROJECT_NAME=humaidan-preview
APP_ENV_FILE=.env.preview
APP_DOMAIN=preview.alhumaydhanlaw.com
ACME_EMAIL=info@alhumaydhanlaw.com
DB_PASSWORD=الناتج-4
PREVIEW_BASIC_USER=preview
PREVIEW_BASIC_HASH='البصمة'
```
> ⚠️ **البصمة بين علامتي `'` إلزاميًا**، مثل `'$2a$14$...'`. بدونهما يفشل التشغيل.

ثم احمِ الملفين:
```bash
chmod 600 .env .env.preview
```

### ب4. التشغيل
```bash
alias dcp='docker compose -f docker-compose.yml -f docker-compose.preview.yml'
dcp up -d db clamav && sleep 90
dcp run --rm app migrate
dcp run --rm app php artisan db:seed --force
dcp up -d
dcp ps
```
✅ **علامة النجاح:** الخدمات الست حالتها `running` أو `healthy`: caddy وapp وworker وscheduler وdb وclamav.

> اختصار `dcp` يُفقد عند إغلاق النافذة. أعد لصق سطر `alias` في كل جلسة جديدة.

### ب5. الدخول
1. افتح `https://preview.alhumaydhanlaw.com`.
2. في نافذة الحماية: اسم المستخدم `preview`، وكلمة المرور الناتج 5.
3. في شاشة دخول المنصة: `lawyer@example.test` / `Demo-Pass-2026`.

✅ **علامة النجاح:** قفل 🔒 في المتصفح، وتظهر صفحة «إنجاز الأسبوع».

### ب6. الفحوص
```bash
dcp exec app php artisan ops:scanner-selftest
dcp exec app php artisan backup:run --verify
```
✅ **علامة النجاح:**
- الأمر الأول يقول «محرك الفحص يعمل». إن ظهر `error` فانتظر 5 دقائق وأعده.
- الأمر الثاني يقول «تم التحقق من سلامة النسخة».

ثم في المتصفح: القائمة الجانبية ← **إدارة المكتب ← حالة النظام** ← **تشغيل الفحوص الآن**.

### ب7. أسبوع التجربة
استخدم المنصة كيوم عمل حقيقي بأسماء وهمية، من الكمبيوتر والجوال، كما في المرحلة 5 من `00-خطة-الجاهزية-خطوة-بخطوة.md`.

ادخل أيضًا بحساب العميل `cfo@example.test` (كلمة المرور نفسها) وأرسل مطالبة.

سجّل كل ملاحظة وأرسلها لي لأعدّلها قبل التشغيل الحقيقي.

---

# المرحلة ج: المنصة الحقيقية app (≈ ساعتان)

### ج1. إيقاف التجربة
تشغيل البيئتين معًا يتطلب وكيلًا مشتركًا؛ أخبرني إن أردت ذلك. غير ذلك أوقف التجربة:
```bash
cd /opt/humaidan/platform && docker compose -f docker-compose.yml -f docker-compose.preview.yml down
```

### ج2. نسخة الإنتاج ومفاتيح جديدة
```bash
cd /opt && git clone https://github.com/f7huma/my-skills.git humaidan-prod
cd /opt/humaidan-prod && git checkout add-design-skills && cd platform
```
- كرر أمر توليد المفاتيح من ب1 **4 مرات جديدة**. لا تعِد استخدام مفاتيح التجربة.
- احفظها تحت عنوان «إنتاج — لا تُفقد»، **ونسخة ثانية في مكان منفصل**. فقدانها يعني فقدان الملفات المشفرة.

### ج3. إعدادات البريد
**المكان:** hPanel ← **Emails** ← صندوق `no-reply@alhumaydhanlaw.com` ← إعدادات الاتصال (Connect apps / Configuration). انسخ منها:
- خادم SMTP: عادة `smtp.hostinger.com`
- المنفذ: عادة `465`
- اسم المستخدم: البريد كاملًا
- كلمة مرور الصندوق

### ج4. ملف إعداد الإنتاج
```bash
cp .env.example .env.production && nano .env.production
```
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.alhumaydhanlaw.com
APP_KEY=مفتاح-إنتاج-1
DB_HOST=db
DB_DATABASE=humaidan
DB_USERNAME=humaidan
DB_PASSWORD=مفتاح-إنتاج-4-بدون-base64
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
MFA_REQUIRED_FOR_LAWYER=true
SCANNER_DRIVER=clamav
FILES_ENCRYPTION_KEY=مفتاح-إنتاج-2
BACKUP_ENCRYPTION_KEY=مفتاح-إنتاج-3
OPS_ALERT_MAIL=info@alhumaydhanlaw.com
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@alhumaydhanlaw.com
MAIL_PASSWORD=كلمة-مرور-الصندوق
MAIL_FROM_ADDRESS=no-reply@alhumaydhanlaw.com
```
ثم ملف متغيرات التشغيل:
```bash
nano .env
```
```
COMPOSE_PROJECT_NAME=humaidan
APP_ENV_FILE=.env.production
APP_DOMAIN=app.alhumaydhanlaw.com
ACME_EMAIL=info@alhumaydhanlaw.com
DB_PASSWORD=مفتاح-إنتاج-4-بدون-base64
```
```bash
chmod 600 .env .env.production
```

### ج5. التشغيل
```bash
docker compose build
docker compose up -d db clamav && sleep 90
docker compose run --rm app migrate
docker compose run --rm app php artisan db:seed --class='Database\Seeders\ReferenceDataSeeder' --force
docker compose up -d
docker compose ps
```
> ⚠️ في الإنتاج لا تنفّذ `db:seed` بدون `--class`، حتى لا تُضاف بيانات وهمية.

✅ **علامة النجاح:** الخدمات الست تعمل، و`https://app.alhumaydhanlaw.com` يفتح شاشة الدخول بقفل 🔒.

### ج6. حسابك وتفعيل الأمان
```bash
docker compose exec app php artisan office:invite-lawyer بريدك@alhumaydhanlaw.com "فيصل حميضان الحميضان"
```
1. افتح الرابط الذي يظهر، وعيّن كلمة مرور من 12 حرفًا فأكثر.
2. فعّل **المصادقة الثنائية**:
   - ثبّت على جوالك Microsoft Authenticator أو Google Authenticator.
   - امسح الرمز المعروض، ثم أدخل الرقم.
3. **اطبع رموز الاسترداد** أو احفظها في الخزنة.

### ج7. الإعدادات القانونية
في المنصة ← **الإعدادات**:
- **قواعد المواعيد:** راجع المواد 14 و18 و36 من نظام التنفيذ، واعتمد القاعدة أو أبقها مسودة.
- **تقويم الإجازات:** صحّح تواريخ العيدين واعتمد كل يوم.
- **أنواع طلبات الإجراء:** اضبط المهل.
- **عام:** نافذة تنبيه الوكالات.

### ج8. النسخ الاحتياطي خارج الخادم
- **نسخ Hostinger:** hPanel ← VPS ← **Backups** ← فعّل النسخ التلقائي. دوّن تكراره ومدة الاحتفاظ به.
- **نسخة على جهاز المكتب، أسبوعيًا كبداية.** على **جهازك** وليس الخادم:
  ```bash
  scp -r root@عنوان-IP:/var/lib/docker/volumes/humaidan_backups/_data ./humaidan-backups
  ```
  الملفات مشفرة، فلا تُفتح بدون مفتاح النسخ. احفظ المجلد في مكان آمن.

### ج9. الفحص النهائي قبل أول عميل
```bash
docker compose exec app php artisan ops:scanner-selftest
docker compose exec app php artisan backup:run --verify
docker compose exec app php artisan ops:security-check
```
✅ **علامة النجاح:**
- `ops:security-check` بلا ✗، باستثناء بند «وجهة النسخ المنفصلة» إن اعتمدت النسخ على جهاز المكتب. وثّق ذلك.
- اختبار استعادة ناجح:
  1. انسخ `.env.restore-test.example` إلى `.env.restore-test`، وضع فيه مفاتيح الإنتاج الثلاثة.
  2. نفّذ:
     ```bash
     ./scripts/restore-test.sh <اسم-آخر-نسخة>
     ```
     اسم النسخة يظهر من:
     ```bash
     docker compose exec app php artisan backup:list
     ```
  3. يجب أن تظهر `ok = نعم`.

### ج10. الإطلاق
1. وقّع على بنود `07-قائمة-الإطلاق.md`.
2. أدخل **عميلًا حقيقيًا واحدًا**، وراقب أسبوعًا.
3. بعدها أدخل بقية العملاء، وادعُ مستخدمي البوابة من صفحة كل عميل ← **حسابات البوابة**.

---

## بعد الإطلاق
| متى | ماذا |
|---|---|
| يوميًا (تلقائي) | نسخ احتياطي، تنبيهات المواعيد، فحص الملفات. انتبه لبريد التنبيهات |
| أسبوعيًا | نظرة على «حالة النظام» + تنزيل النسخ لجهاز المكتب (ج8) |
| شهريًا | اختبار استعادة (ج9) |
| عند كل تحديث أرسله لك | `cd /opt/humaidan-prod/platform && ./scripts/deploy.sh add-design-skills` |
