<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class AutomationRunRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function create(array $data): array
    {
        $sql = "
            INSERT INTO automation_runs (
                automation_id, store_id, event_id, trigger_type,
                status, started_at, completed_at, duration_ms,
                idempotency_key, error, context, result, created_at
            ) VALUES (
                :automation_id, :store_id, :event_id, :trigger_type,
                :status, :started_at, :completed_at, :duration_ms,
                :idempotency_key, :error, :context, :result, NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':automation_id' => $data['automation_id'],
            ':store_id' => $data['store_id'] ?? null,
            ':event_id' => $data['event_id'] ?? null,
            ':trigger_type' => $data['trigger_type'],
            ':status' => $data['status'] ?? 'pending',
            ':started_at' => $data['started_at'] ?? date('Y-m-d H:i:s'),
            ':completed_at' => $data['completed_at'] ?? null,
            ':duration_ms' => $data['duration_ms'] ?? null,
            ':idempotency_key' => $data['idempotency_key'] ?? null,
            ':error' => $data['error'] ?? null,
            ':context' => !empty($data['context']) ? json_encode($data['context'], JSON_UNESCAPED_UNICODE) : null,
            ':result' => !empty($data['result']) ? json_encode($data['result'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['status', 'completed_at', 'duration_ms', 'error', 'result', 'context'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $val = $data[$f];
                if (in_array($f, ['context', 'result'], true) && is_array($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                $fields[] = "`{$f}` = :{$f}";
                $params[":{$f}"] = $val;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE automation_runs SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   a.name AS automation_name,
                   s.name AS store_name
            FROM automation_runs r
            LEFT JOIN automations a ON r.automation_id = a.id
            LEFT JOIN stores s ON r.store_id = s.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['context'] = !empty($row['context']) ? json_decode($row['context'], true) : [];
        $row['result'] = !empty($row['result']) ? json_decode($row['result'], true) : [];

        return $row;
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        if (empty($key)) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM automation_runs WHERE idempotency_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['context'] = !empty($row['context']) ? json_decode($row['context'], true) : [];
        $row['result'] = !empty($row['result']) ? json_decode($row['result'], true) : [];

        return $row;
    }

    public function listByAutomation(int $automationId, int $page = 1, int $perPage = 20): array
    {
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM automation_runs WHERE automation_id = :aid");
        $countStmt->execute([':aid' => $automationId]);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare("
            SELECT * FROM automation_runs
            WHERE automation_id = :aid
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':aid', $automationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['context'] = !empty($r['context']) ? json_decode($r['context'], true) : [];
            $r['result'] = !empty($r['result']) ? json_decode($r['result'], true) : [];
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

    public function listFiltered(?int $storeId = null, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if ($storeId !== null && $storeId > 0) {
            $where[] = 'r.store_id = :store_id';
            $params[':store_id'] = $storeId;
        }

        if (!empty($filters['automation_id'])) {
            $where[] = 'r.automation_id = :automation_id';
            $params[':automation_id'] = (int)$filters['automation_id'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['trigger_type'])) {
            $where[] = 'r.trigger_type = :trigger_type';
            $params[':trigger_type'] = $filters['trigger_type'];
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM automation_runs r WHERE {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "
            SELECT r.*,
                   a.name AS automation_name,
                   s.name AS store_name
            FROM automation_runs r
            LEFT JOIN automations a ON r.automation_id = a.id
            LEFT JOIN stores s ON r.store_id = s.id
            WHERE {$whereClause}
            ORDER BY r.created_at DESC
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
            $r['context'] = !empty($r['context']) ? json_decode($r['context'], true) : [];
            $r['result'] = !empty($r['result']) ? json_decode($r['result'], true) : [];
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
}
