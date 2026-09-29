<?php

namespace App\Database;

use PDO;
use Exception;

class MigrationRunner
{
    private PDO $pdo;
    private string $migrationsPath;

    public function __construct(?PDO $pdo = null, ?string $migrationsPath = null)
    {
        $this->pdo = $pdo ?? Connection::get();
        $this->migrationsPath = $migrationsPath ?? __DIR__ . '/Migrations';
    }

    public function init(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->pdo->exec($sql);
    }

    public function migrate(): array
    {
        $this->init();

        $executed = $this->getExecutedMigrations();
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);

        $nextBatch = $this->getNextBatchNumber();
        $applied = [];

        foreach ($files as $file) {
            $migrationName = basename($file, '.php');

            if (in_array($migrationName, $executed, true)) {
                continue;
            }

            require_once $file;

            $className = $this->resolveClassName($migrationName);
            if (!class_exists($className)) {
                throw new Exception("Migration class [{$className}] not found in [{$file}].");
            }

            $migration = new $className();

            try {
                if ($this->pdo->inTransaction()) {
                    // Already in transaction
                } else {
                    $this->pdo->beginTransaction();
                }

                $migration->up($this->pdo);

                $stmt = $this->pdo->prepare("INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, ?)");
                $stmt->execute([$migrationName, $nextBatch]);

                if ($this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
                $applied[] = $migrationName;
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw new Exception("Migration [{$migrationName}] failed: " . $e->getMessage(), 0, $e);
            }
        }

        return $applied;
    }

    public function rollback(): array
    {
        $this->init();

        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM `migrations`");
        $lastBatch = (int)($stmt->fetch()['max_batch'] ?? 0);

        if ($lastBatch === 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("SELECT `migration` FROM `migrations` WHERE `batch` = ? ORDER BY `id` DESC");
        $stmt->execute([$lastBatch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $rolledBack = [];

        foreach ($migrations as $migrationName) {
            $file = $this->migrationsPath . '/' . $migrationName . '.php';
            if (file_exists($file)) {
                require_once $file;
                $className = $this->resolveClassName($migrationName);
                if (class_exists($className)) {
                    $migration = new $className();
                    try {
                        if (!$this->pdo->inTransaction()) {
                            $this->pdo->beginTransaction();
                        }
                        $migration->down($this->pdo);

                        $delStmt = $this->pdo->prepare("DELETE FROM `migrations` WHERE `migration` = ?");
                        $delStmt->execute([$migrationName]);

                        if ($this->pdo->inTransaction()) {
                            $this->pdo->commit();
                        }
                        $rolledBack[] = $migrationName;
                    } catch (Exception $e) {
                        if ($this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        throw $e;
                    }
                }
            }
        }

        return $rolledBack;
    }

    public function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT `migration` FROM `migrations` ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getNextBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM `migrations`");
        $max = (int)($stmt->fetch()['max_batch'] ?? 0);
        return $max + 1;
    }

    private function resolveClassName(string $filename): string
    {
        // Strip numeric prefix like "001_"
        $name = preg_replace('/^[0-9]+_/', '', $filename);
        // Convert snake_case to PascalCase
        $parts = explode('_', $name);
        $pascal = implode('', array_map('ucfirst', $parts));
        return $pascal;
    }
}
