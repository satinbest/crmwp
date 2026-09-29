<?php

namespace App\Services;

use App\Repositories\AuditLogRepository;

class AuditService
{
    private AuditLogRepository $repository;

    public function __construct(?AuditLogRepository $repository = null)
    {
        $this->repository = $repository ?? new AuditLogRepository();
    }

    public function log(
        ?int $userId,
        ?int $storeId,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $this->repository->create(
            $userId,
            $storeId,
            $action,
            $entityType,
            $entityId,
            $oldValues,
            $newValues,
            $ip,
            $userAgent
        );
    }

    public function getRecentLogs(int $limit = 20): array
    {
        return $this->repository->listRecent($limit);
    }
}
