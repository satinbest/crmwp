<?php

namespace App\Services\Bulk;

use App\Database\Connection;
use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Services\AuditService;
use App\Support\Logger;
use Exception;
use InvalidArgumentException;
use PDO;

class BulkOperationEngine
{
    private PDO $pdo;
    private StoreRepository $storeRepo;
    private AuditService $auditService;
    private array $handlers = [];

    public function __construct(?PDO $pdo = null, ?AuditService $auditService = null)
    {
        $this->pdo = $pdo ?? Connection::get();
        $this->storeRepo = new StoreRepository($this->pdo);
        $this->auditService = $auditService ?? new AuditService();

        // Register default handlers
        $this->registerHandler(new ProductBulkHandler());
        $this->registerHandler(new OrderBulkHandler());
        $this->registerHandler(new CustomerBulkHandler($this->pdo));
    }

    public function registerHandler(BulkOperationHandlerInterface $handler): void
    {
        $this->handlers[$handler->getEntityType()] = $handler;
    }

    public function getHandler(string $entityType): BulkOperationHandlerInterface
    {
        $normalized = $this->normalizeEntityType($entityType);
        if (!isset($this->handlers[$normalized])) {
            throw new InvalidArgumentException("موجودیتی با نوع '{$entityType}' برای عملیات گروهی یافت نشد.");
        }
        return $this->handlers[$normalized];
    }

    public function normalizeEntityType(string $entity): string
    {
        $slug = strtolower(trim($entity));
        return match ($slug) {
            'product', 'products' => 'products',
            'order', 'orders' => 'orders',
            'customer', 'customers' => 'customers',
            default => $slug,
        };
    }

    public function getTargetEntityDbEnum(string $entity): string
    {
        $slug = strtolower(trim($entity));
        return match ($slug) {
            'product', 'products' => 'product',
            'order', 'orders' => 'order',
            'customer', 'customers' => 'customer',
            default => 'product',
        };
    }

    /**
     * Preview bulk operation: generates count, sample before/after, and warnings without modifying WooCommerce or CRM.
     */
    public function preview(int $storeId, int $userId, array $requestData): array
    {
        $store = $this->storeRepo->findById($storeId);
        if (!$store) {
            throw new InvalidArgumentException("فروشگاه مورد نظر یافت نشد.");
        }

        $entityType = $requestData['entity'] ?? ($requestData['target_entity'] ?? '');
        $handler = $this->getHandler($entityType);

        $actionData = $requestData['action'] ?? [];
        $actionType = $actionData['type'] ?? '';
        if (empty($actionType)) {
            throw new InvalidArgumentException("نوع عملیات گروهی (action.type) مشخص نشده است.");
        }

        $actionParams = $handler->validateAction($actionType, $actionData, $store);

        $selection = $requestData['selection'] ?? [];
        $filter = $requestData['filter'] ?? ($selection['filter'] ?? []);

        // Resolve total affected count & preview sample
        $affectedCount = $handler->resolveCount($store, $selection, $filter);
        $previewResult = $handler->preview($store, $selection, $filter, $actionType, $actionParams);
        $finalCount = $previewResult['affected_count'] ?? $affectedCount;

        return [
            'entity' => $handler->getEntityType(),
            'affected_count' => $finalCount,
            'parent_count' => $previewResult['parent_count'] ?? $affectedCount,
            'simple_count' => $previewResult['simple_count'] ?? 0,
            'variable_count' => $previewResult['variable_count'] ?? 0,
            'variation_count' => $previewResult['variation_count'] ?? 0,
            'breakdown' => $previewResult['breakdown'] ?? [
                'simple' => $previewResult['simple_count'] ?? 0,
                'variable' => $previewResult['variable_count'] ?? 0,
                'variation' => $previewResult['variation_count'] ?? 0,
                'total' => $finalCount,
            ],
            'target' => $actionParams['target'] ?? 'parent',
            'action' => [
                'type' => $actionType,
                'params' => $actionParams,
            ],
            'sample' => $previewResult['sample'] ?? [],
            'warnings' => $previewResult['warnings'] ?? [],
        ];
    }

    /**
     * Create bulk operation record in DB with pending status.
     */
    public function createOperation(int $storeId, int $userId, array $requestData): array
    {
        $store = $this->storeRepo->findById($storeId);
        if (!$store) {
            throw new InvalidArgumentException("فروشگاه مورد نظر یافت نشد.");
        }

        $entityType = $requestData['entity'] ?? ($requestData['target_entity'] ?? '');
        $handler = $this->getHandler($entityType);

        $actionData = $requestData['action'] ?? [];
        $actionType = $actionData['type'] ?? '';
        if (empty($actionType)) {
            throw new InvalidArgumentException("نوع عملیات گروهی (action.type) مشخص نشده است.");
        }

        $actionParams = $handler->validateAction($actionType, $actionData, $store);

        $selection = $requestData['selection'] ?? [];
        $filter = $requestData['filter'] ?? ($selection['filter'] ?? []);

        // Server-side authoritative count at execution time
        $previewResult = $handler->preview($store, $selection, $filter, $actionType, $actionParams);
        $affectedCount = $previewResult['affected_count'] ?? $handler->resolveCount($store, $selection, $filter);
        if ($affectedCount <= 0) {
            throw new InvalidArgumentException("هیچ رکوردی منطبق با فیلترها یا شناسه‌های انتخابی برای اجرای عملیات یافت نشد.");
        }

        // Abuse protection: limit max affected records per bulk operation (Phase 16)
        if ($affectedCount > 5000) {
            throw new InvalidArgumentException("حداکثر تعداد مجاز رکوردها در یک عملیات گروهی ۵,۰۰۰ رکورد است. جهت حفظ پایداری سرور، لطفاً از فیلترهای محدودتر استفاده نمایید.");
        }

        $idempotencyKey = !empty($requestData['idempotency_key']) ? trim((string)$requestData['idempotency_key']) : null;
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $stmt = $this->pdo->prepare("
                SELECT id FROM bulk_operations 
                WHERE store_id = :store_id 
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.idempotency_key')) = :idempotency_key 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute(['store_id' => $storeId, 'idempotency_key' => $idempotencyKey]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                $existingOp = $this->getOperation((int)$existingId, $storeId);
                $existingOp['is_cached_idempotent'] = true;
                return $existingOp;
            }
        }

        $targetEntityDb = $this->getTargetEntityDbEnum($entityType);

        $payload = [
            'action' => [
                'type' => $actionType,
                'params' => $actionParams,
            ],
            'selection' => $selection,
            'filter' => $filter,
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $payload['idempotency_key'] = $idempotencyKey;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO bulk_operations (
                store_id, user_id, type, action_type, target_entity,
                status, total_items, processed_items, success_items, failed_items, skipped_items,
                filter_criteria, payload, created_at, updated_at
            ) VALUES (
                :store_id, :user_id, :type, :action_type, :target_entity,
                'pending', :total_items, 0, 0, 0, 0,
                :filter_criteria, :payload, NOW(), NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'user_id' => $userId,
            'type' => $actionType,
            'action_type' => $actionType,
            'target_entity' => $targetEntityDb,
            'total_items' => $affectedCount,
            'filter_criteria' => json_encode($filter, JSON_UNESCAPED_UNICODE),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $opId = (int)$this->pdo->lastInsertId();

        $this->auditService->log(
            $userId,
            $storeId,
            'BULK_OPERATION_CREATED',
            'bulk_operation',
            (string)$opId,
            null,
            ['action' => $actionType, 'target_entity' => $targetEntityDb, 'total_items' => $affectedCount]
        );

        return $this->getOperation($opId, $storeId);
    }

    /**
     * Execute a bulk operation in safe controlled batches.
     * Prevents concurrent execution using atomic state transition.
     */
    public function executeOperation(int $operationId, int $batchSize = 50): array
    {
        // 1. Concurrency Control: Atomic lock from 'pending' to 'processing'
        $stmt = $this->pdo->prepare("
            UPDATE bulk_operations
            SET status = 'processing', started_at = NOW(), updated_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->execute(['id' => $operationId]);

        $acquired = $stmt->rowCount() > 0;
        $op = $this->getOperationByIdRaw($operationId);

        if (!$op) {
            throw new InvalidArgumentException("عملیات گروهی یافت نشد.");
        }

        // If not acquired, either already running, completed, or cancelled
        if (!$acquired) {
            if ($op['status'] === 'processing') {
                Logger::info("Operation {$operationId} is already running in another process.");
                return $this->getOperation($operationId, (int)$op['store_id']);
            }
            return $this->getOperation($operationId, (int)$op['store_id']);
        }

        $storeId = (int)$op['store_id'];
        $userId = (int)$op['user_id'];
        $store = $this->storeRepo->findById($storeId);
        if (!$store) {
            $this->markOperationStatus($operationId, 'failed', ['error' => 'فروشگاه نامعتبر یا حذف شده است']);
            return $this->getOperation($operationId, $storeId);
        }

        $targetEntity = $op['target_entity'];
        $handler = $this->getHandler($targetEntity);

        $payload = is_string($op['payload']) ? json_decode($op['payload'], true) : $op['payload'];
        $actionData = $payload['action'] ?? [];
        $actionType = $actionData['type'] ?? $op['type'];
        $actionParams = $actionData['params'] ?? [];
        $selection = $payload['selection'] ?? [];
        $filter = $payload['filter'] ?? ($selection['filter'] ?? []);

        $totalItems = (int)$op['total_items'];
        $processedTotal = 0;
        $successTotal = 0;
        $failedTotal = 0;
        $skippedTotal = 0;

        $page = 1;
        $isCancelled = false;
        $overallResults = [];

        try {
            while (true) {
                // Check if cancelled before starting next batch
                $currentStatus = $this->pdo->query("SELECT status FROM bulk_operations WHERE id = {$operationId}")->fetchColumn();
                if ($currentStatus === 'cancelled') {
                    $isCancelled = true;
                    Logger::info("Bulk operation {$operationId} was cancelled by user. Halting execution.");
                    break;
                }

                // Fetch batch
                $entities = $handler->resolveEntitiesBatch($store, $selection, $filter, $page, $batchSize);
                if (empty($entities)) {
                    break;
                }

                // Execute batch
                $batchResults = $handler->executeBatch($store, $entities, $actionType, $actionParams);

                // Persist item results
                $itemInsertStmt = $this->pdo->prepare("
                    INSERT INTO bulk_operation_items (
                        bulk_operation_id, entity_id, status, old_state, new_state, error_code, error_message, processed_at
                    ) VALUES (
                        :op_id, :entity_id, :status, :old_state, :new_state, :error_code, :error_message, NOW()
                    )
                ");

                foreach ($batchResults as $res) {
                    $itemStatus = $res['status'];
                    if ($itemStatus === 'completed') {
                        $successTotal++;
                    } elseif ($itemStatus === 'skipped') {
                        $skippedTotal++;
                    } else {
                        $failedTotal++;
                    }
                    $processedTotal++;

                    $itemInsertStmt->execute([
                        'op_id' => $operationId,
                        'entity_id' => (int)$res['entity_id'],
                        'status' => $itemStatus,
                        'old_state' => isset($res['old_value']) ? json_encode($res['old_value'], JSON_UNESCAPED_UNICODE) : null,
                        'new_state' => isset($res['new_value']) ? json_encode($res['new_value'], JSON_UNESCAPED_UNICODE) : null,
                        'error_code' => $res['error_code'] ?? null,
                        'error_message' => $res['error_message'] ?? null,
                    ]);

                    $overallResults[] = $res;
                }

                // Update operation progress counters
                $this->pdo->prepare("
                    UPDATE bulk_operations
                    SET processed_items = :processed,
                        success_items = :success,
                        failed_items = :failed,
                        skipped_items = :skipped,
                        updated_at = NOW()
                    WHERE id = :id
                ")->execute([
                    'processed' => $processedTotal,
                    'success' => $successTotal,
                    'failed' => $failedTotal,
                    'skipped' => $skippedTotal,
                    'id' => $operationId,
                ]);

                // Next page if using filter; if using explicit IDs, slice moves with page
                $page++;

                // Stop if all items processed
                if ($processedTotal >= $totalItems) {
                    break;
                }
            }

            // Determine final status
            if ($isCancelled) {
                $finalStatus = 'cancelled';
            } elseif ($failedTotal > 0 && $successTotal === 0 && $skippedTotal === 0) {
                $finalStatus = 'failed';
            } elseif ($failedTotal > 0 || $skippedTotal > 0) {
                $finalStatus = ($successTotal > 0) ? 'partial' : ($skippedTotal > 0 ? 'completed' : 'failed');
            } else {
                $finalStatus = 'completed';
            }

            $summaryData = [
                'total' => $totalItems,
                'processed' => $processedTotal,
                'succeeded' => $successTotal,
                'failed' => $failedTotal,
                'skipped' => $skippedTotal,
            ];

            $this->pdo->prepare("
                UPDATE bulk_operations
                SET status = :status,
                    completed_at = NOW(),
                    result_data = :result_data,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'status' => $finalStatus,
                'result_data' => json_encode($summaryData, JSON_UNESCAPED_UNICODE),
                'id' => $operationId,
            ]);

            // Business activity & Audit logging
            $this->auditService->log(
                $userId,
                $storeId,
                'BULK_OPERATION_EXECUTED',
                'bulk_operation',
                (string)$operationId,
                null,
                [
                    'status' => $finalStatus,
                    'action' => $actionType,
                    'target_entity' => $targetEntity,
                    'summary' => $summaryData,
                ]
            );

            // Record store activity if applicable
            $this->recordStoreBulkActivity($storeId, $userId, $targetEntity, $actionType, $successTotal);

        } catch (Exception $e) {
            Logger::error("Fatal exception during bulk operation {$operationId}: " . $e->getMessage());
            $this->pdo->prepare("
                UPDATE bulk_operations
                SET status = 'failed',
                    completed_at = NOW(),
                    result_data = :res,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'res' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                'id' => $operationId,
            ]);
        }

        return $this->getOperation($operationId, $storeId);
    }

    /**
     * Cancel an operation in pending or processing state.
     */
    public function cancelOperation(int $operationId, int $userId): array
    {
        $op = $this->getOperationByIdRaw($operationId);
        if (!$op) {
            throw new InvalidArgumentException("عملیات گروهی یافت نشد.");
        }

        if (in_array($op['status'], ['completed', 'failed', 'cancelled'], true)) {
            throw new InvalidArgumentException("عملیات با وضعیت '{$op['status']}' قابل لغو نیست.");
        }

        $this->pdo->prepare("
            UPDATE bulk_operations
            SET status = 'cancelled',
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ")->execute(['id' => $operationId]);

        $this->auditService->log(
            $userId,
            (int)$op['store_id'],
            'BULK_OPERATION_CANCELLED',
            'bulk_operation',
            (string)$operationId,
            ['old_status' => $op['status']],
            ['new_status' => 'cancelled']
        );

        return [
            'success' => true,
            'message' => 'عملیات با موفقیت متوقف شد. آیتم‌هایی که تا قبل از لغو در ووکامرس اعمال شده‌اند حفظ شده و پردازش آیتم‌های بعدی لغو گردید.',
            'operation' => $this->getOperation($operationId, (int)$op['store_id']),
        ];
    }

    /**
     * Execute a single chunk/step of a bulk operation.
     * Allows real-time frontend progress reporting and cancellation without HTTP timeouts.
     */
    public function executeChunk(int $operationId, int $chunkSize = 25): array
    {
        $op = $this->getOperationByIdRaw($operationId);
        if (!$op) {
            throw new InvalidArgumentException("عملیات گروهی یافت نشد.");
        }

        if (in_array($op['status'], ['completed', 'cancelled', 'failed'], true)) {
            $res = $this->getOperation($operationId, (int)$op['store_id']);
            $res['is_done'] = true;
            $res['chunk_results'] = [];
            return $res;
        }

        // Set status to processing if pending
        if ($op['status'] === 'pending') {
            $this->pdo->prepare("
                UPDATE bulk_operations
                SET status = 'processing', started_at = NOW(), updated_at = NOW()
                WHERE id = :id AND status = 'pending'
            ")->execute(['id' => $operationId]);
            $op['status'] = 'processing';
        }

        $storeId = (int)$op['store_id'];
        $userId = (int)$op['user_id'];
        $store = $this->storeRepo->findById($storeId);
        if (!$store) {
            $this->markOperationStatus($operationId, 'failed', ['error' => 'فروشگاه نامعتبر یا حذف شده است']);
            return $this->getOperation($operationId, $storeId);
        }

        $targetEntity = $op['target_entity'];
        $handler = $this->getHandler($targetEntity);

        $payload = is_string($op['payload']) ? json_decode($op['payload'], true) : $op['payload'];
        $actionData = $payload['action'] ?? [];
        $actionType = $actionData['type'] ?? $op['type'];
        $actionParams = $actionData['params'] ?? [];
        $selection = $payload['selection'] ?? [];
        $filter = $payload['filter'] ?? ($selection['filter'] ?? []);

        $totalItems = (int)$op['total_items'];
        $processedTotal = (int)($op['processed_items'] ?? 0);
        $successTotal = (int)($op['success_items'] ?? 0);
        $failedTotal = (int)($op['failed_items'] ?? 0);
        $skippedTotal = (int)($op['skipped_items'] ?? 0);

        // Calculate page for this chunk
        $page = (int)floor($processedTotal / $chunkSize) + 1;

        // Fetch entities for this chunk
        $entities = $handler->resolveEntitiesBatch($store, $selection, $filter, $page, $chunkSize);

        $chunkResults = [];
        if (!empty($entities)) {
            $batchResults = $handler->executeBatch($store, $entities, $actionType, $actionParams);

            $itemInsertStmt = $this->pdo->prepare("
                INSERT INTO bulk_operation_items (
                    bulk_operation_id, entity_id, status, old_state, new_state, error_code, error_message, processed_at
                ) VALUES (
                    :op_id, :entity_id, :status, :old_state, :new_state, :error_code, :error_message, NOW()
                )
            ");

            foreach ($batchResults as $res) {
                $itemStatus = $res['status'];
                if ($itemStatus === 'completed') {
                    $successTotal++;
                } elseif ($itemStatus === 'skipped') {
                    $skippedTotal++;
                } else {
                    $failedTotal++;
                }
                $processedTotal++;

                $itemInsertStmt->execute([
                    'op_id' => $operationId,
                    'entity_id' => (int)$res['entity_id'],
                    'status' => $itemStatus,
                    'old_state' => isset($res['old_value']) ? json_encode($res['old_value'], JSON_UNESCAPED_UNICODE) : null,
                    'new_state' => isset($res['new_value']) ? json_encode($res['new_value'], JSON_UNESCAPED_UNICODE) : null,
                    'error_code' => $res['error_code'] ?? null,
                    'error_message' => $res['error_message'] ?? null,
                ]);

                $chunkResults[] = $res;
            }
        }

        $isDone = empty($entities) || ($processedTotal >= $totalItems);

        if ($isDone) {
            if ($failedTotal > 0 && $successTotal === 0 && $skippedTotal === 0) {
                $finalStatus = 'failed';
            } elseif ($failedTotal > 0 || $skippedTotal > 0) {
                $finalStatus = ($successTotal > 0) ? 'partial' : ($skippedTotal > 0 ? 'completed' : 'failed');
            } else {
                $finalStatus = 'completed';
            }

            $summaryData = [
                'total' => $totalItems,
                'processed' => $processedTotal,
                'succeeded' => $successTotal,
                'failed' => $failedTotal,
                'skipped' => $skippedTotal,
            ];

            $this->pdo->prepare("
                UPDATE bulk_operations
                SET status = :status,
                    processed_items = :processed,
                    success_items = :success,
                    failed_items = :failed,
                    skipped_items = :skipped,
                    completed_at = NOW(),
                    result_data = :result_data,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'status' => $finalStatus,
                'processed' => $processedTotal,
                'success' => $successTotal,
                'failed' => $failedTotal,
                'skipped' => $skippedTotal,
                'result_data' => json_encode($summaryData, JSON_UNESCAPED_UNICODE),
                'id' => $operationId,
            ]);

            // Audit
            $this->auditService->log(
                $userId,
                $storeId,
                'BULK_OPERATION_EXECUTED',
                'bulk_operation',
                (string)$operationId,
                null,
                [
                    'status' => $finalStatus,
                    'action' => $actionType,
                    'target_entity' => $targetEntity,
                    'summary' => $summaryData,
                ]
            );

            $this->recordStoreBulkActivity($storeId, $userId, $targetEntity, $actionType, $successTotal);
        } else {
            $this->pdo->prepare("
                UPDATE bulk_operations
                SET processed_items = :processed,
                    success_items = :success,
                    failed_items = :failed,
                    skipped_items = :skipped,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'processed' => $processedTotal,
                'success' => $successTotal,
                'failed' => $failedTotal,
                'skipped' => $skippedTotal,
                'id' => $operationId,
            ]);
        }

        $formattedOp = $this->getOperation($operationId, $storeId);
        $formattedOp['chunk_results'] = $chunkResults;
        $formattedOp['is_done'] = $isDone;
        return $formattedOp;
    }

    /**
     * Retry failed items from a previously executed bulk operation.
     */
    public function retryFailedItems(int $operationId, int $userId): array
    {
        $op = $this->getOperationByIdRaw($operationId);
        if (!$op) {
            throw new InvalidArgumentException("عملیات گروهی یافت نشد.");
        }

        $storeId = (int)$op['store_id'];

        // Get failed items
        $stmt = $this->pdo->prepare("
            SELECT entity_id FROM bulk_operation_items
            WHERE bulk_operation_id = :op_id AND status = 'failed'
        ");
        $stmt->execute(['op_id' => $operationId]);
        $failedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($failedIds)) {
            throw new InvalidArgumentException("هیچ آیتم ناموفقی در این عملیات برای تلاش مجدد وجود ندارد.");
        }

        $payload = is_string($op['payload']) ? json_decode($op['payload'], true) : $op['payload'];
        $actionData = $payload['action'] ?? [];

        // Build new requestData targeting only failed IDs
        $newRequestData = [
            'entity' => $op['target_entity'],
            'action' => $actionData,
            'selection' => [
                'type' => 'ids',
                'ids' => array_values(array_unique(array_map('intval', $failedIds))),
            ],
            'filter' => [],
        ];

        return $this->createOperation($storeId, $userId, $newRequestData);
    }

    /**
     * Presets management: List presets for store
     */
    public function listPresets(int $storeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.username, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_display_name
            FROM bulk_operation_presets p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.store_id = :store_id
            ORDER BY p.id DESC
        ");
        $stmt->execute(['store_id' => $storeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'store_id' => (int)$r['store_id'],
                'user_id' => (int)$r['user_id'],
                'user_name' => $r['user_display_name'] ?? $r['username'],
                'title' => $r['title'],
                'description' => $r['description'],
                'target_entity' => $r['target_entity'],
                'filter_criteria' => is_string($r['filter_criteria']) ? json_decode($r['filter_criteria'], true) : $r['filter_criteria'],
                'action_data' => is_string($r['action_data']) ? json_decode($r['action_data'], true) : $r['action_data'],
                'created_at' => $r['created_at'],
                'updated_at' => $r['updated_at'],
            ];
        }, $rows);
    }

    /**
     * Create a new preset
     */
    public function createPreset(int $storeId, int $userId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if (empty($title)) {
            throw new InvalidArgumentException("عنوان الگو الزامی است.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO bulk_operation_presets (store_id, user_id, title, description, target_entity, filter_criteria, action_data, created_at, updated_at)
            VALUES (:store_id, :user_id, :title, :description, :target_entity, :filter_criteria, :action_data, NOW(), NOW())
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'target_entity' => $data['target_entity'] ?? 'products',
            'filter_criteria' => json_encode($data['filter_criteria'] ?? [], JSON_UNESCAPED_UNICODE),
            'action_data' => json_encode($data['action_data'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        $presetId = (int)$this->pdo->lastInsertId();

        return [
            'id' => $presetId,
            'store_id' => $storeId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'target_entity' => $data['target_entity'] ?? 'products',
            'filter_criteria' => $data['filter_criteria'] ?? [],
            'action_data' => $data['action_data'] ?? [],
        ];
    }

    /**
     * Delete a preset
     */
    public function deletePreset(int $presetId, int $storeId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM bulk_operation_presets WHERE id = :id AND store_id = :store_id");
        $stmt->execute(['id' => $presetId, 'store_id' => $storeId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retrieve operation details with store scoping and items summary.
     */
    public function getOperation(int $operationId, int $storeId): ?array
    {
        $this->detectAndMarkStaleOperations();

        $stmt = $this->pdo->prepare("
            SELECT bo.*,
                   u.username,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_display_name,
                   s.name AS store_name
            FROM bulk_operations bo
            LEFT JOIN users u ON bo.user_id = u.id
            LEFT JOIN stores s ON bo.store_id = s.id
            WHERE bo.id = :id AND bo.store_id = :store_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $operationId, 'store_id' => $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->formatOperationRecord($row, true);
    }

    /**
     * List operations for a store with filtering and pagination.
     */
    public function listOperations(int $storeId, array $filters = []): array
    {
        $this->detectAndMarkStaleOperations();

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $where = ['bo.store_id = :store_id'];
        $params = ['store_id' => $storeId];

        if (!empty($filters['entity']) && $filters['entity'] !== 'all') {
            $where[] = 'bo.target_entity = :entity';
            $params['entity'] = $this->getTargetEntityDbEnum($filters['entity']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'bo.status = :status';
            $params['status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM bulk_operations bo WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT bo.*,
                   u.username,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_display_name,
                   s.name AS store_name
            FROM bulk_operations bo
            LEFT JOIN users u ON bo.user_id = u.id
            LEFT JOIN stores s ON bo.store_id = s.id
            WHERE {$whereSql}
            ORDER BY bo.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $formatted = array_map(fn($r) => $this->formatOperationRecord($r, false), $rows);

        return [
            'data' => $formatted,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Stale operations detector: marks operations stuck in 'processing' for > 15 minutes as stale/interrupted.
     */
    public function detectAndMarkStaleOperations(int $timeoutMinutes = 15): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE bulk_operations
            SET status = 'failed',
                completed_at = NOW(),
                result_data = JSON_OBJECT('error', 'عملیات به دلیل انقضای زمان پاسخ متوقف شد (Stale Operation)'),
                updated_at = NOW()
            WHERE status = 'processing'
              AND updated_at < (NOW() - INTERVAL :minutes MINUTE)
        ");
        $stmt->execute(['minutes' => $timeoutMinutes]);
        return $stmt->rowCount();
    }

    private function getOperationByIdRaw(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bulk_operations WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function formatOperationRecord(array $row, bool $includeItems = false): array
    {
        $total = (int)($row['total_items'] ?? 0);
        $processed = (int)($row['processed_items'] ?? 0);
        $percent = ($total > 0) ? min(100, (int)round(($processed / $total) * 100)) : 0;

        $payload = !empty($row['payload']) && is_string($row['payload'])
            ? json_decode($row['payload'], true)
            : ($row['payload'] ?? []);

        $filterCriteria = !empty($row['filter_criteria']) && is_string($row['filter_criteria'])
            ? json_decode($row['filter_criteria'], true)
            : ($row['filter_criteria'] ?? []);

        $resultData = !empty($row['result_data']) && is_string($row['result_data'])
            ? json_decode($row['result_data'], true)
            : ($row['result_data'] ?? null);

        $out = [
            'id' => (int)$row['id'],
            'store_id' => (int)$row['store_id'],
            'store_name' => $row['store_name'] ?? '',
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_display_name'] ?? ($row['username'] ?? 'مدیر'),
            'type' => $row['type'] ?? '',
            'action_type' => $row['action_type'] ?? ($row['type'] ?? ''),
            'target_entity' => $row['target_entity'] ?? '',
            'entity_type' => $this->normalizeEntityType($row['target_entity'] ?? ''),
            'status' => $row['status'] ?? 'pending',
            'total_items' => $total,
            'processed_items' => $processed,
            'success_items' => (int)($row['success_items'] ?? 0),
            'failed_items' => (int)($row['failed_items'] ?? 0),
            'skipped_items' => (int)($row['skipped_items'] ?? 0),
            'total' => $total,
            'processed' => $processed,
            'succeeded' => (int)($row['success_items'] ?? 0),
            'succeeded_count' => (int)($row['success_items'] ?? 0),
            'failed' => (int)($row['failed_items'] ?? 0),
            'failed_count' => (int)($row['failed_items'] ?? 0),
            'skipped' => (int)($row['skipped_items'] ?? 0),
            'skipped_count' => (int)($row['skipped_items'] ?? 0),
            'affected_count' => $total,
            'percent' => $percent,
            'filter_criteria' => $filterCriteria,
            'payload' => $payload,
            'result_data' => $resultData,
            'started_at' => $row['started_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if ($includeItems) {
            // Load items (failures, skipped, and recent successes)
            $itemsStmt = $this->pdo->prepare("
                SELECT * FROM bulk_operation_items
                WHERE bulk_operation_id = :op_id
                ORDER BY (status = 'failed') DESC, (status = 'skipped') DESC, id ASC
                LIMIT 100
            ");
            $itemsStmt->execute(['op_id' => (int)$row['id']]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $out['items'] = array_map(function ($it) {
                return [
                    'id' => (int)$it['id'],
                    'entity_id' => (int)$it['entity_id'],
                    'status' => $it['status'],
                    'old_value' => !empty($it['old_state']) && is_string($it['old_state']) ? json_decode($it['old_state'], true) : $it['old_state'],
                    'new_value' => !empty($it['new_state']) && is_string($it['new_state']) ? json_decode($it['new_state'], true) : $it['new_state'],
                    'error_code' => $it['error_code'] ?? null,
                    'error_message' => $it['error_message'] ?? null,
                    'processed_at' => $it['processed_at'] ?? null,
                ];
            }, $items);
        }

        return $out;
    }

    private function recordStoreBulkActivity(int $storeId, int $userId, string $entity, string $actionType, int $count): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activities (store_id, user_id, action_type, entity_type, entity_id, details, created_at)
                VALUES (:store_id, :user_id, :action_type, :entity_type, 0, :details, NOW())
            ");
            $stmt->execute([
                'store_id' => $storeId,
                'user_id' => $userId,
                'action_type' => "bulk_{$actionType}",
                'entity_type' => $entity,
                'details' => json_encode([
                    'action' => $actionType,
                    'count' => $count,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            // Activity log non-fatal
        }
    }

    public function evaluateTarget(int $storeId, int $userId, array $requestData): array
    {
        $store = $this->storeRepo->findById($storeId);
        if (!$store) {
            throw new InvalidArgumentException("فروشگاه مورد نظر یافت نشد.");
        }

        $entityType = $requestData['entity'] ?? ($requestData['target_entity'] ?? 'products');
        $handler = $this->getHandler($entityType);

        $selection = $requestData['selection'] ?? [];
        $filter = $requestData['filter'] ?? ($selection['filter'] ?? []);
        $targetScope = $requestData['target_scope'] ?? ($requestData['target'] ?? 'parent');
        if (!in_array($targetScope, ['parent', 'variations', 'both'], true)) {
            $targetScope = 'parent';
        }

        $parentCount = $handler->resolveCount($store, $selection, $filter);
        $sampleEntities = $handler->resolveSample($store, $selection, $filter, 15);

        $sampleCount = count($sampleEntities);
        $variableCountInSample = 0;
        $sampleVariationCount = 0;
        $adapter = new \App\Integrations\WooCommerce\ProductAdapter($store);

        $sampleList = [];
        foreach ($sampleEntities as $prod) {
            $isVariable = ($prod['type'] ?? '') === 'variable' || !empty($prod['variations']);
            if ($isVariable) {
                $variableCountInSample++;
            }

            $catNames = array_map(fn($c) => $c['name'] ?? '', $prod['categories'] ?? []);

            $sampleList[] = [
                'id' => (int)$prod['id'],
                'name' => (string)($prod['name'] ?? "محصول #{$prod['id']}"),
                'type' => $prod['type'] ?? 'simple',
                'sku' => (string)($prod['sku'] ?? ''),
                'regular_price' => $prod['regular_price'] ?? '',
                'sale_price' => $prod['sale_price'] ?? '',
                'price' => $prod['price'] ?? '',
                'stock_quantity' => $prod['stock_quantity'] ?? null,
                'stock_status' => $prod['stock_status'] ?? 'instock',
                'categories' => $catNames,
                'is_variable' => $isVariable,
            ];

            if (($targetScope === 'variations' || $targetScope === 'both') && $isVariable) {
                try {
                    $varsRes = $adapter->listVariations((int)$prod['id']);
                    $vars = $varsRes['data'] ?? (is_array($varsRes) ? $varsRes : []);
                    $sampleVariationCount += count($vars);
                } catch (\Exception $e) {
                    // Non-blocking
                }
            }
        }

        $estimatedVariationsCount = $sampleVariationCount;
        if ($parentCount > $sampleCount && $sampleCount > 0) {
            $estimatedVariationsCount = (int)round(($sampleVariationCount / $sampleCount) * $parentCount);
        }

        $estimatedVariableCount = $sampleCount > 0 ? (int)round(($variableCountInSample / $sampleCount) * $parentCount) : 0;
        $estimatedSimpleCount = max(0, $parentCount - $estimatedVariableCount);

        if (($filter['type'] ?? '') === 'simple') {
            $estimatedSimpleCount = $parentCount;
            $estimatedVariableCount = 0;
            $estimatedVariationsCount = 0;
        } elseif (($filter['type'] ?? '') === 'variable') {
            $estimatedSimpleCount = 0;
            $estimatedVariableCount = $parentCount;
        }

        $finalAffectedCount = match ($targetScope) {
            'variations' => max($sampleVariationCount, $estimatedVariationsCount),
            'both' => $parentCount + max($sampleVariationCount, $estimatedVariationsCount),
            default => $parentCount,
        };

        return [
            'entity' => $handler->getEntityType(),
            'total_parents' => $parentCount,
            'affected_count' => $finalAffectedCount,
            'simple_count' => $estimatedSimpleCount,
            'variable_count' => $estimatedVariableCount,
            'variation_count' => max($sampleVariationCount, $estimatedVariationsCount),
            'target_scope' => $targetScope,
            'sample_products' => $sampleList,
            'breakdown' => [
                'simple' => $estimatedSimpleCount,
                'variable' => $estimatedVariableCount,
                'variation' => max($sampleVariationCount, $estimatedVariationsCount),
                'total' => $finalAffectedCount,
            ],
        ];
    }
}

