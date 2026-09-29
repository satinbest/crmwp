<?php

class UpdateStoresTablePhase2
{
    public function up(PDO $pdo): void
    {
        // Add last_connection_check if it doesn't exist
        $cols = $pdo->query("SHOW COLUMNS FROM `stores` LIKE 'last_connection_check'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `stores` ADD COLUMN `last_connection_check` DATETIME NULL AFTER `last_sync_at`;");
        }

        // Add woocommerce_version if it doesn't exist
        $cols = $pdo->query("SHOW COLUMNS FROM `stores` LIKE 'woocommerce_version'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `stores` ADD COLUMN `woocommerce_version` VARCHAR(50) NULL AFTER `wc_version`;");
        }

        // Add wordpress_version if it doesn't exist
        $cols = $pdo->query("SHOW COLUMNS FROM `stores` LIKE 'wordpress_version'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `stores` ADD COLUMN `wordpress_version` VARCHAR(50) NULL AFTER `wp_version`;");
        }
    }

    public function down(PDO $pdo): void
    {
        // Keep columns safely
    }
}
