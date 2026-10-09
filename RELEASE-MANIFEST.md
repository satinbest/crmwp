# Release Manifest: سامانه مدیریت و CRM ووکامرس (CRMWP)

## مشخصات بسته انتشار پروداکشن (Production Release Package)

* **Application Name**: WooCommerce Management & CRM Platform (`satinbest/crmwp`)
* **Application Version**: `1.1.0 (Production Stable)`
* **Release Date**: ۰۶ اکتبر ۲۰۲۶ (۱۴۰۵/۰۷/۱۵) / 2026-10-06
* **Git Tag**: `v1.1.0`
* **Git Commit**: `44c9cc82b331f34b1bd6f0803b5ebdf84016f931` (Head of `main`)
* **Build Status**: `PASSED` (تمامی تست‌های امنیتی، استقرار، سلامت، داشبورد و ارتباط API ۱۰۰٪ موفق)
* **Security Status**: `PASSED` (اسکن امنیتی جامع انجام شد؛ هیچ کلید، سکرت، توکن یا اطلاعات محرمانه در پکیج وجود ندارد)
* **Archive File**: `CRM-Production-Release-1.1.0.zip`
* **Workspace Archive**: `CRM-Production-Release-1.1.0.zip`
* **Release Folder**: `release/`
* **SHA-256 Checksum**: `2459faaa29a3913de184892351f92e9d5d4d4a17daa2894cf8f230934e24baeb`
* **WooCommerce Compatibility**: ووکامرس نسخه 7.x, 8.x, 9.x و بالاتر (WooCommerce REST API v3)
* **HPOS Compatibility**: پشتیبانی کامل و تشخیص خودکار (High-Performance Order Storage Auto-Detection)
* **Database Migration Version**: ۱۹ مایگریشن ساختار پایگاه داده (`app/Database/Migrations/`)
* **PHP Requirements**: حداقل `PHP 8.2.0` (تست‌شده روی PHP 8.2, 8.3, 8.4)
* **MariaDB / MySQL Requirements**: MariaDB 10.6+ / 11.x یا MySQL 8.0+ با Charset `utf8mb4` و Collation `utf8mb4_unicode_ci` (موتور InnoDB با پشتیبانی Foreign Keys و Transactions)
* **Required PHP Extensions**:
  * `pdo` و `pdo_mysql`: ارتباط امن و پایدار با دیتابیس
  * `openssl`: رمزنگاری متقارن AES-256-CBC کلیدهای اتصال و سکرت‌ها
  * `mbstring`: پردازش و استانداردسازی متون و کاراکترهای یونیکد فارسی
  * `curl`: برقراری اتصالات HTTPS پایدار با فروشگاه‌های ووکامرس
  * `json`: اعتبارسنجی و تبدیل Payloadهای ساختاریافته
  * `session`: مدیریت نشست‌های امن با فلگ‌های ایزوله (HttpOnly, SameSite=Lax/Strict)
  * `fileinfo`: اعتبارسنجی امن فایل‌های آپلودی
* **Optional / Recommended PHP Extensions**:
  * `memcached` یا `memcache`: اتصال کش شیء توزیع‌شده (TCP / Unix Socket)
  * `apcu`: شتاب‌دهنده حافظه محلی
  * `zip`: پشتیبان‌گیری و بازگشایی بسته‌ها
* **Production Runtime Requirements**: وب‌سرور استاندارد (Apache 2.4+ / Nginx / LiteSpeed) با PHP-FPM
* **Installation Entry Point & Notes**: نصب‌کننده تحت وب خودکار: `http://your-domain.com/install` یا کنسول CLI: `php cli.php install`
* **Cron Requirements**: اجرای دوره‌ای پس‌زمینه هر ۱ الی ۵ دقیقه از طریق CLI: `php cron.php` یا وب‌کرون ایمن: `GET /api/v1/system/cron?secret=YOUR_CRON_SECRET`
* **Storage Requirements**: مجوز نوشتن وب‌سرور (`chmod 775` یا `755`) روی دایرکتوری `storage/` و زیرپوشه‌های `cache`, `logs`, `uploads`, `locks`, `temp`
* **Known Limitations**:
  * حداقل نسخه PHP مورد نیاز 8.2 است (نسخه‌های قدیمی‌تر PHP 7.x یا 8.1 پشتیبانی نمی‌شوند).
  * نیازمند فعال بودن ماژول Rewrite آپاچی یا تنظیم معادل آن در Nginx/LiteSpeed جهت هدایت درخواست‌ها به `public/`.
  * دایرکتوری `storage/` باید دسترسی نوشتن برای کاربر وب‌سرور داشته باشد.
  * برای اجرای بلادرنگ اتوماسیون‌ها و همگام‌سازی، اجرای منظم کران‌جاب سیستمی الزامی است.
* **Security & Confidentiality Notes**:
  - هیچ داده‌ای از فروشگاه‌های پیشین یا پروداکشن در بسته قرار ندارد.
  - فرانت‌اند و دارایی‌های بصری (فونت Vazirmatn و آیکون‌ها) ۱۰۰٪ ایزوله و محلی هستند (بدون CDN و بدون فونت خارجی).
  - استفاده از هرگونه `font-mono` در طراحی حذف شده و فونت استاندارد با اعداد فارسی بومی فعال است.
  - اطلاعات حساس حمایت مالی (شماره کارت بانکی و مشخصات) فقط در پیکربندی محرمانه بک‌اند مدیریت می‌شود و هیچ شماره کارتی در مخزن گیت یا سورس استاتیک قرار ندارد.
  - هیچ درخواستی به Gravatar یا سرویس‌های خارجی فرستاده نمی‌شود؛ سیستم آواتار داخلی با نماد بومی `ProfileCircle` فعال است.

---

## تغییرات عمده و قابلیت‌های جدید نسخه ۱.۱.۰ (Major Features & Improvements)

### ۱. بازطراحی کامل داشبورد مدیریتی ووکامرس
- تبدیل داشبورد از یک صفحه معماری به **مرکز عملیاتی و زنده فروشگاه ووکامرس**.
- کارت‌های شاخص کلیدی فروش، سفارش‌ها، مشتریان و محصولات با فیلترهای بازه زمانی و محاسبات زنده.
- کارت‌های پیگیری فوری سفارش‌های در انتظار، در حال پردازش، تکمیل‌شده و هشدار اقلام کم‌موجودی انبار.
- نمودار تعاملی روند فروش روزانه همراه با برچسب‌های مبلغ و تعداد سفارش‌ها.
- نمودار توزیع وضعیت سفارش‌ها، تب‌های مشتریان جدید و مشتریان برتر، و فید رخدادهای زنده فروشگاه و CRM.
- تقویم روز شمسی و ساعت زنده هماهنگ با تایم‌زون فروشگاه (`Asia/Tehran`).

### ۲. سیستم راهنمای تعاملی و متمرکز بخش‌ها (Help System)
- دکمه‌های ظریف راهنما (`HelpButton`) در تمامی ویجت‌ها و کارت‌های داشبورد با آیکون محلی `info-circle`.
- ساختار متمرکز داده‌های راهنما در [resources/js/config/helpContent.js](file:///d:/crmwp/resources/js/config/helpContent.js) با توضیحات عملیاتی پیرامون عملکرد هر بخش، منبع داده‌ها و نحوه استفاده.
- پاپ‌اور پیشرفته با قابلیت انتقال مستقیم به `document.body` (Teleport) جهت جلوگیری از برش توسط لایه‌های دارای `overflow-hidden`.
- محاسبه هوشمند کادر صفحه (Viewport) جهت جلوگیری از خروج در موبایل و تبلت، با پشتیبانی از کلید `Escape` و کلیک خارج.

### ۳. بازطراحی کامل سیستم حمایت مالی اختیاری از توسعه‌دهنده (Donate Modal Redesign)
- دکمه مینیمال و جذاب «♡ حمایت» در هدر سامانه و در مجاورت مرکز اعلان‌ها (`[Donate] [Notifications]`).
- بازطراحی کامل و مدرن پنجره حمایت به سبک رابط‌های پرمیوم و تمیز SaaS با کانتینر آیکون برجسته قلب Iconsax بدون استفاده از ایموجی.
- کارت تفکیک‌شده اطلاعات پرداخت (`Donation Information Card`) با آیکون کارت بانکی Iconsax.
- فرمت‌بندی ۴ رقمی فارسی شماره کارت (`۶۲۱۹   ۸۶۱۹   ۳۱۹۶   ۵۴۰۳`) با تایپوگرافی استاندارد وزیرمتن و **عدم استفاده از `font-mono`**.
- دکمه کپی تعاملی با تغییر وضعیت به «کپی شد» و آیکون تیک موفقیت (`copy-success`) همراه با نوتیفیکیشن Toast محلی و کپی مقدار خام ۱۶ رقمی در کلیپ‌بورد.
- پیوندهای کلیک‌پذیر ایمیل و گیت‌هاب رسمی پروژه (`satinbest/crmwp`) با `rel="noopener noreferrer"`.
- دریافت امن داده‌ها منحصراً از نقطه انتهایی بک‌اند (`GET /api/v1/system/donate`) به عنوان منبع حقیقت، بدون هرگونه هاردکد در سورس فرانت‌اند یا گیت.
- دسترسی‌پذیری کامل: کلید `Escape`، کلیک روی پس‌زمینه، کنترل فوکوس کیبورد، قفل اسکرول بدنه (`body overflow-hidden`) و هماهنگی کامل با حالت‌های تاریک و روشن.
- پیکربندی آسان از طریق متغیرهای محیطی در فایل سرور:
  ```ini
  DONATE_RECIPIENT_NAME="کمک مالی به حسین محمدپور"
  DONATE_CARD_NUMBER="6219861931965403"
  DONATE_EMAIL="info@hosseinmohammadpour.ir"
  DONATE_GITHUB="satinbest/crmwp"
  ```

### ۴. بهینه‌سازی و پولیش هدر سامانه
- حذف خط عمودی مزاحم (Border Divider) کنار آواتار و نام کاربر.
- هماهنگ‌سازی فواصل، اندازه آیکون‌ها و ترازبندی عمودی المان‌ها.
- ایزولاسیون کامل آواتار با استفاده از نمادهای وکتور داخلی پروژه بدون هرگونه وابستگی به Gravatar.

### ۵. کش شیء (Object Cache) و پایداری شبکه
- پشتیبانی کامل از اتصالات TCP و Unix Domain Socket در Memcached.
- تست واقعی اتصال شامل چرخه ۴ مرحله‌ای: `Set -> Get -> Verify -> Delete`.
- محاسبه صحیح و دقیق ضریب اصابت (Hit Ratio) با وضعیت «بدون داده» برای مواردی که هنوز ترددی ثبت نشده است.
- استقلال کامل کش فایل و کش شیء.

### ۶. پایگاه داده محلی (Local Cache)، همگام‌سازی و Bulk Price Safety
- **Local Cache & MariaDB Ingestion**: ایجاد جداول `wc_local_products`, `wc_local_orders`, `wc_local_customers`, `wc_local_categories` و `wc_local_sync_meta` با ایزولاسیون سخت‌گیرانه فروشگاه‌ها (Foreign Keys + Cascade + Store Boundary) و حذف درخواست‌های زائد و تکراری به ووکامرس.
- **Sync Manager کنترل‌شده**: مدیریت وضعیت همگام‌سازی، قفل‌های همزمانی ۱۵ دقیقه‌ای با قابلیت بازیابی خودکار کرش، سازگاری ۱۰۰٪ با هاست اشتراکی بدون تحمیل ردیس یا صف‌های اجباری.
- **دکمه همگام‌سازی دستی (`SyncButton`)**: در صفحات داشبورد، محصولات، سفارش‌ها و مشتریان همراه با هشدار داده‌های قدیمی (Stale Data)، وضعیت پیشرفت و بازیابی از حالت خالی (Empty State).
- **انتخاب هم‌زمان چند دسته‌بندی در عملیات گروهی**: مولتی‌سلکت واکنش‌گرا با جستجو، انتخاب همه، پاک‌سازی و حذف تکرار کالاهای همپوشان در بک‌اند و فرانت‌اند.
- **نسخه پشتیبان JSON الزامی پیش از تغییر قیمت**: ایجاد فایل ساختاریافته `crm-price-backup-[operation-id]-[timestamp].json` با نگهداری دقیق اعداد اعشاری به شکل رشته، تفکیک قیمت خالی از صفر، اسکیما ۱.۰ و هش چک‌سام SHA-256. توقف قطعی عملیات در صورت عدم دریافت حتی یک کالا.
- **تأیید واقعی قیمت‌ها پس از تغییر (Real Verification)**: بازخوانی مجدد قیمت‌ها از ووکامرس پس از اتمام هر بچ و مقایسه با قیمت مورد انتظار؛ گزارش دقیق موارد تأیید شده، ناموفق و دارای مغایرت.
- **بازگردانی امن قیمت‌ها از JSON (Price Restore)**: آپلود فایل، اعتبارسنجی ساختار و هش امنیتی، مقایسه زنده با قیمت‌های جاری ووکامرس، پیش‌نمایش تفاوت‌ها، قابلیت صرف‌نظر از مغایرت‌های جدید، تأیید صریح کاربر و بازخوانی و تأیید مجدد پس از Restore.

---

## متغیرهای محیطی مورد نیاز (Environment Variables Template)

```ini
# Application
APP_NAME="WooCommerce Management & CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.yourdomain.com
APP_SECRET=strong_random_secret_at_least_32_characters
ENCRYPTION_KEY=64_character_hex_encryption_key
CRON_SECRET=strong_random_secret_for_cron
APP_VERSION=1.1.0

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD="your_db_password"
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Session
SESSION_LIFETIME=7200
SESSION_SECURE=true
SESSION_SAME_SITE=Lax

# Localization
TIMEZONE=Asia/Tehran
LOCALE=fa

# Voluntary Developer Donation (Optional)
DONATE_RECIPIENT_NAME="کمک مالی به حسین محمدپور"
DONATE_CARD_NUMBER="6219861931965403"
DONATE_EMAIL="info@hosseinmohammadpour.ir"
DONATE_GITHUB="satinbest/crmwp"
```

---

## ضمانت‌های محرمانگی و عدم وابستگی‌های سرور (Production Guarantees)

```text
Production Credentials: NOT INCLUDED
Production Database: NOT INCLUDED
Production Logs: NOT INCLUDED
Development Dependencies: NOT REQUIRED AT RUNTIME
Node.js Runtime: NOT REQUIRED
npm Runtime: NOT REQUIRED
Composer Runtime: NOT REQUIRED
```

---

## ساختار بسته نهایی (Release Contents)

```text
CRM-Production-Release-1.1.0.zip
├── .htaccess                     # هدایت درخواست‌ها به public/ و محافظت از فایل‌های محرمانه
├── .env.example                  # الگوی متغیرهای محیطی پروداکشن (فاقد هرگونه اطلاعات واقعی)
├── cron.php                      # ورودی اجرای دوره‌ای پس‌زمینه (سازگار با CLI و وب‌توکن)
├── cli.php                       # ابزار مدیریتی خط فرمان (مخصوص سرورهای دارای SSH)
├── README.md                     # مستندات جامع سامانه
├── INSTALL.md                    # راهنمای قدم‌به‌قدم نصب و راه‌اندازی
├── UPGRADE.md                    # راهنمای ارتقا و به‌روزرسانی
├── SECURITY.md                   # مستندات و استانداردهای امنیتی
├── RELEASE-MANIFEST.md           # همین مانیفست تحویل
├── RELEASE_MANIFEST.json         # متادیتای ساختاریافته بسته
├── CHANGELOG.md                  # گزارش تغییرات تفصیلی نسخه‌ها
├── DEPLOYMENT.md                 # راهنمای استقرار در سرور و هاست
├── BACKUP.md                     # راهنمای پشتیبان‌گیری
├── BACKUP_RESTORE.md             # راهنمای بازیابی اضطراری
├── PRODUCTION_CHECKLIST.md       # چک‌لیست قبل و بعد از راه‌اندازی
├── app/                          # کنترلرها، سرویس‌ها، مدل‌ها و پایگاه داده
│   └── Database/
│       ├── Migrations/           # ۱۹ مایگریشن ساختار دیتابیس
│       └── Seeders/              # اطلاعات پایه نقش‌ها و دسترسی‌ها (بدون اطلاعات پروداکشن)
├── config/                       # پیکربندی‌های سامانه (app, database, auth, cors, ...)
├── routes/                       # تعاریف مسیرهای API و وب
├── public/                       # ریشه عمومی وب‌سرور (Document Root)
│   ├── index.php                 # نقطه ورود درخواست‌ها
│   ├── .htaccess                 # قوانین Rewrite و امنیت
│   └── assets/                   # فایل‌های کامپایل‌شده JS/CSS و فونت‌های WOFF2
├── storage/                      # پوشه‌های ذخیره‌سازی داده‌های موقت با فایل‌های محافظت
│   ├── cache/                    # کش سریع فایل‌ها
│   ├── logs/                     # گزارش‌های خطای سامانه
│   ├── uploads/                  # فایل‌های بارگذاری‌شده
│   ├── locks/                    # قفل‌های کرون و تسک‌های پس‌زمینه
│   └── temp/                     # فایل‌های موقت
└── vendor/                       # کلیه کتابخانه‌های PHP مورد نیاز Runtime (آماده اجرا بدون composer)
```

---

## اقلام حذف‌شده و استثنا شده از انتشار (Exclusions)

* `.git/` و تاریخچه مخزن
* `node_modules/` و پکیج‌های توسعه‌ای Node
* `tests/` و اسکریپت‌های تست واحد و یکپارچگی
* `.env` حاوی اطلاعات محلی یا اسرار سرور توسعه
* `storage/installed.lock` (امکان اجرای Web Installer روی هاست جدید)
* لاگ‌ها و فایل‌های موقت (`storage/logs/*.log`, `storage/cache/*`, `storage/framework/cache/*`)
* فایل‌های ابزاری توسعه و اسکریپت‌های موقت (`scratch/`)

---

## چک‌لیست استقرار نهایی (Production Checklist)

1. [x] بیلد فرانت‌اند و استایل‌ها با Vite به شکل بهینه و مینیمایز شده در `public/assets/`.
2. [x] کلیه فونت‌های وزیرمتن (۹ وزن استاندارد) به صورت WOFF2 محلی قرار دارند.
3. [x] آیکون‌ها از Iconsax محلی بارگذاری می‌شوند.
4. [x] هیچ تماسی با Gravatar یا CDNهای خارجی در شبکه مرورگر ثبت نمی‌شود.
5. [x] شماره کارت و اطلاعات Donate به شکل امن و از طریق بک‌اند کنترل می‌شود.
6. [x] خط عمودی اضافی کنار آواتار در هدر به طور کامل حذف شده است.
7. [x] سیستم Help برای تمامی بخش‌های داشبورد پیاده‌سازی شده و عملکرد آن تأیید شده است.
8. [x] ماژول کش شیء اتصالات TCP و Unix Socket را با تست واقعی پشتیبانی می‌کند.
9. [x] هیچ سکرت یا کلید معتبر توسعه در ریپازیتوری یا بسته انتشار باقی نمانده است.
10. [x] فایل انتشار ZIP با ساختار مستقل و استاندارد آماده بهره‌برداری است.
