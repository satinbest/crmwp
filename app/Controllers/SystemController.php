<?php

namespace App\Controllers;

use App\Database\Connection;
use App\Support\Config;
use App\Support\Request;
use App\Support\Response;
use PDO;
use Exception;
use Throwable;

class SystemController extends BaseController
{
    /**
     * GET /api/v1/system/health
     * GET /api/v1/health
     */
    public function health(Request $request): Response
    {
        $dbStatus = 'disconnected';
        $dbVersion = null;

        try {
            $pdo = Connection::get();
            $stmt = $pdo->query("SELECT VERSION() as v");
            $row = $stmt->fetch();
            $dbVersion = $row['v'] ?? 'unknown';
            $dbStatus = 'connected';
        } catch (Throwable $e) {
            $dbStatus = 'unavailable';
        }

        $storageLogsWritable = is_writable(dirname(__DIR__, 2) . '/storage/logs');
        $storageCacheWritable = is_writable(dirname(__DIR__, 2) . '/storage/cache');

        $isOk = ($dbStatus === 'connected') && $storageLogsWritable;

        $healthData = [
            'status' => $isOk ? 'ok' : 'degraded',
            'app_env' => Config::get('app.env', 'production'),
            'php_version' => PHP_VERSION,
            'database' => [
                'status' => $dbStatus,
                'version' => $dbVersion,
            ],
            'storage' => [
                'logs_writable' => $storageLogsWritable,
                'cache_writable' => $storageCacheWritable,
            ],
            'session' => [
                'status' => session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive',
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return $this->success($healthData, [], $isOk ? 200 : 503);
    }

    /**
     * GET /api/v1/health/liveness
     * GET /health
     * Fast check that PHP process is alive and responding
     */
    public function liveness(Request $request): Response
    {
        return $this->success([
            'status' => 'alive',
            'timestamp' => date('c'),
        ]);
    }

    /**
     * GET /api/v1/health/ready
     * Check if system is ready to accept traffic (DB & Storage)
     */
    public function readiness(Request $request): Response
    {
        $dbReady = false;
        try {
            $pdo = Connection::get();
            $pdo->query("SELECT 1");
            $dbReady = true;
        } catch (Throwable $e) {
            $dbReady = false;
        }

        $logsDir = dirname(__DIR__, 2) . '/storage/logs';
        $storageReady = is_dir($logsDir) && is_writable($logsDir);

        $ready = $dbReady && $storageReady;

        if (!$ready) {
            return $this->error(
                'NOT_READY',
                'سامانه هنوز آماده پاسخگویی کامل نیست.',
                [
                    'database' => $dbReady ? 'ready' : 'unreachable',
                    'storage' => $storageReady ? 'ready' : 'not_writable',
                ],
                503
            );
        }

        return $this->success([
            'status' => 'ready',
            'database' => 'ready',
            'storage' => 'ready',
            'timestamp' => date('c'),
        ]);
    }
}
