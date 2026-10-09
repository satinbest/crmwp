<?php

declare(strict_types=1);

class CreateLocalCacheAndPriceSafetyTables
{
    public function up(PDO $pdo): void
    {
        // 1. Local Sync Metadata Table (Per Store & Entity)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `wc_local_sync_meta` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `entity_type` VARCHAR(50) NOT NULL,
                `status` ENUM('idle', 'running', 'completed', 'partial', 'failed') NOT NULL DEFAULT 'idle',
                `last_sync_started_at` DATETIME NULL,
                `last_sync_completed_at` DATETIME NULL,
                `last_successful_sync_at` DATETIME NULL,
                `records_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `synced_records` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_error` TEXT NULL,
                `is_complete` TINYINT(1) NOT NULL DEFAULT 0,
                `metadata` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_sync_store_entity` (`store_id`, `entity_type`),
                INDEX `idx_sync_status` (`store_id`, `status`),
                CONSTRAINT `fk_sm_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Local Products Table (Store-scoped)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `wc_local_products` (
                `store_id` INT UNSIGNED NOT NULL,
                `wc_id` BIGINT UNSIGNED NOT NULL,
                `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NULL,
                `type` VARCHAR(50) NOT NULL DEFAULT 'simple',
                `status` VARCHAR(50) NOT NULL DEFAULT 'publish',
                `sku` VARCHAR(100) NULL,
                `price` VARCHAR(50) NULL,
                `regular_price` VARCHAR(50) NULL,
                `sale_price` VARCHAR(50) NULL,
                `stock_status` VARCHAR(50) NOT NULL DEFAULT 'instock',
                `stock_quantity` INT NULL,
                `manage_stock` TINYINT(1) NOT NULL DEFAULT 0,
                `categories` JSON NULL,
                `tags` JSON NULL,
                `attributes` JSON NULL,
                `date_created` DATETIME NULL,
                `date_modified` DATETIME NULL,
                `raw_data` LONGTEXT NULL,
                `synced_at` DATETIME NOT NULL,
                PRIMARY KEY (`store_id`, `wc_id`),
                INDEX `idx_lp_store_parent` (`store_id`, `parent_id`),
                INDEX `idx_lp_store_type` (`store_id`, `type`),
                INDEX `idx_lp_store_status` (`store_id`, `status`),
                INDEX `idx_lp_store_stock` (`store_id`, `stock_status`),
                INDEX `idx_lp_store_sku` (`store_id`, `sku`),
                INDEX `idx_lp_store_date` (`store_id`, `date_created`),
                CONSTRAINT `fk_lp_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Local Orders Table (Store-scoped)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `wc_local_orders` (
                `store_id` INT UNSIGNED NOT NULL,
                `wc_id` BIGINT UNSIGNED NOT NULL,
                `order_number` VARCHAR(100) NOT NULL,
                `customer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
                `currency` VARCHAR(10) NOT NULL DEFAULT 'IRT',
                `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                `customer_name` VARCHAR(255) NULL,
                `customer_email` VARCHAR(255) NULL,
                `items_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `date_created` DATETIME NULL,
                `date_modified` DATETIME NULL,
                `raw_data` LONGTEXT NULL,
                `synced_at` DATETIME NOT NULL,
                PRIMARY KEY (`store_id`, `wc_id`),
                INDEX `idx_lo_store_customer` (`store_id`, `customer_id`),
                INDEX `idx_lo_store_status` (`store_id`, `status`),
                INDEX `idx_lo_store_date` (`store_id`, `date_created`),
                CONSTRAINT `fk_lo_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 4. Local Customers Table (Store-scoped)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `wc_local_customers` (
                `store_id` INT UNSIGNED NOT NULL,
                `wc_id` BIGINT UNSIGNED NOT NULL,
                `email` VARCHAR(255) NULL,
                `first_name` VARCHAR(100) NULL,
                `last_name` VARCHAR(100) NULL,
                `username` VARCHAR(100) NULL,
                `role` VARCHAR(50) NULL DEFAULT 'customer',
                `orders_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_spent` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                `raw_data` LONGTEXT NULL,
                `synced_at` DATETIME NOT NULL,
                PRIMARY KEY (`store_id`, `wc_id`),
                INDEX `idx_lc_store_email` (`store_id`, `email`),
                CONSTRAINT `fk_lc_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 5. Local Categories Table (Store-scoped)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `wc_local_categories` (
                `store_id` INT UNSIGNED NOT NULL,
                `wc_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NULL,
                `parent` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `count` INT UNSIGNED NOT NULL DEFAULT 0,
                `raw_data` JSON NULL,
                `synced_at` DATETIME NOT NULL,
                PRIMARY KEY (`store_id`, `wc_id`),
                INDEX `idx_lcat_store_parent` (`store_id`, `parent`),
                CONSTRAINT `fk_lcat_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 6. Price Backups Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `price_backups` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `operation_id` INT UNSIGNED NULL,
                `schema_version` VARCHAR(20) NOT NULL DEFAULT '1.0',
                `backup_uid` VARCHAR(100) NOT NULL UNIQUE,
                `filename` VARCHAR(255) NOT NULL,
                `operation_type` VARCHAR(100) NOT NULL,
                `items_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `checksum` VARCHAR(64) NOT NULL,
                `file_path` VARCHAR(255) NOT NULL,
                `currency` VARCHAR(10) NOT NULL DEFAULT 'IRT',
                `status` ENUM('valid', 'restored', 'partial_restored', 'invalid') NOT NULL DEFAULT 'valid',
                `meta` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_pb_store` (`store_id`),
                INDEX `idx_pb_op` (`operation_id`),
                CONSTRAINT `fk_pb_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_pb_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 7. Enhance bulk_operation_items table with verification & price safety columns
        $itemColumns = $pdo->query("SHOW COLUMNS FROM `bulk_operation_items`")->fetchAll(PDO::FETCH_COLUMN);

        $pdo->exec("ALTER TABLE `bulk_operation_items` MODIFY COLUMN `status` ENUM(
            'pending', 'processing', 'completed', 'success', 'failed', 'skipped', 'cancelled', 'verification_failed', 'restored'
        ) NOT NULL DEFAULT 'pending'");

        if (!in_array('expected_price', $itemColumns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operation_items` ADD COLUMN `expected_price` VARCHAR(50) NULL AFTER `new_state`");
        }
        if (!in_array('verified_price', $itemColumns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operation_items` ADD COLUMN `verified_price` VARCHAR(50) NULL AFTER `expected_price`");
        }
        if (!in_array('verification_status', $itemColumns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operation_items` ADD COLUMN `verification_status` ENUM('unverified', 'verified', 'discrepancy') NOT NULL DEFAULT 'unverified' AFTER `verified_price`");
        }
        if (!in_array('verification_notes', $itemColumns, true)) {
            $pdo->exec("ALTER TABLE `bulk_operation_items` ADD COLUMN `verification_notes` TEXT NULL AFTER `verification_status`");
        }

        // 8. Register and assign permissions
        $permissions = [
            ['name' => 'bulk.backup', 'group_name' => 'bulk', 'display_name' => 'پشتیبان‌گیری قیمت‌ها', 'description' => 'ایجاد و دانلود فایل پشتیبان قیمت محصولات قبل از تغییرات'],
            ['name' => 'bulk.restore', 'group_name' => 'bulk', 'display_name' => 'بازگردانی قیمت‌ها', 'description' => 'بازیابی قیمت محصولات از فایل پشتیبان JSON'],
            ['name' => 'sync.manage', 'group_name' => 'sync', 'display_name' => 'مدیریت و اجرای همگام‌سازی محلی', 'description' => 'اجرای همگام‌سازی دستی و پیکربندی کش محلی'],
            ['name' => 'sync.view', 'group_name' => 'sync', 'display_name' => 'مشاهده وضعیت همگام‌سازی', 'description' => 'مشاهده تاریخچه و وضعیت کش محلی فروشگاه'],
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

        // Grant to Admin and Manager roles
        $adminRoles = $pdo->query("SELECT id FROM `roles` WHERE LOWER(`name`) = 'admin' OR `slug` = 'administrator'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($adminRoles as $adminRoleId) {
            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) SELECT :role_id, id FROM `permissions` WHERE `name` IN ('bulk.backup', 'bulk.restore', 'sync.manage', 'sync.view')");
            $assignStmt->execute([':role_id' => $adminRoleId]);
        }

        $managerRoles = $pdo->query("SELECT id FROM `roles` WHERE LOWER(`name`) = 'manager' OR `slug` = 'manager'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($managerRoles as $mgrRoleId) {
            $assignStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) SELECT :role_id, id FROM `permissions` WHERE `name` IN ('bulk.backup', 'bulk.restore', 'sync.manage', 'sync.view')");
            $assignStmt->execute([':role_id' => $mgrRoleId]);
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `price_backups`;");
        $pdo->exec("DROP TABLE IF EXISTS `wc_local_categories`;");
        $pdo->exec("DROP TABLE IF EXISTS `wc_local_customers`;");
        $pdo->exec("DROP TABLE IF EXISTS `wc_local_orders`;");
        $pdo->exec("DROP TABLE IF EXISTS `wc_local_products`;");
        $pdo->exec("DROP TABLE IF EXISTS `wc_local_sync_meta`;");
    }
}
