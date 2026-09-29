<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class CustomerTaskRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function listByCustomer(int $storeId, int $customerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_assigned.first_name, u_assigned.last_name)), ''), u_assigned.username) AS assigned_user_name,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_creator.first_name, u_creator.last_name)), ''), u_creator.username) AS creator_user_name
            FROM tasks t
            LEFT JOIN users u_assigned ON t.assigned_user_id = u_assigned.id
            LEFT JOIN users u_creator ON t.created_by_user_id = u_creator.id
            WHERE t.store_id = :store_id AND t.wc_customer_id = :customer_id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByOrder(int $storeId, int $orderId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_assigned.first_name, u_assigned.last_name)), ''), u_assigned.username) AS assigned_user_name,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_creator.first_name, u_creator.last_name)), ''), u_creator.username) AS creator_user_name
            FROM tasks t
            LEFT JOIN users u_assigned ON t.assigned_user_id = u_assigned.id
            LEFT JOIN users u_creator ON t.created_by_user_id = u_creator.id
            WHERE t.store_id = :store_id AND t.wc_order_id = :order_id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $taskId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_assigned.first_name, u_assigned.last_name)), ''), u_assigned.username) AS assigned_user_name,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_creator.first_name, u_creator.last_name)), ''), u_creator.username) AS creator_user_name
            FROM tasks t
            LEFT JOIN users u_assigned ON t.assigned_user_id = u_assigned.id
            LEFT JOIN users u_creator ON t.created_by_user_id = u_creator.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (
                store_id, wc_customer_id, wc_order_id, assigned_user_id, created_by_user_id,
                title, description, priority, status, due_date, created_at, updated_at
            ) VALUES (
                :store_id, :customer_id, :order_id, :assigned_user_id, :created_by_user_id,
                :title, :description, :priority, :status, :due_date, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'store_id' => $data['store_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'order_id' => $data['order_id'] ?? $data['wc_order_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'pending',
            'due_date' => $data['due_date'] ?? null,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $taskId, array $data): ?array
    {
        $fields = [];
        $params = ['id' => $taskId];

        if (array_key_exists('title', $data)) {
            $fields[] = 'title = :title';
            $params['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $fields[] = 'description = :description';
            $params['description'] = $data['description'];
        }
        if (array_key_exists('priority', $data)) {
            $fields[] = 'priority = :priority';
            $params['priority'] = $data['priority'];
        }
        if (array_key_exists('status', $data)) {
            $fields[] = 'status = :status';
            $params['status'] = $data['status'];
            if ($data['status'] === 'completed') {
                $fields[] = 'completed_at = NOW()';
            } elseif ($data['status'] !== 'completed') {
                $fields[] = 'completed_at = NULL';
            }
        }
        if (array_key_exists('due_date', $data)) {
            $fields[] = 'due_date = :due_date';
            $params['due_date'] = $data['due_date'];
        }
        if (array_key_exists('assigned_user_id', $data)) {
            $fields[] = 'assigned_user_id = :assigned_user_id';
            $params['assigned_user_id'] = $data['assigned_user_id'];
        }

        if (empty($fields)) {
            return $this->findById($taskId);
        }

        $fields[] = 'updated_at = NOW()';
        $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->findById($taskId);
    }

    public function delete(int $taskId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = :id");
        return $stmt->execute(['id' => $taskId]);
    }

    public function complete(int $taskId): ?array
    {
        return $this->update($taskId, ['status' => 'completed']);
    }

    public function reopen(int $taskId): ?array
    {
        return $this->update($taskId, ['status' => 'in_progress']);
    }

    public function listStoreTasks(int $storeId, array $filters = [], ?int $currentUserId = null): array
    {
        $sql = "
            SELECT t.*,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_assigned.first_name, u_assigned.last_name)), ''), u_assigned.username) AS assigned_user_name,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u_creator.first_name, u_creator.last_name)), ''), u_creator.username) AS creator_user_name
            FROM tasks t
            LEFT JOIN users u_assigned ON t.assigned_user_id = u_assigned.id
            LEFT JOIN users u_creator ON t.created_by_user_id = u_creator.id
            WHERE t.store_id = :store_id
        ";
        $params = ['store_id' => $storeId];

        // View tabs
        $view = $filters['view'] ?? 'all';
        if ($view === 'my' && $currentUserId) {
            $sql .= " AND t.assigned_user_id = :current_user_id";
            $params['current_user_id'] = $currentUserId;
        } elseif ($view === 'today') {
            $sql .= " AND DATE(t.due_date) = CURDATE()";
        } elseif ($view === 'upcoming') {
            $sql .= " AND t.due_date > NOW() AND t.status NOT IN ('completed', 'cancelled')";
        } elseif ($view === 'overdue') {
            $sql .= " AND t.due_date < NOW() AND t.status NOT IN ('completed', 'cancelled')";
        } elseif ($view === 'completed') {
            $sql .= " AND t.status = 'completed'";
        }

        // Direct filters
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND t.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $sql .= " AND t.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_user_id = :assigned_user_id";
            $params['assigned_user_id'] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND t.wc_customer_id = :customer_id";
            $params['customer_id'] = (int)$filters['customer_id'];
        }

        if (!empty($filters['order_id'])) {
            $sql .= " AND t.wc_order_id = :order_id";
            $params['order_id'] = (int)$filters['order_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (t.title LIKE :search OR t.description LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        $sql .= " ORDER BY CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date ASC, t.created_at DESC";

        if (!empty($filters['limit'])) {
            $limit = min(100, max(1, (int)$filters['limit']));
            $offset = max(0, (int)($filters['offset'] ?? 0));
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countMetrics(int $storeId, ?int $userId = null): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS open_tasks,
                SUM(CASE WHEN status NOT IN ('completed', 'cancelled') AND due_date < NOW() THEN 1 ELSE 0 END) AS overdue_tasks,
                SUM(CASE WHEN DATE(due_date) = CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS due_today,
                SUM(CASE WHEN due_date > NOW() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS upcoming_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
                SUM(CASE WHEN assigned_user_id = :user_id AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS my_open_tasks
            FROM tasks
            WHERE store_id = :store_id
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'user_id' => $userId ?? 0,
        ]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int)($res['total'] ?? 0),
            'open_tasks' => (int)($res['open_tasks'] ?? 0),
            'overdue_tasks' => (int)($res['overdue_tasks'] ?? 0),
            'due_today' => (int)($res['due_today'] ?? 0),
            'upcoming_tasks' => (int)($res['upcoming_tasks'] ?? 0),
            'completed_tasks' => (int)($res['completed_tasks'] ?? 0),
            'my_open_tasks' => (int)($res['my_open_tasks'] ?? 0),
        ];
    }
}
