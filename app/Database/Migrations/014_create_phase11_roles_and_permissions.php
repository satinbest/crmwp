<?php

class CreatePhase11RolesAndPermissions
{
    public function up(PDO $pdo): void
    {
        // 1. Enhance roles table with slug and status if not present
        $cols = $pdo->query("SHOW COLUMNS FROM `roles`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('slug', $cols, true)) {
            $pdo->exec("ALTER TABLE `roles` ADD COLUMN `slug` VARCHAR(60) NULL AFTER `name`;");
            $pdo->exec("UPDATE `roles` SET `slug` = LOWER(REPLACE(`name`, ' ', '_')) WHERE `slug` IS NULL;");
            $pdo->exec("ALTER TABLE `roles` MODIFY COLUMN `slug` VARCHAR(60) NOT NULL UNIQUE;");
        }

        if (!in_array('status', $cols, true)) {
            $pdo->exec("ALTER TABLE `roles` ADD COLUMN `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER `description`;");
        }

        // 2. Centralized permission list covering all phases and modules
        $permissions = [
            // Dashboard
            ['dashboard.view', 'dashboard', 'مشاهده داشبورد', 'مشاهده آمار و نمودارهای صفحه اصلی'],

            // Customers
            ['customers.view', 'customers', 'مشاهده مشتریان', 'مشاهده فهرست و پروفایل مشتریان'],
            ['customers.update', 'customers', 'ویرایش مشتریان', 'بروزرسانی مشخصات و آدرس مشتریان'],
            ['customers.delete', 'customers', 'حذف مشتریان', 'حذف حساب مشتریان'],
            ['customers.bulk', 'customers', 'عملیات گروهی مشتریان', 'اجرای عملیات گروهی روی مشتریان'],

            // Orders
            ['orders.view', 'orders', 'مشاهده سفارش‌ها', 'مشاهده فهرست و جزییات سفارش‌های ووکامرس'],
            ['orders.update', 'orders', 'ویرایش سفارش‌ها', 'ویرایش مقادیر و یادداشت‌های سفارش'],
            ['orders.change_status', 'orders', 'تغییر وضعیت سفارش', 'تغییر وضعیت سفارش‌ها در ووکامرس'],
            ['orders.add_note', 'orders', 'ثبت یادداشت سفارش', 'افزودن یادداشت خصوصی یا مشتری برای سفارش'],
            ['orders.refund', 'orders', 'استرداد وجه سفارش', 'ثبت استرداد وجه برای سفارش'],
            ['orders.bulk', 'orders', 'عملیات گروهی سفارش‌ها', 'اجرای عملیات گروهی روی سفارش‌ها'],

            // Products
            ['products.view', 'products', 'مشاهده محصولات', 'مشاهده فهرست و جزییات محصولات'],
            ['products.create', 'products', 'ایجاد محصول', 'تعریف و ثبت محصول جدید در ووکامرس'],
            ['products.update', 'products', 'ویرایش محصول', 'ویرایش قیمت، مشخصات و تصاویر محصول'],
            ['products.delete', 'products', 'حذف محصول', 'انتقال به زباله‌دان یا حذف دائم محصول'],
            ['products.manage_inventory', 'products', 'مدیریت موجودی محصول', 'تغییر وضعیت و تعداد موجودی انبار محصول'],
            ['products.manage_variations', 'products', 'مدیریت متغیرها', 'مدیریت متغیرها و تنوع‌های محصول متغیر'],
            ['products.bulk', 'products', 'عملیات گروهی محصولات', 'اجرای تغییر قیمت، دسته‌بندی و وضعیت گروهی'],

            // Inventory
            ['inventory.view', 'inventory', 'مشاهده انبار', 'مشاهده کارتابل اختصاصی انبار و کالاهای ناموجود'],
            ['inventory.update', 'inventory', 'تغییر سریع موجودی', 'افزایش، کاهش و تنظیم مستقیم موجودی کالا'],
            ['inventory.manage_stock', 'inventory', 'پیکربندی انبار', 'تغییر آستانه کمبود موجودی و پیش‌خرید'],
            ['inventory.bulk', 'inventory', 'عملیات گروهی انبار', 'مدیریت موجودی گروهی انبار'],

            // CRM
            ['crm.view', 'crm', 'مشاهده CRM', 'مشاهده پیشخوان CRM و آمار تعاملات مشتریان'],
            ['crm.manage', 'crm', 'مدیریت CRM', 'دسترسی کامل مدیریتی به امکانات CRM'],

            // Segments
            ['segments.view', 'segments', 'مشاهده بخش‌بندی‌ها', 'مشاهده بخش‌های مشتریان'],
            ['segments.create', 'segments', 'ایجاد بخش‌بندی', 'تعریف سگمنت پویا با شرط‌ساز'],
            ['segments.update', 'segments', 'ویرایش بخش‌بندی', 'ویرایش شروط و نام سگمنت'],
            ['segments.delete', 'segments', 'حذف بخش‌بندی', 'حذف سگمنت مشتریان'],

            // Tags
            ['tags.view', 'tags', 'مشاهده برچسب‌ها', 'مشاهده برچسب‌های مشتریان'],
            ['tags.create', 'tags', 'ایجاد برچسب', 'تعریف تگ رنگی جدید'],
            ['tags.update', 'tags', 'ویرایش برچسب', 'تغییر نام و رنگ برچسب'],
            ['tags.delete', 'tags', 'حذف برچسب', 'حذف برچسب مشتری'],

            // Tasks
            ['tasks.view', 'tasks', 'مشاهده وظایف', 'مشاهده کارتابل وظایف تیمی و اختصاصی'],
            ['tasks.create', 'tasks', 'ایجاد وظیفه', 'ثبت وظیفه پیگیری جدید'],
            ['tasks.update', 'tasks', 'ویرایش وظیفه', 'تغییر وضعیت، مهلت و متن وظیفه'],
            ['tasks.delete', 'tasks', 'حذف وظیفه', 'حذف وظیفه پیگیری'],
            ['tasks.assign', 'tasks', 'تخصیص وظیفه', 'ارجاع وظیفه به سایر اعضای تیم'],

            // Activities
            ['activities.view', 'activities', 'مشاهده فعالیت‌ها', 'مشاهده تایم‌لاین رویدادها و سوابق تعاملات'],

            // Reports
            ['reports.view', 'reports', 'مشاهده گزارش‌ها', 'مشاهده داشبورد تحلیل و گزارش‌های پایه'],
            ['reports.sales', 'reports', 'گزارش فروش', 'تحلیل درآمد و روند فروش دوره‌ای'],
            ['reports.orders', 'reports', 'گزارش سفارش‌ها', 'تحلیل آماری سفارش‌ها و وضعیت‌ها'],
            ['reports.customers', 'reports', 'گزارش مشتریان', 'گزارش ارزش طول عمر مشتری و وفاداری'],
            ['reports.products', 'reports', 'گزارش محصولات', 'تحلیل کالاهای پرفروش و کم‌گردش'],
            ['reports.export', 'reports', 'خروجی اکسل و CSV', 'دریافت خروجی داده‌ها و گزارش‌ها'],

            // Bulk Operations
            ['bulk.view', 'bulk', 'مشاهده عملیات گروهی', 'مشاهده تاریخچه و وضعیت عملیات گروهی'],
            ['bulk.preview', 'bulk', 'پیش‌نمایش عملیات', 'بررسی تأثیر عملیات پیش از اجرا'],
            ['bulk.execute', 'bulk', 'اجرای عملیات گروهی', 'اجرای تغییرات دسته‌جمعی سرورساید'],
            ['bulk.cancel', 'bulk', 'لغو عملیات', 'متوقف‌سازی عملیات گروهی در حال اجرا'],

            // Stores
            ['stores.view', 'stores', 'مشاهده فروشگاه‌ها', 'مشاهده فهرست اتصالات و وضعیت فروشگاه‌ها'],
            ['stores.create', 'stores', 'اتصال فروشگاه جدید', 'افزودن فروشگاه ووکامرس جدید'],
            ['stores.update', 'stores', 'ویرایش اتصال فروشگاه', 'تغییر کلیدها و تنظیمات فروشگاه'],
            ['stores.delete', 'stores', 'حذف فروشگاه', 'قطع اتصال و حذف فروشگاه'],
            ['stores.manage', 'stores', 'مدیریت اتصالات', 'تست اتصال و مدیریت قابلیت‌های ووکامرس'],

            // Users
            ['users.view', 'users', 'مشاهده کاربران', 'مشاهده فهرست اعضای تیم و کاربران'],
            ['users.create', 'users', 'ایجاد کاربر', 'تعریف کاربر جدید با نقش و فروشگاه'],
            ['users.update', 'users', 'ویرایش کاربر', 'ویرایش مشخصات، نقش و دسترسی‌های کاربر'],
            ['users.delete', 'users', 'حذف کاربر', 'حذف حساب کاربری عضو تیم'],
            ['users.manage', 'users', 'مدیریت کامل کاربران', 'تغییر رمز، فعال/غیرفعال‌سازی کاربر'],

            // Roles
            ['roles.view', 'roles', 'مشاهده نقش‌ها', 'مشاهده فهرست نقش‌ها و ماتریس دسترسی'],
            ['roles.create', 'roles', 'ایجاد نقش', 'تعریف نقش کاربری جدید'],
            ['roles.update', 'roles', 'ویرایش نقش', 'تغییر نام و مجوزهای نقش'],
            ['roles.delete', 'roles', 'حذف نقش', 'حذف نقش‌های سفارشی'],
            ['roles.manage', 'roles', 'مدیریت مجوزها', 'تخصیص مجوزها و تکثیر نقش'],

            // Settings & Logs
            ['settings.view', 'settings', 'مشاهده تنظیمات', 'مشاهده پیکربندی و وضعیت سیستم'],
            ['settings.manage', 'settings', 'مدیریت تنظیمات', 'تغییر تنظیمات عمومی سامانه'],
            ['logs.view', 'logs', 'مشاهده لاگ‌های بازرسی', 'بررسی سوابق امنیتی و تغییرات کاربران'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO `permissions` (`name`, `group_name`, `display_name`, `description`, `created_at`, `updated_at`)
            VALUES (:name, :group, :display, :desc, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `group_name` = VALUES(`group_name`),
                `display_name` = VALUES(`display_name`),
                `description` = VALUES(`description`)
        ");

        foreach ($permissions as $p) {
            $stmt->execute([
                'name' => $p[0],
                'group' => $p[1],
                'display' => $p[2],
                'desc' => $p[3],
            ]);
        }

        // 3. Ensure Default Roles exist with clean slugs
        $defaultRoles = [
            ['Admin', 'admin', 'مدیر ارشد', 'دسترسی نامحدود به تمام بخش‌ها، تنظیمات و امنیت سیستم'],
            ['Manager', 'manager', 'مدیر فروشگاه', 'مدیریت بخش‌های تجاری، مشتریان، انبار، CRM و گزارش‌ها'],
            ['Sales', 'sales', 'کارشناس فروش', 'بررسی سفارش‌ها، تعامل با مشتریان و مدیریت وظایف CRM'],
            ['Inventory Manager', 'inventory_manager', 'مدیر انبار', 'مدیریت اختصاصی محصولات، انبار و موجودی کالاها'],
            ['Support', 'support', 'پشتیبانی مشتریان', 'پاسخگویی به مشتریان، یادداشت‌ها و فعالیت‌های CRM'],
            ['Viewer', 'viewer', 'مشاهده‌گر', 'مشاهده گزارش‌ها و آمار سیستم به صورت فقط‌خواندنی'],
        ];

        $roleStmt = $pdo->prepare("
            INSERT INTO `roles` (`name`, `slug`, `display_name`, `description`, `status`, `created_at`, `updated_at`)
            VALUES (:name, :slug, :display, :desc, 'active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `slug` = VALUES(`slug`),
                `display_name` = VALUES(`display_name`),
                `description` = VALUES(`description`)
        ");

        foreach ($defaultRoles as $r) {
            $roleStmt->execute([
                'name' => $r[0],
                'slug' => $r[1],
                'display' => $r[2],
                'desc' => $r[3],
            ]);
        }

        // 4. Assign permissions to roles
        $assignPerms = function(string $roleSlug, array $permNames) use ($pdo) {
            $roleId = $pdo->query("SELECT id FROM `roles` WHERE `slug` = " . $pdo->quote($roleSlug) . " OR `name` = " . $pdo->quote($roleSlug))->fetchColumn();
            if (!$roleId) return;

            // Fetch permission IDs
            if (in_array('*', $permNames, true)) {
                $permIds = $pdo->query("SELECT id FROM `permissions`")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $in = implode(',', array_map(fn($p) => $pdo->quote($p), $permNames));
                $permIds = $pdo->query("SELECT id FROM `permissions` WHERE `name` IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
            }

            $insert = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($permIds as $pid) {
                $insert->execute([$roleId, $pid]);
            }
        };

        // Admin: ALL
        $assignPerms('admin', ['*']);

        // Manager: Stores, Customers, Orders, Products, Inventory, CRM, Reports, Bulk (excluding system security)
        $assignPerms('manager', [
            'dashboard.view', 'stores.view',
            'customers.view', 'customers.update', 'customers.bulk',
            'orders.view', 'orders.update', 'orders.change_status', 'orders.add_note', 'orders.refund', 'orders.bulk',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.manage_inventory', 'products.manage_variations', 'products.bulk',
            'inventory.view', 'inventory.update', 'inventory.manage_stock', 'inventory.bulk',
            'crm.view', 'crm.manage',
            'segments.view', 'segments.create', 'segments.update', 'segments.delete',
            'tags.view', 'tags.create', 'tags.update', 'tags.delete',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign',
            'activities.view',
            'reports.view', 'reports.sales', 'reports.orders', 'reports.customers', 'reports.products', 'reports.export',
            'bulk.view', 'bulk.preview', 'bulk.execute', 'bulk.cancel',
            'users.view', 'roles.view', 'settings.view', 'logs.view',
        ]);

        // Sales: Customers, Orders, CRM, Tasks, Activities
        $assignPerms('sales', [
            'dashboard.view', 'stores.view',
            'customers.view', 'customers.update',
            'orders.view', 'orders.update', 'orders.change_status', 'orders.add_note',
            'crm.view',
            'tasks.view', 'tasks.create', 'tasks.update',
            'activities.view',
        ]);

        // Inventory Manager: Products & Inventory
        $assignPerms('inventory_manager', [
            'dashboard.view', 'stores.view',
            'products.view', 'products.create', 'products.update', 'products.manage_inventory', 'products.manage_variations', 'products.bulk',
            'inventory.view', 'inventory.update', 'inventory.manage_stock', 'inventory.bulk',
            'activities.view',
        ]);

        // Support: Customers, Orders, CRM, Activities, Notes
        $assignPerms('support', [
            'dashboard.view', 'stores.view',
            'customers.view',
            'orders.view', 'orders.add_note',
            'crm.view',
            'tasks.view', 'tasks.create', 'tasks.update',
            'activities.view',
        ]);

        // Viewer: Read-only access
        $assignPerms('viewer', [
            'dashboard.view', 'stores.view',
            'customers.view',
            'orders.view',
            'products.view',
            'inventory.view',
            'crm.view',
            'activities.view',
            'reports.view',
        ]);

        // 5. Seed Demo Users with encrypted/hashed password
        $defaultPassword = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        $storeId = (int)($pdo->query("SELECT id FROM `stores` ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

        $demoUsers = [
            ['admin', 'admin@crmwp.local', 'حسین', 'مدیری', 'admin'],
            ['manager', 'manager@crmwp.local', 'علی', 'فروشگاهی', 'manager'],
            ['sales', 'sales@crmwp.local', 'سارا', 'کریمی', 'sales'],
            ['inventory_mgr', 'inventory@crmwp.local', 'رضا', 'انباردار', 'inventory_manager'],
            ['support', 'support@crmwp.local', 'مهسا', 'پشتیبان', 'support'],
            ['viewer', 'viewer@crmwp.local', 'نیما', 'مشاهده‌گر', 'viewer'],
        ];

        $userStmt = $pdo->prepare("
            INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `is_active`, `created_at`, `updated_at`)
            VALUES (:username, :email, :pass, :first, :last, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `first_name` = VALUES(`first_name`),
                `last_name` = VALUES(`last_name`),
                `is_active` = 1
        ");

        $assignUserRole = $pdo->prepare("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)");
        $assignUserStore = $pdo->prepare("INSERT IGNORE INTO `user_stores` (`user_id`, `store_id`) VALUES (?, ?)");

        foreach ($demoUsers as $du) {
            $userStmt->execute([
                'username' => $du[0],
                'email' => $du[1],
                'pass' => $defaultPassword,
                'first' => $du[2],
                'last' => $du[3],
            ]);

            $uid = (int)$pdo->query("SELECT id FROM `users` WHERE `username` = " . $pdo->quote($du[0]))->fetchColumn();
            $rid = (int)$pdo->query("SELECT id FROM `roles` WHERE `slug` = " . $pdo->quote($du[4]) . " OR `name` = " . $pdo->quote($du[4]))->fetchColumn();

            if ($uid && $rid) {
                $assignUserRole->execute([$uid, $rid]);
            }
            if ($uid && $storeId) {
                $assignUserStore->execute([$uid, $storeId]);
            }
        }
    }

    public function down(PDO $pdo): void
    {
        // No destruct in down
    }
}
