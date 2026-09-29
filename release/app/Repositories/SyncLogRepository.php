<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class SyncLogRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function create(array $data): int
    {
        $entityType = $data['entity_type'] ?? ($data['sync_type'] ?? 'all');
        $summary = isset($data['details']) && is_array($data['details'])
            ? json_encode($data['details'], JSON_UNESCAPED_UNICODE)
            : (isset($data['summary']) && is_array($data['summary']) ? json_encode($data['summary'], JSON_UNESCAPED_UNICODE) : null);

        $stmt = $this->pdo->prepare("
            INSERT INTO `sync_logs` (
                `store_id`, `sync_type`, `entity_type`, `direction`,
                `status`, `processed`, `failed`, `summary`, `details`,
                `error_message`, `started_at`
            ) VALUES (
                :store_id, :sync_type, :entity_type, :direction,
                :status, :processed, :failed, :summary, :details,
                :error_message, NOW()
            )
        ");

        $stmt->execute([
            'store_id' => (int)$data['store_id'],
            'sync_type' => $entityType,
            'entity_type' => $entityType,
            'direction' => $data['direction'] ?? 'inbound_reconcile',
            'status' => $data['status'] ?? 'running',
            'processed' => (int)($data['processed'] ?? 0),
            'failed' => (int)($data['failed'] ?? 0),
            'summary' => $summary,
            'details' => $summary,
            'error_message' => $data['error_message'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('status', $data)) {
            $fields[] = "`status` = :status";
            $params['status'] = $data['status'];
            if (in_array($data['status'], ['completed', 'failed', 'partial'], true)) {
                $fields[] = "`completed_at` = NOW()";
            }
        }

        if (array_key_exists('processed', $data)) {
            $fields[] = "`processed` = :processed";
            $params['processed'] = (int)$data['processed'];
        }

        if (array_key_exists('failed', $data)) {
            $fields[] = "`failed` = :failed";
            $params['failed'] = (int)$data['failed'];
        }

        if (array_key_exists('details', $data) || array_key_exists('summary', $data)) {
            $details = $data['details'] ?? $data['summary'];
            $encoded = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : (string)$details;
            $fields[] = "`summary` = :summary";
            $fields[] = "`details` = :details";
            $params['summary'] = $encoded;
            $params['details'] = $encoded;
        }

        if (array_key_exists('error_message', $data)) {
            $fields[] = "`error_message` = :error_message";
            $params['error_message'] = $data['error_message'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE `sync_logs` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT sl.*, s.name as store_name
            FROM `sync_logs` sl
            LEFT JOIN `stores` s ON sl.store_id = s.id
            WHERE sl.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->formatRow($row) : null;
    }

    public function list(int $storeId, array $filters = []): array
    {
        $conditions = ["sl.store_id = :store_id"];
        $params = ['store_id' => $storeId];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = "sl.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['entity_type'])) {
            $conditions[] = "(sl.entity_type = :entity_type OR sl.sync_type = :entity_type)";
            $params['entity_type'] = $filters['entity_type'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        // Count total
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `sync_logs` sl {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Pagination
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int)($filters['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT sl.*, s.name as store_name
            FROM `sync_logs` sl
            LEFT JOIN `stores` s ON sl.store_id = s.id
            {$whereClause}
            ORDER BY sl.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => array_map([$this, 'formatRow'], $rows),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int)ceil($total / $perPage) ?: 1,
            ],
        ];
    }

    public function getLastSuccessfulSync(int $storeId, ?string $entityType = null): ?array
    {
        $sql = "SELECT * FROM `sync_logs` WHERE `store_id` = ? AND `status` = 'completed'";
        $params = [$storeId];

        if ($entityType !== null) {
            $sql .= " AND (`entity_type` = ? OR `sync_type` = ?)";
            $params[] = $entityType;
            $params[] = $entityType;
        }

        $sql .= " ORDER BY `id` DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->formatRow($row) : null;
    }

    public function getHealthStats(int $storeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_syncs,
                SUM(CASE WHEN `status` = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN `status` = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN `status` = 'partial' THEN 1 ELSE 0 END) as partial,
                MAX(CASE WHEN `status` = 'completed' THEN `completed_at` ELSE NULL END) as last_completed_at,
                MAX(`started_at`) as last_reconciliation_at
            FROM `sync_logs`
            WHERE `store_id` = ?
        ");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_syncs' => (int)($row['total_syncs'] ?? 0),
            'completed' => (int)($row['completed'] ?? 0),
            'failed' => (int)($row['failed'] ?? 0),
            'partial' => (int)($row['partial'] ?? 0),
            'last_completed_at' => $row['last_completed_at'] ?? null,
            'last_reconciliation_at' => $row['last_reconciliation_at'] ?? null,
        ];
    }

    private function formatRow(array $row): array
    {
        $details = null;
        $rawDetails = $row['details'] ?? $row['summary'];
        if (!empty($rawDetails)) {
            $decoded = json_decode($rawDetails, true);
            $details = is_array($decoded) ? $decoded : $rawDetails;
        }

        return [
            'id' => (int)$row['id'],
            'store_id' => (int)$row['store_id'],
            'store_name' => $row['store_name'] ?? null,
            'entity_type' => $row['entity_type'] ?? $row['sync_type'],
            'sync_type' => $row['sync_type'],
            'direction' => $row['direction'] ?? 'inbound_reconcile',
            'status' => $row['status'],
            'processed' => (int)($row['processed'] ?? 0),
            'failed' => (int)($row['failed'] ?? 0),
            'details' => $details,
            'summary' => $details,
            'error_message' => $row['error_message'],
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
        ];
    }
}
