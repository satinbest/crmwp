<?php

class CreateWebhooksAndSyncTables
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `webhook_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `topic` VARCHAR(100) NOT NULL,
                `event_id` VARCHAR(150) NULL,
                `payload` LONGTEXT NOT NULL,
                `status` ENUM('received', 'processed', 'ignored', 'error') NOT NULL DEFAULT 'received',
                `error_message` TEXT NULL,
                `ip_address` VARCHAR(45) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_wl_store_topic` (`store_id`, `topic`),
                INDEX `idx_wl_event` (`event_id`),
                CONSTRAINT `fk_wl_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `sync_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `sync_type` VARCHAR(50) NOT NULL,
                `status` ENUM('running', 'completed', 'failed') NOT NULL DEFAULT 'running',
                `summary` JSON NULL,
                `error_message` TEXT NULL,
                `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `completed_at` DATETIME NULL,
                INDEX `idx_sl_store` (`store_id`),
                CONSTRAINT `fk_sl_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `sync_logs`;");
        $pdo->exec("DROP TABLE IF EXISTS `webhook_logs`;");
    }
}
