<?php

namespace App\Support\Cache;

class NullCacheDriver implements CacheDriverInterface
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'null';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 60): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function forget(string $key): bool
    {
        return true;
    }

    public function forgetByPrefix(string $prefix): int
    {
        return 0;
    }

    public function flush(): bool
    {
        return true;
    }
}
