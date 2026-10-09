<?php

declare(strict_types=1);

namespace App\Services;

use App\Automation\AutomationEngine;
use App\Database\Connection;
use App\Support\Logger;
use PDO;
use Throwable;

class CronService
{
    private string $lockDir;
    private string $lockFile;

    public function __construct(?string $rootDir = null)
    {
        $baseDir = $rootDir ?? dirname(__DIR__, 2);
        $this->lockDir = $baseDir . '/storage/locks';
        $this->lockFile = $this->lockDir . '/cron.lock';
    }

    /**
     * Execute all periodic cron tasks with file-lock concurrency protection.
     *
     * @return array Results and metrics of execution
     */
    public function run(): array
    {
        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0755, true);
        }

        $lockHandle = fopen($this->lockFile, 'c+');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle) {
                fclose($lockHandle);
            }
            return [
                'status' => 'skipped',
                'message' => 'Another cron process is currently running. Skipping execution.',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        $startTime = microtime(true);
        $result = [
            'status' => 'success',
            'started_at' => date('Y-m-d H:i:s'),
            'automations' => ['processed' => 0, 'scheduled_count' => 0],
            'stale_bulk_recovered' => 0,
            'cleanup' => [
                'notifications_deleted' => 0,
                'webhook_logs_deleted' => 0,
                'sync_logs_deleted' => 0,
            ],
            'errors' => [],
            'duration_seconds' => 0.0,
        ];

        try {
            // 1. Process Scheduled Automations
            try {
                $engine = new AutomationEngine();
                $autoRes = $engine->runScheduled(50);
                $result['automations']['processed'] = (int)($autoRes['processed'] ?? 0);
                $result['automations']['scheduled_count'] = (int)($autoRes['scheduled_automations_count'] ?? 0);
            } catch (Throwable $e) {
                $result['errors'][] = 'Automations: ' . $e->getMessage();
                Logger::error('Cron AutomationEngine error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }

            // 2. Recover Stale / Interrupted Bulk Operations & Sync Locks
            try {
                $pdo = Connection::get();
                $staleStmt = $pdo->prepare(
                    "UPDATE bulk_operations SET status = 'failed' WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
                );
                $staleStmt->execute();
                $result['stale_bulk_recovered'] = $staleStmt->rowCount();

                // Recover expired sync locks in wc_local_sync_meta
                $staleSyncStmt = $pdo->prepare(
                    "UPDATE wc_local_sync_meta SET status = 'failed', last_error = 'انقضای خودکار قفل همگام‌سازی پس از ۱۵ دقیقه' WHERE status = 'running' AND last_sync_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
                );
                $staleSyncStmt->execute();
                $result['stale_sync_recovered'] = $staleSyncStmt->rowCount();
            } catch (Throwable $e) {
                $result['errors'][] = 'Recovery: ' . $e->getMessage();
            }

            // 3. Batch Cleanup of Old Logs & Notifications (Older than 30 days)
            try {
                $pdo = Connection::get();
                // Notifications cleanup
                $notifStmt = $pdo->prepare(
                    "DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 500"
                );
                $notifStmt->execute();
                $result['cleanup']['notifications_deleted'] = $notifStmt->rowCount();

                // Webhook logs cleanup
                $whStmt = $pdo->prepare(
                    "DELETE FROM webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 500"
                );
                $whStmt->execute();
                $result['cleanup']['webhook_logs_deleted'] = $whStmt->rowCount();

                // Sync logs cleanup
                $syncStmt = $pdo->prepare(
                    "DELETE FROM sync_logs WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 500"
                );
                $syncStmt->execute();
                $result['cleanup']['sync_logs_deleted'] = $syncStmt->rowCount();
            } catch (Throwable $e) {
                $result['errors'][] = 'Log cleanup: ' . $e->getMessage();
            }

            $result['duration_seconds'] = round(microtime(true) - $startTime, 3);
            if (!empty($result['errors'])) {
                $result['status'] = 'completed_with_warnings';
            }
        } catch (Throwable $e) {
            $result['status'] = 'failed';
            $result['errors'][] = $e->getMessage();
            Logger::error('Cron execution failed completely: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        return $result;
    }
}
