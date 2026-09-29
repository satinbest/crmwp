<?php

namespace App\Database\Seeders;

use App\Database\Connection;
use App\Support\Security;
use PDO;

class DatabaseSeeder
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function run(?bool $seedDemo = null): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedRolePermissions();

        $isProduction = \App\Support\Env::get('APP_ENV', 'production') === 'production';
        if ($seedDemo === false || ($seedDemo === null && $isProduction)) {
            // Production clean seed: only RBAC roles & permissions, no fake demo stores/users
            return;
        }

        $this->seedUsers();
        $this->seedStores();
        $this->seedUserStores();
        $this->seedStoreCrmData();
        $this->seedDemoPhase12Notifications();
        $this->seedDemoWebhookData();
        $this->seedDemoAutomations();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            // Customers
            ['name' => 'customers.view', 'group_name' => 'customers', 'display_name' => 'مشاهده مشتریان', 'description' => 'مشاهده لیست و جزئیات مشتریان'],
            ['name' => 'customers.edit', 'group_name' => 'customers', 'display_name' => 'ویرایش مشتریان', 'description' => 'ویرایش اطلاعات و متادیتای مشتریان'],

            // Orders
            ['name' => 'orders.view', 'group_name' => 'orders', 'display_name' => 'مشاهده سفارش‌ها', 'description' => 'مشاهده لیست و جزئیات سفارش‌های ووکامرس'],
            ['name' => 'orders.edit', 'group_name' => 'orders', 'display_name' => 'ویرایش سفارش‌ها', 'description' => 'تغییر وضعیت و افزودن یادداشت به سفارش'],
            ['name' => 'orders.refund', 'group_name' => 'orders', 'display_name' => 'استرداد وجه سفارش', 'description' => 'ثبت استرداد وجه (Refund) در ووکامرس'],

            // Products
            ['name' => 'products.view', 'group_name' => 'products', 'display_name' => 'مشاهده محصولات', 'description' => 'مشاهده فهرست و جزئیات محصولات و متغیرها'],
            ['name' => 'products.create', 'group_name' => 'products', 'display_name' => 'ایجاد محصول', 'description' => 'تعریف محصول جدید در فروشگاه'],
            ['name' => 'products.edit', 'group_name' => 'products', 'display_name' => 'ویرایش محصول', 'description' => 'تغییر قیمت، مشخصات و توضیحات محصول'],
            ['name' => 'products.delete', 'group_name' => 'products', 'display_name' => 'حذف محصول', 'description' => 'حذف یا انتقال محصول به زباله‌دان'],

            // Inventory
            ['name' => 'inventory.view', 'group_name' => 'inventory', 'display_name' => 'مشاهده انبار', 'description' => 'مشاهده وضعیت موجودی انبار و کالاهای ناموجود'],
            ['name' => 'inventory.edit', 'group_name' => 'inventory', 'display_name' => 'مدیریت انبار', 'description' => 'تغییر و تنظیم موجودی و وضعیت انبار'],

            // CRM
            ['name' => 'crm.view', 'group_name' => 'crm', 'display_name' => 'مشاهده CRM', 'description' => 'مشاهده وظایف، برچسب‌ها، سگمنت‌ها و تایم‌لاین'],
            ['name' => 'crm.manage', 'group_name' => 'crm', 'display_name' => 'مدیریت CRM', 'description' => 'ثبت یادداشت، ایجاد تسک، تخصیص برچسب و دسته‌بندی'],

            // Bulk Operations
            ['name' => 'bulk.view', 'group_name' => 'bulk', 'display_name' => 'مشاهده عملیات گروهی', 'description' => 'مشاهده لاگ و پیش‌نمایش عملیات‌های انبوه'],
            ['name' => 'bulk.execute', 'group_name' => 'bulk', 'display_name' => 'اجرای عملیات گروهی', 'description' => 'اجرای تغییرات دسته‌جمعی روی قیمت‌ها، محصولات یا وضعیت‌ها'],

            // Reports
            ['name' => 'reports.view', 'group_name' => 'reports', 'display_name' => 'مشاهده گزارش‌ها', 'description' => 'مشاهده داشبورد آماری، درآمد و نمودارها'],

            // Stores
            ['name' => 'stores.view', 'group_name' => 'stores', 'display_name' => 'مشاهده فروشگاه‌ها', 'description' => 'مشاهده لیست فروشگاه‌های ووکامرس متصل'],
            ['name' => 'stores.manage', 'group_name' => 'stores', 'display_name' => 'مدیریت فروشگاه‌ها', 'description' => 'اتصال، ویرایش و تست اتصال فروشگاه جدید'],

            // Users & RBAC
            ['name' => 'users.view', 'group_name' => 'users', 'display_name' => 'مشاهده کاربران', 'description' => 'مشاهده اعضای سیستم و نقش‌ها'],
            ['name' => 'users.manage', 'group_name' => 'users', 'display_name' => 'مدیریت کاربران', 'description' => 'افزودن و تغییر دسترسی‌های کاربران سیستم'],

            // Settings
            ['name' => 'settings.view', 'group_name' => 'settings', 'display_name' => 'مشاهده تنظیمات', 'description' => 'مشاهده تنظیمات سیستم'],
            ['name' => 'settings.manage', 'group_name' => 'settings', 'display_name' => 'مدیریت تنظیمات', 'description' => 'تغییر تنظیمات سامانه'],
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO `permissions` (`name`, `group_name`, `display_name`, `description`)
            VALUES (:name, :group_name, :display_name, :description)
            ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`), `description` = VALUES(`description`)
        ");

        foreach ($permissions as $p) {
            $stmt->execute($p);
        }
    }

    private function seedRoles(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'administrator', 'display_name' => 'مدیر ارشد', 'description' => 'دسترسی نامحدود به تمام بخش‌های سیستم'],
            ['name' => 'Manager', 'slug' => 'manager', 'display_name' => 'مدیر فروشگاه', 'description' => 'مدیریت کامل بخش‌های تجاری و CRM'],
            ['name' => 'Sales', 'slug' => 'sales', 'display_name' => 'کارشناس فروش', 'description' => 'بررسی سفارش‌ها و مدیریت تعاملات مشتریان'],
            ['name' => 'Support', 'slug' => 'support', 'display_name' => 'پشتیبانی', 'description' => 'پاسخگویی به مشتریان و ثبت وظایف پیگیری'],
            ['name' => 'Warehouse', 'slug' => 'warehouse', 'display_name' => 'انباردار', 'description' => 'مدیریت موجودی انبار و تأمین کالا'],
            ['name' => 'Viewer', 'slug' => 'viewer', 'display_name' => 'مشاهده‌گر', 'description' => 'مشاهده گزارش‌ها و آمار به صورت فقط‌خواندنی'],
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO `roles` (`name`, `slug`, `display_name`, `description`)
            VALUES (:name, :slug, :display_name, :description)
            ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`), `display_name` = VALUES(`display_name`), `description` = VALUES(`description`)
        ");

        foreach ($roles as $r) {
            $stmt->execute($r);
        }
    }

    private function seedRolePermissions(): void
    {
        $roleStmt = $this->pdo->query("SELECT `name`, `id` FROM `roles`");
        $roles = $roleStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $permStmt = $this->pdo->query("SELECT `name`, `id` FROM `permissions`");
        $permMap = $permStmt->fetchAll(PDO::FETCH_KEY_PAIR); // name => id

        // Admin gets all permissions
        $adminId = $roles['Admin'] ?? null;
        if ($adminId) {
            $insert = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($permMap as $pName => $pId) {
                $insert->execute([$adminId, $pId]);
            }
        }

        // Manager permissions
        $managerId = $roles['Manager'] ?? null;
        if ($managerId) {
            $managerPerms = [
                'customers.view', 'customers.edit',
                'orders.view', 'orders.edit', 'orders.refund',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'inventory.view', 'inventory.edit',
                'crm.view', 'crm.manage',
                'bulk.view', 'bulk.execute',
                'reports.view',
                'stores.view',
                'users.view',
                'settings.view'
            ];
            $insert = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($managerPerms as $pName) {
                if (isset($permMap[$pName])) {
                    $insert->execute([$managerId, $permMap[$pName]]);
                }
            }
        }

        // Sales permissions
        $salesId = $roles['Sales'] ?? null;
        if ($salesId) {
            $salesPerms = [
                'customers.view', 'customers.edit',
                'orders.view', 'orders.edit',
                'products.view',
                'inventory.view',
                'crm.view', 'crm.manage',
                'reports.view',
                'stores.view'
            ];
            $insert = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($salesPerms as $pName) {
                if (isset($permMap[$pName])) {
                    $insert->execute([$salesId, $permMap[$pName]]);
                }
            }
        }

        // Support permissions
        $supportId = $roles['Support'] ?? null;
        if ($supportId) {
            $supportPerms = [
                'customers.view',
                'orders.view',
                'products.view',
                'crm.view', 'crm.manage',
                'stores.view'
            ];
            $insert = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($supportPerms as $pName) {
                if (isset($permMap[$pName])) {
                    $insert->execute([$supportId, $permMap[$pName]]);
                }
            }
        }

        // Warehouse permissions
        $warehouseId = $roles['Warehouse'] ?? null;
        if ($warehouseId) {
            $warehousePerms = [
                'products.view',
                'inventory.view', 'inventory.edit',
                'orders.view',
                'stores.view'
            ];
            $insert = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($warehousePerms as $pName) {
                if (isset($permMap[$pName])) {
                    $insert->execute([$warehouseId, $permMap[$pName]]);
                }
            }
        }

        // Viewer permissions
        $viewerId = $roles['Viewer'] ?? null;
        if ($viewerId) {
            $viewerPerms = [
                'customers.view', 'orders.view', 'products.view',
                'inventory.view', 'crm.view', 'reports.view', 'stores.view'
            ];
            $insert = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($viewerPerms as $pName) {
                if (isset($permMap[$pName])) {
                    $insert->execute([$viewerId, $permMap[$pName]]);
                }
            }
        }
    }

    private function seedUsers(): void
    {
        $roleStmt = $this->pdo->query("SELECT `id`, `name` FROM `roles`");
        $roles = $roleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $roleMap = array_flip($roles);

        $adminPassword = Security::hashPassword('AdminPassword123!');
        $managerPassword = Security::hashPassword('ManagerPassword123!');
        $salesPassword = Security::hashPassword('SalesPassword123!');
        $viewerPassword = Security::hashPassword('ViewerPassword123!');

        // 1. Admin User
        $stmt = $this->pdo->prepare("
            INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `is_active`)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `is_active` = 1
        ");
        $stmt->execute(['admin', 'admin@crmwp.local', $adminPassword, 'حسین', 'مدیری']);

        $userStmt = $this->pdo->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
        $userStmt->execute(['admin']);
        $adminUserId = (int)$userStmt->fetchColumn();

        if ($adminUserId && isset($roleMap['Admin'])) {
            $this->pdo->prepare("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)")
                ->execute([$adminUserId, $roleMap['Admin']]);
        }

        // 2. Manager User
        $stmt->execute(['manager', 'manager@crmwp.local', $managerPassword, 'سارا', 'رضایی']);
        $userStmt->execute(['manager']);
        $managerUserId = (int)$userStmt->fetchColumn();

        if ($managerUserId && isset($roleMap['Manager'])) {
            $this->pdo->prepare("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)")
                ->execute([$managerUserId, $roleMap['Manager']]);
        }

        // 3. Sales User
        $stmt->execute(['sales', 'sales@crmwp.local', $salesPassword, 'علی', 'احمدی']);
        $userStmt->execute(['sales']);
        $salesUserId = (int)$userStmt->fetchColumn();

        if ($salesUserId && isset($roleMap['Sales'])) {
            $this->pdo->prepare("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)")
                ->execute([$salesUserId, $roleMap['Sales']]);
        }

        // 4. Viewer User
        $stmt->execute(['viewer', 'viewer@crmwp.local', $viewerPassword, 'مریم', 'کاظمی']);
        $userStmt->execute(['viewer']);
        $viewerUserId = (int)$userStmt->fetchColumn();

        if ($viewerUserId && isset($roleMap['Viewer'])) {
            $this->pdo->prepare("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)")
                ->execute([$viewerUserId, $roleMap['Viewer']]);
        }
    }

    private function seedStores(): void
    {
        $repo = new \App\Repositories\StoreRepository($this->pdo);

        $demoStores = [
            [
                'name' => 'فروشگاه مرکزی مد و پوشاک',
                'url' => 'demo://fashion-store.local',
                'consumer_key' => 'ck_demo_fashion_store_key',
                'consumer_secret' => 'cs_demo_fashion_store_secret',
                'status' => 'active',
                'woocommerce_version' => '9.2.0',
                'wordpress_version' => '6.6.1',
                'hpos_enabled' => 1,
                'currency' => 'IRR',
                'timezone' => 'Asia/Tehran',
                'is_demo' => 1,
                'icon' => 'bag-2',
                'capabilities' => [
                    'rest_api_available' => true,
                    'api_version' => 'wc/v3',
                    'woocommerce_version' => '9.2.0',
                    'wordpress_version' => '6.6.1',
                    'hpos_enabled' => true,
                    'currency' => 'IRR',
                    'currency_symbol' => '﷼',
                    'timezone' => 'Asia/Tehran',
                    'order_refunds_supported' => true,
                    'webhooks_supported' => true,
                    'custom_order_statuses' => [
                        ['slug' => 'pending', 'name' => 'در انتظار پرداخت'],
                        ['slug' => 'processing', 'name' => 'در حال پردازش'],
                        ['slug' => 'custom-prep', 'name' => 'در حال بسته‌بندی سفارشی'],
                        ['slug' => 'completed', 'name' => 'تکمیل شده'],
                        ['slug' => 'cancelled', 'name' => 'لغو شده'],
                    ],
                    'detected_at' => date('Y-m-d H:i:s'),
                ],
                'last_connection_check' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'فروشگاه دیجیتال و الکترونیک',
                'url' => 'demo://digital-store.local',
                'consumer_key' => 'ck_demo_digital_store_key',
                'consumer_secret' => 'cs_demo_digital_store_secret',
                'status' => 'active',
                'woocommerce_version' => '9.2.0',
                'wordpress_version' => '6.6.1',
                'hpos_enabled' => 1,
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'is_demo' => 1,
                'icon' => 'mobile',
                'capabilities' => [
                    'rest_api_available' => true,
                    'api_version' => 'wc/v3',
                    'woocommerce_version' => '9.2.0',
                    'wordpress_version' => '6.6.1',
                    'hpos_enabled' => true,
                    'currency' => 'USD',
                    'currency_symbol' => '$',
                    'timezone' => 'America/New_York',
                    'order_refunds_supported' => true,
                    'webhooks_supported' => true,
                    'custom_order_statuses' => [
                        ['slug' => 'pending', 'name' => 'Pending payment'],
                        ['slug' => 'processing', 'name' => 'Processing'],
                        ['slug' => 'completed', 'name' => 'Completed'],
                        ['slug' => 'cancelled', 'name' => 'Cancelled'],
                    ],
                    'detected_at' => date('Y-m-d H:i:s'),
                ],
                'last_connection_check' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'فروشگاه کتاب و لوازم‌التحریر',
                'url' => 'demo://books-stationery.local',
                'consumer_key' => 'ck_demo_books_store_key',
                'consumer_secret' => 'cs_demo_books_store_secret',
                'status' => 'active',
                'woocommerce_version' => '9.2.0',
                'wordpress_version' => '6.6.1',
                'hpos_enabled' => 1,
                'currency' => 'EUR',
                'timezone' => 'Europe/Berlin',
                'is_demo' => 1,
                'icon' => 'book',
                'capabilities' => [
                    'rest_api_available' => true,
                    'api_version' => 'wc/v3',
                    'woocommerce_version' => '9.2.0',
                    'wordpress_version' => '6.6.1',
                    'hpos_enabled' => true,
                    'currency' => 'EUR',
                    'currency_symbol' => '€',
                    'timezone' => 'Europe/Berlin',
                    'order_refunds_supported' => true,
                    'webhooks_supported' => true,
                    'custom_order_statuses' => [
                        ['slug' => 'pending', 'name' => 'In Wartestellung'],
                        ['slug' => 'processing', 'name' => 'In Bearbeitung'],
                        ['slug' => 'completed', 'name' => 'Abgeschlossen'],
                        ['slug' => 'cancelled', 'name' => 'Storniert'],
                    ],
                    'detected_at' => date('Y-m-d H:i:s'),
                ],
                'last_connection_check' => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($demoStores as $storeData) {
            $existing = $this->pdo->prepare("SELECT id FROM `stores` WHERE `name` = ? OR `url` = ?");
            $existing->execute([$storeData['name'], $storeData['url']]);
            $existingId = $existing->fetchColumn();

            if (!$existingId) {
                // If there's an existing single store with IRR currency (from previous seeding), update it to match demo store 1
                if ($storeData['currency'] === 'IRR') {
                    $oldSingle = $this->pdo->query("SELECT id FROM `stores` WHERE `currency` = 'IRR' LIMIT 1")->fetchColumn();
                    if ($oldSingle) {
                        $this->pdo->prepare("UPDATE `stores` SET `name` = ?, `url` = ?, `is_demo` = 1, `icon` = ?, `status` = 'active', `timezone` = ? WHERE `id` = ?")
                            ->execute([$storeData['name'], $storeData['url'], $storeData['icon'], $storeData['timezone'], $oldSingle]);
                        continue;
                    }
                }
                $repo->create($storeData);
            } else {
                $this->pdo->prepare("UPDATE `stores` SET `is_demo` = 1, `icon` = ?, `status` = 'active', `currency` = ?, `timezone` = ? WHERE `id` = ?")
                    ->execute([$storeData['icon'], $storeData['currency'], $storeData['timezone'], $existingId]);
            }
        }
    }

    private function seedUserStores(): void
    {
        $stores = $this->pdo->query("SELECT id, name, currency FROM `stores` ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($stores)) {
            return;
        }

        $storeMap = [];
        foreach ($stores as $s) {
            $storeMap[$s['currency']] = (int)$s['id'];
        }

        $storeIRR = $storeMap['IRR'] ?? $stores[0]['id'];
        $storeUSD = $storeMap['USD'] ?? ($stores[1]['id'] ?? $storeIRR);
        $storeEUR = $storeMap['EUR'] ?? ($stores[2]['id'] ?? $storeIRR);

        // Fetch users
        $adminId = (int)$this->pdo->query("SELECT id FROM `users` WHERE `username` = 'admin'")->fetchColumn();
        $managerId = (int)$this->pdo->query("SELECT id FROM `users` WHERE `username` = 'manager'")->fetchColumn();
        $salesId = (int)$this->pdo->query("SELECT id FROM `users` WHERE `username` = 'sales'")->fetchColumn();
        $viewerId = (int)$this->pdo->query("SELECT id FROM `users` WHERE `username` = 'viewer'")->fetchColumn();

        $assign = $this->pdo->prepare("INSERT IGNORE INTO `user_stores` (`user_id`, `store_id`) VALUES (?, ?)");

        // Admin: all stores
        if ($adminId) {
            foreach ($stores as $s) {
                $assign->execute([$adminId, (int)$s['id']]);
            }
        }

        // Manager: Store 1 & Store 2
        if ($managerId) {
            $assign->execute([$managerId, $storeIRR]);
            if ($storeUSD !== $storeIRR) {
                $assign->execute([$managerId, $storeUSD]);
            }
        }

        // Sales: Store 2 (USD)
        if ($salesId && $storeUSD) {
            $assign->execute([$salesId, $storeUSD]);
        }

        // Viewer: Store 3 (EUR)
        if ($viewerId && $storeEUR) {
            $assign->execute([$viewerId, $storeEUR]);
        }
    }

    private function seedStoreCrmData(): void
    {
        $stores = $this->pdo->query("SELECT id, currency FROM `stores`")->fetchAll(PDO::FETCH_KEY_PAIR);
        $adminId = (int)$this->pdo->query("SELECT id FROM `users` WHERE `username` = 'admin'")->fetchColumn();

        foreach ($stores as $storeId => $currency) {
            $storeId = (int)$storeId;

            // Check if tags exist for this store
            $tagCount = (int)$this->pdo->query("SELECT COUNT(*) FROM `tags` WHERE `store_id` = {$storeId}")->fetchColumn();
            if ($tagCount === 0) {
                $tagStmt = $this->pdo->prepare("INSERT INTO `tags` (`store_id`, `name`, `color`) VALUES (?, ?, ?)");
                if ($currency === 'USD') {
                    $tagStmt->execute([$storeId, 'B2B Tech', '#2563EB']);
                    $tagStmt->execute([$storeId, 'Early Adopter', '#059669']);
                    $tagStmt->execute([$storeId, 'Warranty Claim', '#DC2626']);
                } elseif ($currency === 'EUR') {
                    $tagStmt->execute([$storeId, 'Buchliebhaber', '#7C3AED']);
                    $tagStmt->execute([$storeId, 'Student', '#0D9488']);
                    $tagStmt->execute([$storeId, 'Bibliothek', '#EA580C']);
                } else {
                    $tagStmt->execute([$storeId, 'مشتری VIP', '#4F46E5']);
                    $tagStmt->execute([$storeId, 'خرید عمده', '#10B981']);
                    $tagStmt->execute([$storeId, 'باشگاه مشتریان', '#F59E0B']);
                }
            }

            // Segments
            $segCount = (int)$this->pdo->query("SELECT COUNT(*) FROM `segments` WHERE `store_id` = {$storeId}")->fetchColumn();
            if ($segCount === 0) {
                $segStmt = $this->pdo->prepare("INSERT INTO `segments` (`store_id`, `name`, `description`, `rules`) VALUES (?, ?, ?, ?)");
                if ($currency === 'USD') {
                    $segStmt->execute([$storeId, 'Laptop Buyers', 'Customers purchasing laptops & workstations', json_encode(['category' => 'computers', 'min_spent' => 500])]);
                    $segStmt->execute([$storeId, 'Enterprise Tech', 'Accounts with multiple orders', json_encode(['min_orders' => 3])]);
                } elseif ($currency === 'EUR') {
                    $segStmt->execute([$storeId, 'Abonnenten', 'Monatliche Buchclub Abonnenten', json_encode(['type' => 'subscription'])]);
                    $segStmt->execute([$storeId, 'Belletristik Leser', 'Kunden mit Interesse an Belletristik', json_encode(['category' => 'fiction'])]);
                } else {
                    $segStmt->execute([$storeId, 'خریداران مد بهاره', 'مشتریانی که در فصل بهار پوشاک سفارش داده‌اند', json_encode(['tag' => 'spring_fashion'])]);
                    $segStmt->execute([$storeId, 'سبد خرید رها شده', 'مشتریانی با سفارش پرداخت نشده', json_encode(['status' => 'pending'])]);
                }
            }

            // Tasks
            $taskCount = (int)$this->pdo->query("SELECT COUNT(*) FROM `tasks` WHERE `store_id` = {$storeId}")->fetchColumn();
            if ($taskCount === 0 && $adminId) {
                $taskStmt = $this->pdo->prepare("INSERT INTO `tasks` (`store_id`, `created_by_user_id`, `title`, `description`, `status`, `due_date`) VALUES (?, ?, ?, ?, ?, ?)");
                if ($currency === 'USD') {
                    $taskStmt->execute([$storeId, $adminId, 'Follow up with Michael Brown for Server quote', 'Call customer regarding bulk server order', 'pending', date('Y-m-d H:i:s', strtotime('+2 days'))]);
                    $taskStmt->execute([$storeId, $adminId, 'Review RMA #204 for Jessica Miller', 'Defective RAM replacement verification', 'in_progress', date('Y-m-d H:i:s', strtotime('+1 day'))]);
                } elseif ($currency === 'EUR') {
                    $taskStmt->execute([$storeId, $adminId, 'Lieferung für Universitätsbibliothek prüfen', 'Großbestellung von Fachbüchern abwickeln', 'pending', date('Y-m-d H:i:s', strtotime('+3 days'))]);
                } else {
                    $taskStmt->execute([$storeId, $adminId, 'تماس جهت سفارش‌های بالای ۵ میلیون', 'پیگیری رضایت‌سنجی خریداران پوشاک رسمی', 'pending', date('Y-m-d H:i:s', strtotime('+2 days'))]);
                    $taskStmt->execute([$storeId, $adminId, 'بررسی استرداد سفارش #1042', 'تاییدیه انبار و بازگشت وجه مشتری', 'completed', date('Y-m-d H:i:s', strtotime('-1 day'))]);
                }
            }

            // Notifications
            $notifCount = (int)$this->pdo->query("SELECT COUNT(*) FROM `notifications` WHERE `store_id` = {$storeId}")->fetchColumn();
            if ($notifCount === 0 && $adminId) {
                $notifStmt = $this->pdo->prepare("INSERT INTO `notifications` (`user_id`, `store_id`, `type`, `title`, `message`, `priority`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($currency === 'USD') {
                    $notifStmt->execute([$adminId, $storeId, 'inventory_low_stock', 'Stock Alert: Ultra Wireless Headphones', 'Only 2 units remaining in stock', 'high', date('Y-m-d H:i:s', strtotime('-30 minutes'))]);
                    $notifStmt->execute([$adminId, $storeId, 'order_status_changed', 'Payment Confirmed: Order #2001', 'Order total $540 paid via Stripe', 'normal', date('Y-m-d H:i:s', strtotime('-2 hours'))]);
                } elseif ($currency === 'EUR') {
                    $notifStmt->execute([$adminId, $storeId, 'order_created', 'Neue Bestellung eingegangen #3001', 'Bestellwert 119.50 € von Hans Müller', 'normal', date('Y-m-d H:i:s', strtotime('-1 hour'))]);
                } else {
                    $notifStmt->execute([$adminId, $storeId, 'inventory_low_stock', 'هشدار موجودی: پیراهن لینن تابستانه', 'موجودی انبار به ۱۸ عدد کاهش یافته است', 'high', date('Y-m-d H:i:s', strtotime('-15 minutes'))]);
                    $notifStmt->execute([$adminId, $storeId, 'order_status_changed', 'سفارش جدید #1042 دریافت شد', 'مبلغ سفارش ۳۴۵,۰۰۰ تومان پرداخت شده است', 'normal', date('Y-m-d H:i:s', strtotime('-1 hour'))]);
                }
            }
        }
    }

    private function seedDemoPhase12Notifications(): void
    {
        $adminId = (int)$this->pdo->query("SELECT id FROM `users` WHERE `username` = 'admin'")->fetchColumn();
        $storeId = (int)$this->pdo->query("SELECT id FROM `stores` ORDER BY id ASC LIMIT 1")->fetchColumn();

        if (!$adminId) {
            return;
        }

        $existing = (int)$this->pdo->query("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = {$adminId} AND `type` = 'task_assigned'")->fetchColumn();
        if ($existing > 0) {
            return;
        }

        $validStoreId = $storeId > 0 ? $storeId : null;

        $demoNotifications = [
            [
                'user_id' => $adminId,
                'store_id' => $validStoreId,
                'type' => 'task_assigned',
                'title' => 'وظیفه جدید تخصیص یافت',
                'message' => 'وظیفه «پیگیری سفارش شماره ۱۰۰۱ مشتری علی رضایی» به شما واگذار گردید.',
                'data' => json_encode(['task_id' => 1, 'order_id' => 1001, 'customer_name' => 'علی رضایی'], JSON_UNESCAPED_UNICODE),
                'priority' => 'high',
                'action_url' => '/crm/tasks',
                'read_at' => null,
            ],
            [
                'user_id' => $adminId,
                'store_id' => $validStoreId,
                'type' => 'low_stock',
                'title' => 'هشدار کمبود موجودی کالا',
                'message' => 'موجودی انبار محصول «لپ‌تاپ گیمینگ ایسوس مدل ROG Strix» به ۳ عدد کاهش یافته است.',
                'data' => json_encode(['product_id' => 301, 'current_stock' => 3, 'low_stock_amount' => 5], JSON_UNESCAPED_UNICODE),
                'priority' => 'urgent',
                'action_url' => '/inventory',
                'read_at' => null,
            ],
            [
                'user_id' => $adminId,
                'store_id' => $validStoreId,
                'type' => 'bulk_operation_completed',
                'title' => 'عملیات دسته‌جمعی تکمیل شد',
                'message' => 'عملیات افزایش قیمت محصولات دسته لپ‌تاپ با موفقیت خاتمه یافت. ۲۴۷ محصول بروزرسانی شدند.',
                'data' => json_encode(['operation_id' => 101, 'processed' => 247, 'failed' => 0], JSON_UNESCAPED_UNICODE),
                'priority' => 'normal',
                'action_url' => '/bulk-operations',
                'read_at' => date('Y-m-d H:i:s', time() - 3600),
            ],
            [
                'user_id' => $adminId,
                'store_id' => $validStoreId,
                'type' => 'order_attention',
                'title' => 'سفارش نیازمند توجه ویژه',
                'message' => 'سفارش شماره ۱۰۰۲ با وضعیت انتقال کارت‌به‌کارت بیش از ۱۲ ساعت در انتظار تایید مانده است.',
                'data' => json_encode(['order_id' => 1002, 'status' => 'pending', 'total' => '24500000'], JSON_UNESCAPED_UNICODE),
                'priority' => 'high',
                'action_url' => '/orders/1002',
                'read_at' => date('Y-m-d H:i:s', time() - 7200),
            ],
            [
                'user_id' => $adminId,
                'store_id' => null, // Global System Notification
                'type' => 'system',
                'title' => 'به‌روزرسانی موفقیت‌آمیز سیستم',
                'message' => 'سامانه مدیریت ووکامرس و CRM به آخرین نسخه با امکانات مرکز اعلان‌ها ارتقا یافت.',
                'data' => json_encode(['version' => '1.12.0', 'release' => 'Phase 12'], JSON_UNESCAPED_UNICODE),
                'priority' => 'low',
                'action_url' => '/dashboard',
                'read_at' => date('Y-m-d H:i:s', time() - 86400),
            ],
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO `notifications` (`user_id`, `store_id`, `type`, `title`, `message`, `data`, `priority`, `action_url`, `read_at`, `created_at`)
            VALUES (:user_id, :store_id, :type, :title, :message, :data, :priority, :action_url, :read_at, NOW())
        ");
        foreach ($demoNotifications as $dn) {
            $stmt->execute($dn);
        }
    }

    private function seedDemoWebhookData(): void
    {
        // Only seed demo webhook logs if not strictly production
        $env = \App\Support\Env::get('APP_ENV', 'development');
        if ($env === 'production') {
            return;
        }

        $stmt = $this->pdo->query("SELECT id FROM `stores` LIMIT 1");
        $storeId = (int)$stmt->fetchColumn();
        if ($storeId <= 0) {
            return;
        }

        // Check if logs already exist
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `webhook_logs` WHERE `store_id` = ?");
        $countStmt->execute([$storeId]);
        if ((int)$countStmt->fetchColumn() > 0) {
            return;
        }

        $logRepo = new \App\Repositories\WebhookLogRepository($this->pdo);

        // 1. Processed: order.created
        $logRepo->create([
            'store_id' => $storeId,
            'delivery_id' => 'del_demo_wc_001042',
            'webhook_id' => '1',
            'resource_id' => '1042',
            'topic' => 'order.created',
            'event' => 'order.created',
            'signature' => 'sig_demo_hash_abc123',
            'payload' => [
                'id' => 1042,
                'status' => 'processing',
                'total' => '345000',
                'currency' => 'IRR',
                'customer_id' => 1,
            ],
            'status' => 'processed',
            'ip_address' => '127.0.0.1',
            'attempt' => 1,
            'processing_time_ms' => 42,
            'processed_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
        ]);

        // 2. Processed: product.updated
        $logRepo->create([
            'store_id' => $storeId,
            'delivery_id' => 'del_demo_wc_002208',
            'webhook_id' => '2',
            'resource_id' => '208',
            'topic' => 'product.updated',
            'event' => 'product.updated',
            'signature' => 'sig_demo_hash_def456',
            'payload' => [
                'id' => 208,
                'name' => 'پیراهن لینن تابستانه',
                'manage_stock' => true,
                'stock_quantity' => 18,
            ],
            'status' => 'processed',
            'ip_address' => '127.0.0.1',
            'attempt' => 1,
            'processing_time_ms' => 28,
            'processed_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        // 3. Duplicate: order.updated
        $logRepo->create([
            'store_id' => $storeId,
            'delivery_id' => 'del_demo_wc_dup_001928',
            'webhook_id' => '1',
            'resource_id' => '1042',
            'topic' => 'order.updated',
            'event' => 'order.updated',
            'signature' => 'sig_demo_hash_dup789',
            'payload' => [
                'id' => 1042,
                'status' => 'processing',
                'notice' => 'duplicate attempt from WooCommerce replay',
            ],
            'status' => 'duplicate',
            'error_message' => 'Duplicate delivery ID suppressed to protect idempotency',
            'ip_address' => '127.0.0.1',
            'attempt' => 1,
            'processed_at' => date('Y-m-d H:i:s', strtotime('-12 minutes')),
        ]);

        // 4. Failed: order.updated
        $logRepo->create([
            'store_id' => $storeId,
            'delivery_id' => 'del_demo_wc_fail_00991',
            'webhook_id' => '1',
            'resource_id' => '991',
            'topic' => 'order.updated',
            'event' => 'order.updated',
            'signature' => 'sig_demo_hash_fail000',
            'payload' => [
                'id' => 991,
                'status' => 'failed',
            ],
            'status' => 'failed',
            'error_message' => 'Network timeout: Connection to WooCommerce webhook target dropped',
            'ip_address' => '127.0.0.1',
            'attempt' => 1,
            'processing_time_ms' => 10005,
            'processed_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        ]);

        // 5. Ignored: action.created
        $logRepo->create([
            'store_id' => $storeId,
            'delivery_id' => 'del_demo_wc_ign_0044',
            'webhook_id' => '4',
            'resource_id' => '44',
            'topic' => 'action.woocommerce_tax_rates_sync',
            'event' => 'action.created',
            'signature' => 'sig_demo_hash_ign555',
            'payload' => ['ping' => true],
            'status' => 'ignored',
            'ip_address' => '127.0.0.1',
            'attempt' => 1,
            'processed_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
        ]);

        // Demo Sync Logs
        $syncRepo = new \App\Repositories\SyncLogRepository($this->pdo);

        // 1. Successful sync
        $syncRepo->create([
            'store_id' => $storeId,
            'entity_type' => 'orders',
            'direction' => 'inbound_reconcile',
            'status' => 'completed',
            'processed' => 15,
            'failed' => 0,
            'details' => [
                'inspected' => 15,
                'cache_refreshed' => 15,
                'range' => '24h',
            ],
        ]);

        // 2. Partial sync
        $syncRepo->create([
            'store_id' => $storeId,
            'entity_type' => 'products',
            'direction' => 'inbound_reconcile',
            'status' => 'partial',
            'processed' => 40,
            'failed' => 1,
            'details' => [
                'inspected' => 41,
                'error' => 'Product #105 variation timeout',
            ],
        ]);

        // 3. Failed sync
        $syncRepo->create([
            'store_id' => $storeId,
            'entity_type' => 'all',
            'direction' => 'inbound_reconcile',
            'status' => 'failed',
            'processed' => 0,
            'failed' => 1,
            'error_message' => 'Connection refused: upstream server 127.0.0.1:8001 was unreachable',
            'details' => ['error' => 'Connection refused'],
        ]);
    }

    private function seedDemoAutomations(): void
    {
        // Check if automations table exists
        $tables = $this->pdo->query("SHOW TABLES LIKE 'automations'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            return;
        }

        // Avoid re-seeding if already populated
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM automations")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $storeId = 2; // Store A

        $demoAutomations = [
            [
                'store_id' => $storeId,
                'name' => 'ارتقای مشتری به VIP با خرید بالای ۵ میلیون',
                'description' => 'به محض ثبت سفارش با مبلغ بالاتر از ۵,۰۰۰,۰۰۰ تومان، برچسب VIP به مشتری اضافه شده و تسک پیگیری ایجاد می‌شود.',
                'status' => 'active',
                'trigger_type' => 'order.created',
                'trigger_config' => json_encode(['source' => 'woocommerce'], JSON_UNESCAPED_UNICODE),
                'conditions' => json_encode([
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'order.total', 'operator' => 'greater_than', 'value' => 5000000]
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'actions' => json_encode([
                    ['type' => 'add_customer_tag', 'config' => ['tag_name' => 'VIP', 'tag_color' => '#E11D48']],
                    ['type' => 'create_activity', 'config' => ['action_type' => 'vip_tag_awarded', 'description' => 'مشتری با سفارش بالای ۵ میلیون به سطح VIP ارتقا یافت.']],
                    ['type' => 'create_notification', 'config' => ['title' => 'مشتری VIP جدید', 'message' => 'سفارش {{order.number}} با مبلغ {{order.total}} ثبت و مشتری به عنوان VIP برچسب‌گذاری شد.']]
                ], JSON_UNESCAPED_UNICODE),
                'execution_mode' => 'immediate',
                'created_by' => 1,
            ],
            [
                'store_id' => $storeId,
                'name' => 'هشدار کسری موجودی کالا و وظیفه سفارش مجدد',
                'description' => 'در صورت رسیدن موجودی محصول به ۵ عدد یا کمتر، کارتابل انبار وظیفه تأمین فوری دریافت می‌کند.',
                'status' => 'active',
                'trigger_type' => 'inventory.low_stock',
                'trigger_config' => json_encode(['threshold' => 5], JSON_UNESCAPED_UNICODE),
                'conditions' => json_encode([
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'product.stock_quantity', 'operator' => 'less_or_equal', 'value' => 5]
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'actions' => json_encode([
                    ['type' => 'create_task', 'config' => ['title' => 'تأمین فوری موجودی محصول {{product.name}}', 'description' => 'موجودی انبار کالا با شناسه {{product.id}} به {{product.stock_quantity}} عدد کاهش یافته است.', 'priority' => 'urgent', 'due_days' => 2]],
                    ['type' => 'create_notification', 'config' => ['title' => 'کسری موجودی کالا', 'message' => 'موجودی محصول «{{product.name}}» به حداقل رسیده است.', 'priority' => 'high']]
                ], JSON_UNESCAPED_UNICODE),
                'execution_mode' => 'immediate',
                'created_by' => 1,
            ],
            [
                'store_id' => $storeId,
                'name' => 'ثبت فعالیت تعامل پس از تکمیل موفق سفارش',
                'description' => 'به محض تغییر وضعیت سفارش به تکمیل شده (Completed)، فعالیت مربوطه ثبت و یادداشت اختصاصی به سفارش اضافه می‌گردد.',
                'status' => 'active',
                'trigger_type' => 'order.status_changed',
                'trigger_config' => json_encode(['to_status' => 'completed'], JSON_UNESCAPED_UNICODE),
                'conditions' => json_encode([
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'order.status', 'operator' => 'equals', 'value' => 'completed']
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'actions' => json_encode([
                    ['type' => 'create_activity', 'config' => ['action_type' => 'order_delivered', 'description' => 'سفارش {{order.number}} تحویل و وضعیت آن تکمیل گردید.']],
                    ['type' => 'add_order_note', 'config' => ['note' => 'سفارش به طور خودکار به عنوان تحویل موفق در CRM ثبت گردید.', 'customer_note' => false]]
                ], JSON_UNESCAPED_UNICODE),
                'execution_mode' => 'immediate',
                'created_by' => 1,
            ],
            [
                'store_id' => $storeId,
                'name' => 'اطلاع‌رسانی تاخیر و فرارسیدن موعد سررسید وظیفه',
                'description' => 'اگر مهلت انجام وظیفه‌ای سررسید شود و هنوز ناقص باشد، اولویت آن به فوری تغییر کرده و هشدار صادر می‌شود.',
                'status' => 'active',
                'trigger_type' => 'task.overdue',
                'trigger_config' => null,
                'conditions' => json_encode([
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'task.status', 'operator' => 'not_equals', 'value' => 'completed']
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'actions' => json_encode([
                    ['type' => 'create_notification', 'config' => ['title' => 'هشدار سررسید وظیفه', 'message' => 'مهلت انجام وظیفه «{{task.title}}» به اتمام رسیده است.', 'priority' => 'high']],
                    ['type' => 'update_task', 'config' => ['priority' => 'urgent']]
                ], JSON_UNESCAPED_UNICODE),
                'execution_mode' => 'immediate',
                'created_by' => 1,
            ],
            [
                'store_id' => $storeId,
                'name' => 'پیگیری دوره‌ای و وفادارسازی مشتریان غیرفعال',
                'description' => 'بررسی زمان‌بندی‌شده دوره‌ای جهت ثبت وظیفه تماس با مشتریان دارای سابقه خرید جهت بازگردانی.',
                'status' => 'active',
                'trigger_type' => 'scheduled',
                'trigger_config' => json_encode(['interval' => 'daily'], JSON_UNESCAPED_UNICODE),
                'conditions' => json_encode([
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'customer.order_count', 'operator' => 'greater_than', 'value' => 0]
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'actions' => json_encode([
                    ['type' => 'create_task', 'config' => ['title' => 'تماس پیگیری و نظرسنجی با مشتری {{customer.name}}', 'description' => 'بررسی تجربه خرید و ارائه کد تخفیف اختصاصی جهت فعال‌سازی مجدد.', 'priority' => 'normal', 'due_days' => 3]]
                ], JSON_UNESCAPED_UNICODE),
                'execution_mode' => 'batch',
                'created_by' => 1,
            ]
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO automations (
                store_id, name, description, status, trigger_type,
                trigger_config, conditions, actions, execution_mode,
                created_by, updated_by, created_at, updated_at
            ) VALUES (
                :store_id, :name, :description, :status, :trigger_type,
                :trigger_config, :conditions, :actions, :execution_mode,
                :created_by, :updated_by, NOW(), NOW()
            )
        ");

        foreach ($demoAutomations as $da) {
            $stmt->execute([
                ':store_id' => $da['store_id'],
                ':name' => $da['name'],
                ':description' => $da['description'],
                ':status' => $da['status'],
                ':trigger_type' => $da['trigger_type'],
                ':trigger_config' => $da['trigger_config'],
                ':conditions' => $da['conditions'],
                ':actions' => $da['actions'],
                ':execution_mode' => $da['execution_mode'],
                ':created_by' => $da['created_by'],
                ':updated_by' => $da['created_by'],
            ]);
        }
    }
}

