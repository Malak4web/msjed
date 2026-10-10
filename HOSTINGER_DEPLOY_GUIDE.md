# دليل النشر التلقائي على هوستنجر (Hostinger Shared Hosting) عبر GitHub Actions

تم إعداد ملف الـ CI/CD في المسار:
[`.github/workflows/deploy.yml`](file:///e:/Amr/msjed/.github/workflows/deploy.yml)

يقوم الـ Workflow بالآتي تلقائيًا عند كل `git push` لفرع `main` (أو عند الضغط على **Run workflow** يدويًا):
1. تنزيل الكود وعمل بيلد للواجهة (`frontend`) باستخدام Node 20 وأمر `npm run build:laravel` (الملفات الناتجة تذهب مباشرة إلى `backend/public`).
2. تجهيز بيئة PHP 8.3 مع جميع الإضافات المطلوبة، وعمل `composer install --no-dev --optimize-autoloader`.
3. الاتصال بسيرفر هوستنجر عبر **SSH** باستخدام الـ IP واسم المستخدم وكلمة المرور.
4. مزامنة ملفات المشروع الجاهزة عبر `rsync` مع استثناء وحماية الملفات الحساسة (`.env` وقواعد البيانات ومجلد المرفقات والفواتير والنسخ الاحتياطية).
5. تشغيل أوامر Laravel على السيرفر: الميجريشن (`php artisan migrate --force`)، ربط مجلد التخزين (`storage:link`)، وتفعيل الكاش للـ Config والـ Routes والـ Views لأفضل سرعة.

---

## 1. الحصول على بيانات SSH من هوستنجر (Hostinger)

1. ادخل على لوحة تحكم هوستنجر (**hPanel**).
2. اختر موقعك ثم توجه إلى **Advanced (متقدم)** ← **SSH Access (الوصول إلى SSH)**.
3. تأكد أن حالة الـ SSH هي **Enabled (مفعّل)**.
4. ستجد أمامك البيانات التالية:
   - **SSH IP:** عنوان الآي بي الخاص بالسيرفر.
   - **SSH Port:** في هوستنجر المشترك غالبًا يكون **`65002`** (وليس 22).
   - **SSH Username:** اسم المستخدم (يبدأ عادةً بـ `u` متبوعًا بأرقام، مثل `u123456789`).
   - **Password:** كلمة المرور الخاصة بحساب SSH (يمكنك تعيينها أو تغييرها من نفس الصفحة).

---

## 2. إضافة الـ Secrets في GitHub

ادخل إلى مستودع المشروع على GitHub ثم:
**Settings** ← **Secrets and variables** ← **Actions** ← اضغط **New repository secret** وقم بإضافة المتغيرات الآتية:

| اسم الـ Secret | القيمة المطلوبة | مثال |
|---|---|---|
| `SSH_HOST` | عنوان IP السيرفر من هوستنجر | `185.123.45.67` |
| `SSH_USERNAME` | اسم مستخدم SSH | `u123456789` |
| `SSH_PASSWORD` | كلمة سر SSH | `YourStrongPass123` |
| `SSH_PORT` | المنفذ (اختياري، مبرمج افتراضيًا على 65002) | `65002` |
| `SSH_TARGET_DIR` | المسار الكامل لمجلد المشروع على السيرفر | `/home/u123456789/msjed` أو `/home/u123456789/domains/domain.com/public_html` |

> 💡 **ملاحظة لمعرفة المسار الكامل (`SSH_TARGET_DIR`):**
> عند الاتصال بـ SSH عبر Terminal واكتب أمر `pwd` لمعرفة مسارك الرئيسي، والذي يكون عادةً:
> `/home/u123456789/domains/اسم-موقعك.com/public_html`

---

## 3. خيارات تنظيم المجلدات على هوستنجر

### الخيار الأول (الأفضل والأكثر أمانًا - يُنصح به):
1. ارفع المشروع إلى مجلد خارج `public_html`، مثلاً:
   `SSH_TARGET_DIR = /home/u123456789/domains/yourdomain.com/msjed`
2. افتح الـ SSH مرة واحدة واحذف مجلد `public_html` الفارغ واعمل رابط رمزي (Symlink) يشير إلى `public`:
   ```bash
   cd /home/u123456789/domains/yourdomain.com
   rm -rf public_html
   ln -s msjed/public public_html
   ```
   *بذلك يكون كود التطبيق وملف `.env` محمي تمامًا خارج متناول المتصفح.*

### الخيار الثاني (الرفع المباشر داخل `public_html`):
إذا فضلت وضع المشروع داخل `public_html` مباشرة:
`SSH_TARGET_DIR = /home/u123456789/domains/yourdomain.com/public_html`
- قمنا بإضافة ملف [`.htaccess`](file:///e:/Amr/msjed/backend/.htaccess) في جذر الـ backend ليقوم تلقائيًا بتحويل الطلبات إلى مجلد `public` ومنع الوصول المباشر إلى ملفات الكود و`.env`.

---

## 4. إعدادات هامة في لوحة هوستنجر

1. **إصدار PHP:**
   - توجه في hPanel إلى **Advanced** ← **PHP Configuration**.
   - اختر **PHP 8.3** (حيث أن Laravel 13 يتطلب PHP 8.3 على الأقل) واضغط **Save**.

2. **قاعدة البيانات (MySQL):**
   - أنشئ قاعدة بيانات ومستخدم وكلمة سر من قسم **Databases** ← **Management**.
   - ادخل على الـ File Manager أو عبر SSH وعدل ملف `.env` في مجلد المشروع وضع بيانات القاعدة:
     ```env
     APP_ENV=production
     APP_DEBUG=false
     APP_URL=https://yourdomain.com

     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=u123456789_mosque
     DB_USERNAME=u123456789_admin
     DB_PASSWORD=YourDatabasePassword
     ```

3. **المهام التلقائية (Cron Job للـ Scheduler):**
   - في hPanel اذهب إلى **Advanced** ← **Cron Jobs**.
   - اختر النوع: **Custom**.
   - التوقيت: كل دقيقة (`* * * * *`).
   - الأمر (Command):
     ```bash
     cd /home/u123456789/domains/yourdomain.com/public_html && php artisan schedule:run >> /dev/null 2>&1
     ```
     *(مع تعديل المسار لمسار مشروعك الفعلي)*.

---

## 5. اختبار التشغيل لأول مرة

1. بعد عمل Commit و Push للتعديلات إلى GitHub:
   ```bash
   git add .
   git commit -m "Add GitHub Actions CI/CD deployment workflow for Hostinger"
   git push origin main
   ```
2. يمكنك التوجه إلى تبويب **Actions** في GitHub لمشاهدة الخطوات في الوقت الفعلي والتأكد من نجاح الرفع والتشغيل.
