<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Memcached;
use Memcache;
use Throwable;

class MemcachedCacheDriver implements CacheDriverInterface
{
    private mixed $client = null; // Memcached or Memcache instance
    private string $clientType = 'none'; // 'memcached', 'memcache', or 'none'
    private bool $connected = false;
    private string $connectionType = 'tcp'; // 'tcp' or 'socket'
    private string $host;
    private int $port;
    private ?string $socketPath = null;
    private ?string $connectionError = null;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 11211,
        string $connectionType = 'tcp',
        ?string $socketPath = null
    ) {
        // Auto-detect socket mode
        $trimmedHost = trim($host);
        $trimmedSocket = !empty($socketPath) ? trim($socketPath) : '';

        if (
            str_starts_with($trimmedHost, '/') ||
            str_starts_with($trimmedHost, '\\') ||
            $connectionType === 'socket' ||
            $connectionType === 'unix_socket' ||
            (!empty($trimmedSocket) && $connectionType !== 'tcp')
        ) {
            $this->connectionType = 'socket';
            $this->socketPath = !empty($trimmedSocket) ? $trimmedSocket : $trimmedHost;
            $this->host = $this->socketPath;
            $this->port = 0; // Unix domain socket MUST use port 0 in PHP Memcached
        } else {
            $this->connectionType = 'tcp';
            $this->host = !empty($trimmedHost) ? $trimmedHost : '127.0.0.1';
            $this->port = $port > 0 ? $port : 11211;
            $this->socketPath = null;
        }

        $this->initializeClient();
    }

    /**
     * Initialize connection using Memcached or Memcache PHP extension.
     */
    private function initializeClient(): void
    {
        // 1. Prioritize PECL 'memcached' extension (libmemcached)
        if (extension_loaded('memcached') && class_exists(Memcached::class)) {
            $this->clientType = 'memcached';
            $this->connectMemcached();
            return;
        }

        // 2. Fallback to legacy PECL 'memcache' extension if available
        if (extension_loaded('memcache') && class_exists(Memcache::class)) {
            $this->clientType = 'memcache';
            $this->connectMemcache();
            return;
        }

        $this->clientType = 'none';
        $this->connected = false;
        $this->connectionError = 'اکستنشن PHP Memcached یا Memcache بر روی این سرور نصب یا فعال نیست.';
    }

    private function connectMemcached(): void
    {
        try {
            $m = new Memcached();
            $m->setOption(Memcached::OPT_CONNECT_TIMEOUT, 300);
            $m->setOption(Memcached::OPT_POLL_TIMEOUT, 300);
            $m->setOption(Memcached::OPT_RETRY_TIMEOUT, 1);
            $m->setOption(Memcached::OPT_COMPRESSION, true);

            $servers = $m->getServerList();
            if (empty($servers)) {
                if ($this->connectionType === 'socket') {
                    if (empty($this->socketPath)) {
                        $this->connected = false;
                        $this->connectionError = 'مسیر فایل Unix Domain Socket مشخص نشده است.';
                        return;
                    }
                    $m->addServer($this->socketPath, 0);
                } else {
                    $m->addServer($this->host, $this->port);
                }
            }

            // Real probe via getStats()
            $stats = @$m->getStats();
            $isLive = false;

            if (is_array($stats) && !empty($stats)) {
                if ($this->connectionType === 'socket') {
                    $probeKeys = [$this->socketPath, "{$this->socketPath}:0"];
                    foreach ($probeKeys as $pk) {
                        if (isset($stats[$pk]) && is_array($stats[$pk]) && ($stats[$pk]['pid'] ?? 0) > 0) {
                            $isLive = true;
                            break;
                        }
                    }
                    if (!$isLive) {
                        foreach ($stats as $s) {
                            if (is_array($s) && ($s['pid'] ?? 0) > 0) {
                                $isLive = true;
                                break;
                            }
                        }
                    }
                } else {
                    $serverKey = "{$this->host}:{$this->port}";
                    if (isset($stats[$serverKey]) && is_array($stats[$serverKey]) && ($stats[$serverKey]['pid'] ?? 0) > 0) {
                        $isLive = true;
                    } else {
                        foreach ($stats as $s) {
                            if (is_array($s) && ($s['pid'] ?? 0) > 0) {
                                $isLive = true;
                                break;
                            }
                        }
                    }
                }
            }

            if ($isLive) {
                $this->client = $m;
                $this->connected = true;
                $this->connectionError = null;
            } else {
                $this->client = null;
                $this->connected = false;
                $this->connectionError = $this->connectionType === 'socket'
                    ? "عدم امکان اتصال به سرور از طریق Unix Socket '{$this->socketPath}'"
                    : "عدم امکان اتصال به سرور Memcached روی {$this->host}:{$this->port}";
            }
        } catch (Throwable $e) {
            $this->client = null;
            $this->connected = false;
            $this->connectionError = $e->getMessage();
        }
    }

    private function connectMemcache(): void
    {
        try {
            $m = new Memcache();
            $success = false;

            if ($this->connectionType === 'socket') {
                $success = @$m->pconnect("unix://{$this->socketPath}", 0);
            } else {
                $success = @$m->pconnect($this->host, $this->port, 1);
            }

            if ($success) {
                $this->client = $m;
                $this->connected = true;
                $this->connectionError = null;
            } else {
                $this->client = null;
                $this->connected = false;
                $this->connectionError = "اتصال با اکستنشن Memcache به شکست انجامید.";
            }
        } catch (Throwable $e) {
            $this->client = null;
            $this->connected = false;
            $this->connectionError = $e->getMessage();
        }
    }

    /**
     * Executes a comprehensive real diagnostic test cycle:
     * 1. Extension inspection
     * 2. Network / Socket accessibility inspection
     * 3. Connection
     * 4. Write (Set)
     * 5. Read & Verify (Get)
     * 6. Delete
     * 7. Latency calculation
     */
    public function runDiagnosticTest(): array
    {
        $start = microtime(true);
        $extInstalled = extension_loaded('memcached') || extension_loaded('memcache');
        $extName = extension_loaded('memcached') ? 'memcached' : (extension_loaded('memcache') ? 'memcache' : null);
        $extVersion = $extName ? phpversion($extName) : null;

        // 1. Extension check
        if (!$extInstalled) {
            return [
                'success' => false,
                'driver' => 'memcached',
                'connection_type' => $this->connectionType,
                'host' => $this->host,
                'port' => $this->port,
                'socket_path' => $this->socketPath,
                'extension_installed' => false,
                'extension_name' => null,
                'extension_version' => null,
                'connection_status' => 'NOT_INSTALLED',
                'connected' => false,
                'write_ok' => false,
                'read_ok' => false,
                'delete_ok' => false,
                'latency_ms' => 0.0,
                'message' => 'اکستنشن PHP Memcached یا Memcache بر روی این سرور نصب یا فعال نیست (extension_loaded = false).',
            ];
        }

        // 2. Socket-specific checks
        if ($this->connectionType === 'socket') {
            if (PHP_OS_FAMILY === 'Windows') {
                return [
                    'success' => false,
                    'driver' => 'memcached',
                    'connection_type' => 'socket',
                    'host' => $this->host,
                    'port' => 0,
                    'socket_path' => $this->socketPath,
                    'extension_installed' => true,
                    'extension_name' => $extName,
                    'extension_version' => $extVersion,
                    'connection_status' => 'OS_UNSUPPORTED',
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0.0,
                    'message' => 'استفاده از Unix Domain Socket در سیستم‌عامل ویندوز پشتیبانی نمی‌شود. لطفاً از اتصال شبکه TCP (مثلاً 127.0.0.1:11211) استفاده فرمایید.',
                ];
            }

            if (!empty($this->socketPath) && !file_exists($this->socketPath)) {
                return [
                    'success' => false,
                    'driver' => 'memcached',
                    'connection_type' => 'socket',
                    'host' => $this->host,
                    'port' => 0,
                    'socket_path' => $this->socketPath,
                    'extension_installed' => true,
                    'extension_name' => $extName,
                    'extension_version' => $extVersion,
                    'connection_status' => 'SOCKET_NOT_FOUND',
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0.0,
                    'message' => "فایل Unix Socket در مسیر '{$this->socketPath}' یافت نشد. لطفاً از در حال اجرا بودن سرویس Memcached و صحت مسیر اطمینان حاصل کنید.",
                ];
            }

            if (!empty($this->socketPath) && (!is_readable($this->socketPath) || !is_writable($this->socketPath))) {
                $currentUser = get_current_user();
                return [
                    'success' => false,
                    'driver' => 'memcached',
                    'connection_type' => 'socket',
                    'host' => $this->host,
                    'port' => 0,
                    'socket_path' => $this->socketPath,
                    'extension_installed' => true,
                    'extension_name' => $extName,
                    'extension_version' => $extVersion,
                    'connection_status' => 'PERMISSION_DENIED',
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => 0.0,
                    'message' => "فایل سوکت '{$this->socketPath}' وجود دارد اما مجوز دسترسی لازم (Permission) برای کاربر PHP ({$currentUser}) وجود ندارد.",
                ];
            }
        } else {
            // TCP pre-flight network probe with 1.5s timeout
            $errno = 0;
            $errstr = '';
            $fp = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 1.5);
            if (!$fp) {
                return [
                    'success' => false,
                    'driver' => 'memcached',
                    'connection_type' => 'tcp',
                    'host' => $this->host,
                    'port' => $this->port,
                    'socket_path' => null,
                    'extension_installed' => true,
                    'extension_name' => $extName,
                    'extension_version' => $extVersion,
                    'connection_status' => 'CONNECTION_REFUSED',
                    'connected' => false,
                    'write_ok' => false,
                    'read_ok' => false,
                    'delete_ok' => false,
                    'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                    'message' => "امکان برقراری اتصال TCP با {$this->host}:{$this->port} وجود ندارد ({$errstr} [{$errno}]). سرویس Memcached در حال اجرا نیست یا پورت فایروال مسدود است.",
                ];
            }
            fclose($fp);
        }

        // 3. Operational Cycle with Set, Get, Delete
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'driver' => 'memcached',
                'connection_type' => $this->connectionType,
                'host' => $this->host,
                'port' => $this->port,
                'socket_path' => $this->socketPath,
                'extension_installed' => true,
                'extension_name' => $extName,
                'extension_version' => $extVersion,
                'connection_status' => 'INITIALIZATION_FAILED',
                'connected' => false,
                'write_ok' => false,
                'read_ok' => false,
                'delete_ok' => false,
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'message' => $this->connectionError ?: 'ارتباط با سرور برقرار نشد.',
            ];
        }

        $testKey = '__crmwp_memc_diag_' . bin2hex(random_bytes(4));
        $testPayload = ['diagnostic' => 'ok', 'timestamp' => time()];

        // Step 1: Write
        $writeOk = $this->set($testKey, $testPayload, 10);

        // Step 2: Read
        $readOk = false;
        if ($writeOk) {
            $readData = $this->get($testKey);
            $readOk = is_array($readData) && isset($readData['diagnostic']) && $readData['diagnostic'] === 'ok';
        }

        // Step 3: Delete
        $deleteOk = $this->forget($testKey);
        $stillExists = $this->has($testKey);
        $deleteVerified = $deleteOk && !$stillExists;

        $latencyMs = round((microtime(true) - $start) * 1000, 2);
        $allPassed = $writeOk && $readOk && $deleteVerified;

        return [
            'success' => $allPassed,
            'driver' => 'memcached',
            'client_extension' => $this->clientType,
            'connection_type' => $this->connectionType,
            'host' => $this->host,
            'port' => $this->port,
            'socket_path' => $this->socketPath,
            'extension_installed' => true,
            'extension_name' => $extName,
            'extension_version' => $extVersion,
            'connection_status' => $allPassed ? 'CONNECTED' : 'PARTIAL_FAILURE',
            'connected' => true,
            'write_ok' => $writeOk,
            'read_ok' => $readOk,
            'delete_ok' => $deleteVerified,
            'latency_ms' => $latencyMs,
            'message' => $allPassed
                ? "تست کامل اتصال و عملیات Memcached ({$this->connectionType}) با موفقیت در زمان {$latencyMs} میلی‌ثانیه به پایان رسید (Write, Read, Delete تایید شد)."
                : "تست عملیاتی با خطا در مراحل ذخیره یا خواندن مواجه شد.",
        ];
    }

    public function isAvailable(): bool
    {
        return $this->connected && $this->client !== null;
    }

    public function getName(): string
    {
        return 'memcached';
    }

    public function getConnectionType(): string
    {
        return $this->connectionType;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getSocketPath(): ?string
    {
        return $this->socketPath;
    }

    public function getConnectionError(): ?string
    {
        return $this->connectionError;
    }

    public function getClientType(): string
    {
        return $this->clientType;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isAvailable()) {
            return $default;
        }

        try {
            if ($this->clientType === 'memcached') {
                $value = $this->client->get($key);
                if ($this->client->getResultCode() === Memcached::RES_NOTFOUND) {
                    return $default;
                }
                return $value !== false ? $value : $default;
            } elseif ($this->clientType === 'memcache') {
                $value = $this->client->get($key);
                return $value !== false ? $value : $default;
            }
        } catch (Throwable) {
            return $default;
        }

        return $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 60): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            if ($this->clientType === 'memcached') {
                return (bool)$this->client->set($key, $value, $ttlSeconds);
            } elseif ($this->clientType === 'memcache') {
                return (bool)$this->client->set($key, $value, 0, $ttlSeconds);
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            if ($this->clientType === 'memcached') {
                $this->client->get($key);
                return $this->client->getResultCode() === Memcached::RES_SUCCESS;
            } elseif ($this->clientType === 'memcache') {
                return $this->client->get($key) !== false;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    public function forget(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            if ($this->clientType === 'memcached') {
                return (bool)$this->client->delete($key);
            } elseif ($this->clientType === 'memcache') {
                return (bool)$this->client->delete($key);
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    public function forgetByPrefix(string $prefix): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        try {
            if ($this->clientType === 'memcached') {
                $keys = $this->client->getAllKeys();
                if (!is_array($keys)) {
                    return 0;
                }

                $deleted = 0;
                foreach ($keys as $k) {
                    if (str_starts_with($k, $prefix)) {
                        if ($this->client->delete($k)) {
                            $deleted++;
                        }
                    }
                }
                return $deleted;
            }
        } catch (Throwable) {
            return 0;
        }

        return 0;
    }

    public function flush(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            return (bool)$this->client->flush();
        } catch (Throwable) {
            return false;
        }
    }
}
