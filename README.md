# نظام حسابات مسجد خالد بن الوليد

نظام لإدارة عُهد وفلوس المسجد. فلوس المسجد مش في خزنة واحدة، هي متقسّمة مع أشخاص. النظام بيعرف كل شخص معاه كام، ويمنع أي صرف من غير موافقة الإمام، ويسجّل كل جنيه بفاتورته.

---

## المحتويات

1. [مكوّنات المشروع](#1-مكوّنات-المشروع)
2. [التشغيل على جهاز التطوير](#2-التشغيل-على-جهاز-التطوير)
3. [حسابات التجربة](#3-حسابات-التجربة)
4. [ملف `.env`](#4-ملف-env)
5. [النقل لـ MySQL](#5-النقل-لـ-mysql)
6. [المهام التلقائية (Scheduler)](#6-المهام-التلقائية-scheduler)
7. [النسخ الاحتياطي والاسترجاع](#7-النسخ-الاحتياطي-والاسترجاع)
8. [إشعارات Push](#8-إشعارات-push)
9. [النشر على سيرفر حقيقي](#9-النشر-على-سيرفر-حقيقي)
10. [تطبيق موبايل وديسكتوب (Capacitor / Electron)](#10-تطبيق-موبايل-وديسكتوب)
11. [قواعد العمل المهمة](#11-قواعد-العمل-المهمة)
12. [الاختبارات](#12-الاختبارات)
13. [هيكل الكود](#13-هيكل-الكود)

---

## 1) مكوّنات المشروع

| الجزء | المكان | التقنيات |
|---|---|---|
| الـ API | `backend/` | Laravel 13، Sanctum (توكن)، spatie/permission، spatie/activitylog، mPDF، Laravel Excel، spatie/backup، web-push |
| الواجهة | `frontend/` | Vue 3 (Composition API)، Vite، Pinia، Vue Router، Tailwind، Chart.js، PWA |
| الديزاين | `design/` | تصدير Google Stitch (الألوان والمقاسات في `DESIGN.md`) |

**ليه الواجهة منفصلة عن Laravel؟** عشان نفس الواجهة تتغلّف بعدين بـ Capacitor (أندرويد وآيفون) وبـ Electron (ديسكتوب) من غير أي تعديل في السيرفر. الواجهة بتكلّم الـ API بس، بتوكن Bearer. في النشر العادي بتتبني جوه Laravel، فبيبقى سيرفر واحد بس (شوف [النشر](#9-النشر-على-سيرفر-حقيقي)).

---

## 2) التشغيل على جهاز التطوير

**المتطلبات:** PHP 8.3 أو أحدث (فيه امتدادات `gd` و`zip` و`mbstring` و`openssl` و`pdo_sqlite` أو `pdo_mysql`)، وComposer، وNode 20 أو أحدث.

```bash
# 1) الـ API
cd backend
composer install
cp .env.example .env          # أول مرة بس
php artisan key:generate      # أول مرة بس
php artisan push:keys         # أول مرة بس، وانسخ الناتج في .env (شوف الجزء 8)
php artisan migrate:fresh --seed    # قاعدة بيانات جديدة + بيانات تجريبية لآخر 6 شهور
php artisan serve             # http://127.0.0.1:8000

# 2) الواجهة (في تيرمنال تاني)
cd frontend
npm install
npm run dev                   # http://127.0.0.1:5173
```

افتح **http://127.0.0.1:5173**. طلبات `/api` بتتحوّل للسيرفر تلقائيًا.

> **ويندوز:** لو PowerShell قال إن `php` مش موجود، ضيف مجلد PHP للـ PATH. ولو طلع خطأ OpenSSL في الإشعارات، شوف `OPENSSL_CONF` في الجزء 4.

---

## 3) حسابات التجربة

كلمة المرور لكل الحسابات: **`12345678`**

| الاسم | الموبايل | الدور |
|---|---|---|
| الشيخ أحمد | `01000000001` | إمام المسجد، وهو اللي بيوافق على الصرف |
| الحاج محمود | `01000000002` | أمين الصندوق |
| الأستاذ خالد | `01000000003` | المحاسب |

"عم سيد" عامل المسجد معاه عهدة، لكن ملوش حساب دخول. أي مستخدم يقدر يسجّل طلبات أو إيرادات بالنيابة عنه.

> ⚠️ **قبل الاستخدام الحقيقي:** ابدأ بقاعدة نضيفة من غير البيانات التجريبية:
> ```bash
> php artisan migrate:fresh
> php artisan db:seed --class=RolesAndPermissionsSeeder
> php artisan db:seed --class=LookupsSeeder
> php artisan mosque:create-imam      # هيسألك عن الاسم والموبايل وكلمة المرور
> ```
> بعد كده ادخل بحساب الإمام، وضيف باقي المستخدمين والأشخاص حاملي العُهد من **الإعدادات**. وأول إيراد لكل شخص يبقى "رصيد افتتاحي" بمصدر "أخرى".

---

## 4) ملف `.env`

أهم المتغيرات في `backend/.env`:

| المتغير | الاستخدام |
|---|---|
| `APP_URL` | رابط السيرفر، مثلًا `https://mosque.example.com` |
| `APP_LOCALE=ar` | رسائل الأخطاء بالعربي |
| `APP_DEBUG` | لازم تبقى `false` على السيرفر الحقيقي |
| `DB_CONNECTION` | `sqlite` للتجربة، و`mysql` للتشغيل الفعلي |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` | إشعارات Push، ولّدهم بـ `php artisan push:keys` |
| `OPENSSL_CONF` | **ويندوز بس**: مسار `openssl.cnf`، مثلًا `'C:\Program Files\php\extras\ssl\openssl.cnf'` (بعلامات تنصيص مفردة) |
| `BACKUP_DISKS` | فين تتحفظ النسخ الاحتياطية (الافتراضي `backups` = `storage/app/backups`) |
| `BACKUP_PATH` | مسار تاني للنسخ، والأفضل يكون على هارد تاني، مثلًا `D:\mosque-backups` |
| `BACKUP_ARCHIVE_PASSWORD` | كلمة سر لتشفير ملف النسخة (اختياري لكن مستحسن) |
| `BACKUP_NOTIFY_EMAIL` | إيميل يوصله تنبيه لو النسخ الاحتياطي فشل (محتاج إعدادات `MAIL_*`) |
| `CORS_ALLOWED_ORIGINS` | لو الواجهة على دومين مختلف، مثلًا `https://app.example.com,capacitor://localhost` |
| `WHATSAPP_ENABLED` / `WHATSAPP_API_URL` / `WHATSAPP_API_TOKEN` | إشعارات واتساب، والكود جاهز لها لكنها مقفولة (شوف `app/Notifications/Channels/WhatsAppChannel.php`) |

وفي `frontend/.env` (انسخه من `.env.example`):

| المتغير | الاستخدام |
|---|---|
| `VITE_API_URL` | فاضي في التطوير. في الموبايل والديسكتوب: الرابط الكامل، مثلًا `https://mosque.example.com/api` |
| `VITE_ROUTER_MODE=hash` | لـ Electron بس |

---

## 5) النقل لـ MySQL

1. اعمل قاعدة بيانات بترميز `utf8mb4`:
   ```sql
   CREATE DATABASE mosque_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. عدّل `backend/.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=mosque_finance
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. شغّل `php artisan migrate --seed`، أو `migrate:fresh --seed` لو عايز البيانات التجريبية.

كل المبالغ متخزنة `decimal(12,2)`، والعمليات المالية بتقفل الصفوف (`lockForUpdate`) جوه transaction، وده بيشتغل كامل على MySQL/InnoDB.

---

## 6) المهام التلقائية (Scheduler)

| المهمة | الميعاد | بتعمل إيه |
|---|---|---|
| `recurring:run` | كل يوم 00:15 | تولّد "مستحق" لكل مصروف ثابت في الشهر، وتعلّم اللي ميعاده عدّى "متأخر" |
| `backup:clean` | كل يوم 01:00 | تمسح النسخ الاحتياطية القديمة |
| `backup:run` | كل يوم 01:30 | نسخة احتياطية لقاعدة البيانات والمرفقات |

عشان المهام دي تشتغل، لازم أمر واحد يتشغّل **كل دقيقة**:

**لينكس (cron):**
```cron
* * * * * cd /var/www/mosque/backend && php artisan schedule:run >> /dev/null 2>&1
```

**ويندوز (Task Scheduler):** اعمل Task جديدة بالإعدادات دي:
- **Trigger:** يومي، مع "Repeat task every 1 minute" لمدة "Indefinitely".
- **Action:** `php` مع الـ Arguments `artisan schedule:run`.
- **Start in:** مسار مجلد `backend`.

للتجربة اليدوية: `php artisan schedule:list` بيعرض المهام، و`php artisan recurring:run` بيشغّل المصروفات الثابتة فورًا. المهمتين دول آمنين لو اتشغّلوا أكتر من مرة.

---

## 7) النسخ الاحتياطي والاسترجاع

**إيه اللي بيتنسخ:**
- قاعدة البيانات: على MySQL بـ `mysqldump`، وعلى SQLite الملف نفسه بيتنسخ.
- كل المرفقات: الفواتير، وصور الإيصالات، والشعار، وده مجلد `storage/app/private`.

| | |
|---|---|
| **التشغيل اليدوي** | `php artisan backup:run` |
| **مكان النسخ** | `storage/app/backups/mosque-finance/` (أو `BACKUP_PATH`) |
| **مدة الحفظ** | الإعدادات الافتراضية بتحتفظ بكل النسخ 7 أيام، وبعدها نسخة يوميًا لمدة 16 يوم، ونسخة أسبوعيًا لمدة 8 أسابيع، وهكذا (`config/backup.php`) |

> **MySQL:** لازم `mysqldump` يبقى في الـ PATH. Laragon وXAMPP فيهم الأمر ده جاهز.
> **مهم:** انسخ مجلد النسخ لمكان تاني (هارد خارجي أو Google Drive) كل فترة. النسخة اللي على نفس الجهاز مش هتنفع لو الجهاز نفسه باظ.

**الاسترجاع:**
1. فك ضغط ملف النسخة (ولو ليه كلمة سر، استخدم `BACKUP_ARCHIVE_PASSWORD`).
2. **MySQL:** `mysql -u root mosque_finance < db-dumps/mysql-mosque_finance.sql` (بعد فك `.gz`).
   **SQLite:** حط `database.sqlite` مكان `backend/database/database.sqlite`.
3. انسخ محتويات مجلد `private` على `backend/storage/app/private`.

---

## 8) إشعارات Push

- **الإمام:** بيوصله إشعار لما يتعمل طلب صرف جديد.
- **صاحب الطلب:** بيوصله إشعار لما الطلب يتوافق عليه أو يترفض أو يرجع للتعديل.

**الإعداد على السيرفر (مرة واحدة):**
1. `php artisan push:keys`، وانسخ السطرين في `.env`.
2. **ويندوز:** ظبط `OPENSSL_CONF` (الجزء 4).

**على كل جهاز:** ادخل **الإشعارات ← تفعيل**.

> الـ Push محتاج **HTTPS** (أو `localhost` في التطوير). على آيفون محتاج iOS 16.4 أو أحدث، والتطبيق يكون **مثبّت على الشاشة الرئيسية** (Add to Home Screen).

الإشعارات بتتسجل كمان جوه التطبيق (جرس 🔔)، فلو الـ Push مش متاح محدش هيفوته حاجة.

**التجربة على موبايل حقيقي من غير سيرفر:** الرابط `http://192.168.x.x` مش هينفع، لأن المتصفح بيمنع الإشعارات على أي رابط مش HTTPS. اعمل الآتي:
1. `cd frontend && npm run build:laravel`
2. `cd backend && php artisan serve --port=8000`
3. شغّل `tools\mobile-https.cmd`، وهيطبعلك رابط `https://xxxx.trycloudflare.com`.
4. افتح الرابط من الموبايل، وثبّته على الشاشة الرئيسية، وادخل **الإشعارات ← تفعيل**، وبعدين **إشعار تجريبي**.

الرابط بيتغيّر كل مرة تشغّل فيها الأداة، والمستخدم لازم يفعّل الإشعارات تاني بعد كل تغيير. وهو متاح لأي حد يعرفه طول ما الشباك مفتوح، فاقفله لما تخلّص.

**لو الإشعار موصلش:** صفحة الإشعارات بتقولك السبب بالظبط. مثلًا: الرابط مش https، أو الآيفون لسه مش متثبّت عليه التطبيق، أو الإذن مرفوض من الإعدادات. وزرار "إشعار تجريبي" بيعرض نتيجة الإرسال لكل جهاز.

---

## 9) النشر على سيرفر حقيقي

```bash
# الواجهة تتبني جوه public بتاع Laravel ← سيرفر واحد
cd frontend
npm ci
npm run build:laravel

cd ../backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- خلّي الـ document root بتاع Apache أو Nginx هو `backend/public`. أي رابط مش ملف بيرجّع الواجهة تلقائيًا (`routes/web.php`).
- **Nginx:**
  ```nginx
  location / { try_files $uri $uri/ /index.php?$query_string; }
  ```
- **HTTPS إجباري** عشان الـ PWA والإشعارات والكاميرا يشتغلوا. استخدم Let's Encrypt.
- ظبط الـ Scheduler (الجزء 6).
- `APP_DEBUG=false` و`APP_ENV=production`.
- اقفل الشهور بعد مراجعتها من **الإعدادات ← بيانات المسجد والنظام ← إقفال الشهور**.

**تثبيت التطبيق:** على الموبايل افتح الموقع من Chrome، ومن القائمة اختار "إضافة للشاشة الرئيسية". على الديسكتوب هتلاقي أيقونة تثبيت في شريط العنوان.

---

## 10) تطبيق موبايل وديسكتوب

الواجهة جاهزة للتغليف من غير تعديل:

**Capacitor (أندرويد وآيفون):**
```bash
cd frontend
echo "VITE_API_URL=https://mosque.example.com/api" > .env.production.local
npm run build
npm i @capacitor/core @capacitor/cli @capacitor/android
npx cap init "حسابات المسجد" com.mosque.finance --web-dir dist
npx cap add android && npx cap sync && npx cap open android
```
وضيف `capacitor://localhost` و`http://localhost` في `CORS_ALLOWED_ORIGINS` (أو سيبها `*`).

**Electron (ديسكتوب):** ابني بـ `VITE_ROUTER_MODE=hash` و`VITE_API_URL` الكامل، وحمّل `dist/index.html` في `BrowserWindow`.

**الخطوط:** الخطوط والأيقونات جوه التطبيق (`public/fonts`) وبتشتغل أوفلاين. لو استخدمت أيقونة Material Symbols جديدة، ضيفها في `frontend/scripts/fetch-fonts.sh` وشغّل `npm run fonts`، وإلا هتظهر كاسم مكتوب.

---

## 11) قواعد العمل المهمة

- **الرصيد = مجموع حركات الشخص في `ledger_entries`.** ده المرجع الوحيد، و`balance_cache` مجرد تسريع.
- **الرصيد المتاح للصرف = الرصيد الحالي − الطلبات المعتمدة اللي لسه متنفذتش (محجوز).**
- **مفيش أي خصم قبل "تم التنفيذ".** الموافقة بتحجز المبلغ بس. والمبلغ الفعلي ممكن يقل عن المعتمد، لكن مينفعش يزيد عنه، والفرق محتاج طلب جديد.
- **الموافقة والرفض والإرجاع للإمام بس**، ومش جوه جدول الصلاحيات القابل للتعديل.
  - **طلب الإمام نفسه:** موافقة تلقائية متسجّلة، أو موافقة المحاسب، حسب الإعداد.
- **مفيش حذف لأي عملية مالية.** التصحيح بيتم بـ **قيد عكسي** بسبب، والعملية الأصلية بتفضل ظاهرة ومتعلّم عليها.
- **إقفال الشهر:** محدش يقدر يسجّل أو يعدّل بتاريخ جوّاه. الإمام والمحاسب بس اللي يقفلوا ويفتحوا.
- **سجل التدقيق:** أي إضافة أو تعديل بيتسجل بالمستخدم والوقت والقيم قبل وبعد. شوفه من **التقارير ← سجل التعديلات**.
- **المرفقات:** متخزنة في مكان خاص، وبتتفتح بروابط موقّعة صالحة ساعة بس. الصور بتتصغّر وتتضغط تلقائيًا.

---

## 12) الاختبارات

```bash
cd backend && php artisan test
```

الاختبارات بتغطي:
- حساب الرصيد والحجز.
- منع الصرف بأكتر من الرصيد.
- الموافقة للإمام بس.
- التحويل بين الأشخاص.
- القيد العكسي.
- إقفال الفترة.
- دورة الطلب الكاملة بالفاتورة.
- المصروفات الثابتة.
- كشف الحساب.
- التقارير والتصدير.
- الإشعارات وPush.

---

## 13) هيكل الكود

```
backend/
  app/Services/          ← كل منطق العمل هنا (Ledger, Income, ExpenseRequest, Transfer, Recurring, Report, Statement...)
  app/Http/Controllers/Api/  ← Controllers رفيعة بتنادي الـ Services
  app/Policies/          ← صلاحيات طلب الصرف
  app/Notifications/     ← الإشعارات + قنوات Push وواتساب
  app/Exports/           ← تصدير Excel
  resources/views/pdf/   ← قوالب PDF (الإيصال، والتقارير)
  database/seeders/      ← الأدوار، والقوائم، والبيانات التجريبية
  tests/Feature/         ← الاختبارات
frontend/
  src/pages/             ← الشاشات
  src/components/        ← MoneyAmount, PersonCard, StatusBadge, RequestTimeline, TileSelect, AppDrawer, BottomSheet, DataTable...
  src/composables/       ← إجراءات الطلبات، والـ Push، والأزرار السريعة
  src/sw.js              ← Service Worker (أوفلاين + Push)
  tailwind.config.js     ← ألوان وخطوط Stitch
```

### الـ API باختصار

```
POST /api/auth/login · GET /api/auth/me · POST /api/auth/logout
GET  /api/dashboard · GET /api/meta
GET|POST /api/incomes · GET /api/incomes/{id}/receipt?format=a5|thermal · POST /api/incomes/{id}/reverse
GET|POST /api/expense-requests · GET /api/expense-requests/{id}
POST /api/expense-requests/{id}/approve|reject|return|execute|cancel|reverse|update
GET|POST /api/transfers · POST /api/transfers/{id}/reverse
GET /api/custodians · GET /api/custodians/{id}/statement?from&to&format=json|pdf|xlsx
GET|POST|PUT /api/recurring-expenses · POST /api/recurring-expenses/{id}/create-request
GET /api/reports/{monthly|expenses-by-category|incomes-by-source|balances|approvals|audit}?from&to&format=json|pdf|xlsx
GET /api/notifications · POST /api/notifications/{id}/read · POST /api/push/subscribe
GET|PUT /api/settings · /api/users · /api/permissions · /api/lookups/{type} · /api/period-locks
```
