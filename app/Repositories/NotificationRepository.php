<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class NotificationRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.*, s.name AS store_name
            FROM notifications n
            LEFT JOIN stores s ON n.store_id = s.id
            WHERE n.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
            $row['is_read'] = !empty($row['read_at']);
        }

        return $row ?: null;
    }

    public function listForUser(
        int $userId,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        array $allowedStoreIds = []
    ): array {
        $where = ["n.user_id = :user_id"];
        $params = ['user_id' => $userId];

        // Store isolation: user can only see notifications that have NO store_id (global/system)
        // OR have a store_id that is in their allowed stores list (unless user is admin with all stores)
        if (!empty($allowedStoreIds)) {
            $storePlaceholders = [];
            foreach ($allowedStoreIds as $idx => $sid) {
                $p = ":store_id_{$idx}";
                $storePlaceholders[] = $p;
                $params["store_id_{$idx}"] = (int)$sid;
            }
            $storesIn = implode(',', $storePlaceholders);
            $where[] = "(n.store_id IS NULL OR n.store_id IN ({$storesIn}))";
        } elseif (isset($filters['store_id'])) {
            // Specific store requested
            $where[] = "n.store_id = :store_id";
            $params['store_id'] = (int)$filters['store_id'];
        }

        // Read/Unread Filter
        if (isset($filters['status'])) {
            if ($filters['status'] === 'unread') {
                $where[] = "n.read_at IS NULL";
            } elseif ($filters['status'] === 'read') {
                $where[] = "n.read_at IS NOT NULL";
            }
        } elseif (isset($filters['is_read'])) {
            if ($filters['is_read'] === false || $filters['is_read'] === 0 || $filters['is_read'] === '0') {
                $where[] = "n.read_at IS NULL";
            } elseif ($filters['is_read'] === true || $filters['is_read'] === 1 || $filters['is_read'] === '1') {
                $where[] = "n.read_at IS NOT NULL";
            }
        }

        // Type filter
        if (!empty($filters['type'])) {
            $where[] = "n.type = :type";
            $params['type'] = $filters['type'];
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $where[] = "n.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications n WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Calculate pagination
        $perPage = max(1, min(100, $perPage));
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        // Fetch paginated rows
        $query = "
            SELECT n.*, s.name AS store_name
            FROM notifications n
            LEFT JOIN stores s ON n.store_id = s.id
            WHERE {$whereClause}
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
            $row['is_read'] = !empty($row['read_at']);
        }
        unset($row);

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function getUnreadCount(int $userId, ?int $storeId = null, array $allowedStoreIds = []): int
    {
        $where = ["user_id = :user_id", "read_at IS NULL"];
        $params = ['user_id' => $userId];

        if ($storeId !== null) {
            $where[] = "(store_id = :store_id OR store_id IS NULL)";
            $params['store_id'] = $storeId;
        } elseif (!empty($allowedStoreIds)) {
            $storePlaceholders = [];
            foreach ($allowedStoreIds as $idx => $sid) {
                $p = ":store_id_{$idx}";
                $storePlaceholders[] = $p;
                $params["store_id_{$idx}"] = (int)$sid;
            }
            $storesIn = implode(',', $storePlaceholders);
            $where[] = "(store_id IS NULL OR store_id IN ({$storesIn}))";
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE {$whereClause}");
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (
                user_id, store_id, type, title, message, data, priority, action_url, read_at, expires_at, created_at
            ) VALUES (
                :user_id, :store_id, :type, :title, :message, :data, :priority, :action_url, :read_at, :expires_at, NOW()
            )
        ");

        $jsonData = null;
        if (isset($data['data'])) {
            $jsonData = is_string($data['data']) ? $data['data'] : json_encode($data['data'], JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            'user_id' => (int)$data['user_id'],
            'store_id' => !empty($data['store_id']) ? (int)$data['store_id'] : null,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $jsonData,
            'priority' => $data['priority'] ?? 'normal',
            'action_url' => $data['action_url'] ?? null,
            'read_at' => $data['read_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications
            SET read_at = COALESCE(read_at, NOW())
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0 || (bool)$this->findById($id);
    }

    public function markAllAsRead(int $userId, ?int $storeId = null, array $allowedStoreIds = []): int
    {
        $where = ["user_id = :user_id", "read_at IS NULL"];
        $params = ['user_id' => $userId];

        if ($storeId !== null) {
            $where[] = "(store_id = :store_id OR store_id IS NULL)";
            $params['store_id'] = $storeId;
        } elseif (!empty($allowedStoreIds)) {
            $storePlaceholders = [];
            foreach ($allowedStoreIds as $idx => $sid) {
                $p = ":store_id_{$idx}";
                $storePlaceholders[] = $p;
                $params["store_id_{$idx}"] = (int)$sid;
            }
            $storesIn = implode(',', $storePlaceholders);
            $where[] = "(store_id IS NULL OR store_id IN ({$storesIn}))";
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $this->pdo->prepare("
            UPDATE notifications
            SET read_at = NOW()
            WHERE {$whereClause}
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function hasRecentDuplicate(
        int $userId,
        string $type,
        ?int $storeId,
        string $keyField,
        $keyValue,
        int $withinSeconds = 86400
    ): bool {
        $storeSql = $storeId !== null ? "store_id = :store_id" : "store_id IS NULL";
        $params = [
            'user_id' => $userId,
            'type' => $type,
            'seconds' => $withinSeconds,
        ];
        if ($storeId !== null) {
            $params['store_id'] = $storeId;
        }

        // Search for recent notifications of this type within time window
        $stmt = $this->pdo->prepare("
            SELECT data
            FROM notifications
            WHERE user_id = :user_id
              AND type = :type
              AND {$storeSql}
              AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)
            ORDER BY id DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (!$row['data']) continue;
            $data = json_decode($row['data'], true);
            if (is_array($data) && isset($data[$keyField]) && (string)$data[$keyField] === (string)$keyValue) {
                return true;
            }
        }

        return false;
    }

    public function deleteOlderThan(int $days = 30): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }

    public function getPreferences(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_notification_preferences WHERE user_id = :user_id LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pref) {
            // Default preferences
            return [
                'user_id' => $userId,
                'task_notifications' => true,
                'order_notifications' => true,
                'inventory_notifications' => true,
                'bulk_notifications' => true,
                'system_notifications' => true,
                'retention_days' => 30,
            ];
        }

        return [
            'user_id' => (int)$pref['user_id'],
            'task_notifications' => (bool)$pref['task_notifications'],
            'order_notifications' => (bool)$pref['order_notifications'],
            'inventory_notifications' => (bool)$pref['inventory_notifications'],
            'bulk_notifications' => (bool)$pref['bulk_notifications'],
            'system_notifications' => (bool)$pref['system_notifications'],
            'retention_days' => (int)$pref['retention_days'],
        ];
    }

    public function updatePreferences(int $userId, array $data): array
    {
        $current = $this->getPreferences($userId);

        $task = isset($data['task_notifications']) ? (int)(bool)$data['task_notifications'] : (int)$current['task_notifications'];
        $order = isset($data['order_notifications']) ? (int)(bool)$data['order_notifications'] : (int)$current['order_notifications'];
        $inventory = isset($data['inventory_notifications']) ? (int)(bool)$data['inventory_notifications'] : (int)$current['inventory_notifications'];
        $bulk = isset($data['bulk_notifications']) ? (int)(bool)$data['bulk_notifications'] : (int)$current['bulk_notifications'];
        $system = isset($data['system_notifications']) ? (int)(bool)$data['system_notifications'] : (int)$current['system_notifications'];
        $retention = isset($data['retention_days']) ? max(7, min(365, (int)$data['retention_days'])) : (int)$current['retention_days'];

        $stmt = $this->pdo->prepare("
            INSERT INTO user_notification_preferences (
                user_id, task_notifications, order_notifications, inventory_notifications, bulk_notifications, system_notifications, retention_days, created_at, updated_at
            ) VALUES (
                :user_id, :task, :order, :inventory, :bulk, :system, :retention, NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                task_notifications = VALUES(task_notifications),
                order_notifications = VALUES(order_notifications),
                inventory_notifications = VALUES(inventory_notifications),
                bulk_notifications = VALUES(bulk_notifications),
                system_notifications = VALUES(system_notifications),
                retention_days = VALUES(retention_days),
                updated_at = NOW()
        ");

        $stmt->execute([
            'user_id' => $userId,
            'task' => $task,
            'order' => $order,
            'inventory' => $inventory,
            'bulk' => $bulk,
            'system' => $system,
            'retention' => $retention,
        ]);

        return $this->getPreferences($userId);
    }
}
