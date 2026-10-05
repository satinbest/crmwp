<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Memcached;
use Throwable;

class MemcachedCacheDriver implements CacheDriverInterface
{
    private ?Memcached $memcached = null;
    private bool $connected = false;
    private string $connectionType = 'tcp'; // 'tcp' or 'socket'
    private string $host;
    private int $port;
    private ?string $socketPath = null;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 11211,
        string $connectionType = 'tcp',
        ?string $socketPath = null
    ) {
        // Auto-detect socket if host starts with a slash (e.g. /memcached.sock) or connectionType requested
        if (str_starts_with(trim($host), '/') || $connectionType === 'socket' || $connectionType === 'unix_socket' || !empty($socketPath)) {
            $this->connectionType = 'socket';
            $this->socketPath = !empty($socketPath) ? trim($socketPath) : trim($host);
            $this->host = $this->socketPath;
            $this->port = 0; // Unix socket in PHP Memcached MUST use port 0
        } else {
            $this->connectionType = 'tcp';
            $this->host = trim($host);
            $this->port = $port > 0 ? $port : 11211;
            $this->socketPath = null;
        }

        if (!extension_loaded('memcached')) {
            $this->connected = false;
            return;
        }

        try {
            $this->memcached = new Memcached();
            $this->memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 300);
            $this->memcached->setOption(Memcached::OPT_POLL_TIMEOUT, 300);
            $this->memcached->setOption(Memcached::OPT_RETRY_TIMEOUT, 1);
            $this->memcached->setOption(Memcached::OPT_COMPRESSION, true);

            $servers = $this->memcached->getServerList();
            if (empty($servers)) {
                if ($this->connectionType === 'socket') {
                    // Unix Domain Socket requires path as first arg, and port 0
                    $this->memcached->addServer($this->socketPath, 0);
                } else {
                    // TCP requires hostname/IP and TCP port (e.g. 11211)
                    $this->memcached->addServer($this->host, $this->port);
                }
            }

            // Real connection test via getStats()
            $stats = @$this->memcached->getStats();
            $serverFound = false;

            if (is_array($stats) && !empty($stats)) {
                if ($this->connectionType === 'socket') {
                    $possibleKeys = [$this->socketPath, "{$this->socketPath}:0"];
                    foreach ($possibleKeys as $pk) {
                        if (isset($stats[$pk]) && is_array($stats[$pk]) && ($stats[$pk]['pid'] ?? 0) > 0) {
                            $serverFound = true;
                            break;
                        }
                    }
                    if (!$serverFound) {
                        foreach ($stats as $k => $s) {
                            if (is_array($s) && ($s['pid'] ?? 0) > 0) {
                                $serverFound = true;
                                break;
                            }
                        }
                    }
                } else {
                    $serverKey = "{$this->host}:{$this->port}";
                    if (isset($stats[$serverKey]) && is_array($stats[$serverKey]) && ($stats[$serverKey]['pid'] ?? 0) > 0) {
                        $serverFound = true;
                    }
                }
            }

            if ($serverFound) {
                // Secondary check: verify real write and delete capability
                $testPingKey = '__crmwp_memc_ping_' . bin2hex(random_bytes(3));
                if ($this->memcached->set($testPingKey, 1, 2)) {
                    $this->memcached->delete($testPingKey);
                    $this->connected = true;
                } else {
                    $this->connected = false;
                }
            } else {
                $this->connected = false;
            }
        } catch (Throwable $e) {
            $this->connected = false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->connected && $this->memcached !== null;
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

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isAvailable()) {
            return $default;
        }

        $value = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value !== false ? $value : $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 60): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return $this->memcached->set($key, $value, $ttlSeconds);
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->memcached->get($key);
        return $this->memcached->getResultCode() === Memcached::RES_SUCCESS;
    }

    public function forget(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return $this->memcached->delete($key);
    }

    public function forgetByPrefix(string $prefix): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $keys = $this->memcached->getAllKeys();
        if (!is_array($keys)) {
            return 0;
        }

        $deleted = 0;
        foreach ($keys as $k) {
            if (str_starts_with($k, $prefix)) {
                if ($this->memcached->delete($k)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function flush(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return $this->memcached->flush();
    }
}
