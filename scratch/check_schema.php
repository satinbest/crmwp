<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
$pdo = \App\Database\Connection::get();

$tables = [
    'customer_notes',
    'customer_tags',
    'tags',
    'segments',
    'tasks',
    'activities',
    'notifications',
    'audit_logs',
    'bulk_operations',
    'webhook_logs',
    'sync_logs',
    'stores',
    'user_stores',
];

foreach ($tables as $t) {
    $cols = $pdo->query("SHOW COLUMNS FROM `{$t}` LIKE 'store_id'")->fetchAll(PDO::FETCH_ASSOC);
    $allCols = $pdo->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN);
    $indexes = $pdo->query("SHOW INDEX FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
    $idxNames = array_unique(array_column($indexes, 'Key_name'));
    echo "Table {$t}:\n";
    echo "  store_id: " . (!empty($cols) ? 'YES' : 'NO') . "\n";
    echo "  indexes: " . implode(', ', $idxNames) . "\n\n";
}
