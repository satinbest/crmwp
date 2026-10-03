<?php

namespace App\Support\Cache;

class ApcuCacheDriver implements CacheDriverInterface
{
    public function isAvailable(): bool
    {
        return extension_loaded('apcu') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public function getName(): string
    {
        return 'apcu';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isAvailable()) {
            return $default;
        }

        $success = false;
        $val = apcu_fetch($key, $success);
        return $success ? $val : $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 60): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return apcu_store($key, $value, $ttlSeconds);
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return apcu_exists($key);
    }

    public function forget(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return apcu_delete($key);
    }

    public function forgetByPrefix(string $prefix): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $deleted = 0;
        $iterator = new \APCUIterator('/^' . preg_quote($prefix, '/') . '/');
        foreach ($iterator as $item) {
            if (apcu_delete($item['key'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function flush(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        return apcu_clear_cache();
    }
}
