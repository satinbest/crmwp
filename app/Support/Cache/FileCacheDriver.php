<?php

namespace App\Support\Cache;

class FileCacheDriver implements CacheDriverInterface
{
    private string $storageDir;
    private array $memory = [];

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? (dirname(__DIR__, 3) . '/storage/framework/cache');
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    public function isAvailable(): bool
    {
        return is_dir($this->storageDir) && is_writable($this->storageDir);
    }

    public function getName(): string
    {
        return 'file';
    }

    private function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        // Add a short hash to prevent collision when special chars replaced
        $hash = substr(md5($key), 0, 8);
        return $this->storageDir . '/' . $safeKey . '_' . $hash . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $now = time();

        // 1. Check in-memory memoization
        if (isset($this->memory[$key])) {
            $item = $this->memory[$key];
            if ($item['expires_at'] === null || $item['expires_at'] > $now) {
                return $item['value'];
            }
            unset($this->memory[$key]);
        }

        // 2. Check file
        $filePath = $this->getFilePath($key);
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

        $this->memory[$key] = $payload;
        return $payload['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 60): bool
    {
        $expiresAt = $ttlSeconds > 0 ? (time() + $ttlSeconds) : null;
        $payload = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        $this->memory[$key] = $payload;

        $filePath = $this->getFilePath($key);
        $written = @file_put_contents($filePath, serialize($payload), LOCK_EX);

        return $written !== false;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__cache_not_found__') !== '__cache_not_found__';
    }

    public function forget(string $key): bool
    {
        unset($this->memory[$key]);

        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }

        return true;
    }

    public function forgetByPrefix(string $prefix): int
    {
        $count = 0;

        foreach (array_keys($this->memory) as $memKey) {
            if (str_starts_with($memKey, $prefix)) {
                unset($this->memory[$memKey]);
                $count++;
            }
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix);
        $files = glob($this->storageDir . '/' . $safePrefix . '*.cache');
        if ($files) {
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function flush(): bool
    {
        $this->memory = [];
        $files = glob($this->storageDir . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        return true;
    }
}
