<?php

namespace App\Support\Cache;

use Redis;
use Throwable;

class RedisCacheDriver implements CacheDriverInterface
{
    private ?Redis $redis = null;
    private bool $connected = false;
    private string $host;
    private int $port;
    private ?string $password;
    private int $database;

    public function __construct(string $host = '127.0.0.1', int $port = 6379, ?string $password = null, int $database = 0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;

        if (!extension_loaded('redis')) {
            return;
        }

        try {
            $this->redis = new Redis();
            $connected = $this->redis->connect($this->host, $this->port, 0.5);
            if ($connected) {
                if (!empty($this->password)) {
                    $this->redis->auth($this->password);
                }
                if ($this->database > 0) {
                    $this->redis->select($this->database);
                }
                $this->connected = true;
            }
        } catch (Throwable $e) {
            $this->connected = false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->connected && $this->redis !== null;
    }

    public function getName(): string
    {
        return 'redis';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isAvailable()) {
            return $default;
        }

        $raw = $this->redis->get($key);
        if ($raw === false || $raw === null) {
            return $default;
        }

        $unserialized = @unserialize($raw);
        return $unserialized !== false || $raw === serialize(false) ? $unserialized : $raw;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 60): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $payload = serialize($value);
        if ($ttlSeconds > 0) {
            return (bool)$this->redis->setex($key, $ttlSeconds, $payload);
        }

        return (bool)$this->redis->set($key, $payload);
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return (bool)$this->redis->exists($key);
    }

    public function forget(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return (bool)$this->redis->del($key);
    }

    public function forgetByPrefix(string $prefix): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $keys = $this->redis->keys("{$prefix}*");
        if (empty($keys)) {
            return 0;
        }

        return (int)$this->redis->del($keys);
    }

    public function flush(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return (bool)$this->redis->flushDB();
    }
}
