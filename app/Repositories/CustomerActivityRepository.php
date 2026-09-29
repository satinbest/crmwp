<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class CustomerActivityRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function listByCustomer(int $storeId, int $customerId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_name,
                   u.email AS user_email
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.store_id = :store_id
              AND a.entity_type = 'customer'
              AND a.entity_id = :customer_id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (!empty($row['details']) && is_string($row['details'])) {
                $row['details'] = json_decode($row['details'], true) ?: [];
            }
        }

        return $rows;
    }

    public function record(int $storeId, ?int $userId, string $actionType, int $customerId, ?array $details = null): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities (store_id, user_id, action_type, entity_type, entity_id, details, created_at)
            VALUES (:store_id, :user_id, :action_type, 'customer', :entity_id, :details, NOW())
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_id' => $customerId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return [
            'id' => $id,
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_type' => 'customer',
            'entity_id' => $customerId,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function listByOrder(int $storeId, int $orderId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_name,
                   u.email AS user_email
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.store_id = :store_id
              AND a.entity_type = 'order'
              AND a.entity_id = :order_id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (!empty($row['details']) && is_string($row['details'])) {
                $row['details'] = json_decode($row['details'], true) ?: [];
            }
        }

        return $rows;
    }

    public function recordForOrder(int $storeId, ?int $userId, string $actionType, int $orderId, ?array $details = null): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities (store_id, user_id, action_type, entity_type, entity_id, details, created_at)
            VALUES (:store_id, :user_id, :action_type, 'order', :entity_id, :details, NOW())
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_id' => $orderId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return [
            'id' => $id,
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function listByProduct(int $storeId, int $productId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_name,
                   u.email AS user_email
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.store_id = :store_id
              AND a.entity_type = 'product'
              AND a.entity_id = :product_id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (!empty($row['details']) && is_string($row['details'])) {
                $row['details'] = json_decode($row['details'], true) ?: [];
            }
        }

        return $rows;
    }

    public function recordForProduct(int $storeId, ?int $userId, string $actionType, int $productId, ?array $details = null): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities (store_id, user_id, action_type, entity_type, entity_id, details, created_at)
            VALUES (:store_id, :user_id, :action_type, 'product', :entity_id, :details, NOW())
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_id' => $productId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return [
            'id' => $id,
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_type' => 'product',
            'entity_id' => $productId,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function recordGeneral(int $storeId, ?int $userId, string $actionType, string $entityType, int $entityId, ?array $details = null): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities (store_id, user_id, action_type, entity_type, entity_id, details, created_at)
            VALUES (:store_id, :user_id, :action_type, :entity_type, :entity_id, :details, NOW())
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return [
            'id' => $id,
            'store_id' => $storeId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function listStoreActivities(int $storeId, array $filters = []): array
    {
        $sql = "
            SELECT a.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_name,
                   u.email AS user_email
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.store_id = :store_id
        ";
        $params = ['store_id' => $storeId];

        if (!empty($filters['entity_type'])) {
            $sql .= " AND a.entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $sql .= " AND a.entity_id = :entity_id";
            $params['entity_id'] = (int)$filters['entity_id'];
        }

        if (!empty($filters['action_type'])) {
            $sql .= " AND a.action_type = :action_type";
            $params['action_type'] = $filters['action_type'];
        }

        $sql .= " ORDER BY a.created_at DESC";

        $limit = min(100, max(1, (int)($filters['limit'] ?? 50)));
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $sql .= " LIMIT $limit OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (!empty($row['details']) && is_string($row['details'])) {
                $row['details'] = json_decode($row['details'], true) ?: [];
            }
        }

        return $rows;
    }
}
