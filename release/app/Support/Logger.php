<?php

namespace App\Support;

class Logger
{
    private static string $logDir = '';

    public static function setLogDir(string $dir): void
    {
        self::$logDir = rtrim($dir, '/\\');
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $dir = self::$logDir ?: dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $date = date('Y-m-d');
        $file = "{$dir}/app-{$date}.log";
        $timestamp = date('Y-m-d H:i:s');

        // Mask any sensitive keys in context
        $sanitizedContext = self::sanitizeContext($context);
        $contextStr = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_UNICODE) : '';

        $entry = sprintf("[%s] [%s] %s%s%s", $timestamp, strtoupper($level), $message, $contextStr, PHP_EOL);
        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    private static function sanitizeContext(array $context): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'current_password', 'new_password',
            'secret', 'consumer_secret', 'consumer_key', 'token', 'access_token',
            'authorization', 'cookie', 'csrf_token', '__csrf_token', 'session_id',
            'api_key', 'private_key', 'encryption_key'
        ];
        $clean = [];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (is_array($value)) {
                $clean[$key] = self::sanitizeContext($value);
            } elseif (in_array($lowerKey, $sensitiveKeys, true) || str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'password')) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_string($value)) {
                // Mask bearer tokens or WooCommerce keys embedded in strings
                $sanitized = preg_replace('/Bearer\s+[a-zA-Z0-9_\-\.]+/i', 'Bearer [REDACTED]', $value);
                $sanitized = preg_replace('/cs_[a-zA-Z0-9]{20,}/i', 'cs_[REDACTED]', $sanitized);
                $clean[$key] = $sanitized;
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
