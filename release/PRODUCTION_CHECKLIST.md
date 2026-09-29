# چک‌لیست استقرار در محیط پروداکشن (Production Deployment Checklist)

این چک‌لیست مراحل ضروری پیش از راه‌اندازی و انتشار سامانه **WooCommerce Management & CRM** در محیط نهایی (Production) را مشخص می‌کند.

---

## ۱. پیکربندی محیطی و فایل .env (Environment & Config)

- [ ] متغیر `APP_ENV` روی `production` تنظیم شده باشد.
- [ ] متغیر `APP_DEBUG` حتماً روی `false` قرار گیرد تا خطاهای سیستمی و مسیر فایل‌ها پنهان بماند.
- [ ] متغیر `APP_URL` به دامنه واقعی و امن سامانه (همراه با HTTPS) اشاره کند.
- [ ] کلید `APP_SECRET` با یک رشته تصادفی قوی و حداقل ۳۲ کاراکتری تولید شده باشد.
- [ ] متغیر `APP_MAINTENANCE` در حالت عادی `false` باشد و تنها در زمان ارتقای زیرساخت `true` گردد.
- [ ] فایل `.env` واقعی هرگز در مخزن گیت (Git) ذخیره یا ارسال نشود.

---

## ۲. پایگاه داده و MariaDB (Database Configuration)

- [ ] دیتابیس MariaDB (نسخه 10.5+ یا 11+) با رمز عبور قوی راه‌اندازی شده باشد.
- [ ] کاراکترست پیش‌فرض دیتابیس `utf8mb4` و Collation آن `utf8mb4_unicode_ci` باشد.
- [ ] کاربر دیتابیس دسترسی محدود به دیتابیس اختصاصی سامانه (`crmwp`) داشته باشد.
- [ ] اجرای میگریشن‌های ساختار با موفقیت انجام شده باشد:
  ```bash
  php bin/migrate.php
  ```
- [ ] در محیط پروداکشن از اجرای Seedهای تستی دمو خودداری شود.

---

## ۳. امنیت نشست و کوکی‌ها (Session & HTTPS)

- [ ] سرور حتماً با گواهی SSL معتبر و پروتکل HTTPS پیکربندی شده باشد.
- [ ] هدایت خودکار تمام ترافیک HTTP به HTTPS در وب‌سرور (Nginx/Apache) فعال باشد.
- [ ] متغیر `SESSION_SECURE=true` فعال باشد تا کوکی‌ها تنها تحت کانال امن HTTPS ارسال گردند.
- [ ] زمان انقضای نشست (`SESSION_LIFETIME`) روی مقدار مناسب (مثلاً ۷۲۰۰ ثانیه / ۲ ساعت) تنظیم باشد.

---

## ۴. وب‌سرور و فایل‌های استاتیک (Web Server & Assets)

- [ ] ریشه وب‌سرور (Document Root) به پوشه `public/` اشاره داشته باشد، نه ریشه اصلی پروژه.
- [ ] دسترسی مستقیم از وب به پوشه‌های `app/`، `config/`، `routes/`، `storage/`، `vendor/` مسدود باشد.
- [ ] فایل‌های بیلد شده فرانت‌اند (`public/assets/`) با دستور `npm run build` ساخته شده و فایل‌های منبع جاوااسکریپت، فونت‌های وزیرمتن و آیکون‌های Iconsax به‌صورت لوکال لود شوند (عدم وابستگی به CDNهای خارجی).
- [ ] هدرهای امنیتی در پاسخ‌ها فعال باشند (CSP, X-Frame-Options: DENY, X-Content-Type-Options: nosniff, Referrer-Policy).
- [ ] ماژول Rewrite وب‌سرور برای هدایت درخواست‌های تک‌صفحه‌ای (SPA) به `index.php` فعال باشد.

---

## ۵. دسترسی‌ها و مجوزهای فایل (Filesystem Permissions)

- [ ] پوشه `storage/logs/` و `storage/cache/` توسط کاربر وب‌سرور (`www-data` یا مشابه) قابل نوشتن (Writeable) باشد.
- [ ] پوشه `public/storage/avatars/` برای آپلود آواتار قابل نوشتن باشد و فایل `.htaccess` محافظت در آن مستقر باشد.
- [ ] فایل‌های کدهای PHP دسترسی فقط‌خواندنی (Read-Only) برای کاربر وب‌سرور داشته باشند.

---

## ۶. وب‌هوک‌های ووکامرس (WooCommerce Webhooks)

- [ ] آدرس وب‌هوک‌های ووکامرس به‌صورت `https://your-domain.com/api/v1/webhooks/woocommerce/{store_id}` در پنل ووکامرس ست شود.
- [ ] رویدادهای کلیدی فعال باشند:
  - `order.created`, `order.updated`, `order.deleted`
  - `product.created`, `product.updated`, `product.deleted`
  - `customer.created`, `customer.updated`
- [ ] کلید محرمانه وب‌هوک (Webhook Secret) در تنظیمات فروشگاه وارد شده و امضای HMAC-SHA256 اعتبارسنجی گردد.

---

## ۷. وظایف زمان‌بندی‌شده و همگام‌سازی (Cron Jobs)

- [ ] کرون جاب دوره‌ای تطبیق داده‌ها (Reconciliation) تنظیم شود (مثلاً هر ۳۰ دقیقه):
  ```cron
  */30 * * * * php /path/to/crmwp/bin/reconcile.php >> /path/to/crmwp/storage/logs/cron.log 2>&1
  ```
- [ ] کرون جاب پاک‌سازی لاگ‌ها و اعلان‌های قدیمی (Cleanup Retention):
  ```cron
  0 2 * * * php /path/to/crmwp/bin/cleanup.php >> /path/to/crmwp/storage/logs/cleanup.log 2>&1
  ```

---

## ۸. سلامت و پایش سیستم (Health & Monitoring)

- [ ] اندپوینت پایش سلامت بررسی شود:
  ```bash
  curl -i https://your-domain.com/api/v1/health
  curl -i https://your-domain.com/api/v1/health/ready
  ```
- [ ] بازگشت کد وضعیت 200 OK و وضعیت `ready` در پاسخ تأیید گردد.

---

## ۹. پشتیبان‌گیری و بازیابی (Backup & Disaster Recovery)

- [ ] تهیه نسخه پشتیبان منظم (روزانه/هفتگی) از دیتابیس MariaDB با `mysqldump`.
- [ ] تهیه پشتیبان از پوشه `public/storage/avatars/` و فایل پیکربندی `.env`.
- [ ] مستندسازی دستورالعمل بازگردانی اطلاعات (Restore Plan) در محیط آزمایشگاهی.
