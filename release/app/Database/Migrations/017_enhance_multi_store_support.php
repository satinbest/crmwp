<?php

class EnhanceMultiStoreSupport
{
    public function up(PDO $pdo): void
    {
        // 1. Update stores table status enum & add is_demo, icon columns
        $storeCols = $pdo->query("SHOW COLUMNS FROM `stores`")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('is_demo', $storeCols, true)) {
            $pdo->exec("ALTER TABLE `stores` ADD COLUMN `is_demo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;");
        }

        if (!in_array('icon', $storeCols, true)) {
            $pdo->exec("ALTER TABLE `stores` ADD COLUMN `icon` VARCHAR(100) NULL AFTER `name`;");
        }

        // Expand status enum to include 'connection_error', 'disabled'
        $pdo->exec("ALTER TABLE `stores` MODIFY COLUMN `status` ENUM('active', 'inactive', 'connection_error', 'disabled', 'error') NOT NULL DEFAULT 'inactive';");

        // 2. Add performance composite indexes
        $this->addIndexIfNotExists($pdo, 'stores', 'idx_stores_status', '(`status`)');
        $this->addIndexIfNotExists($pdo, 'stores', 'idx_stores_demo', '(`is_demo`)');
        $this->addIndexIfNotExists($pdo, 'user_stores', 'idx_us_store_user', '(`store_id`, `user_id`)');
        $this->addIndexIfNotExists($pdo, 'customer_notes', 'idx_cn_store_created', '(`store_id`, `created_at`)');
        $this->addIndexIfNotExists($pdo, 'tasks', 'idx_task_store_status_due', '(`store_id`, `status`, `due_date`)');
        $this->addIndexIfNotExists($pdo, 'activities', 'idx_act_store_created', '(`store_id`, `created_at`)');
        $this->addIndexIfNotExists($pdo, 'audit_logs', 'idx_al_store_created', '(`store_id`, `created_at`)');
        $this->addIndexIfNotExists($pdo, 'notifications', 'idx_notif_store_user', '(`store_id`, `user_id`)');
    }

    public function down(PDO $pdo): void
    {
        // Keep columns safely
    }

    private function addIndexIfNotExists(PDO $pdo, string $table, string $indexName, string $columns): void
    {
        $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$columns};");
        }
    }
}
