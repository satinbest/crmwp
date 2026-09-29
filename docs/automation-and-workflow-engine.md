# مستند فنی معماری موتور اتوماسیون و گردش‌کار (فاز ۱۵)
## CRM & WooCommerce Automation and Workflow Engine

---

## ۱. هدف و چشم‌انداز معماری
موتور اتوماسیون (Workflow Engine) پلتفرم CRMWP طراحی شده است تا بر اساس رویدادهای ووکامرس (مانند ثبت سفارش، کمبود موجودی انبار، تغییر وضعیت سفارش) و رخدادهای داخلی CRM (تغییر وضعیت وظایف، انتساب برچسب به مشتری، اتمام عملیات گروهی)، سناریوهای خودکار چندمرحله‌ای را بدون نیاز به پردازش مداوم (No Background Daemon / Shared-Hosting Compatible) پردازش و اجرا کند.

```text
Incoming Event (Webhook / CRM / Scheduled)
                    ↓
             EventDispatcher
                    ↓
            AutomationEngine
      ┌─────────────┼─────────────┐
      ↓             ↓             ↓
 RuleMatcher  Idempotency   LoopProtection
      ↓
ConditionEvaluator (AND/OR, Numbers, Strings, Arrays)
      ↓
VariableResolver (Whitelisted Context Interpolation)
      ↓
ActionExecutor (Extensible Plugins: Tag, Task, Note, Status, Stock, Notification)
      ↓
Result Tracking / AutomationRun / Activity / Audit
```

---

## ۲. مشخصات ساختار پایگاه داده و مایگریشن‌ها
مایگریشن `018_create_phase15_automations_tables.php` دو جدول اختصاصی با ایندکس‌های بهینه‌سازی شده برای محیط چندفروشگاهی ایجاد می‌کند:

### ۱. جدول `automations`
* `id`: شناسه یکتا
* `store_id`: شناسه فروشگاه (پشتیبانی کامل از Multi-Store و Store Isolation)
* `name`: نام نمایشی گردش‌کار
* `description`: توضیحات فارسی برای اعضای تیم
* `status`: وضعیت (`active`, `inactive`, `draft`, `error`)
* `trigger_type`: شناسه رویداد محرک (مانند `order.created`, `inventory.low_stock`)
* `trigger_config`: ساختار JSON برای پارامترهای تریگر (مانند `to_status: "completed"`)
* `conditions`: ساختار درختی JSON برای شرط‌ساز هوشمند (AND/OR Groups)
* `actions`: آرایه JSON از اقدامات پیکربندی‌شده
* `execution_mode`: حالت اجرا (`immediate`, `delayed`, `batch`)
* `max_runs`: حداکثر تعداد اجرای مجاز
* `run_count`: شمارشگر تعداد اجراهای موفق
* `last_run_at`: برچسب زمانی آخرین اجرا
* `created_by` / `updated_by`: شناسه کاربر سازنده و ویرایش‌کننده

### ۲. جدول `automation_runs`
* `id`: شناسه اجرای یکتا
* `automation_id`: شناسه اتوماسیون مربوطه
* `store_id`: شناسه فروشگاه
* `event_id`: شناسه یکتای رویداد
* `trigger_type`: رویداد محرک
* `status`: وضعیت اجرا (`pending`, `running`, `completed`, `partial`, `failed`, `skipped`, `cancelled`)
* `duration_ms`: مدت زمان اجرا به میلی‌ثانیه
* `idempotency_key`: کلید مهار اجرای تکراری
* `context`: داده‌های رویداد پس از پالایش اطلاعات حساس (Sanitized)
* `result`: نتایج اجرای هر اقدام، ارزیابی شروط و گزارش خطاها

---

## ۳. امنیت، ایزولاسیون و حفاظت مالی (Safety Models)

### ۱. جلوگیری از اجرای مجدد و تکراری (Idempotency)
هر اجرا دارای کلید یکتایی به فرمت زیر است:
```text
idempotency_key = "auto_{automation_id}_evt_{event_id}_{payload_hash}"
```
در صورت دریافت مجدد همان رویداد (مثلاً ارسال چندباره وب‌هوک سفارش توسط ووکامرس)، سیستم از اجرای مضاعف اکشن‌ها جلوگیری نموده و وضعیت `duplicate_suppressed` را ثبت می‌کند.

### ۲. مهار حلقه‌های بینهایت و بازگشتی (Loop & Recursion Prevention)
* هر شیء `AutomationEvent` عمق سلسله‌مراتب (`depth`) و زنجیره محرک‌ها (`triggerChain`) را در خود حمل می‌کند.
* در صورتی که عمق زنجیره به ۵ برسد (`MAX_RECURSION_DEPTH = 5`) یا اتوماسیون در زنجیره تریگر قبلی خود قرار داشته باشد، اجرای خودکار فوراً متوقف شده و با وضعیت `skipped` و علت `recursion_detected` ثبت می‌شود.

### ۳. حفاظت مالی صریح (Financial Safety)
* اقداماتی نظیر استرداد وجه (`refund`) به دلایل امنیتی در نسخه v1 موتور اتوماسیون ممنوع است و تلاش برای ایجاد یا اجرای چنین اکشنی با خطای اعتبارسنجی `422 Unprocessable Entity` مسدود می‌گردد.

### ۴. ضد تزریق قالب و اجرای کد دلخواه (Template Injection Protection)
* موتور `VariableResolver` صرفاً متغیرهای Whitelist شده (`{{order.number}}`, `{{customer.name}}`, `{{product.name}}`, ...) را پردازش می‌کند. هرگونه متغیر ناشناخته یا توابع مخرب به صورت رشته خالی پاکسازی می‌شوند.

### ۵. ایزولاسیون فروشگاه (Store Isolation & Anti-IDOR)
* اتوماسیون‌های فروشگاه A هرگز بر روی رویدادهای فروشگاه B اجرا نمی‌شوند.
* در لایه کنترلر، هرگونه تلاش کاربر برای واکشی، ویرایش یا اجرای اتوماسیون فروشگاهی که به آن دسترسی ندارد با خطای `403 Forbidden` مسدود می‌گردد.

---

## ۴. لیست تریگرها، عملگرها و اقدامات (Plugins & Schema)

### رویدادهای محرک (Triggers):
* **ووکامرس:** `order.created`, `order.updated`, `order.status_changed`, `order.deleted`, `product.created`, `product.updated`, `product.deleted`, `customer.created`, `customer.updated`, `inventory.low_stock`, `inventory.out_of_stock`
* **CRM:** `task.created`, `task.completed`, `task.overdue`, `customer.tag_added`, `customer.tag_removed`, `bulk_operation.completed`, `bulk_operation.failed`
* **سیستم:** `scheduled` (اجرای دسته‌ای با Cron), `manual` (اجرای دستی کاربر)

### عملگرهای شرط‌ساز (Operators):
`equals`, `not_equals`, `greater_than`, `greater_or_equal`, `less_than`, `less_or_equal`, `contains`, `not_contains`, `starts_with`, `ends_with`, `is_empty`, `is_not_empty`, `in`, `not_in`, `between` به همراه گروه‌بندی منطقی `AND` و `OR`.

### اقدامات قابل اجرا (Actions):
۱. `add_customer_tag`: افزودن برچسب رنگی به مشتری
۲. `remove_customer_tag`: حذف برچسب از مشتری
۳. `create_task`: ایجاد خودکار وظیفه پیگیری با مهلت و اولویت
۴. `update_task`: تغییر وضعیت و اولویت وظیفه
۵. `create_activity`: ثبت فعالیت تعاملی در تایم‌لاین CRM
۶. `create_notification`: ارسال اعلان درون‌برنامه‌ای به کاربران
۷. `change_order_status`: تغییر وضعیت سفارش در ووکامرس
۸. `add_order_note`: درج یادداشت خصوصی یا مشتری روی سفارش
۹. `update_product_stock`: تنظیم و تغییر موجودی انبار محصول
۱۰. `update_product_status`: تغییر وضعیت انتشار کالا (publish, draft)

---

## ۵. شبیه‌سازی و تست بدون اثر جانبی (Dry Run)
سرویس `AutomationDryRunService` امکان تست دقیق شروط و پیش‌نمایش متغیرها و اقدامات را بدون اعمال هیچ‌گونه تغییر در پایگاه داده یا ووکامرس فراهم می‌سازد. کاربران پیش از فعال‌سازی اتوماسیون می‌توانند سناریوی خود را با داده‌های نمونه آزمایش نمایند.

---

## ۶. اجرای زمان‌بندی‌شده بر روی هاست اشتراکی (CLI & Cron)
اتوماسیون‌های با محرک `scheduled` با دستور سبک زیر اجرا می‌شوند:
```bash
php cli.php automations:run-scheduled
```
این فرایند در هر اجرا دسته‌ای محدود (پیش‌فرض ۵۰ رکورد) را پردازش کرده و بدون فشار بر منابع هاست اشتراکی خاتمه می‌یابد.
