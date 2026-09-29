<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class CustomerTagRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function listStoreTags(int $storeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, COUNT(ct.wc_customer_id) AS customers_count
            FROM tags t
            LEFT JOIN customer_tags ct ON t.id = ct.tag_id AND t.store_id = ct.store_id
            WHERE t.store_id = :store_id
            GROUP BY t.id
            ORDER BY t.name ASC
        ");
        $stmt->execute(['store_id' => $storeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTagsForCustomer(int $storeId, int $customerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name, t.color, t.created_at
            FROM tags t
            INNER JOIN customer_tags ct ON t.id = ct.tag_id AND t.store_id = ct.store_id
            WHERE ct.store_id = :store_id AND ct.wc_customer_id = :customer_id
            ORDER BY t.name ASC
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findTagByName(int $storeId, string $name): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tags
            WHERE store_id = :store_id AND LOWER(name) = LOWER(:name)
            LIMIT 1
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'name' => trim($name),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findTagById(int $tagId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tags WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $tagId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function addTagToCustomer(int $storeId, int $customerId, string $name, string $color = '#4F46E5'): array
    {
        $cleanName = trim($name);
        $tag = $this->findTagByName($storeId, $cleanName);

        if (!$tag) {
            $stmt = $this->pdo->prepare("
                INSERT INTO tags (store_id, name, color, created_at, updated_at)
                VALUES (:store_id, :name, :color, NOW(), NOW())
            ");
            $stmt->execute([
                'store_id' => $storeId,
                'name' => $cleanName,
                'color' => $color ?: '#4F46E5',
            ]);
            $tagId = (int)$this->pdo->lastInsertId();
            $tag = $this->findTagById($tagId);
        } else {
            $tagId = (int)$tag['id'];
        }

        // Attach tag to customer (ignore if duplicate)
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO customer_tags (tag_id, store_id, wc_customer_id)
            VALUES (:tag_id, :store_id, :customer_id)
        ");
        $stmt->execute([
            'tag_id' => $tagId,
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        return $tag;
    }

    public function removeTagFromCustomer(int $storeId, int $customerId, int $tagId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM customer_tags
            WHERE store_id = :store_id AND wc_customer_id = :customer_id AND tag_id = :tag_id
        ");
        return $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'tag_id' => $tagId,
        ]);
    }

    public function createTag(int $storeId, string $name, string $color = '#4F46E5'): array
    {
        $cleanName = trim($name);
        $existing = $this->findTagByName($storeId, $cleanName);
        if ($existing) {
            return $existing;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO tags (store_id, name, color, created_at, updated_at)
            VALUES (:store_id, :name, :color, NOW(), NOW())
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'name' => $cleanName,
            'color' => $color ?: '#4F46E5',
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findTagById($id);
    }

    public function updateTag(int $tagId, int $storeId, array $data): ?array
    {
        $existing = $this->findTagById($tagId);
        if (!$existing || (int)$existing['store_id'] !== $storeId) {
            return null;
        }

        $fields = [];
        $params = ['id' => $tagId, 'store_id' => $storeId];

        if (!empty($data['name'])) {
            $fields[] = "`name` = :name";
            $params['name'] = trim($data['name']);
        }

        if (!empty($data['color'])) {
            $fields[] = "`color` = :color";
            $params['color'] = trim($data['color']);
        }

        if (!empty($fields)) {
            $fields[] = "`updated_at` = NOW()";
            $sql = "UPDATE tags SET " . implode(', ', $fields) . " WHERE id = :id AND store_id = :store_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        return $this->findTagById($tagId);
    }

    public function deleteTag(int $tagId, int $storeId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tags WHERE id = :id AND store_id = :store_id");
        return $stmt->execute(['id' => $tagId, 'store_id' => $storeId]) && $stmt->rowCount() > 0;
    }

    public function countStoreTags(int $storeId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tags WHERE store_id = :store_id");
        $stmt->execute(['store_id' => $storeId]);
        return (int)$stmt->fetchColumn();
    }
}
