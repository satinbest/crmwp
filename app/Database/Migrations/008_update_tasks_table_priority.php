<?php

class UpdateTasksTablePriority
{
    public function up(PDO $pdo): void
    {
        $cols = $pdo->query("SHOW COLUMNS FROM `tasks` LIKE 'priority'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium' AFTER `description`;");
        }
    }

    public function down(PDO $pdo): void
    {
        // Safe down
    }
}
