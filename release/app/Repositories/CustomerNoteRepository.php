<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class CustomerNoteRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function listByCustomer(int $storeId, int $customerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cn.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS author_name,
                   u.email AS author_email
            FROM customer_notes cn
            LEFT JOIN users u ON cn.user_id = u.id
            WHERE cn.store_id = :store_id AND cn.wc_customer_id = :customer_id
            ORDER BY cn.created_at DESC
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $noteId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cn.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS author_name,
                   u.email AS author_email
            FROM customer_notes cn
            LEFT JOIN users u ON cn.user_id = u.id
            WHERE cn.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $noteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $storeId, int $customerId, int $userId, string $content): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO customer_notes (store_id, wc_customer_id, user_id, content, created_at, updated_at)
            VALUES (:store_id, :customer_id, :user_id, :content, NOW(), NOW())
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $noteId, string $content): ?array
    {
        $stmt = $this->pdo->prepare("
            UPDATE customer_notes
            SET content = :content, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'content' => $content,
            'id' => $noteId,
        ]);

        return $this->findById($noteId);
    }

    public function delete(int $noteId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM customer_notes WHERE id = :id");
        return $stmt->execute(['id' => $noteId]);
    }
}
