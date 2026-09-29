<?php

namespace App\Database;

use App\Support\Config;
use PDO;
use Exception;

class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $default = Config::get('database.default', 'mysql');
            $cfg = Config::get("database.connections.{$default}");

            if (!$cfg) {
                throw new Exception("Database configuration for [{$default}] not found.");
            }

            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['driver'],
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['charset']
            );

            $options = $cfg['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
        }

        return self::$instance;
    }

    public static function setMockInstance(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
