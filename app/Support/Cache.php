<?php

namespace App\Support;

class Cache
{
    private static ?string $storageDir = null;
    private static array $memory = [];

    private static function getStorageDir(): string
    {
        if (self::$storageDir === null) {
            self::$storageDir = dirname(__DIR__, 2) . '/storage/framework/cache';
            if (!is_dir(self::$storageDir)) {
                @mkdir(self::$storageDir, 0775, true);
            }
        }
        return self::$storageDir;
    }

    private static function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return self::getStorageDir() . '/' . $safeKey . '.cache';
    }

    public static function get(string $key, $default = null)
    {
        $now = time();

        // 1. Check in-memory first
        if (isset(self::$memory[$key])) {
            $item = self::$memory[$key];
            if ($item['expires_at'] === null || $item['expires_at'] > $now) {
                return $item['value'];
            }
            unset(self::$memory[$key]);
        }

        // 2. Check file
        $filePath = self::getFilePath($key);
        if (!file_exists($filePath)) {
            return $default;
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return $default;
        }

        $payload = @unserialize($raw);
        if (!is_array($payload) || !array_key_exists('expires_at', $payload)) {
            @unlink($filePath);
            return $default;
        }

        if ($payload['expires_at'] !== null && $payload['expires_at'] <= $now) {
            @unlink($filePath);
            return $default;
        }

        // Store into memory cache
        self::$memory[$key] = $payload;

        return $payload['value'];
    }

    public static function set(string $key, $value, int $ttlSeconds = 60): bool
    {
        $expiresAt = $ttlSeconds > 0 ? (time() + $ttlSeconds) : null;
        $payload = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        self::$memory[$key] = $payload;

        $filePath = self::getFilePath($key);
        $written = @file_put_contents($filePath, serialize($payload), LOCK_EX);

        return $written !== false;
    }

    public static function has(string $key): bool
    {
        return self::get($key, '__cache_not_found__') !== '__cache_not_found__';
    }

    public static function forget(string $key): bool
    {
        unset(self::$memory[$key]);

        $filePath = self::getFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }

        return true;
    }

    public static function forgetByPrefix(string $prefix): int
    {
        $count = 0;

        foreach (array_keys(self::$memory) as $memKey) {
            if (str_starts_with($memKey, $prefix)) {
                unset(self::$memory[$memKey]);
                $count++;
            }
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix);
        $files = glob(self::getStorageDir() . '/' . $safePrefix . '*.cache');
        if ($files) {
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback)
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttlSeconds);
        return $value;
    }

    public static function flush(): bool
    {
        self::$memory = [];
        $files = glob(self::getStorageDir() . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        return true;
    }

    // ==========================================
    // Multi-Store Isolated Cache Layer (Phase 14)
    // Format: store:{store_id}:{type}:{subKey}
    // ==========================================

    public static function storeKey(int $storeId, string $type, string|int $subKey = ''): string
    {
        $key = "store:{$storeId}:{$type}";
        if ($subKey !== '') {
            $key .= ":{$subKey}";
        }
        return $key;
    }

    public static function storeGet(int $storeId, string $type, string|int $subKey = '', $default = null): mixed
    {
        return self::get(self::storeKey($storeId, $type, $subKey), $default);
    }

    public static function storeSet(int $storeId, string $type, string|int $subKey, mixed $value, int $ttlSeconds = 60): bool
    {
        return self::set(self::storeKey($storeId, $type, $subKey), $value, $ttlSeconds);
    }

    public static function storeRemember(int $storeId, string $type, string|int $subKey, int $ttlSeconds, callable $callback): mixed
    {
        return self::remember(self::storeKey($storeId, $type, $subKey), $ttlSeconds, $callback);
    }

    public static function forgetStore(int $storeId): int
    {
        $count = self::forgetByPrefix("store:{$storeId}:");
        // Also clear legacy keys if any
        $count += self::forgetByPrefix("products_{$storeId}");
        $count += self::forgetByPrefix("orders_{$storeId}");
        $count += self::forgetByPrefix("customers_{$storeId}");
        $count += self::forgetByPrefix("inventory_{$storeId}");
        $count += self::forgetByPrefix("dashboard_{$storeId}");
        $count += self::forgetByPrefix("reports_{$storeId}");
        $count += self::forgetByPrefix("segments_{$storeId}");
        return $count;
    }

    public static function forgetStoreType(int $storeId, string $type): int
    {
        return self::forgetByPrefix("store:{$storeId}:{$type}");
    }
}
