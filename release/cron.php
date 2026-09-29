<?php

declare(strict_types=1);

/**
 * WooCommerce Management & CRM Platform - CLI Cron Entry Point
 *
 * Usage:
 *   php cron.php
 *
 * Recommended crontab schedule:
 *   * * * * * cd /path/to/crmwp && php cron.php >> storage/logs/cron.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI access only.\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

use App\Support\Env;
use App\Support\Config;
use App\Support\Logger;
use App\Automation\AutomationEngine;
use App\Database\Connection;

Env::load(__DIR__ . '/.env');
Config::setPath(__DIR__ . '/config');
Logger::setLogDir(__DIR__ . '/storage/logs');

$lockDir = __DIR__ . '/storage/locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFilePath = $lockDir . '/cron.lock';
$lockHandle = fopen($lockFilePath, 'c+');

if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] [CRON] Another cron process is currently running. Skipping execution.\n";
    exit(0);
}

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] [CRON] Starting periodic background tasks...\n";

try {
    // 1. Process Scheduled Automations
    echo " - Running scheduled automations...\n";
    $engine = new AutomationEngine();
    $autoRes = $engine->runScheduled(50);
    echo "   -> Executed: {$autoRes['processed']} automations (Total scheduled: {$autoRes['scheduled_automations_count']})\n";

    // 2. Recover Stale / Interrupted Bulk Operations
    echo " - Checking stale bulk operations...\n";
    try {
        $pdo = Connection::get();
        // Any operation stuck in 'processing' for more than 2 hours is marked as failed
        $staleStmt = $pdo->prepare("UPDATE bulk_operations SET status = 'failed' WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        $staleStmt->execute();
        $staleCount = $staleStmt->rowCount();
        if ($staleCount > 0) {
            echo "   -> Recovered {$staleCount} stale bulk operation(s).\n";
        }
    } catch (\Throwable $e) {
        echo "   -> Bulk operation recovery note: " . $e->getMessage() . "\n";
    }

    // 3. Batch Cleanup of Old Logs & Notifications (Older than 30 days)
    echo " - Running retention cleanup (older than 30 days)...\n";
    try {
        $pdo = Connection::get();
        // Notifications cleanup (read notifications older than 30 days)
        $notifStmt = $pdo->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 500");
        $notifStmt->execute();
        $notifDeleted = $notifStmt->rowCount();

        // Webhook logs cleanup
        $whStmt = $pdo->prepare("DELETE FROM webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 500");
        $whStmt->execute();
        $whDeleted = $whStmt->rowCount();

        // Sync logs cleanup (uses started_at column)
        $syncStmt = $pdo->prepare("DELETE FROM sync_logs WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 500");
        $syncStmt->execute();
        $syncDeleted = $syncStmt->rowCount();

        echo "   -> Cleaned: {$notifDeleted} notifications, {$whDeleted} webhook logs, {$syncDeleted} sync logs.\n";
    } catch (\Throwable $e) {
        echo "   -> Cleanup note: " . $e->getMessage() . "\n";
    }

    $duration = round(microtime(true) - $startTime, 3);
    echo "[" . date('Y-m-d H:i:s') . "] [CRON] Background tasks finished successfully in {$duration}s.\n";
} catch (\Throwable $e) {
    Logger::error("CRON execution failed: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    echo "[" . date('Y-m-d H:i:s') . "] [CRON ERROR] " . $e->getMessage() . "\n";
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
