# راهنمای پشتیبان‌گیری و بازیابی (Backup & Disaster Recovery)

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

# 3. نگهداری بک‌آپ‌های ۳۰ روز اخیر و پاکسازی قدیمی‌ترها
find "$BACKUP_DIR" -name "*.gz" -mtime +30 -exec rm {} \;
```

---

## ۳. مراحل بازیابی سامانه (Restore Procedures)

در صورت خرابی سرور، ارتقای هاست یا سانحه، مراحل بازیابی به ترتیب زیر انجام می‌شود:

### مرحله ۱: استقرار کدهای برنامه
1. فایل‌های پکیج برنامه را در دایرکتوری هدف استخراج کنید.
2. مطمئن شوید دایرکتوری `public/` به عنوان Document Root در وب‌سرور تنظیم شده باشد.

### مرحله ۲: بازیابی پایگاه‌داده
1. یک دیتابیس جدید با انکودینگ `utf8mb4` و تطبیق `utf8mb4_unicode_ci` ایجاد کنید:
   ```sql
   CREATE DATABASE crmwp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. ایمپورت فایل نسخه پشتیبان:
   ```bash
   gunzip < backup_db_2026xxxx.sql.gz | mysql -u <DB_USER> -p<DB_PASS> <DB_NAME>
   ```

### مرحله ۳: بازیابی تنظیمات و کلیدهای رمزنگاری
1. فایل `.env` بک‌آپ گرفته شده را در ریشه پروژه قرار دهید.
2. مطمئن شوید مقدار `ENCRYPTION_KEY` دقیقاً همان کلید قبلی باشد تا رمزگشایی پسوردها و توکن‌های ووکامرس میسر شود.

### مرحله ۴: بازیابی فایل‌های Storage و اعمال دسترسی‌ها
1. آرشیو فایل‌های بارگذاری شده را استخراج نمایید:
   ```bash
   tar -xzvf backup_files_2026xxxx.tar.gz -C /path/to/crmwp/
   ```
2. مجوزهای دسترسی پوشه‌ها را تنظیم کنید:
   ```bash
   chmod -R 755 storage
   chmod -R 755 public
   ```

### مرحله ۵: راستی‌آزمایی پس از بازیابی (Verification)
1. فراخوانی اندپوینت سلامت سیستم:
   ```bash
   curl -I https://your-crm-domain.com/api/v1/health/ready
   ```
   باید وضعیت `200 OK` همراه با تایید ارتباط پایگاه‌داده و وضعیت حافظه برگرداند.
2. ورود به پنل کاربری با حساب مدیر ارشد و بررسی دسترسی به فروشگاه‌ها و لاگ‌ها.
