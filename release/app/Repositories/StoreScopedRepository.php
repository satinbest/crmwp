<?php

namespace App\Repositories;

use App\Database\Connection;
use Exception;
use PDO;
use PDOStatement;

abstract class StoreScopedRepository
{
    protected PDO $pdo;
    protected ?int $storeId = null;

    public function __construct(?PDO $pdo = null, ?int $storeId = null)
    {
        $this->pdo = $pdo ?? Connection::get();
        $this->storeId = $storeId;
    }

    /**
     * Bind repository to a specific store context.
     */
    public function forStore(int $storeId): static
    {
        $clone = clone $this;
        $clone->storeId = $storeId;
        return $clone;
    }

    /**
     * Get current scoped store ID or fail if missing.
     */
    public function getScopedStoreId(): int
    {
        if ($this->storeId === null || $this->storeId <= 0) {
            throw new Exception("Store context is required for this operation (store_id not scoped).", 500);
        }
        return $this->storeId;
    }

    /**
     * Helper to enforce store scope in SQL queries.
     * Appends ' AND store_id = ?' or injects into WHERE clause.
     */
    protected function appendStoreScope(string $sql, string $alias = ''): string
    {
        $prefix = $alias ? "{$alias}." : "";
        $clause = "{$prefix}`store_id` = ?";

        if (stripos($sql, 'WHERE') !== false) {
            return preg_replace('/WHERE\s+/i', "WHERE {$clause} AND ", $sql, 1);
        }

        return $sql . " WHERE {$clause}";
    }

    /**
     * Prepare a store-scoped statement with automatic store_id prepending.
     */
    protected function prepareScoped(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }
}
