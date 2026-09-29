# مستند معماری سامانه چند فروشگاهی (Multi-Store Architecture)

این مستند تشریح‌کننده معماری، لایه‌های امنیتی، ایزولاسیون داده‌ها و نحوه پیاده‌سازی قابلیت **Multi-Store** در فاز ۱۴ سامانه `crmwp` است.

---

## ۱. Store Context مرکزی (Centralized Store Context)
کلیه درخواست‌های وابسته به فروشگاه از طریق کلاس مرکزی [`StoreContext`](file:///d:/crmwp/app/Support/StoreContext.php) و متدهای `resolveStore()` / `resolveStoreContext()` در [`BaseController`](file:///d:/crmwp/app/Controllers/BaseController.php) اعتبارسنجی می‌شوند:
- شناسایی Store ID از طریق هدر استاندارد `X-Store-Id`، پارامترهای Query، پارامترهای Route یا Body صورت می‌گیرد.
- هیچ کنترلر یا سرویسی به صورت پراکنده `$_GET['store_id']` را مصرف نمی‌کند.
- در صورتی که کاربر تنها به یک فروشگاه دسترسی داشته باشد، آن فروشگاه به عنوان پیش‌فرض حل (Resolve) می‌شود.
- در صورت ارسال شناسه فروشگاهی که وجود ندارد، خطای `404 Not Found` بازگردانده می‌شود.

---

## ۲. کنترل دسترسی کاربر به فروشگاه (User → Store Access & Anti-IDOR)
- رابطه چند-به-چند بین کاربران و فروشگاه‌ها در جدول `user_stores` ذخیره و مدیریت می‌گردد.
- سرویس [`RbacService::userHasStoreAccess`](file:///d:/crmwp/app/Services/RbacService.php) اعتبارسنجی قطعی را در لایه بک‌اند انجام می‌دهد:
  ```text
  User Access = (User is Administrator) OR (record exists in user_stores with store_id = target_store_id)
  ```
- **حفاظت کامل در برابر IDOR:** چنانچه کاربری اقدام به ارسال `X-Store-Id` یا `?store_id=` مربوط به فروشگاهی کند که به آن دسترسی ندارد، بک‌اند فوراً پاسخ `403 Forbidden` با کد خطای `STORE_FORBIDDEN` برمی‌گرداند. فرانت‌اند هرگز ملاک تصمیم‌گیری امنیتی نیست.

---

## ۳. لایه آداپتور ووکامرس (WooCommerce Adapter Per Store)
- به جای ایجاد یک کلاینت عمومی سراسری، نمونه‌سازی آداپتورها منحصراً بر پایه مدل [`Store`](file:///d:/crmwp/app/Models/Store.php) و از طریق کارخانه [`WooCommerceAdapterFactory`](file:///d:/crmwp/app/Integrations/WooCommerce/WooCommerceAdapterFactory.php) صورت می‌پذیرد.
- رابط مشترک [`WooCommerceAdapterInterface`](file:///d:/crmwp/app/Integrations/WooCommerce/WooCommerceAdapterInterface.php) دو پیاده‌سازی اصلی دارد:
  1. [`WooCommerceApiAdapter`](file:///d:/crmwp/app/Integrations/WooCommerce/WooCommerceApiAdapter.php): ارتباط واقعی از طریق کلاینت امن REST API و کلیدهای رمزنگاری شده.
  2. [`DemoWooCommerceAdapter`](file:///d:/crmwp/app/Integrations/WooCommerce/DemoWooCommerceAdapter.php): برای فروشگاه‌های حالت دمو (`is_demo = 1`) با داده‌های ایزوله، جستجو، صفحه‌بندی، تغییر وضعیت سفارش‌ها و انبار بدون هیچ‌گونه وابستگی به شبکه خارجی.

---

## ۴. ایزولاسیون کامل کش (Cache Isolation)
- برای جلوگیری از نشت اطلاعات، تمام کلیدهای کش با فرمت ایزوله ساخته می‌شوند:
  ```text
  store:{store_id}:{type}:{subKey}
  ```
- کلاس [`Cache`](file:///d:/crmwp/app/Support/Cache.php) متدهای استانداردی نظیر `storeGet`, `storeSet`, `storeRemember`, `forgetStore` و `forgetStoreType` را ارائه می‌دهد. کش یک فروشگاه تحت هیچ شرایطی توسط فروشگاه دیگر خوانده یا پاک نمی‌شود.

---

## ۵. ایزولاسیون وب‌هوک‌ها (Webhook Isolation)
- اندپوینت دریافت وب‌هوک ووکامرس به صورت فروشگاه-محور طراحی شده است:
  ```text
  POST /api/v1/webhooks/woocommerce/{storeId}
  ```
- اعتبارسنجی امضای امنیتی (HMAC-SHA256) منحصراً با `webhook_secret` همان فروشگاه انجام می‌شود.
- پردازش رویدادها، ایندکس‌های لاگ (`webhook_logs.store_id`) و پاکسازی کش وابسته، صرفاً در کانتکست همان فروشگاه عمل می‌کند.

---

## ۶. تفکیک داده‌های CRM محلی (CRM Store Scope & StoreScopedRepository)
- کلیه جداول محلی مرتبط با CRM شامل ستون `store_id` همراه با کلید خارجی و ایندکس‌های ترکیبی مرکب هستند:
  - `customer_notes` (`store_id`, `wc_customer_id`, `created_at`)
  - `tags` (`store_id`, `name`)
  - `customer_tags` (`tag_id`, `store_id`, `wc_customer_id`)
  - `segments` (`store_id`)
  - `tasks` (`store_id`, `status`, `due_date`)
  - `activities` (`store_id`, `created_at`)
  - `notifications` (`store_id`, `user_id`)
  - `bulk_operations` (`store_id`)
  - `audit_logs` (`store_id`)
- کلاس پایه انتزاعی [`StoreScopedRepository`](file:///d:/crmwp/app/Repositories/StoreScopedRepository.php) تضمین می‌کند که هیچ کوئری اختصاصی فروشگاه بدون شرط `WHERE store_id = ?` در سیستم اجرا نشود.

---

## ۷. ترکیب مدل دسترسی و مجوزها (Permission & Store Access Model)
برای اجرای هر عملیات تجاری دو شرط همزمان باید برقرار باشد:
```text
1. User has permission (e.g. orders.view, products.edit, crm.manage)
   AND
2. User has store access (user_stores.store_id == target_store_id OR user is Admin)
```

---

## ۸. واحد پولی (Currency) و منطقه زمانی (Timezone)
- **Currency:** هر فروشگاه دارای واحد پولی مستقل (مانند `IRR` با نماد تومان/ریال، `USD` با نماد $، `EUR` با نماد €) است که مستقیماً از قابلیت‌های ووکامرس دریافت شده و در تمام گزارش‌ها، ویجت‌ها و قیمت‌ها نمایش می‌یابد.
- **Timezone:** محاسبات تاریخ، فیلترهای زمانی، دوره‌های پیش‌فرض و نمایش لاگ‌ها بر مبنای منطقه زمانی ذخیره شده در تنظیمات همان فروشگاه (مانند `Asia/Tehran`، `America/New_York`، `Europe/Berlin`) پردازش می‌شوند.

---

## ۹. امنیت و عدم افشای کلیدها (Security & Protection)
- کلیدهای دسترسی `consumer_key` و `consumer_secret` به صورت **Encryption at Rest** با الگوریتم `AES-256-CBC` در پایگاه داده ذخیره می‌شوند.
- در پاسخ‌های API (`GET /stores`, `GET /stores/{id}`)، این مقادیر هرگز به مرورگر ارسال نمی‌شوند و به صورت ماسک‌شده (`ck_...1234`, `cs_••••••••`) به همراه پرچم `credentials_configured: true` بازگردانده می‌شوند.
- سیستم از حذف یا غیرفعال‌سازی تنها فروشگاه فعال سامانه جلوگیری می‌نماید تا سامانه همواره در وضعیت پایدار باقی بماند.
