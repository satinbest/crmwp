<?php

class CreatePhase12NotificationsTable
{
    public function up(PDO $pdo): void
    {
        // 1. Check existing columns in notifications table
        $cols = $pdo->query("SHOW COLUMNS FROM `notifications`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('store_id', $cols, true)) {
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `store_id` INT UNSIGNED NULL AFTER `user_id`;");
        }

        // Ensure foreign key fk_notif_store exists and points to stores(id) ON DELETE SET NULL
        $fkExists = false;
        try {
            $constraints = $pdo->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'notifications' 
                  AND CONSTRAINT_NAME = 'fk_notif_store'
            ")->fetchAll(PDO::FETCH_COLUMN);
            $fkExists = !empty($constraints);
        } catch (\Throwable $e) {}

        if (!$fkExists) {
            try {
                // Upgrade safety: Nullify any orphaned store_id before applying constraint
                $pdo->exec("UPDATE `notifications` SET `store_id` = NULL WHERE `store_id` IS NOT NULL AND `store_id` NOT IN (SELECT `id` FROM `stores`);");
                $pdo->exec("ALTER TABLE `notifications` ADD CONSTRAINT `fk_notif_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL;");
            } catch (\Throwable $e) {
                // Ignore if constraint already exists
            }
        }

        if (!in_array('data', $cols, true)) {
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `data` JSON NULL AFTER `message`;");
        }

        if (!in_array('priority', $cols, true)) {
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal' AFTER `data`;");
        }

        if (!in_array('action_url', $cols, true)) {
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `action_url` VARCHAR(255) NULL AFTER `priority`;");
        }

        if (!in_array('read_at', $cols, true)) {
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `read_at` TIMESTAMP NULL DEFAULT NULL AFTER `action_url`;");
            // Sync read_at from is_read if is_read existed
            if (in_array('is_read', $cols, true)) {
                $pdo->exec("UPDATE `notifications` SET `read_at` = `created_at` WHERE `is_read` = 1 AND `read_at` IS NULL;");
            }
        }

        if (!in_array('expires_at', $cols, true)) {
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `read_at`;");
        }

        // Add performance indexes
        $indexes = $pdo->query("SHOW INDEX FROM `notifications`")->fetchAll(PDO::FETCH_ASSOC);
        $indexNames = array_column($indexes, 'Key_name');

        if (!in_array('idx_notif_user_read', $indexNames, true)) {
            try {
                $pdo->exec("ALTER TABLE `notifications` ADD INDEX `idx_notif_user_read` (`user_id`, `read_at`);");
            } catch (\Throwable $e) {}
        }

        if (!in_array('idx_notif_store', $indexNames, true)) {
            try {
                $pdo->exec("ALTER TABLE `notifications` ADD INDEX `idx_notif_store` (`store_id`);");
            } catch (\Throwable $e) {}
        }

        if (!in_array('idx_notif_created', $indexNames, true)) {
            try {
                $pdo->exec("ALTER TABLE `notifications` ADD INDEX `idx_notif_created` (`created_at`);");
            } catch (\Throwable $e) {}
        }

        if (!in_array('idx_notif_type', $indexNames, true)) {
            try {
                $pdo->exec("ALTER TABLE `notifications` ADD INDEX `idx_notif_type` (`type`);");
            } catch (\Throwable $e) {}
        }

        // 2. Create User Notification Preferences table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
                `user_id` INT UNSIGNED PRIMARY KEY,
                `task_notifications` TINYINT(1) NOT NULL DEFAULT 1,
                `order_notifications` TINYINT(1) NOT NULL DEFAULT 1,
                `inventory_notifications` TINYINT(1) NOT NULL DEFAULT 1,
                `bulk_notifications` TINYINT(1) NOT NULL DEFAULT 1,
                `system_notifications` TINYINT(1) NOT NULL DEFAULT 1,
                `retention_days` INT UNSIGNED NOT NULL DEFAULT 30,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_unp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `user_notification_preferences`;");
    }
}
