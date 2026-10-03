<?php

namespace App\Support\Cache;

interface CacheDriverInterface
{
    /**
     * Retrieve an item from the cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache.
     */
    public function set(string $key, mixed $value, int $ttlSeconds = 60): bool;

    /**
     * Check if an item exists in the cache.
     */
    public function has(string $key): bool;

    /**
     * Delete an item from the cache.
     */
    public function forget(string $key): bool;

    /**
     * Delete all items matching a prefix.
     */
    public function forgetByPrefix(string $prefix): int;

    /**
     * Flush all items from the cache.
     */
    public function flush(): bool;

    /**
     * Get driver name / status.
     */
    public function getName(): string;

    /**
     * Check if the driver is currently operational.
     */
    public function isAvailable(): bool;
}
