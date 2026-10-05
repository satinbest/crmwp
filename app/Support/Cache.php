<?php

namespace App\Support;

use App\Support\Cache\CacheDriverInterface;
use App\Support\Cache\FileCacheDriver;
use App\Support\Cache\MemcachedCacheDriver;
use App\Support\Cache\RedisCacheDriver;
use App\Support\Cache\ApcuCacheDriver;
use App\Support\Cache\NullCacheDriver;
use Throwable;

class Cache
{
    // ==========================================
    // Centralized TTL Configurations (in seconds)
    // ==========================================
    public const TTL_CAPABILITIES = 3600;   // 1 hour
    public const TTL_CATEGORIES   = 1800;   // 30 minutes
    public const TTL_ATTRIBUTES   = 1800;   // 30 minutes
    public const TTL_PRODUCTS     = 120;    // 2 minutes
    public const TTL_PRODUCT_ITEM = 120;    // 2 minutes
    public const TTL_ORDERS       = 60;     // 1 minute
    public const TTL_CUSTOMERS    = 120;    // 2 minutes
    public const TTL_DASHBOARD    = 60;     // 1 minute
    public const TTL_INVENTORY    = 60;     // 1 minute
    public const TTL_REPORTS      = 300;    // 5 minutes

    private static ?CacheDriverInterface $driver = null;
    private static int $hits = 0;
    private static int $misses = 0;

    /**
     * Get or initialize the active Cache Driver.
     */
    public static function getDriver(): CacheDriverInterface
    {
        if (self::$driver === null) {
            self::$driver = self::resolveDriver();
        }
        return self::$driver;
    }

    /**
     * Set a custom cache driver (for testing/mocking).
     */
    public static function setDriver(CacheDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * Resolve the appropriate Cache Driver based on configuration and environment.
     */
    private static function resolveDriver(): CacheDriverInterface
    {
        $preferred = strtolower((string)Env::get('CACHE_DRIVER', 'file'));

        // 1. Try Memcached if preferred
        if ($preferred === 'memcached' || $preferred === 'memcache') {
            try {
                $connType = (string)Env::get('MEMCACHED_CONNECTION_TYPE', 'tcp');
                $socketPath = (string)Env::get('MEMCACHED_SOCKET_PATH', '/memcached.sock');
                $host = (string)Env::get('MEMCACHED_HOST', '127.0.0.1');
                $port = (int)Env::get('MEMCACHED_PORT', 11211);
                $driver = new MemcachedCacheDriver($host, $port, $connType, $socketPath);
                if ($driver->isAvailable()) {
                    return $driver;
                }
            } catch (Throwable $e) {
                Logger::warning("Memcached unavailable, falling back to file cache", ['error' => $e->getMessage()]);
            }
        }

        // 2. Try Redis if preferred
        if ($preferred === 'redis') {
            try {
                $host = (string)Env::get('REDIS_HOST', '127.0.0.1');
                $port = (int)Env::get('REDIS_PORT', 6379);
                $pass = Env::get('REDIS_PASSWORD');
                $db = (int)Env::get('REDIS_DB', 0);
                $driver = new RedisCacheDriver($host, $port, $pass, $db);
                if ($driver->isAvailable()) {
                    return $driver;
                }
            } catch (Throwable $e) {
                Logger::warning("Redis unavailable, falling back to file cache", ['error' => $e->getMessage()]);
            }
        }

        // 3. Try APCu if preferred
        if ($preferred === 'apcu') {
            $driver = new ApcuCacheDriver();
            if ($driver->isAvailable()) {
                return $driver;
            }
        }

        // 4. Null driver
        if ($preferred === 'null' || $preferred === 'none' || $preferred === 'array') {
            return new NullCacheDriver();
        }

        // 5. Default reliable fallback: File-based Object Cache
        return new FileCacheDriver();
    }

    // ==========================================
    // Core Cache API
    // ==========================================

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::getDriver()->get($key, $default);
        if ($value !== $default) {
            self::$hits++;
        } else {
            self::$misses++;
        }
        return $value;
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 60): bool
    {
        return self::getDriver()->set($key, $value, $ttlSeconds);
    }

    public static function has(string $key): bool
    {
        return self::getDriver()->has($key);
    }

    public static function forget(string $key): bool
    {
        return self::getDriver()->forget($key);
    }

    public static function forgetByPrefix(string $prefix): int
    {
        return self::getDriver()->forgetByPrefix($prefix);
    }

    public static function flush(): bool
    {
        return self::getDriver()->flush();
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        if ($value !== null) {
            self::set($key, $value, $ttlSeconds);
        }

        return $value;
    }

    // ==========================================
    // Multi-Store & Query-Aware Hashing API
    // ==========================================

    /**
     * Generate a structured store-aware cache key.
     * Format: store:{store_id}:{resource}:{subKey}
     */
    public static function storeKey(int $storeId, string $resource, string|int $subKey = ''): string
    {
        $key = "store:{$storeId}:{$resource}";
        if ($subKey !== '') {
            $key .= ":{$subKey}";
        }
        return $key;
    }

    /**
     * Generate a deterministic hash for query parameters.
     * Sorts keys alphabetically, filters transient params (_ , fresh, no_cache).
     */
    public static function hashQuery(array $queryParams): string
    {
        // Remove transient parameters
        unset(
            $queryParams['_'],
            $queryParams['fresh'],
            $queryParams['no_cache'],
            $queryParams['refresh'],
            $queryParams['csrf_token'],
            $queryParams['api_token']
        );

        if (empty($queryParams)) {
            return 'default';
        }

        ksort($queryParams);
        return md5(http_build_query($queryParams));
    }

    /**
     * Generate a store-aware and query-aware cache key.
     * Format: store:{store_id}:{resource}:{query_hash}
     */
    public static function queryKey(int $storeId, string $resource, array $queryParams = []): string
    {
        $hash = self::hashQuery($queryParams);
        return self::storeKey($storeId, $resource, $hash);
    }

    /**
     * Fetch or calculate a store-aware query result.
     */
    public static function storeRememberQuery(
        int $storeId,
        string $resource,
        array $queryParams,
        int $ttlSeconds,
        callable $callback
    ): mixed {
        $key = self::queryKey($storeId, $resource, $queryParams);
        return self::remember($key, $ttlSeconds, $callback);
    }

    public static function storeGet(int $storeId, string $resource, string|int $subKey = '', mixed $default = null): mixed
    {
        return self::get(self::storeKey($storeId, $resource, $subKey), $default);
    }

    public static function storeSet(int $storeId, string $resource, string|int $subKey, mixed $value, int $ttlSeconds = 60): bool
    {
        return self::set(self::storeKey($storeId, $resource, $subKey), $value, $ttlSeconds);
    }

    public static function storeRemember(int $storeId, string $resource, string|int $subKey, int $ttlSeconds, callable $callback): mixed
    {
        return self::remember(self::storeKey($storeId, $resource, $subKey), $ttlSeconds, $callback);
    }

    /**
     * Invalidate all cached data for a specific store.
     */
    public static function forgetStore(int $storeId): int
    {
        $count = self::forgetByPrefix("store:{$storeId}:");
        // Also clear legacy prefixes if any
        $count += self::forgetByPrefix("products_{$storeId}");
        $count += self::forgetByPrefix("orders_{$storeId}");
        $count += self::forgetByPrefix("customers_{$storeId}");
        $count += self::forgetByPrefix("inventory_{$storeId}");
        $count += self::forgetByPrefix("dashboard_{$storeId}");
        $count += self::forgetByPrefix("reports_{$storeId}");
        $count += self::forgetByPrefix("segments_{$storeId}");
        $count += self::forgetByPrefix("crm_summary_{$storeId}");
        return $count;
    }

    /**
     * Invalidate all cached data for a specific resource within a store.
     */
    public static function forgetStoreResource(int $storeId, string $resource): int
    {
        return self::forgetByPrefix("store:{$storeId}:{$resource}");
    }

    /**
     * Handle webhook-triggered cache invalidation.
     */
    public static function handleWebhookInvalidation(int $storeId, string $topic): void
    {
        if (str_starts_with($topic, 'product.')) {
            self::forgetStoreResource($storeId, 'products');
            self::forgetStoreResource($storeId, 'inventory');
            self::forgetStoreResource($storeId, 'categories');
            self::forgetStoreResource($storeId, 'dashboard');
        } elseif (str_starts_with($topic, 'order.')) {
            self::forgetStoreResource($storeId, 'orders');
            self::forgetStoreResource($storeId, 'dashboard');
            self::forgetStoreResource($storeId, 'reports');
            self::forgetStoreResource($storeId, 'crm_summary');
        } elseif (str_starts_with($topic, 'customer.')) {
            self::forgetStoreResource($storeId, 'customers');
            self::forgetStoreResource($storeId, 'segments');
            self::forgetStoreResource($storeId, 'crm_summary');
            self::forgetStoreResource($storeId, 'dashboard');
        }
    }

    /**
     * Get internal runtime telemetry for health checks.
     */
    public static function getStats(): array
    {
        $driver = self::getDriver();
        $total = self::$hits + self::$misses;
        $hasData = $total > 0;
        $hitRatio = $hasData ? round((self::$hits / $total) * 100, 1) : null;

        return [
            'available' => $driver->isAvailable(),
            'driver' => $driver->getName(),
            'hits' => self::$hits,
            'misses' => self::$misses,
            'total' => $total,
            'has_data' => $hasData,
            'hit_ratio_percent' => $hitRatio,
            'hit_ratio_label' => $hasData ? "{$hitRatio}٪" : 'بدون داده',
        ];
    }
}
