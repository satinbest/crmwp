<?php

class CreatePhase7BulkOperationsTables
{
    public function up(PDO $pdo): void
    {
        // 1. Ensure bulk_operations table has all Phase 7 required columns
        $columns = $pdo->query("SHOW COLUMNS FROM `bulk_operations`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('skipped_items', $columns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operations` ADD COLUMN `skipped_items` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `failed_items`");
        }
        if (!in_array('preview_data', $columns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operations` ADD COLUMN `preview_data` JSON NULL AFTER `payload`");
        }
        if (!in_array('result_data', $columns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operations` ADD COLUMN `result_data` JSON NULL AFTER `preview_data`");
        }
        if (!in_array('action_type', $columns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operations` ADD COLUMN `action_type` VARCHAR(100) NULL AFTER `type`");
        }
        if (!in_array('started_at', $columns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operations` ADD COLUMN `started_at` DATETIME NULL AFTER `result_data`");
        }
        if (!in_array('completed_at', $columns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operations` ADD COLUMN `completed_at` DATETIME NULL AFTER `started_at`");
        }

        // 2. Ensure bulk_operation_items table supports Phase 7 statuses and error details
        $itemColumns = $pdo->query("SHOW COLUMNS FROM `bulk_operation_items`")->fetchAll(PDO::FETCH_COLUMN);

        // Update status enum if needed
        $pdo->exec("ALTER TABLE `bulk_operation_items` MODIFY COLUMN `status` ENUM('pending', 'processing', 'completed', 'success', 'failed', 'skipped', 'cancelled') NOT NULL DEFAULT 'pending'");

        if (!in_array('error_code', $itemColumns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operation_items` ADD COLUMN `error_code` VARCHAR(100) NULL AFTER `new_state`");
        }

        // Add index on status if not exists
        try {
            $pdo->exec("ALTER TABLE `bulk_operation_items` ADD INDEX `idx_boi_status` (`status`)");
        } catch (\Exception $e) {
            // Index might already exist
        }

        // 3. Register Phase 7 Permissions
        $newPermissions = [
            ['name' => 'bulk.preview', 'group_name' => 'bulk', 'display_name' => 'پیش‌نمایش عملیات گروهی', 'description' => 'مشاهده پیش‌نمایش قبل از اجرای عملیات انبوه'],
            ['name' => 'bulk.cancel', 'group_name' => 'bulk', 'display_name' => 'لغو عملیات گروهی', 'description' => 'لغو و متوقف‌سازی عملیات‌های انبوه در حال اجرا'],
            ['name' => 'orders.bulk', 'group_name' => 'orders', 'display_name' => 'عملیات گروهی سفارش‌ها', 'description' => 'تغییر وضعیت یا یادداشت‌گذاری انبوه روی سفارشات'],
            ['name' => 'customers.bulk', 'group_name' => 'customers', 'display_name' => 'عملیات گروهی مشتریان', 'description' => 'افزودن برچسب یا وظیفه انبوه برای مشتریان'],
        ];

        $permStmt = $pdo->prepare("INSERT IGNORE INTO `permissions` (`name`, `group_name`, `display_name`, `description`, `created_at`) VALUES (:name, :group_name, :display_name, :description, NOW())");
        foreach ($newPermissions as $perm) {
            $permStmt->execute($perm);
        }

        // Grant to Admin (role_id 1 or name = 'admin' / 'Admin')
        $adminRoles = $pdo->query("SELECT id FROM `roles` WHERE LOWER(`name`) = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($adminRoles as $adminRoleId) {
            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) SELECT :role_id, id FROM `permissions` WHERE `name` IN ('bulk.preview', 'bulk.cancel', 'orders.bulk', 'customers.bulk', 'products.bulk')");
            $assignStmt->execute([':role_id' => $adminRoleId]);
        }

        // Grant to Manager (role_id 2 or name = 'manager' / 'Manager')
        $managerRoles = $pdo->query("SELECT id FROM `roles` WHERE LOWER(`name`) = 'manager'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($managerRoles as $managerRoleId) {
            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) SELECT :role_id, id FROM `permissions` WHERE `name` IN ('bulk.preview', 'bulk.cancel', 'orders.bulk', 'customers.bulk', 'products.bulk')");
            $assignStmt->execute([':role_id' => $managerRoleId]);
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DELETE FROM `permissions` WHERE `name` IN ('bulk.preview', 'bulk.cancel', 'orders.bulk', 'customers.bulk')");
    }
}
