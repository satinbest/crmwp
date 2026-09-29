<?php

namespace App\Support;

class Config
{
    private static array $configs = [];
    private static string $configPath = '';

    public static function setPath(string $path): void
    {
        self::$configPath = rtrim($path, '/\\');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!isset(self::$configs[$file])) {
            $filePath = (self::$configPath ?: dirname(__DIR__, 2) . '/config') . '/' . $file . '.php';
            if (file_exists($filePath)) {
                self::$configs[$file] = require $filePath;
            } else {
                self::$configs[$file] = [];
            }
        }

        $current = self::$configs[$file];
        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }
}
