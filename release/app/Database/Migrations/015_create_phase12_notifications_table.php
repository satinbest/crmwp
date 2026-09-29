<?php

class CreatePhase12NotificationsTable
{
    public function up(PDO $pdo): void
    {
        // 1. Check existing columns in notifications table
        $cols = $pdo->query("SHOW COLUMNS FROM `notifications`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('store_id', $cols, true)) {
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `store_id` INT UNSIGNED NULL AFTER `user_id`;");
            try {
                $pdo->exec("ALTER TABLE `notifications` ADD CONSTRAINT `fk_notif_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL;");
            } catch (\Throwable $e) {
                // Ignore if store foreign key constraint fails
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

        // 3. Seed Realistic Demo Notifications for Admin (user id 1) and Manager (user id 2)
        $storeId = (int)$pdo->query("SELECT id FROM `stores` ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 2;
        $adminId = (int)$pdo->query("SELECT id FROM `users` WHERE `username` = 'admin'")->fetchColumn() ?: 1;

        if ($adminId) {
            $demoNotifications = [
                [
                    'user_id' => $adminId,
                    'store_id' => $storeId,
                    'type' => 'task_assigned',
                    'title' => 'وظیفه جدید تخصیص یافت',
                    'message' => 'وظیفه «پیگیری سفارش شماره ۱۰۰۱ مشتری علی رضایی» به شما واگذار گردید.',
                    'data' => json_encode(['task_id' => 1, 'order_id' => 1001, 'customer_name' => 'علی رضایی']),
                    'priority' => 'high',
                    'action_url' => '/crm/tasks',
                    'read_at' => null, // Unread
                ],
                [
                    'user_id' => $adminId,
                    'store_id' => $storeId,
                    'type' => 'low_stock',
                    'title' => 'هشدار کمبود موجودی کالا',
                    'message' => 'موجودی انبار محصول «لپ‌تاپ گیمینگ ایسوس مدل ROG Strix» به ۳ عدد کاهش یافته است.',
                    'data' => json_encode(['product_id' => 301, 'current_stock' => 3, 'low_stock_amount' => 5]),
                    'priority' => 'urgent',
                    'action_url' => '/inventory',
                    'read_at' => null, // Unread
                ],
                [
                    'user_id' => $adminId,
                    'store_id' => $storeId,
                    'type' => 'bulk_operation_completed',
                    'title' => 'عملیات دسته‌جمعی تکمیل شد',
                    'message' => 'عملیات افزایش قیمت محصولات دسته لپ‌تاپ با موفقیت خاتمه یافت. ۲۴۷ محصول بروزرسانی شدند.',
                    'data' => json_encode(['operation_id' => 101, 'processed' => 247, 'failed' => 0]),
                    'priority' => 'normal',
                    'action_url' => '/bulk-operations',
                    'read_at' => date('Y-m-d H:i:s', time() - 3600), // Read 1 hour ago
                ],
                [
                    'user_id' => $adminId,
                    'store_id' => $storeId,
                    'type' => 'order_attention',
                    'title' => 'سفارش نیازمند توجه ویژه',
                    'message' => 'سفارش شماره ۱۰۰۲ با وضعیت انتقال کارت‌به‌کارت بیش از ۱۲ ساعت در انتظار تایید مانده است.',
                    'data' => json_encode(['order_id' => 1002, 'status' => 'pending', 'total' => '24500000']),
                    'priority' => 'high',
                    'action_url' => '/orders/1002',
                    'read_at' => date('Y-m-d H:i:s', time() - 7200), // Read 2 hours ago
                ],
                [
                    'user_id' => $adminId,
                    'store_id' => null,
                    'type' => 'system',
                    'title' => 'به‌روزرسانی موفقیت‌آمیز سیستم',
                    'message' => 'سامانه مدیریت ووکامرس و CRM به آخرین نسخه با امکانات مرکز اعلان‌ها ارتقا یافت.',
                    'data' => json_encode(['version' => '1.12.0', 'release' => 'Phase 12']),
                    'priority' => 'low',
                    'action_url' => '/dashboard',
                    'read_at' => date('Y-m-d H:i:s', time() - 86400), // Read 1 day ago
                ],
            ];

            // Only insert if no notifications exist for admin
            $existing = (int)$pdo->query("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = {$adminId}")->fetchColumn();
            if ($existing === 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO `notifications` (`user_id`, `store_id`, `type`, `title`, `message`, `data`, `priority`, `action_url`, `read_at`, `created_at`)
                    VALUES (:user_id, :store_id, :type, :title, :message, :data, :priority, :action_url, :read_at, NOW())
                ");
                foreach ($demoNotifications as $dn) {
                    $stmt->execute($dn);
                }
            }
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `user_notification_preferences`;");
    }
}
