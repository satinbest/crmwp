<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class SegmentRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function listByStore(int $storeId, array $filters = []): array
    {
        $sql = "SELECT * FROM `segments` WHERE `store_id` = :store_id";
        $params = ['store_id' => $storeId];

        if (!empty($filters['search'])) {
            $sql .= " AND (`name` LIKE :search OR `description` LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        $sql .= " ORDER BY `created_at` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['rules'] = is_string($row['rules']) ? json_decode($row['rules'], true) : ($row['rules'] ?? []);
        }

        return $rows;
    }

    public function findById(int $id, ?int $storeId = null): ?array
    {
        $sql = "SELECT * FROM `segments` WHERE `id` = :id";
        $params = ['id' => $id];

        if ($storeId !== null) {
            $sql .= " AND `store_id` = :store_id";
            $params['store_id'] = $storeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['rules'] = is_string($row['rules']) ? json_decode($row['rules'], true) : ($row['rules'] ?? []);
        }

        return $row ?: null;
    }

    public function create(array $data): array
    {
        $rulesJson = is_array($data['rules']) ? json_encode($data['rules'], JSON_UNESCAPED_UNICODE) : $data['rules'];

        $stmt = $this->pdo->prepare("
            INSERT INTO `segments` (`store_id`, `name`, `description`, `rules`, `created_at`, `updated_at`)
            VALUES (:store_id, :name, :description, :rules, NOW(), NOW())
        ");
        $stmt->execute([
            'store_id' => $data['store_id'],
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'rules' => $rulesJson ?: '{}',
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, array $data, ?int $storeId = null): ?array
    {
        $existing = $this->findById($id, $storeId);
        if (!$existing) {
            return null;
        }

        $fields = [];
        $params = ['id' => $id];

        if (isset($data['name'])) {
            $fields[] = "`name` = :name";
            $params['name'] = trim($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $fields[] = "`description` = :description";
            $params['description'] = $data['description'];
        }

        if (isset($data['rules'])) {
            $fields[] = "`rules` = :rules";
            $params['rules'] = is_array($data['rules']) ? json_encode($data['rules'], JSON_UNESCAPED_UNICODE) : $data['rules'];
        }

        if (!empty($fields)) {
            $fields[] = "`updated_at` = NOW()";
            $sql = "UPDATE `segments` SET " . implode(', ', $fields) . " WHERE `id` = :id";
            if ($storeId !== null) {
                $sql .= " AND `store_id` = :store_id";
                $params['store_id'] = $storeId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        return $this->findById($id, $storeId);
    }

    public function delete(int $id, ?int $storeId = null): bool
    {
        $sql = "DELETE FROM `segments` WHERE `id` = :id";
        $params = ['id' => $id];

        if ($storeId !== null) {
            $sql .= " AND `store_id` = :store_id";
            $params['store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    public function countByStore(int $storeId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `segments` WHERE `store_id` = :store_id");
        $stmt->execute(['store_id' => $storeId]);
        return (int)$stmt->fetchColumn();
    }
}
