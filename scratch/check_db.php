<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

$pdo = \App\Database\Connection::get();

foreach (['stores', 'webhook_logs', 'sync_logs'] as $table) {
    echo "=== {$table} ===\n";
    $stmt = $pdo->query("DESCRIBE {$table}");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
}
