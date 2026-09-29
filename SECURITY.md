# خط‌مشی و مستندات امنیت (Security Policy)

این سند معماری و الزامات امنیتی پیاده‌سازی شده در پروژه **WooCommerce Management & CRM** (فاز ۱ تا ۱۶) را شرح می‌دهد.

---

## ۱. لایه‌های دفاع در عمق (Defense in Depth Architecture)

تمامی درخواست‌ها قبل از رسیدن به هسته محاسباتی از لایه‌های متوالی بازرسی عبور می‌کنند:

```text
Browser Client
   ↓ (Security Headers: CSP, HSTS, X-Frame-Options, X-Content-Type-Options)
Web Server / SPA
   ↓ (Session Validation, Strict Mode, Correlation ID generation: req_...)
CSRF Middleware / Rate Limiter
   ↓ (Timing-safe token check, Atomic IP + Route window throttling)
API Router & Authentication
   ↓ (Session verification, Password Hashing: BCRYPT cost 12)
Authorization & RBAC
   ↓ (Granular permission verification: e.g. orders.view, users.manage)
Store Isolation Context (Anti-IDOR)
   ↓ (Store existence, Active status, and User-Store mapping check)
Service Layer & Validation
   ↓ (Input sanitization, Whitelisted sorting & filtering, Parameter bounding)
Database Layer (PDO / MariaDB)
   ↓ (Native prepared statements, utf8mb4 charset, No emulation)
Encrypted Rest Storage
   ↓ (AES-256-CBC + HMAC-SHA256 authenticated encryption for WooCommerce secrets)
External WooCommerce REST API
   ↓ (Timeout limits: 15s read, 8s connect; No blind retries on financial mutations)
```

---

## ۲. احراز هویت و امنیت نشست (Authentication & Session Security)

1. **Session Configuration**:
   - `session.use_strict_mode = 1` فعال است تا از حملات Session Fixation جلوگیری کند.
   - `session.use_only_cookies = 1` مانع از انتقال شناسه نشست از طریق URL یا پارامترهای GET می‌شود.
   - کوکی‌ها با `httponly = true` ارسال می‌شوند تا از دسترسی جاوااسکریپت به کوکی نشست ممانعت شود.
   - فلگ `SameSite = Lax` جهت ممانعت از Cross-Site Request Forgery تنظیم شده است.
   - در صورت استفاده از پروتکل HTTPS، فلگ `secure = true` به‌صورت خودکار فعال می‌گردد.
2. **Session Regeneration**:
   - بلافاصله پس از لاگین موفق، تابع `Session::regenerate()` فراخوانی می‌شود تا شناسه نشست جدید تولید گردد.
3. **Session Expiration**:
   - نشست‌ها پس از منقضی شدن مدت اعتبار (پیش‌فرض ۲ ساعت یا مقدار `SESSION_LIFETIME`) به‌صورت خودکار باطل و پاک‌سازی می‌شوند.
4. **Login Rate Limiting**:
   - مسیر `/api/v1/auth/login` دارای محدودیت نرخ ۵ تلاش در هر ۶۰ ثانیه به ازای هر IP است تا از حملات Brute-Force جلوگیری شود.
5. **Password Storage**:
   - تمامی رمزهای عبور صرفاً با الگوریتم استاندارد `PASSWORD_BCRYPT` و فاکتور هزینه (Cost) ۱۲ هش می‌شوند. هیچ‌گونه الگوریتم برگشت‌پذیر یا منسوخ (MD5/SHA1) مجاز نیست.

---

## ۳. کنترل دسترسی و جداسازی فروشگاه‌ها (Authorization & Store Isolation)

1. **RBAC (Role-Based Access Control)**:
   - کاربران بر اساس نقش‌های تعریف‌شده (Admin، Manager، Sales، Support و ...) و مجوزهای ریزدانه اعتبارسنجی می‌شوند.
2. **حفاظت از آخرین مدیر فعال (Last Active Administrator Protection)**:
   - سیستم اجازه حذف، غیرفعال‌سازی، یا خلع نقش آخرین مدیر ارشد فعال سیستم را تحت هیچ شرایطی نمی‌دهد.
3. **جلوگیری از ارتقای سطح دسترسی (Privilege Escalation Protection)**:
   - کاربران غیرمدیر مجاز به اعطای نقش `Admin` یا تغییر مجوزهای سیستمی خود یا دیگران نیستند.
4. **جداسازی کامل چندفروشگاهی (Store Isolation & Anti-IDOR)**:
   - کلاس مرکزی `StoreContext::resolve()` و `StoreContext::validateAccess()` موظف است قبل از اجرای هر عملیاتی روی سفارش‌ها، مشتریان، محصولات، انبار، وب‌هوک‌ها، لاگ‌ها و اتوماسیون‌ها، دسترسی کاربر به شناسه فروشگاه را بررسی کند.
   - تلاش برای دسترسی به منابع فروشگاه دیگر بلافاصله لاگ شده و خطای `403 Forbidden` بازگردانده می‌شود.
5. **جداسازی کش (Cache Isolation)**:
   - تمامی کلیدهای کش داده‌های مربوط به فروشگاه پیشوند شناسه فروشگاه را دارند (`store:{id}:...`). هیچ کش اشتراکی بین فروشگاه‌ها وجود ندارد.

---

## ۴. امنیت وب‌هوک‌های ووکامرس (WooCommerce Webhooks)

1. **اندپوینت عمومی مجزا**:
   - مسیر `/api/v1/webhooks/woocommerce/{store}` عمداً بدون نیاز به Session طراحی شده است.
2. **اعتبارسنجی امضای رمزنگاری (HMAC-SHA256)**:
   - امضای ارسالی در هدر `x-wc-webhook-signature` با بدنه خام درخواست (`rawBody`) و کلید مخفی وب‌هوک یا Consumer Secret مقایسه می‌شود.
   - مقایسه امضا با استفاده از `hash_equals()` به‌صورت زمان‌ثابت (Timing-safe) انجام می‌شود.
3. **حفاظت در برابر Replay و رویدادهای تکراری (Idempotency)**:
   - شناسه تحویل ووکامرس (`delivery_id`) در دیتابیس ثبت شده و درخواست‌های تکراری با وضعیت `duplicate` پذیرفته شده اما موتور پردازش مجدداً برانگیخته نمی‌شود.
4. **محدودیت حجم داده ارسالی (Payload Size Limit)**:
   - حداکثر حجم مجاز وب‌هوک ۵ مگابایت است؛ داده‌های بزرگتر بلافاصله با خطای `413 Payload Too Large` رد می‌شوند.
5. **حذف داده‌های حساس از لاگ وب‌هوک (Payload Redaction)**:
   - قبل از ذخیره بدنه وب‌هوک در دیتابیس، فیلدهای حساس مانند `password`، `consumer_secret`، `token`، `credit_card`، `cvv` و غیره به‌صورت خودکار ماسک می‌شوند (`***REDACTED***`).

---

## ۵. حفاظت از کلیدها و اطلاعات حساس (Credentials & Encryption at Rest)

1. **WooCommerce Credentials**:
   - کلیدهای `consumer_key` و `consumer_secret` به‌صورت متن خام در پایگاه‌داده ذخیره نمی‌شوند؛ بلکه با الگوریتم رمزنگاری دوجانبه تأییدشده `AES-256-CBC` به همراه امضای اصالت `HMAC-SHA256` ذخیره می‌گردند.
2. **محل کلید رمزنگاری**:
   - کلید رمزنگاری از متغیر محیطی `APP_SECRET` در سرور خوانده می‌شود و در فایل‌های دیتابیس یا مخزن Git قرار نمی‌گیرد.
3. **حفاظت در خروجی‌های API**:
   - کلیدهای محرمانه و رمز عبورها هرگز در پاسخ‌های JSON بازگردانده نمی‌شوند و صرفاً فرمت ماسک‌شده (`ck_************` یا `••••••••••••••••`) نمایش داده می‌شود.

---

## ۶. مقابله با آسیب‌پذیری‌های وب (CSRF, XSS, SQLi, IDOR)

1. **CSRF (Cross-Site Request Forgery)**:
   - تمامی درخواست‌های با متدهای جهش‌دهنده (`POST`, `PUT`, `PATCH`, `DELETE`) موظف به ارسال توکن معتبر در هدر `X-CSRF-TOKEN` هستند.
2. **XSS (Cross-Site Scripting)**:
   - داده‌های ورودی کاربر در هنگام بازنمایی با متدهای مناسب پاک‌سازی می‌شوند.
   - در فرانت‌اند Vue 3، متن‌ها به‌صورت `{{ mustache }}` استاندارد درج شده و استفاده غیرضروری از `v-html` ممنوع است.
   - هدرهای CSP، `X-XSS-Protection` و `X-Content-Type-Options: nosniff` در تمام پاسخ‌ها اعمال می‌شوند.
3. **SQL Injection**:
   - کلیه کوئری‌های تعاملی با MariaDB/MySQL با Prepared Statements و متغیرهای نام‌گذاری شده یا پارامتریک اجرا می‌شوند (`PDO::ATTR_EMULATE_PREPARES => false`).
   - نام فیلدهای `ORDER BY` و جهت مرتب‌سازی (`sort`, `direction`) توسط لیست سفید (Whitelist) محدود و اعتبارسنجی می‌شوند.
4. **تزریق فرمول در خروجی CSV (CSV Formula Injection)**:
   - متد `Security::escapeCsvFormula()` کاراکترهای خطرناک آغازین (`=`, `+`, `-`, `@`, `\t`, `\r`) را با کاراکتر `'` بی‌خطر می‌کند.

---

## ۷. امنیت بارگذاری فایل (File Upload Security)

1. مسیر بارگذاری فایل‌ها اعتبارسنجی نوع واقعی MIME را با `finfo_file` انجام می‌دهد و تنها پسوندهای تصویر مجاز (`jpg`, `png`, `webp`) پذیرفته می‌شوند.
2. حجم فایل آواتار به ۲ مگابایت محدود است.
3. نام فایل در سرور کاملاً تصادفی و با ترکیب شناسه کاربری، زمان و بایت‌های شبه‌تصادفی تولید می‌شود تا امکان Overwrite یا Path Traversal منتفی باشد.
4. پوشه `public/storage/avatars` دارای فایل `.htaccess` محافظت‌شده با دستور `Options -Indexes -ExecCGI` و مسدودسازی کامل اجرای اسکریپت‌های PHP و کدهای اجرایی است.

---

## ۸. ردیابی خطاها و شناسه پیگیری (Error Handling & Correlation ID)

1. در محیط تولید (`APP_DEBUG=false`)، خطاهای داخلی سرور، مسیرهای فایل، اسامی جداول و استک تریس‌ها به کاربر نمایش داده نمی‌شوند.
2. برای هر درخواست یک شناسه پیگیری منحصر‌به‌فرد (`X-Request-Id: req_...`) تولید و در هدر پاسخ و پیام خطای JSON قرار می‌گیرد.
3. لاگ‌های داخلی با همین شناسه ثبت می‌شوند تا بدون افشای اطلاعات محرمانه برای کاربر، عیب‌یابی در سرور ممکن باشد.

---

## ۹. محدودیت نرخ و مقابله با سوءاستفاده (Rate Limiting & Abuse Prevention)

1. **لاگین و احراز هویت (Login Throttling)**:
   - حداکثر ۵ تلاش ناموفق در هر ۶۰ ثانیه بر اساس آدرس IP کاربر (`RateLimitMiddleware`).
2. **وب‌هوک‌ها و بار داده (Webhook & Payload Bounds)**:
   - سقف حجم ورودی وب‌هوک به ۵ مگابایت محدود بوده و داده‌های حجیم‌تر بلافاصله با خطای `413 Payload Too Large` رد می‌شوند.
3. **عملیات دسته‌جمعی (Bulk Abuse Prevention)**:
   - عملیات انبوه بر روی محصولات، سفارش‌ها و مشتریان به‌صورت بسته‌ای (Chunked، حداکثر ۵۰ تا ۱۰۰ رکورد در هر بسته)، قابل لغو (`Cancellable`) و تحت تراکنش پایگاه‌داده اجرا می‌شوند.
4. **کنترل صفحه‌بندی (Pagination Bounds)**:
   - پارامتر `per_page` در تمامی اندپوینت‌های فهرست به سقف ۱۰۰ محدود شده تا از ایجاد سربار حافظه یا کوئری‌های بسیار سنگین جلوگیری گردد (`min(100, max(1, $perPage))`).
5. **تست اتصال فروشگاه (Test Connection Throttling)**:
   - فراخوانی‌های تست ارتباط با ووکامرس دارای تایم‌اوت مشخص (۱۵ ثانیه خواندن، ۸ ثانیه اتصال) و محدودیت نرخ هستند تا از حمله DoS یا مسدودسازی ترد‌های PHP ممانعت شود.

---

## ۱۰. امنیت عملیات مالی و موتور اتوماسیون (Financial Safety & Automation Safeguards)

1. **استرداد وجه و عملیات مالی (Refund Safety)**:
   - سیستم اجازه ارسال درخواست استرداد وجه فراتر از سقف باقیمانده سفارش را نمی‌دهد (`INVALID_REFUND_AMOUNT`).
   - عملیات استرداد وجه مالی کورکورانه مجدداً تلاش (Blind Retry) نمی‌شوند تا از بازگشت چندباره وجه به حساب مشتری پیشگیری شود.
   - اتوماسیون‌ها از اجرای اکشن مستقیم استرداد مالی به شکل خودکار منع شده‌اند (`Financial Safety Exception`).
2. **حفاظت در برابر حلقه‌های بی‌پایان در اتوماسیون (Recursion & Loop Protection)**:
   - حداکثر عمق بازگشتی زنجیره رویدادها ۵ سطح است (`MAX_RECURSION_DEPTH = 5`). رویدادهایی که از این عمق فراتر روند یا باعث چرخه تکراری شوند، بلافاصله متوقف و لاگ می‌شوند.
   - ثبت شناسه رویدادها در زنجیره اجرا جهت جلوگیری از حلقه‌های همپوشان.

---

## ۱۱. گزارش آسیب‌پذیری‌های امنیتی (Vulnerability Reporting)

در صورت کشف هرگونه نقص یا آسیب‌پذیری امنیتی، لطفاً مورد را مستقیماً به آدرس ایمیل مدیر سیستم ارسال فرمایید و از ایجاد Issue عمومی در مخزن کد خودداری فرمایید.
