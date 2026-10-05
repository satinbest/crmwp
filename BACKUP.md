# راهنمای پشتیبان‌گیری و بازیابی (Backup & Disaster Recovery)
## سامانه CRMWP — نسخه 1.0.1 (Production Stable)

این مستند مراحل رسمی تهیه نسخه پشتیبان کامل و بازیابی بدون نقص سامانه **مدیریت و CRM ووکامرس (crmwp)** را برای محیط‌های پروداکشن (هاست اشتراکی و سرور اختصاصی) تشریح می‌کند.

---

## ۱. ارکان اساسی جهت تهیه نسخه پشتیبان (Backup Scope)

برای اینکه سامانه در زمان بروز سانحه بدون از دست رفتن داده‌ها بازیابی شود، تهیه پشتیبان از ۴ مؤلفه زیر **الزامی و حیاتی** است:

1. **پایگاه‌داده (Database)**:
   - ساختار جداول، داده‌های CRM (مشتریان، تسک‌ها، برچسب‌ها، سگمنت‌ها، یادداشت‌ها)، اتوماسیون‌ها، لاگ‌ها و کلیدهای رمزنگاری‌شده ووکامرس.
2. **فایل‌های بارگذاری‌شده و فایل‌های وضعیت (`storage/uploads/` و `storage/locks/`)**:
   - تصاویر آواتار کاربران، خروجی‌های گزارش‌ها و قفل‌های وضعیت.
3. **پیکربندی محیطی و کلیدهای رمزنگاری (`.env`)**:
   - به ویژه پارامترهای `ENCRYPTION_KEY` و `APP_SECRET`.
   > **هشدار امنیتی بسیار مهم**: در صورت مفقود شدن `ENCRYPTION_KEY`، تمامی اطلاعات رمزشده اتصال به فروشگاه‌های ووکامرس (`consumer_key` و `consumer_secret`) غیرقابل رمزگشایی و باطل خواهند شد.
4. **فایل‌های سی دی ان محلی و دارایی‌های بیلد (`public/assets/`)**:
   - فونت‌های وزیرمتن محلی و بسته‌های کامپایل‌شده.

---

## ۲. سناریوهای تهیه نسخه پشتیبان (Backup Procedures)

### الف) خروجی کامل از پایگاه‌داده (Database Dump)
با استفاده از ابزار استاندارد `mysqldump` یا کنترل‌پنل cPanel/DirectAdmin (بخش phpMyAdmin):

```bash
# تهیه بک‌آپ فشرده از دیتابیس با پشتیبانی کامل از utf8mb4
mysqldump -u <DB_USER> -p<DB_PASS> \
  --default-character-set=utf8mb4 \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  <DB_NAME> | gzip > backup_db_$(date +%Y%m%d_%H%M%S).sql.gz
```

### ب) خروجی از فایل‌های استوریج و پیکربندی
```bash
# ایجاد آرشیو از پوشه storage و فایل‌های پیکربندی حیاتی
tar -czvf backup_files_$(date +%Y%m%d_%H%M%S).tar.gz \
  .env \
  storage/uploads \
  storage/locks
```

### ج) اسکریپت پشتیبان‌گیری خودکار روزانه (پیشنهادی برای Cron)
```bash
#!/usr/bin/env bash
BACKUP_DIR="/home/user/backups/crmwp"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
mkdir -p "$BACKUP_DIR"

# 1. دیتابیس
mysqldump -u crmwp_user -p'StrongPassword' --default-character-set=utf8mb4 --single-transaction crmwp_db | gzip > "$BACKUP_DIR/db_$TIMESTAMP.sql.gz"

# 2. فایل‌ها و کلید رمزنگاری
tar -czvf "$BACKUP_DIR/files_$TIMESTAMP.tar.gz" -C /home/user/public_html .env storage/uploads storage/locks

# 3. حذف بک‌آپ‌های قدیمی‌تر از ۳۰ روز
find "$BACKUP_DIR" -type f -name "*.gz" -mtime +30 -delete
```

---

## ۳. مراحل بازیابی سامانه در صورت بروز حادثه (Disaster Recovery)

در صورت خرابی سخت‌افزاری سرور یا نیاز به انتقال سامانه به یک هاست دیگر، مراحل زیر را به ترتیب انجام دهید:

### مرحله ۱: برپایی کدها و بازگردانی پیکربندی
1. کد پکیج انتشار را در مسیر وب‌سرور جدید قرار دهید.
2. فایل `.env` بک‌آپ گرفته شده را دقیقاً در ریشه برنامه قرار دهید.
   > **توجه**: اطمینان حاصل کنید مقدار `ENCRYPTION_KEY` بدون کوچک‌ترین تغییری بازگردانده شود.

### مرحله ۲: بازگردانی دیتابیس
```bash
# ایجاد دیتابیس خالی با همان مشخصات
mysql -u root -p -e "CREATE DATABASE crmwp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# ایمپورت فایل دامپ
gunzip < backup_db_20260928_120000.sql.gz | mysql -u <DB_USER> -p<DB_PASS> crmwp
```

### مرحله ۳: بازگردانی فایل‌های استوریج
```bash
tar -xzvf backup_files_20260928_120000.tar.gz -C /path/to/crmwp/
chmod -R 775 storage
```

### مرحله ۴: اجرای بررسی سلامت و همگام‌سازی
1. با اجرای دستور `php cli.php` مطمئن شوید اتصال به پایگاه‌داده سالم است.
2. به بخش مدیریت فروشگاه‌ها رفته و با کلیک روی «تست اتصال»، برقراری ارتباط با ووکامرس را ارزیابی فرمایید.
