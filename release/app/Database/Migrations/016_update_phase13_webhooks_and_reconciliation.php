<?php

class UpdatePhase13WebhooksAndReconciliation
{
    public function up(PDO $pdo): void
    {
        // 1. Enhance stores table
        $storeCols = $pdo->query("SHOW COLUMNS FROM `stores`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('webhook_secret_encrypted', $storeCols, true)) {
            $pdo->exec("ALTER TABLE `stores` ADD COLUMN `webhook_secret_encrypted` TEXT NULL AFTER `consumer_secret_encrypted`;");
        }

        // 2. Enhance webhook_logs table
        $wlCols = $pdo->query("SHOW COLUMNS FROM `webhook_logs`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('delivery_id', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `delivery_id` VARCHAR(100) NULL AFTER `store_id`;");
        }
        if (!in_array('webhook_id', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `webhook_id` VARCHAR(50) NULL AFTER `delivery_id`;");
        }
        if (!in_array('resource_id', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `resource_id` VARCHAR(100) NULL AFTER `webhook_id`;");
        }
        if (!in_array('event', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `event` VARCHAR(100) NULL AFTER `topic`;");
        }
        if (!in_array('signature', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `signature` VARCHAR(255) NULL AFTER `event`;");
        }
        if (!in_array('attempt', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `attempt` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `signature`;");
        }
        if (!in_array('processing_time_ms', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `processing_time_ms` INT UNSIGNED NULL AFTER `attempt`;");
        }
        if (!in_array('processed_at', $wlCols, true)) {
            $pdo->exec("ALTER TABLE `webhook_logs` ADD COLUMN `processed_at` DATETIME NULL AFTER `processing_time_ms`;");
        }

        // Standardize status column enum
        // Convert any existing 'error' to 'failed'
        $pdo->exec("UPDATE `webhook_logs` SET `status` = 'failed' WHERE `status` = 'error';");
        $pdo->exec("ALTER TABLE `webhook_logs` MODIFY COLUMN `status` ENUM('received', 'processing', 'processed', 'failed', 'ignored', 'duplicate') NOT NULL DEFAULT 'received';");

        // Indexes for webhook_logs
        $this->addIndexIfNotExists($pdo, 'webhook_logs', 'idx_wl_delivery', '(`delivery_id`)');
        $this->addIndexIfNotExists($pdo, 'webhook_logs', 'idx_wl_status', '(`status`)');
        $this->addIndexIfNotExists($pdo, 'webhook_logs', 'idx_wl_created', '(`created_at`)');

        // 3. Enhance sync_logs table
        $slCols = $pdo->query("SHOW COLUMNS FROM `sync_logs`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('entity_type', $slCols, true)) {
            $pdo->exec("ALTER TABLE `sync_logs` ADD COLUMN `entity_type` VARCHAR(50) NULL AFTER `store_id`;");
            $pdo->exec("UPDATE `sync_logs` SET `entity_type` = `sync_type` WHERE `entity_type` IS NULL;");
        }
        if (!in_array('direction', $slCols, true)) {
            $pdo->exec("ALTER TABLE `sync_logs` ADD COLUMN `direction` VARCHAR(20) NOT NULL DEFAULT 'inbound_reconcile' AFTER `entity_type`;");
        }
        if (!in_array('processed', $slCols, true)) {
            $pdo->exec("ALTER TABLE `sync_logs` ADD COLUMN `processed` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `direction`;");
        }
        if (!in_array('failed', $slCols, true)) {
            $pdo->exec("ALTER TABLE `sync_logs` ADD COLUMN `failed` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `processed`;");
        }
        if (!in_array('details', $slCols, true)) {
            $pdo->exec("ALTER TABLE `sync_logs` ADD COLUMN `details` LONGTEXT NULL AFTER `summary`;");
        }

        // Standardize status for sync_logs
        $pdo->exec("ALTER TABLE `sync_logs` MODIFY COLUMN `status` ENUM('running', 'completed', 'failed', 'partial') NOT NULL DEFAULT 'running';");

        // Indexes for sync_logs
        $this->addIndexIfNotExists($pdo, 'sync_logs', 'idx_sl_status', '(`status`)');
        $this->addIndexIfNotExists($pdo, 'sync_logs', 'idx_sl_entity', '(`store_id`, `entity_type`)');

        // 4. Permissions for Phase 13
        $permissions = [
            ['name' => 'webhooks.view', 'group_name' => 'webhooks', 'display_name' => 'مشاهده وب‌هوک‌ها', 'description' => 'مشاهده لاگ‌ها، رویدادها و وضعیت سلامت وب‌هوک‌ها'],
            ['name' => 'webhooks.manage', 'group_name' => 'webhooks', 'display_name' => 'مدیریت وب‌هوک‌ها', 'description' => 'تعریف، ویرایش و مدیریت وب‌هوک‌های ووکامرس'],
            ['name' => 'webhooks.retry', 'group_name' => 'webhooks', 'display_name' => 'تلاش مجدد وب‌هوک', 'description' => 'اجرای مجدد وب‌هوک‌های ناموفق یا نادیده‌گرفته شده'],
            ['name' => 'webhooks.reconcile', 'group_name' => 'webhooks', 'display_name' => 'تطبیق و ریکانسیلیشن', 'description' => 'اجرای یکسان‌سازی وضعیت و بازسازی کش با ووکامرس'],
            ['name' => 'sync.view', 'group_name' => 'sync', 'display_name' => 'مشاهده تاریخچه همگام‌سازی', 'description' => 'مشاهده گزارش‌ها و لاگ‌های تطبیق و همگام‌سازی'],
            ['name' => 'sync.manage', 'group_name' => 'sync', 'display_name' => 'مدیریت همگام‌سازی', 'description' => 'مدیریت و اجرای دستی فرآیندهای همگام‌سازی'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO `permissions` (`name`, `group_name`, `display_name`, `description`, `created_at`, `updated_at`)
            VALUES (:name, :group_name, :display_name, :description, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `group_name` = VALUES(`group_name`),
                `display_name` = VALUES(`display_name`),
                `description` = VALUES(`description`)
        ");

        foreach ($permissions as $p) {
            $stmt->execute($p);
        }

        // Grant all permissions to Administrator
        $adminRoleStmt = $pdo->query("SELECT id FROM `roles` WHERE `slug` = 'administrator' OR `name` = 'مدیر سیستم' LIMIT 1");
        $adminRole = $adminRoleStmt->fetch(PDO::FETCH_ASSOC);

        if ($adminRole) {
            $adminRoleId = (int)$adminRole['id'];
            $permStmt = $pdo->query("SELECT id FROM `permissions` WHERE `name` IN ('webhooks.view', 'webhooks.manage', 'webhooks.retry', 'webhooks.reconcile', 'sync.view', 'sync.manage')");
            $permIds = $permStmt->fetchAll(PDO::FETCH_COLUMN);

            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($permIds as $permId) {
                $assignStmt->execute([$adminRoleId, $permId]);
            }
        }

        // Grant view, retry, reconcile to Manager role
        $mgrRoleStmt = $pdo->query("SELECT id FROM `roles` WHERE `slug` = 'manager' OR `name` = 'مدیر فروشگاه' LIMIT 1");
        $mgrRole = $mgrRoleStmt->fetch(PDO::FETCH_ASSOC);

        if ($mgrRole) {
            $mgrRoleId = (int)$mgrRole['id'];
            $permStmt = $pdo->query("SELECT id FROM `permissions` WHERE `name` IN ('webhooks.view', 'webhooks.retry', 'webhooks.reconcile', 'sync.view')");
            $permIds = $permStmt->fetchAll(PDO::FETCH_COLUMN);

            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            foreach ($permIds as $permId) {
                $assignStmt->execute([$mgrRoleId, $permId]);
            }
        }
    }

    public function down(PDO $pdo): void
    {
        // Keep columns safely
    }

    private function addIndexIfNotExists(PDO $pdo, string $table, string $indexName, string $columns): void
    {
        $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$columns};");
        }
    }
}
