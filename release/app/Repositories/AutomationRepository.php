<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class AutomationRepository extends StoreScopedRepository
{
    public function __construct(?PDO $pdo = null, ?int $storeId = null)
    {
        parent::__construct($pdo ?? Connection::get(), $storeId);
    }

    public function listFiltered(?int $storeId = null, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if ($storeId !== null && $storeId > 0) {
            $where[] = 'store_id = :store_id';
            $params[':store_id'] = $storeId;
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['trigger_type'])) {
            $where[] = 'trigger_type = :trigger_type';
            $params[':trigger_type'] = $filters['trigger_type'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countSql = "SELECT COUNT(*) FROM automations WHERE {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Fetch rows
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "
            SELECT a.*,
                   s.name AS store_name,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS creator_name
            FROM automations a
            LEFT JOIN stores s ON a.store_id = s.id
            LEFT JOIN users u ON a.created_by = u.id
            WHERE {$whereClause}
            ORDER BY a.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['trigger_config'] = !empty($r['trigger_config']) ? json_decode($r['trigger_config'], true) : [];
            $r['conditions'] = !empty($r['conditions']) ? json_decode($r['conditions'], true) : [];
            $r['actions'] = !empty($r['actions']) ? json_decode($r['actions'], true) : [];
        }

        return [
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   s.name AS store_name,
                   s.url AS store_url,
                   s.currency AS store_currency,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS creator_name
            FROM automations a
            LEFT JOIN stores s ON a.store_id = s.id
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['trigger_config'] = !empty($row['trigger_config']) ? json_decode($row['trigger_config'], true) : [];
        $row['conditions'] = !empty($row['conditions']) ? json_decode($row['conditions'], true) : [];
        $row['actions'] = !empty($row['actions']) ? json_decode($row['actions'], true) : [];

        return $row;
    }

    /**
     * Find all active automations matching a trigger type for a store.
     */
    public function findActiveByTrigger(string $triggerType, ?int $storeId = null): array
    {
        $sql = "
            SELECT * FROM automations
            WHERE status = 'active'
              AND trigger_type = :trigger_type
        ";
        $params = [':trigger_type' => $triggerType];

        if ($storeId !== null && $storeId > 0) {
            $sql .= " AND (store_id = :store_id OR store_id IS NULL)";
            $params[':store_id'] = $storeId;
        }

        $sql .= " ORDER BY id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['trigger_config'] = !empty($r['trigger_config']) ? json_decode($r['trigger_config'], true) : [];
            $r['conditions'] = !empty($r['conditions']) ? json_decode($r['conditions'], true) : [];
            $r['actions'] = !empty($r['actions']) ? json_decode($r['actions'], true) : [];
        }

        return $rows;
    }

    public function create(array $data): array
    {
        $sql = "
            INSERT INTO automations (
                store_id, name, description, status, trigger_type,
                trigger_config, conditions, actions, execution_mode,
                max_runs, run_count, created_by, updated_by, created_at, updated_at
            ) VALUES (
                :store_id, :name, :description, :status, :trigger_type,
                :trigger_config, :conditions, :actions, :execution_mode,
                :max_runs, 0, :created_by, :updated_by, NOW(), NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':store_id' => $data['store_id'] ?? null,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'] ?? 'active',
            ':trigger_type' => $data['trigger_type'],
            ':trigger_config' => !empty($data['trigger_config']) ? json_encode($data['trigger_config'], JSON_UNESCAPED_UNICODE) : null,
            ':conditions' => !empty($data['conditions']) ? json_encode($data['conditions'], JSON_UNESCAPED_UNICODE) : null,
            ':actions' => is_string($data['actions']) ? $data['actions'] : json_encode($data['actions'] ?? [], JSON_UNESCAPED_UNICODE),
            ':execution_mode' => $data['execution_mode'] ?? 'immediate',
            ':max_runs' => $data['max_runs'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = [
            'store_id', 'name', 'description', 'status', 'trigger_type',
            'trigger_config', 'conditions', 'actions', 'execution_mode',
            'max_runs', 'updated_by'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                if (in_array($field, ['trigger_config', 'conditions', 'actions'], true) && is_array($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                $fields[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $val;
            }
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $fields[] = "`updated_at` = NOW()";
        $setClause = implode(', ', $fields);

        $stmt = $this->pdo->prepare("UPDATE automations SET {$setClause} WHERE id = :id");
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM automations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function incrementRunCount(int $id, ?string $lastRunAt = null): void
    {
        $now = $lastRunAt ?? date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            UPDATE automations
            SET run_count = run_count + 1,
                last_run_at = :last_run_at
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id, ':last_run_at' => $now]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE automations SET status = :status, updated_at = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }
}
