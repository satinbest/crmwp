<?php

class CreateBulkAndAuditTables
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `audit_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NULL,
                `store_id` INT UNSIGNED NULL,
                `action` VARCHAR(100) NOT NULL,
                `entity_type` VARCHAR(50) NOT NULL,
                `entity_id` VARCHAR(100) NULL,
                `old_values` JSON NULL,
                `new_values` JSON NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_al_action` (`action`),
                INDEX `idx_al_user` (`user_id`),
                INDEX `idx_al_store` (`store_id`),
                CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_al_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bulk_operations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `target_entity` ENUM('product', 'order', 'customer') NOT NULL,
                `status` ENUM('pending', 'processing', 'completed', 'partial', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
                `total_items` INT UNSIGNED NOT NULL DEFAULT 0,
                `processed_items` INT UNSIGNED NOT NULL DEFAULT 0,
                `success_items` INT UNSIGNED NOT NULL DEFAULT 0,
                `failed_items` INT UNSIGNED NOT NULL DEFAULT 0,
                `filter_criteria` JSON NULL,
                `payload` JSON NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_bo_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_bo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bulk_operation_items` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `bulk_operation_id` INT UNSIGNED NOT NULL,
                `entity_id` BIGINT UNSIGNED NOT NULL,
                `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
                `old_state` JSON NULL,
                `new_state` JSON NULL,
                `error_message` TEXT NULL,
                `processed_at` DATETIME NULL,
                INDEX `idx_boi_op` (`bulk_operation_id`),
                CONSTRAINT `fk_boi_op` FOREIGN KEY (`bulk_operation_id`) REFERENCES `bulk_operations` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `type` VARCHAR(50) NOT NULL DEFAULT 'info',
                `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_notif_user` (`user_id`),
                CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
                `key_name` VARCHAR(100) PRIMARY KEY,
                `value` LONGTEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `settings`;");
        $pdo->exec("DROP TABLE IF EXISTS `notifications`;");
        $pdo->exec("DROP TABLE IF EXISTS `bulk_operation_items`;");
        $pdo->exec("DROP TABLE IF EXISTS `bulk_operations`;");
        $pdo->exec("DROP TABLE IF EXISTS `audit_logs`;");
    }
}
