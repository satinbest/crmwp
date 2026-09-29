<?php

class CreateCrmTables
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `customer_notes` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `wc_customer_id` BIGINT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `content` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_cn_store_customer` (`store_id`, `wc_customer_id`),
                CONSTRAINT `fk_cn_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_cn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tags` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `color` VARCHAR(20) DEFAULT '#4F46E5',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_tag_store_name` (`store_id`, `name`),
                CONSTRAINT `fk_tag_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `customer_tags` (
                `tag_id` INT UNSIGNED NOT NULL,
                `store_id` INT UNSIGNED NOT NULL,
                `wc_customer_id` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`tag_id`, `store_id`, `wc_customer_id`),
                INDEX `idx_ct_store_customer` (`store_id`, `wc_customer_id`),
                CONSTRAINT `fk_ct_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ct_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `segments` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `description` VARCHAR(255) NULL,
                `rules` JSON NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_seg_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tasks` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `wc_customer_id` BIGINT UNSIGNED NULL,
                `assigned_user_id` INT UNSIGNED NULL,
                `created_by_user_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
                `due_date` DATETIME NULL,
                `completed_at` DATETIME NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_task_store_customer` (`store_id`, `wc_customer_id`),
                CONSTRAINT `fk_task_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_task_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_task_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `activities` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `action_type` VARCHAR(100) NOT NULL,
                `entity_type` VARCHAR(50) NOT NULL,
                `entity_id` BIGINT UNSIGNED NOT NULL,
                `details` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_act_store_entity` (`store_id`, `entity_type`, `entity_id`),
                CONSTRAINT `fk_act_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_act_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `activities`;");
        $pdo->exec("DROP TABLE IF EXISTS `tasks`;");
        $pdo->exec("DROP TABLE IF EXISTS `segments`;");
        $pdo->exec("DROP TABLE IF EXISTS `customer_tags`;");
        $pdo->exec("DROP TABLE IF EXISTS `tags`;");
        $pdo->exec("DROP TABLE IF EXISTS `customer_notes`;");
    }
}
