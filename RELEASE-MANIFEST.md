# Release Manifest: سامانه مدیریت و CRM ووکامرس (CRMWP)

## مشخصات بسته انتشار پروداکشن (Production Release Package)

* **Application Version**: `1.0.0 (Production Stable)`
* **Release Date**: ۰۳ اکتبر ۲۰۲۶ (۱۴۰۵/۰۷/۱۲)
* **Build Status**: `PASSED` (تمامی تست‌های امنیتی، استقرار، مایگریشن و عملیات گروهی ۱۰۰٪ موفق)
* **Archive File**: `CRM-Production-Release-v1.0.0.zip`
* **Release Folder**: `release/`
* **Database Migration Version**: ۱۸ مایگریشن کامل (`001_create_roles_and_permissions_tables.php` تا `018_create_phase15_automations_tables.php`)
* **PHP Requirements**: حداقل `PHP 8.2.0` (تست‌شده روی PHP 8.2, 8.3, 8.4)
* **MariaDB Requirements**: MariaDB 10.6+ / 11.x یا MySQL 8.0+ با Charset `utf8mb4` و Collation `utf8mb4_unicode_ci` (موتور InnoDB با پشتیبانی Foreign Keys و Transactions)
* **Required PHP Extensions**:
  * `pdo` و `pdo_mysql`: ارتباط امن و پایدار با دیتابیس
  * `openssl`: رمزنگاری متقارن AES-256-CBC کلیدهای اتصال و سکرت‌ها
  * `mbstring`: پردازش و استانداردسازی متون و کاراکترهای یونیکد فارسی
  * `curl`: برقراری اتصالات HTTPS پایدار با فروشگاه‌های ووکامرس
  * `json`: اعتبارسنجی و تبدیل Payloadهای ساختاریافته
  * `session`: مدیریت نشست‌های امن با فلگ‌های ایزوله (HttpOnly, SameSite=Lax/Strict)
  * `fileinfo`: اعتبارسنجی امن فایل‌های آپلودی
* **Production Runtime Requirements**: وب‌سرور استاندارد (Apache 2.4+ / Nginx / LiteSpeed) با PHP-FPM
* **Installation Entry Point**: نصب‌کننده تحت وب: `http://your-domain.com/install` یا کنسول CLI: `php cli.php install`
* **Cron Requirements**: اجرای دوره‌ای پس‌زمینه هر ۱ الی ۵ دقیقه از طریق CLI: `php cron.php` یا وب‌کرون ایمن: `GET /api/v1/system/cron?secret=YOUR_CRON_SECRET`
* **Storage Requirements**: مجوز نوشتن وب‌سرور (`chmod 775` یا `755`) روی دایرکتوری `storage/` و زیرپوشه‌های `cache`, `logs`, `uploads`, `locks`, `temp`
* **Known Limitations**: برای عملیات گروهی سنگین روی بیش از ۱۰۰۰ قلم کالا، توصیه می‌شود `memory_limit` در PHP حداقل `256M` یا `512M` و `max_execution_time` حداقل ۱۲۰ ثانیه باشد.
* **Security Notes**:
  - هیچ داده‌ای از فروشگاه‌های پیشین یا پروداکشن در بسته قرار ندارد.
  - فرانت‌اند و دارایی‌های بصری (فونت Vazirmatn و آیکون‌ها) ۱۰۰٪ ایزوله و محلی هستند (بدون CDN و بدون فونت خارجی).
  - استفاده از هرگونه `font-mono` در طراحی حذف شده و فونت استاندارد با اعداد فارسی بومی فعال است.
  - معماری لایه‌بندی Z-Index مرکزی پیاده‌سازی شده و بخش اعلانات روی هدر و تمامی محتواها بدون تداخل نمایش داده می‌شود.

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

## جدول وضعیت وابستگی‌های زمان اجرا (Runtime Dependencies)

| وابستگی | وضعیت در پروداکشن | توضیح فنی |
| :--- | :--- | :--- |
| **Node.js / npm** | ❌ غیرضروری (Zero Runtime Dependency) | تمامی مراحل ساخت فرانت‌اند توسط Vite در مرحله بیلد انجام شده و خروجی در `public/assets/` قرار گرفته است. |
| **Composer** | ❌ غیرضروری در سرور | پوشه `vendor/` با کلاس‌مپ بهینه‌شده تولید شده و همراه بسته عرضه می‌شود. |
| **Redis / Memcached** | ❌ غیرضروری | موتور کش داخلی سریع و مبتنی بر فایل در مسیر `storage/cache/` فعال است. |
| **Docker / Supervisor** | ❌ غیرضروری | سامانه سبک و بدون نیاز به پردازشگر دائمی اجرا می‌شود. |
| **دسترسی SSH / CLI** | ❌ اختیاری (غیرضروری) | راه‌اندازی از طریق مرورگر (Web Installer) و وب‌کرون کاملاً پشتیبانی می‌شود. |
| **CDN یا اینترنت خارجی برای UI** | ❌ کاملاً ایزوله (100% Offline) | فونت Vazirmatn و آیکون‌های وکتور محلی هستند. |

---

## مراحل ساخت و کامپایل بسته (Build Pipeline)

۱. **کامپایل فرانت‌اند (Frontend Build)**:
   ```bash
   npm run build
   ```
   * ایجاد ۹ وزن فونت استاندارد Vazirmatn در فرمت بهینه `woff2` داخل `public/assets/`.
   * خروجی فشرده و تفکیک‌شده به چانک‌های مجزا بدون Source Mapهای حساس.
   * حذف کامل کلاس‌های `font-mono` و استانداردسازی تایپوگرافی با اعداد طبیعی فارسی.

۲. **بهینه‌سازی Autoload بک‌اند**:
   ```bash
   composer dump-autoload -o --no-dev
   ```
   * تولید کلاس‌مپ بهینه از کلیه کلاس‌های پروژه.

۳. **تولید بسته انتشار نهایی**:
   ```bash
   php build_release.php
   ```
   * بسته‌بندی در قالب فایل `CRM-Production-Release-v1.0.0.zip`.

---

## ساختار بسته نهایی (Release Contents)

```text
CRM-Production-Release-v1.0.0.zip
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
├── RELEASE_NOTES.md              # یادداشت‌های تغییرات نسخه
├── DEPLOYMENT.md                 # راهنمای استقرار در سرور و هاست
├── BACKUP.md                     # راهنمای پشتیبان‌گیری
├── BACKUP_RESTORE.md             # راهنمای بازیابی اضطراری
├── PRODUCTION_CHECKLIST.md       # چک‌لیست قبل و بعد از راه‌اندازی
├── app/                          # کنترلرها، سرویس‌ها، مدل‌ها و پایگاه داده
│   └── Database/
│       ├── Migrations/           # ۱۸ مایگریشن ساختار دیتابیس
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

موارد زیر به دلیل امنیت و عدم نیاز در محیط پروداکشن، به صورت قطعی از فایل بسته ZIP حذف شده‌اند:
* `.git/` و تاریخچه مخزن
* `node_modules/` و پکیج‌های توسعه‌ای Node
* `tests/` و اسکریپت‌های تست واحد و یکپارچگی
* `.env` واقعی حاوی اطلاعات محیط توسعه
* `storage/installed.lock` (قفل نصب سیستم برای امکان اجرای Web Installer روی هاست نو)
* لاگ‌ها و کش‌های محیط توسعه (`storage/logs/*.log`, `storage/cache/*`, `storage/framework/cache/*`)
* فایل‌های ابزاری توسعه و اسکریپت‌های موقت (`scratch/`)
