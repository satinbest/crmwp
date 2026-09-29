<?php

declare(strict_types=1);

/**
 * WooCommerce Management & CRM Platform - Periodic Cron Entry Point
 *
 * Usage:
 *   1. CLI (Recommended for VPS / SSH):
 *      php cron.php
 *      Schedule: * * * * * cd /path/to/crmwp && php cron.php >> storage/logs/cron.log 2>&1
 *
 *   2. Web Cron (For Shared Hosting without CLI):
 *      curl -s "https://crm.yourdomain.com/api/v1/system/cron?token=YOUR_CRON_SECRET"
 *      or:
 *      php -r "file_get_contents('https://crm.yourdomain.com/api/v1/system/cron?token=YOUR_CRON_SECRET');"
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\CronService;
use App\Support\Config;
use App\Support\Env;
use App\Support\Logger;

Env::load(__DIR__ . '/.env');
Config::setPath(__DIR__ . '/config');
Logger::setLogDir(__DIR__ . '/storage/logs');

$isCli = (php_sapi_name() === 'cli');

// If invoked via HTTP directly to cron.php, verify secret token
if (!$isCli) {
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    $cronSecret = Env::get('CRON_SECRET', Env::get('APP_SECRET', ''));

    if (empty($cronSecret) || empty($providedToken) || !hash_equals($cronSecret, $providedToken)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'code' => 'FORBIDDEN',
            'message' => 'Unauthorized cron execution. Valid security token is required.',
        ], JSON_UNESCAPED_UNICODE);
        exit(1);
    }
}

$cronService = new CronService(__DIR__);
$result = $cronService->run();

if ($isCli) {
    echo "[" . date('Y-m-d H:i:s') . "] [CRON] Status: {$result['status']}\n";
    if (isset($result['automations'])) {
        echo " - Automations processed: {$result['automations']['processed']} (Total scheduled: {$result['automations']['scheduled_count']})\n";
    }
    if (isset($result['stale_bulk_recovered'])) {
        echo " - Stale bulk operations recovered: {$result['stale_bulk_recovered']}\n";
    }
    if (isset($result['cleanup'])) {
        echo " - Cleanup: {$result['cleanup']['notifications_deleted']} notifications, {$result['cleanup']['webhook_logs_deleted']} webhook logs, {$result['cleanup']['sync_logs_deleted']} sync logs\n";
    }
    if (!empty($result['errors'])) {
        echo " - Warnings/Errors: " . implode('; ', $result['errors']) . "\n";
    }
    echo " - Execution duration: {$result['duration_seconds']}s\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
