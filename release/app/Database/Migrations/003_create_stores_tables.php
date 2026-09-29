<?php

class CreateStoresTables
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `stores` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `url` VARCHAR(255) NOT NULL,
                `consumer_key_encrypted` TEXT NOT NULL,
                `consumer_secret_encrypted` TEXT NOT NULL,
                `status` ENUM('active', 'inactive', 'error') NOT NULL DEFAULT 'inactive',
                `wp_version` VARCHAR(50) NULL,
                `wc_version` VARCHAR(50) NULL,
                `hpos_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `currency` VARCHAR(10) DEFAULT 'IRR',
                `timezone` VARCHAR(50) DEFAULT 'Asia/Tehran',
                `capabilities` JSON NULL,
                `last_sync_at` DATETIME NULL,
                `last_error` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_stores` (
                `user_id` INT UNSIGNED NOT NULL,
                `store_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`user_id`, `store_id`),
                CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_us_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `user_stores`;");
        $pdo->exec("DROP TABLE IF EXISTS `stores`;");
    }
}
