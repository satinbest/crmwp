<?php

class CreatePhase15AutomationsTables
{
    public function up(PDO $pdo): void
    {
        // 1. Create automations table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `automations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NULL,
                `name` VARCHAR(191) NOT NULL,
                `description` TEXT NULL,
                `status` ENUM('active', 'inactive', 'draft', 'error') NOT NULL DEFAULT 'active',
                `trigger_type` VARCHAR(100) NOT NULL,
                `trigger_config` LONGTEXT NULL,
                `conditions` LONGTEXT NULL,
                `actions` LONGTEXT NOT NULL,
                `execution_mode` ENUM('immediate', 'delayed', 'batch') NOT NULL DEFAULT 'immediate',
                `max_runs` INT UNSIGNED NULL,
                `run_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_run_at` DATETIME NULL,
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_automations_store` (`store_id`),
                INDEX `idx_automations_status` (`status`),
                INDEX `idx_automations_trigger` (`trigger_type`),
                INDEX `idx_automations_store_status` (`store_id`, `status`),
                INDEX `idx_automations_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Create automation_runs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `automation_runs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `automation_id` INT UNSIGNED NOT NULL,
                `store_id` INT UNSIGNED NULL,
                `event_id` VARCHAR(100) NULL,
                `trigger_type` VARCHAR(100) NOT NULL,
                `status` ENUM('pending', 'running', 'completed', 'partial', 'failed', 'skipped', 'cancelled') NOT NULL DEFAULT 'pending',
                `started_at` DATETIME NULL,
                `completed_at` DATETIME NULL,
                `duration_ms` INT UNSIGNED NULL,
                `idempotency_key` VARCHAR(191) NULL,
                `error` TEXT NULL,
                `context` LONGTEXT NULL,
                `result` LONGTEXT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_autorun_automation` (`automation_id`),
                INDEX `idx_autorun_store` (`store_id`),
                INDEX `idx_autorun_status` (`status`),
                INDEX `idx_autorun_event` (`event_id`),
                INDEX `idx_autorun_idempotency` (`idempotency_key`),
                INDEX `idx_autorun_auto_status` (`automation_id`, `status`),
                INDEX `idx_autorun_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Register Permissions in permissions table
        $permissions = [
            ['automations.view', 'automations', 'مشاهده اتوماسیون‌ها', 'مشاهده فهرست و جزییات گردش‌کارهای خودکار'],
            ['automations.create', 'automations', 'ایجاد اتوماسیون', 'تعریف سناریوی جدید با شرط‌ساز و اکشن‌ها'],
            ['automations.update', 'automations', 'ویرایش اتوماسیون', 'ویرایش تریگر، شروط و اقدامات اتوماسیون'],
            ['automations.delete', 'automations', 'حذف اتوماسیون', 'حذف گردش‌کارهای خودکار'],
            ['automations.enable', 'automations', 'فعال‌سازی اتوماسیون', 'فعال‌سازی وضعیت اجرای اتوماسیون'],
            ['automations.disable', 'automations', 'غیرفعال‌سازی اتوماسیون', 'توقف موقت اجرای اتوماسیون'],
            ['automations.run', 'automations', 'اجرای دستی اتوماسیون', 'تست و اجرای فوری اتوماسیون روی رویداد'],
            ['automations.view_runs', 'automations', 'مشاهده تاریخچه اجرا', 'مشاهده لاگ‌ها و وضعیت اجراهای اتوماسیون'],
            ['automations.manage', 'automations', 'مدیریت کامل موتور اتوماسیون', 'تنظیمات، تکرار و پاکسازی تاریخچه اتوماسیون'],
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

        // 4. Assign permissions to administrator and store_manager
        $roleStmt = $pdo->prepare("SELECT id, slug FROM roles WHERE slug IN ('administrator', 'store_manager')");
        $roleStmt->execute();
        $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

        $permIdsStmt = $pdo->prepare("SELECT id, name FROM permissions WHERE name LIKE 'automations.%'");
        $permIdsStmt->execute();
        $allAutomationPerms = $permIdsStmt->fetchAll(PDO::FETCH_ASSOC);

        $rolePermInsert = $pdo->prepare("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            VALUES (:role_id, :permission_id)
        ");

        foreach ($roles as $role) {
            $isManager = ($role['slug'] === 'store_manager');
            foreach ($allAutomationPerms as $perm) {
                // Manager gets view, create, update, enable, disable, run, view_runs
                if ($isManager && in_array($perm['name'], ['automations.delete', 'automations.manage'], true)) {
                    continue;
                }
                $rolePermInsert->execute([
                    'role_id' => $role['id'],
                    'permission_id' => $perm['id'],
                ]);
            }
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `automation_runs`;");
        $pdo->exec("DROP TABLE IF EXISTS `automations`;");
        $pdo->exec("DELETE FROM `permissions` WHERE `name` LIKE 'automations.%';");
    }
}
