<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class AuditLogRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function create(
        ?int $userId,
        ?int $storeId,
        string $action,
        string $entityType,
        ?string $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO `audit_logs` (
                `user_id`, `store_id`, `action`, `entity_type`, `entity_id`,
                `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $storeId,
            $action,
            $entityType,
            $entityId,
            $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            $ip,
            $userAgent ? substr($userAgent, 0, 255) : null,
        ]);
    }

    public function listRecent(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT al.*, u.username, u.first_name, u.last_name
            FROM `audit_logs` al
            LEFT JOIN `users` u ON al.user_id = u.id
            ORDER BY al.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
