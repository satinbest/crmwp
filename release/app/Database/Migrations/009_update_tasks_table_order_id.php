<?php

class UpdateTasksTableOrderId
{
    public function up(PDO $pdo): void
    {
        $cols = $pdo->query("SHOW COLUMNS FROM `tasks` LIKE 'wc_order_id'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `wc_order_id` BIGINT UNSIGNED NULL AFTER `wc_customer_id`, ADD INDEX `idx_task_store_order` (`store_id`, `wc_order_id`);");
        }
    }

    public function down(PDO $pdo): void
    {
        $cols = $pdo->query("SHOW COLUMNS FROM `tasks` LIKE 'wc_order_id'")->fetchAll();
        if (!empty($cols)) {
            $pdo->exec("ALTER TABLE `tasks` DROP INDEX `idx_task_store_order`, DROP COLUMN `wc_order_id`;");
        }
    }
}
