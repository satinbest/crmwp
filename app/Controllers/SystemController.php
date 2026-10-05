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
        $storageCacheWritable = is_writable(dirname(__DIR__, 2) . '/storage/framework/cache') || is_writable(dirname(__DIR__, 2) . '/storage/cache');

        $isOk = ($dbStatus === 'connected') && $storageLogsWritable;

        $healthData = [
            'status' => $isOk ? 'ok' : 'degraded',
            'app_env' => Config::get('app.env', 'production'),
            'php_version' => PHP_VERSION,
            'database' => [
                'status' => $dbStatus,
                'version' => $dbVersion,
            ],
            'cache' => \App\Support\Cache::getStats(),
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
     * GET /api/v1/system/about
     * Returns sanitized application metadata, versions, and connection summary
     */
    public function about(Request $request): Response
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

        // Active Store Info (Sanitized - Zero Secrets)
        $storeInfo = null;
        try {
            $storeRepo = new \App\Repositories\StoreRepository();
            $storeId = (int)($request->header('X-Store-ID') ?? $request->query('store_id') ?? 1);
            $store = $storeRepo->findById($storeId) ?? $storeRepo->all()[0] ?? null;

            if ($store) {
                $storeInfo = [
                    'id' => $store->id,
                    'name' => $store->name,
                    'url' => $store->url,
                    'status' => $store->status,
                    'is_demo' => $store->isDemo(),
                    'woocommerce_version' => $store->woocommerce_version ?: ($store->wc_version ?: '11.1.2'),
                    'wordpress_version' => $store->wordpress_version ?: ($store->wp_version ?: '7.1.2'),
                    'hpos_enabled' => (bool)$store->hpos_enabled,
                    'currency' => $store->currency ?: 'IRT',
                    'timezone' => $store->timezone ?: 'Asia/Tehran',
                    'last_connection_check' => $store->last_connection_check,
                    'connection_status' => in_array($store->status, ['active'], true) ? 'connected' : ($store->status === 'inactive' ? 'disconnected' : 'error'),
                ];
            }
        } catch (Throwable $e) {
            // Silently fallback without crashing
        }

        $aboutData = [
            'app' => [
                'name' => Config::get('app.name', 'CRMWP'),
                'title' => 'سامانه مدیریت یکپارچه ووکامرس و مشتریان (CRM)',
                'version' => Config::get('app.version', '1.0.0'),
                'environment' => Config::get('app.env', 'production'),
                'locale' => Config::get('app.locale', 'fa'),
                'timezone' => Config::get('app.timezone', 'Asia/Tehran'),
                'description' => 'سامانه متمرکز و بومی برای مدیریت هوشمند فروشگاه WooCommerce، مشتریان، سفارش‌ها، محصولات، انبارداری و اتوماسیون ارتباط با مشتری.',
            ],
            'developer' => [
                'name' => Config::get('app.developer.name', 'تیم توسعه CRMWP'),
                'website' => Config::get('app.developer.website', ''),
                'email' => Config::get('app.developer.email', ''),
            ],
            'runtime' => [
                'php_version' => PHP_VERSION,
                'database' => [
                    'type' => 'MariaDB / MySQL',
                    'version' => $dbVersion,
                    'status' => $dbStatus,
                ],
                'cache' => \App\Support\Cache::getStats(),
            ],
            'technologies' => [
                'frontend' => [
                    ['name' => 'Vue.js 3', 'badge' => 'Composition API', 'icon' => 'vue'],
                    ['name' => 'Vite', 'badge' => 'Build Tool', 'icon' => 'vite'],
                    ['name' => 'Tailwind CSS', 'badge' => 'Utility-First CSS', 'icon' => 'tailwind'],
                    ['name' => 'Vazirmatn', 'badge' => 'Local Persian Font', 'icon' => 'font'],
                    ['name' => 'Iconsax', 'badge' => 'Local SVG Icons', 'icon' => 'iconsax'],
                    ['name' => 'Pinia', 'badge' => 'State Management', 'icon' => 'pinia'],
                ],
                'backend' => [
                    ['name' => 'PHP 8.4', 'badge' => 'Clean Layered Architecture', 'icon' => 'php'],
                    ['name' => 'REST API', 'badge' => 'JSON API v1', 'icon' => 'api'],
                    ['name' => 'Object Cache', 'badge' => 'Multi-Driver (File/Redis)', 'icon' => 'cache'],
                ],
                'database' => [
                    ['name' => 'MariaDB / MySQL', 'badge' => 'ACID Relational Storage', 'icon' => 'database'],
                ],
                'integration' => [
                    ['name' => 'WooCommerce REST API', 'badge' => 'Direct v3 Integration', 'icon' => 'woo'],
                    ['name' => 'Webhooks Engine', 'badge' => 'Real-time Event Ingestion', 'icon' => 'webhook'],
                    ['name' => 'HPOS Support', 'badge' => 'High-Performance Order Storage', 'icon' => 'hpos'],
                ],
            ],
            'architecture' => [
                'flow' => 'CRM Client (Vue 3) → Backend API (PHP Layered) → WooCommerce REST API & Real-time Webhooks',
                'data' => 'CRM Extensions, Segments, Tasks & Logs → Local MariaDB Storage',
                'cache' => 'Query Acceleration & Rate Limiting → Multi-Driver Object Cache Layer',
            ],
            'production_independence' => [
                'offline_capable' => true,
                'no_external_cdn' => true,
                'local_assets' => true,
                'message' => 'این سامانه برای اجرای رابط کاربری و پردازش‌های خود کاملاً خودکفا بوده و به هیچ سرور خارجی، CDN یا فونت آنلاین وابسته نیست.',
            ],
            'ui_specs' => [
                'rtl' => true,
                'font' => 'Vazirmatn Local',
                'modes' => ['Light Mode', 'Dark Mode'],
                'responsive' => true,
            ],
            'store' => $storeInfo,
            'copyright' => [
                'year' => (int)date('Y'),
                'holder' => Config::get('app.developer.name', 'CRMWP'),
            ],
        ];

        return $this->success($aboutData);
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

    /**
     * GET /api/v1/system/cron
     * POST /api/v1/system/cron
     * Secure Web Cron endpoint for shared hosting environments
     */
    public function cron(Request $request): Response
    {
        $providedToken = (string)($request->query('token') ?? $request->input('token') ?? $request->header('X-Cron-Token') ?? '');
        $cronSecret = \App\Support\Env::get('CRON_SECRET', \App\Support\Env::get('APP_SECRET', ''));

        if (empty($cronSecret) || empty($providedToken) || !hash_equals($cronSecret, $providedToken)) {
            return $this->error(
                'FORBIDDEN',
                'دسترسی غیرمجاز. ارسال توکن امنیتی Cron الزامی است.',
                [],
                403
            );
        }

        $cronService = new \App\Services\CronService(dirname(__DIR__, 2));
        $result = $cronService->run();

        $statusCode = ($result['status'] === 'failed') ? 500 : 200;
        return $this->success($result, [], $statusCode);
    }

    /**
     * GET /api/v1/system/cache
     * Returns object cache telemetry, driver status, and configuration
     */
    public function cacheInfo(Request $request): Response
    {
        $driver = \App\Support\Cache::getDriver();
        $activeDriverName = $driver->getName();
        $isAvailable = $driver->isAvailable();

        $configuredDriver = strtolower((string)\App\Support\Env::get('CACHE_DRIVER', 'file'));
        $memcachedConnType = strtolower((string)\App\Support\Env::get('MEMCACHED_CONNECTION_TYPE', 'tcp'));
        $memcachedSocketPath = (string)\App\Support\Env::get('MEMCACHED_SOCKET_PATH', '/memcached.sock');
        $memcachedHost = (string)\App\Support\Env::get('MEMCACHED_HOST', '127.0.0.1');
        $memcachedPort = (int)\App\Support\Env::get('MEMCACHED_PORT', 11211);
        $redisHost = (string)\App\Support\Env::get('REDIS_HOST', '127.0.0.1');
        $redisPort = (int)\App\Support\Env::get('REDIS_PORT', 6379);
        $redisDb = (int)\App\Support\Env::get('REDIS_DB', 0);

        $extensions = [
            'memcached' => extension_loaded('memcached'),
            'redis' => extension_loaded('redis'),
            'apcu' => extension_loaded('apcu'),
        ];

        // Safe status string
        $status = 'disabled';
        if ($isAvailable) {
            $status = 'connected';
        } elseif ($configuredDriver !== 'file' && $configuredDriver !== 'null') {
            $status = 'disconnected';
        }

        $stats = \App\Support\Cache::getStats();

        // Calculate file storage size if file driver
        $fileStorageSize = 0;
        $fileCount = 0;
        if ($activeDriverName === 'file') {
            $cacheDir = dirname(__DIR__, 2) . '/storage/framework/cache';
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*.cache') ?: [];
                $fileCount = count($files);
                foreach ($files as $f) {
                    $fileStorageSize += (int)@filesize($f);
                }
            }
        }

        return $this->success([
            'status' => $status,
            'active_driver' => $activeDriverName,
            'is_available' => $isAvailable,
            'configured_driver' => $configuredDriver,
            'extensions' => $extensions,
            'telemetry' => $stats,
            'storage' => [
                'file_items_count' => $fileCount,
                'file_size_bytes' => $fileStorageSize,
                'file_size_formatted' => $fileStorageSize > 1048576 ? round($fileStorageSize / 1048576, 2) . ' MB' : round($fileStorageSize / 1024, 2) . ' KB',
            ],
            'config' => [
                'memcached' => [
                    'connection_type' => $memcachedConnType,
                    'socket_path' => $memcachedSocketPath,
                    'host' => $memcachedHost,
                    'port' => $memcachedPort,
                    'supported' => $extensions['memcached'],
                ],
                'redis' => [
                    'host' => $redisHost,
                    'port' => $redisPort,
                    'database' => $redisDb,
                    'has_password' => !empty(\App\Support\Env::get('REDIS_PASSWORD')),
                    'supported' => $extensions['redis'],
                ],
                'apcu' => [
                    'supported' => $extensions['apcu'],
                ],
                'file' => [
                    'supported' => true,
                    'writable' => is_writable(dirname(__DIR__, 2) . '/storage/framework/cache') || is_writable(dirname(__DIR__, 2) . '/storage'),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/system/cache/test
     * Executes a real live test (CONNECT -> SET -> GET -> VERIFY -> DELETE)
     */
    public function testCacheConnection(Request $request): Response
    {
        $targetDriver = strtolower((string)$request->input('driver', ''));
        $connectionType = strtolower((string)$request->input('connection_type', 'tcp'));
        $socketPath = (string)$request->input('socket_path', '/memcached.sock');
        $host = (string)$request->input('host', '');
        $port = (int)$request->input('port', 0);
        $password = (string)$request->input('password', '');
        $database = (int)$request->input('database', 0);

        if (str_starts_with($host, '/') || $connectionType === 'socket' || $connectionType === 'unix_socket') {
            $connectionType = 'socket';
            if (empty($socketPath) || $socketPath === '/memcached.sock') {
                $socketPath = str_starts_with($host, '/') ? $host : $socketPath;
            }
        }

        $start = microtime(true);
        $testKey = 'crmwp_diag_test_' . bin2hex(random_bytes(4));
        $testValue = ['ping' => 'pong', 'timestamp' => time(), 'test_id' => $testKey];

        /** @var \App\Support\Cache\CacheDriverInterface $driver */
        $driver = null;
        $extensionName = '';

        if ($targetDriver === 'memcached') {
            $extensionName = 'memcached';
            if (!extension_loaded('memcached')) {
                return $this->success([
                    'success' => false,
                    'driver' => 'memcached',
                    'connection_type' => $connectionType,
                    'connection_status' => 'BLOCKED / NOT AVAILABLE',
                    'extension_installed' => false,
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0,
                    'message' => 'اکستنشن PHP Memcached بر روی سرور نصب یا فعال نیست (BLOCKED / NOT AVAILABLE).',
                ]);
            }
            $testHost = !empty($host) ? $host : (string)\App\Support\Env::get('MEMCACHED_HOST', '127.0.0.1');
            $testPort = $port > 0 ? $port : (int)\App\Support\Env::get('MEMCACHED_PORT', 11211);
            $driver = new \App\Support\Cache\MemcachedCacheDriver($testHost, $testPort, $connectionType, $socketPath);
        } elseif ($targetDriver === 'redis') {
            $extensionName = 'redis';
            if (!extension_loaded('redis')) {
                return $this->success([
                    'success' => false,
                    'driver' => 'redis',
                    'connection_type' => 'tcp',
                    'connection_status' => 'BLOCKED / NOT AVAILABLE',
                    'extension_installed' => false,
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0,
                    'message' => 'اکستنشن PHP Redis بر روی سرور نصب یا فعال نیست (BLOCKED / NOT AVAILABLE).',
                ]);
            }
            $testHost = !empty($host) ? $host : (string)\App\Support\Env::get('REDIS_HOST', '127.0.0.1');
            $testPort = $port > 0 ? $port : (int)\App\Support\Env::get('REDIS_PORT', 6379);
            $testPass = !empty($password) ? $password : \App\Support\Env::get('REDIS_PASSWORD');
            $testDb = $database > 0 ? $database : (int)\App\Support\Env::get('REDIS_DB', 0);
            $driver = new \App\Support\Cache\RedisCacheDriver($testHost, $testPort, $testPass, $testDb);
        } elseif ($targetDriver === 'apcu') {
            $extensionName = 'apcu';
            if (!extension_loaded('apcu')) {
                return $this->success([
                    'success' => false,
                    'driver' => 'apcu',
                    'connection_type' => 'memory',
                    'connection_status' => 'BLOCKED / NOT AVAILABLE',
                    'extension_installed' => false,
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0,
                    'message' => 'اکستنشن PHP APCu بر روی سرور فعال نیست (BLOCKED / NOT AVAILABLE).',
                ]);
            }
            $driver = new \App\Support\Cache\ApcuCacheDriver();
        } else {
            // Default active driver
            $driver = \App\Support\Cache::getDriver();
        }

        if (!$driver->isAvailable()) {
            $isSocket = ($targetDriver === 'memcached' && $connectionType === 'socket');
            $failMessage = $isSocket
                ? "اتصال به Unix Socket در مسیر '{$socketPath}' امکان‌پذیر نیست یا سرویس در دسترس نمی‌باشد (BLOCKED / NOT AVAILABLE)."
                : "برقراری ارتباط با سرویس {$driver->getName()} با شکست مواجه شد. لطفاً هاست و پورت سرور را بررسی نمایید.";

            return $this->success([
                'success' => false,
                'driver' => $driver->getName(),
                'connection_type' => $connectionType,
                'connection_status' => 'BLOCKED / NOT AVAILABLE',
                'extension_installed' => $extensionName ? extension_loaded($extensionName) : true,
                'connected' => false,
                'write_ok' => false,
                'read_ok' => false,
                'delete_ok' => false,
                'latency_ms' => 0,
                'message' => $failMessage,
            ]);
        }

        // Live Real Operational Test
        $writeOk = $driver->set($testKey, $testValue, 10);
        $readOk = false;
        if ($writeOk) {
            $readData = $driver->get($testKey);
            $readOk = is_array($readData) && isset($readData['ping']) && $readData['ping'] === 'pong';
        }
        $deleteOk = $driver->forget($testKey);
        $hasKeyAfterDelete = $driver->has($testKey);
        $deleteVerified = $deleteOk && !$hasKeyAfterDelete;

        $latencyMs = round((microtime(true) - $start) * 1000, 2);
        $allPassed = $writeOk && $readOk && $deleteVerified;

        return $this->success([
            'success' => $allPassed,
            'driver' => $driver->getName(),
            'connection_type' => $connectionType,
            'connection_status' => $allPassed ? 'CONNECTED' : 'PARTIAL_FAILURE',
            'extension_installed' => true,
            'connected' => true,
            'write_ok' => $writeOk,
            'read_ok' => $readOk,
            'delete_ok' => $deleteVerified,
            'latency_ms' => $latencyMs,
            'message' => $allPassed
                ? "تست کامل کش ({$driver->getName()} - {$connectionType}) با موفقیت در زمان {$latencyMs} میلی‌ثانیه به پایان رسید (Write, Read, Delete تایید شد)."
                : "تست عملیاتی با خطا مواجه شد.",
        ]);
    }

    /**
     * POST /api/v1/system/cache/flush
     * Clears all cached objects across the application
     */
    public function flushCache(Request $request): Response
    {
        $user = $this->currentUser($request);
        if (!$user) {
            return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
        }

        $rbac = new \App\Services\RbacService();
        if (!$rbac->userHasPermission((int)$user->id, 'settings.manage') &&
            !$rbac->userHasRole((int)$user->id, 'admin')) {
            return $this->error('FORBIDDEN', 'شما مجوز پاکسازی کش سراسری را ندارید.', [], 403);
        }

        $cleared = \App\Support\Cache::flush();

        return $this->success([
            'cleared' => $cleared,
            'message' => 'کلیه داده‌های کش شیء با موفقیت پاکسازی شدند.',
            'timestamp' => date('c'),
        ]);
    }
}

