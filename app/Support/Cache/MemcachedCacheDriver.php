<?php

namespace App\Support\Cache;

use Memcached;
use Throwable;

class MemcachedCacheDriver implements CacheDriverInterface
{
    private ?Memcached $memcached = null;
    private bool $connected = false;
    private string $host;
    private int $port;

    public function __construct(string $host = '127.0.0.1', int $port = 11211)
    {
        $this->host = $host;
        $this->port = $port;

        if (!extension_loaded('memcached')) {
            return;
        }

        try {
            $this->memcached = new Memcached();
            $this->memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 200);
            $this->memcached->setOption(Memcached::OPT_POLL_TIMEOUT, 200);
            $this->memcached->setOption(Memcached::OPT_RETRY_TIMEOUT, 1);
            $this->memcached->setOption(Memcached::OPT_COMPRESSION, true);

            $servers = $this->memcached->getServerList();
            if (empty($servers)) {
                $this->memcached->addServer($this->host, $this->port);
            }

            $stats = @$this->memcached->getStats();
            $serverKey = "{$this->host}:{$this->port}";
            if (isset($stats[$serverKey]) && $stats[$serverKey]['pid'] > 0) {
                $this->connected = true;
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
