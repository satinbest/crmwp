<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class WebhookLogRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function create(array $data): int
    {
        $payload = is_array($data['payload'])
            ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE)
            : (string)($data['payload'] ?? '{}');

        $stmt = $this->pdo->prepare("
            INSERT INTO `webhook_logs` (
                `store_id`, `delivery_id`, `webhook_id`, `resource_id`,
                `topic`, `event`, `signature`, `payload`, `status`,
                `error_message`, `ip_address`, `attempt`, `processing_time_ms`,
                `processed_at`, `created_at`
            ) VALUES (
                :store_id, :delivery_id, :webhook_id, :resource_id,
                :topic, :event, :signature, :payload, :status,
                :error_message, :ip_address, :attempt, :processing_time_ms,
                :processed_at, NOW()
            )
        ");

        $stmt->execute([
            'store_id' => (int)$data['store_id'],
            'delivery_id' => $data['delivery_id'] ?? null,
            'webhook_id' => $data['webhook_id'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
            'topic' => $data['topic'] ?? 'action.created',
            'event' => $data['event'] ?? ($data['topic'] ?? 'action.created'),
            'signature' => $data['signature'] ?? null,
            'payload' => $payload,
            'status' => $data['status'] ?? 'received',
            'error_message' => $data['error_message'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'attempt' => (int)($data['attempt'] ?? 1),
            'processing_time_ms' => isset($data['processing_time_ms']) ? (int)$data['processing_time_ms'] : null,
            'processed_at' => $data['processed_at'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $error = null, ?int $processingTimeMs = null): void
    {
        $sql = "UPDATE `webhook_logs` SET `status` = :status";
        $params = [
            'id' => $id,
            'status' => $status,
        ];

        if ($error !== null) {
            $sql .= ", `error_message` = :error";
            $params['error'] = $error;
        }

        if ($processingTimeMs !== null) {
            $sql .= ", `processing_time_ms` = :processing_time_ms";
            $params['processing_time_ms'] = $processingTimeMs;
        }

        if (in_array($status, ['processed', 'failed', 'ignored', 'duplicate'], true)) {
            $sql .= ", `processed_at` = NOW()";
        }

        $sql .= " WHERE `id` = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function incrementAttempt(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE `webhook_logs` SET `attempt` = `attempt` + 1 WHERE `id` = ?");
        $stmt->execute([$id]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT wl.*, s.name as store_name
            FROM `webhook_logs` wl
            LEFT JOIN `stores` s ON wl.store_id = s.id
            WHERE wl.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->formatRow($row);
    }

    public function findByDeliveryId(int $storeId, string $deliveryId): ?array
    {
        if (empty($deliveryId)) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM `webhook_logs`
            WHERE `store_id` = ? AND `delivery_id` = ?
            ORDER BY `id` ASC LIMIT 1
        ");
        $stmt->execute([$storeId, $deliveryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->formatRow($row) : null;
    }

    public function findRecentDuplicate(int $storeId, string $event, ?string $resourceId, int $windowSeconds = 120): ?array
    {
        if (empty($resourceId)) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM `webhook_logs`
            WHERE `store_id` = ?
              AND `event` = ?
              AND `resource_id` = ?
              AND `status` IN ('processed', 'processing')
              AND `created_at` >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            ORDER BY `id` DESC LIMIT 1
        ");
        $stmt->execute([$storeId, $event, $resourceId, $windowSeconds]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->formatRow($row) : null;
    }

    public function markIgnored(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE `webhook_logs` SET `status` = 'ignored', `processed_at` = NOW() WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    public function list(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['store_id'])) {
            $conditions[] = "wl.store_id = :store_id";
            $params['store_id'] = (int)$filters['store_id'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = "wl.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['event'])) {
            $conditions[] = "(wl.event = :event OR wl.topic = :event)";
            $params['event'] = $filters['event'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(wl.delivery_id LIKE :search OR wl.resource_id LIKE :search OR wl.topic LIKE :search OR wl.error_message LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count total
        $countSql = "SELECT COUNT(*) FROM `webhook_logs` wl {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Pagination
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT wl.*, s.name as store_name
            FROM `webhook_logs` wl
            LEFT JOIN `stores` s ON wl.store_id = s.id
            {$whereClause}
            ORDER BY wl.id DESC
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

    public function getHealthStats(int $storeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN `status` = 'processed' THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN `status` = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN `status` = 'duplicate' THEN 1 ELSE 0 END) as duplicate,
                SUM(CASE WHEN `status` = 'ignored' THEN 1 ELSE 0 END) as ignored,
                SUM(CASE WHEN `status` = 'failed' AND `created_at` >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as failed_24h,
                MAX(CASE WHEN `status` = 'processed' THEN `created_at` ELSE NULL END) as last_processed_at,
                MAX(`created_at`) as last_webhook_at,
                AVG(CASE WHEN `processing_time_ms` IS NOT NULL THEN `processing_time_ms` ELSE NULL END) as avg_processing_time_ms
            FROM `webhook_logs`
            WHERE `store_id` = ?
        ");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int)($row['total'] ?? 0);
        $failed24h = (int)($row['failed_24h'] ?? 0);
        $lastWebhook = $row['last_webhook_at'] ?? null;

        // Health evaluation: healthy, warning, failing
        $healthStatus = 'healthy';
        if ($failed24h > 5) {
            $healthStatus = 'failing';
        } elseif ($failed24h > 0 || ($total > 0 && $lastWebhook && strtotime($lastWebhook) < strtotime('-7 days'))) {
            $healthStatus = 'warning';
        }

        return [
            'store_id' => $storeId,
            'health_status' => $healthStatus,
            'total_webhooks' => $total,
            'processed_count' => (int)($row['processed'] ?? 0),
            'failed_count' => (int)($row['failed'] ?? 0),
            'duplicate_count' => (int)($row['duplicate'] ?? 0),
            'ignored_count' => (int)($row['ignored'] ?? 0),
            'failed_24h' => $failed24h,
            'last_webhook_at' => $lastWebhook,
            'last_processed_at' => $row['last_processed_at'] ?? null,
            'avg_processing_time_ms' => round((float)($row['avg_processing_time_ms'] ?? 0), 1),
        ];
    }

    private function formatRow(array $row): array
    {
        $payload = null;
        if (!empty($row['payload'])) {
            $decoded = json_decode($row['payload'], true);
            $payload = is_array($decoded) ? $decoded : $row['payload'];
        }

        return [
            'id' => (int)$row['id'],
            'store_id' => (int)$row['store_id'],
            'store_name' => $row['store_name'] ?? null,
            'delivery_id' => $row['delivery_id'],
            'webhook_id' => $row['webhook_id'],
            'resource_id' => $row['resource_id'],
            'topic' => $row['topic'],
            'event' => $row['event'] ?? $row['topic'],
            'status' => $row['status'],
            'error_message' => $row['error_message'],
            'error' => $row['error_message'],
            'ip_address' => $row['ip_address'],
            'attempt' => (int)$row['attempt'],
            'processing_time_ms' => $row['processing_time_ms'] !== null ? (int)$row['processing_time_ms'] : null,
            'processed_at' => $row['processed_at'],
            'created_at' => $row['created_at'],
            'payload' => $payload,
        ];
    }
}
