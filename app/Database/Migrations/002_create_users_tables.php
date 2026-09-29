<?php

class CreateUsersTables
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `email` VARCHAR(191) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `first_name` VARCHAR(100) NULL,
                `last_name` VARCHAR(100) NULL,
                `avatar` VARCHAR(255) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_login_at` DATETIME NULL,
                `last_login_ip` VARCHAR(45) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_roles` (
                `user_id` INT UNSIGNED NOT NULL,
                `role_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`user_id`, `role_id`),
                CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `user_roles`;");
        $pdo->exec("DROP TABLE IF EXISTS `users`;");
    }
}
