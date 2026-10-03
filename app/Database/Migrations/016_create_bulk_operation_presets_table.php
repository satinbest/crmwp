<?php

class CreateBulkOperationPresetsTable
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bulk_operation_presets` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `store_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `target_entity` VARCHAR(50) NOT NULL DEFAULT 'products',
                `filter_criteria` JSON NOT NULL,
                `action_data` JSON NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_bop_store` (`store_id`),
                INDEX `idx_bop_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `bulk_operation_presets`");
    }
}
