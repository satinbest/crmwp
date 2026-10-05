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
            'app_version' => Config::get('app.version', defined('CRM_APP_VERSION') ? CRM_APP_VERSION : '1.1.0'),
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
                'version' => Config::get('app.version', defined('CRM_APP_VERSION') ? CRM_APP_VERSION : '1.1.0'),
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

        $hasMemcachedExt = extension_loaded('memcached');
        $hasMemcacheExt = extension_loaded('memcache');
        $extensions = [
            'memcached' => $hasMemcachedExt,
            'memcache' => $hasMemcacheExt,
            'memcached_any' => $hasMemcachedExt || $hasMemcacheExt,
            'redis' => extension_loaded('redis'),
            'apcu' => extension_loaded('apcu'),
        ];

        // Overall status
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
        $cacheDir = dirname(__DIR__, 2) . '/storage/framework/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.cache') ?: [];
            $fileCount = count($files);
            foreach ($files as $f) {
                $fileStorageSize += (int)@filesize($f);
            }
        }

        $socketExists = !empty($memcachedSocketPath) && file_exists($memcachedSocketPath);
        $socketReadable = $socketExists && is_readable($memcachedSocketPath);
        $socketWritable = $socketExists && is_writable($memcachedSocketPath);

        return $this->success([
            'status' => $status,
            'active_driver' => $activeDriverName,
            'is_available' => $isAvailable,
            'configured_driver' => $configuredDriver,
            'extensions' => $extensions,
            'extension_versions' => [
                'memcached' => $hasMemcachedExt ? phpversion('memcached') : null,
                'memcache' => $hasMemcacheExt ? phpversion('memcache') : null,
                'redis' => $extensions['redis'] ? phpversion('redis') : null,
                'apcu' => $extensions['apcu'] ? phpversion('apcu') : null,
            ],
            'system' => [
                'os_family' => PHP_OS_FAMILY,
                'php_user' => get_current_user(),
            ],
            'telemetry' => $stats,
            'storage' => [
                'file_items_count' => $fileCount,
                'file_size_bytes' => $fileStorageSize,
                'file_size_formatted' => $fileStorageSize > 1048576 ? round($fileStorageSize / 1048576, 2) . ' MB' : round($fileStorageSize / 1024, 2) . ' KB',
            ],
            'drivers_status' => [
                'file_cache' => [
                    'active' => true,
                    'status' => 'healthy',
                    'label' => 'فعال و آماده',
                    'items_count' => $fileCount,
                    'writable' => is_writable($cacheDir) || is_writable(dirname(__DIR__, 2) . '/storage'),
                ],
                'object_cache' => [
                    'connected' => ($activeDriverName === 'memcached' || $activeDriverName === 'redis') && $isAvailable,
                    'status' => (($activeDriverName === 'memcached' || $activeDriverName === 'redis') && $isAvailable) ? 'connected' : 'disconnected',
                    'label' => (($activeDriverName === 'memcached' || $activeDriverName === 'redis') && $isAvailable) ? 'متصل و پایدار' : 'اتصال برقرار نیست',
                ],
            ],
            'config' => [
                'memcached' => [
                    'connection_type' => $memcachedConnType,
                    'socket_path' => $memcachedSocketPath,
                    'socket_exists' => $socketExists,
                    'socket_accessible' => $socketReadable && $socketWritable,
                    'host' => $memcachedHost,
                    'port' => $memcachedPort,
                    'supported' => $extensions['memcached_any'],
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
                    'writable' => is_writable($cacheDir) || is_writable(dirname(__DIR__, 2) . '/storage'),
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
        $targetDriver = strtolower((string)$request->input('driver', 'file'));
        $connectionType = strtolower((string)$request->input('connection_type', 'tcp'));
        $socketPath = (string)$request->input('socket_path', '/memcached.sock');
        $host = (string)$request->input('host', '');
        $port = (int)$request->input('port', 0);
        $password = (string)$request->input('password', '');
        $database = (int)$request->input('database', 0);

        if (str_starts_with($host, '/') || str_starts_with($host, '\\') || $connectionType === 'socket' || $connectionType === 'unix_socket') {
            $connectionType = 'socket';
            if (empty($socketPath) || $socketPath === '/memcached.sock') {
                $socketPath = (str_starts_with($host, '/') || str_starts_with($host, '\\')) ? $host : $socketPath;
            }
        }

        // Test Memcached with complete diagnostic inspection
        if ($targetDriver === 'memcached' || $targetDriver === 'memcache') {
            $testHost = !empty($host) ? $host : (string)\App\Support\Env::get('MEMCACHED_HOST', '127.0.0.1');
            $testPort = $port > 0 ? $port : (int)\App\Support\Env::get('MEMCACHED_PORT', 11211);
            $testSocket = !empty($socketPath) ? $socketPath : (string)\App\Support\Env::get('MEMCACHED_SOCKET_PATH', '/memcached.sock');

            $driver = new \App\Support\Cache\MemcachedCacheDriver($testHost, $testPort, $connectionType, $testSocket);
            $diagResult = $driver->runDiagnosticTest();

            return $this->success($diagResult);
        }

        // Test File Cache
        if ($targetDriver === 'file') {
            $start = microtime(true);
            $fileDriver = new \App\Support\Cache\FileCacheDriver();
            $testKey = '__crmwp_file_diag_' . bin2hex(random_bytes(4));
            $testVal = ['diag' => 'ok', 'time' => time()];

            $writeOk = $fileDriver->set($testKey, $testVal, 10);
            $readOk = false;
            if ($writeOk) {
                $ret = $fileDriver->get($testKey);
                $readOk = is_array($ret) && isset($ret['diag']) && $ret['diag'] === 'ok';
            }
            $deleteOk = $fileDriver->forget($testKey);
            $hasAfter = $fileDriver->has($testKey);
            $deleteVerified = $deleteOk && !$hasAfter;

            $latency = round((microtime(true) - $start) * 1000, 2);
            $allPassed = $writeOk && $readOk && $deleteVerified;

            return $this->success([
                'success' => $allPassed,
                'driver' => 'file',
                'connection_type' => 'local_disk',
                'connection_status' => $allPassed ? 'CONNECTED' : 'PARTIAL_FAILURE',
                'extension_installed' => true,
                'connected' => true,
                'write_ok' => $writeOk,
                'read_ok' => $readOk,
                'delete_ok' => $deleteVerified,
                'latency_ms' => $latency,
                'message' => $allPassed
                    ? "کش دیسک محلی (File Cache) کاملاً سالم و فعال است (تاخیر: {$latency} میلی‌ثانیه)."
                    : "خطا در تست نوشتن یا خواندن کش دیسک محلی.",
            ]);
        }

        // Test Redis
        if ($targetDriver === 'redis') {
            $start = microtime(true);
            if (!extension_loaded('redis')) {
                return $this->success([
                    'success' => false,
                    'driver' => 'redis',
                    'connection_type' => 'tcp',
                    'connection_status' => 'NOT_INSTALLED',
                    'extension_installed' => false,
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0.0,
                    'message' => 'اکستنشن PHP Redis بر روی سرور نصب نیست.',
                ]);
            }

            $testHost = !empty($host) ? $host : (string)\App\Support\Env::get('REDIS_HOST', '127.0.0.1');
            $testPort = $port > 0 ? $port : (int)\App\Support\Env::get('REDIS_PORT', 6379);
            $testPass = !empty($password) ? $password : \App\Support\Env::get('REDIS_PASSWORD');
            $testDb = $database > 0 ? $database : (int)\App\Support\Env::get('REDIS_DB', 0);
            $redisDriver = new \App\Support\Cache\RedisCacheDriver($testHost, $testPort, $testPass, $testDb);

            if (!$redisDriver->isAvailable()) {
                return $this->success([
                    'success' => false,
                    'driver' => 'redis',
                    'connection_type' => 'tcp',
                    'connection_status' => 'CONNECTION_FAILED',
                    'extension_installed' => true,
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                    'message' => "عدم امکان اتصال به سرور Redis روی {$testHost}:{$testPort}.",
                ]);
            }

            $testKey = '__crmwp_redis_diag_' . bin2hex(random_bytes(4));
            $testVal = ['diag' => 'ok', 'time' => time()];
            $writeOk = $redisDriver->set($testKey, $testVal, 10);
            $readOk = false;
            if ($writeOk) {
                $ret = $redisDriver->get($testKey);
                $readOk = is_array($ret) && isset($ret['diag']) && $ret['diag'] === 'ok';
            }
            $deleteOk = $redisDriver->forget($testKey);
            $hasAfter = $redisDriver->has($testKey);
            $deleteVerified = $deleteOk && !$hasAfter;
            $latency = round((microtime(true) - $start) * 1000, 2);

            return $this->success([
                'success' => $writeOk && $readOk && $deleteVerified,
                'driver' => 'redis',
                'connection_type' => 'tcp',
                'connection_status' => 'CONNECTED',
                'extension_installed' => true,
                'connected' => true,
                'write_ok' => $writeOk,
                'read_ok' => $readOk,
                'delete_ok' => $deleteVerified,
                'latency_ms' => $latency,
                'message' => "تست کامل Redis با موفقیت انجام شد ({$latency} میلی‌ثانیه).",
            ]);
        }

        // Test APCu
        if ($targetDriver === 'apcu') {
            $start = microtime(true);
            if (!extension_loaded('apcu')) {
                return $this->success([
                    'success' => false,
                    'driver' => 'apcu',
                    'connection_type' => 'memory',
                    'connection_status' => 'NOT_INSTALLED',
                    'extension_installed' => false,
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0.0,
                    'message' => 'اکستنشن PHP APCu بر روی سرور نصب نیست.',
                ]);
            }

            $apcuDriver = new \App\Support\Cache\ApcuCacheDriver();
            $testKey = '__crmwp_apcu_diag_' . bin2hex(random_bytes(4));
            $testVal = ['diag' => 'ok', 'time' => time()];
            $writeOk = $apcuDriver->set($testKey, $testVal, 10);
            $readOk = false;
            if ($writeOk) {
                $ret = $apcuDriver->get($testKey);
                $readOk = is_array($ret) && isset($ret['diag']) && $ret['diag'] === 'ok';
            }
            $deleteOk = $apcuDriver->forget($testKey);
            $hasAfter = $apcuDriver->has($testKey);
            $deleteVerified = $deleteOk && !$hasAfter;
            $latency = round((microtime(true) - $start) * 1000, 2);

            return $this->success([
                'success' => $writeOk && $readOk && $deleteVerified,
                'driver' => 'apcu',
                'connection_type' => 'memory',
                'connection_status' => 'CONNECTED',
                'extension_installed' => true,
                'connected' => true,
                'write_ok' => $writeOk,
                'read_ok' => $readOk,
                'delete_ok' => $deleteVerified,
                'latency_ms' => $latency,
                'message' => "تست APCu با موفقیت انجام شد ({$latency} میلی‌ثانیه).",
            ]);
        }

        return $this->error('INVALID_DRIVER', 'درایور نامعتبر است.', [], 400);
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

    /**
     * GET /api/v1/system/donate
     * Returns sanitized developer donation configuration from backend source of truth
     */
    public function donateInfo(Request $request): Response
    {
        $donate = Config::get('app.donate', []);

        $rawCard = (string)($donate['card_number'] ?? '');
        $cleanCard = preg_replace('/\D/', '', $rawCard);

        return $this->success([
            'recipient_name' => $donate['recipient_name'] ?? 'کمک مالی به حسین محمدپور',
            'card_number' => $cleanCard,
            'email' => $donate['email'] ?? 'info@hosseinmohammadpour.ir',
            'github' => $donate['github'] ?? 'satinbest/crmwp',
            'has_card' => !empty($cleanCard),
        ]);
    }
}

