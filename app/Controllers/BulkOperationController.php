<?php

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Services\Bulk\BulkOperationEngine;
use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;
use Exception;
use InvalidArgumentException;

class BulkOperationController extends BaseController
{
    private BulkOperationEngine $engine;
    private StoreRepository $storeRepo;
    private RbacService $rbacService;

    public function __construct(
        ?BulkOperationEngine $engine = null,
        ?StoreRepository $storeRepo = null,
        ?RbacService $rbacService = null
    ) {
        $this->engine = $engine ?? new BulkOperationEngine();
        $this->storeRepo = $storeRepo ?? new StoreRepository();
        $this->rbacService = $rbacService ?? new RbacService();
    }


    /**
     * Authorize user for a bulk action and target entity.
     */
    private function authorizeBulk(int $userId, string $operationPerm, string $entity): void
    {
        // 1. Check operation permission (e.g. bulk.preview, bulk.execute, bulk.cancel, bulk.view)
        if (!$this->rbacService->userHasPermission($userId, $operationPerm)) {
            // Allow bulk.view as fallback for bulk.preview if granted
            if ($operationPerm === 'bulk.preview' && $this->rbacService->userHasPermission($userId, 'bulk.view')) {
                // Allowed
            } else {
                throw new Exception("شما مجوز لازم برای این بخش از عملیات گروهی [{$operationPerm}] را ندارید.", 403);
            }
        }

        // 2. Check entity-specific bulk permission
        $normEntity = $this->engine->normalizeEntityType($entity);
        $entityPerm = match ($normEntity) {
            'products' => 'products.bulk',
            'orders' => 'orders.bulk',
            'customers' => 'customers.bulk',
            default => 'bulk.execute',
        };

        if (!$this->rbacService->userHasPermission($userId, $entityPerm)) {
            throw new Exception("شما مجوز لازم برای انجام عملیات گروهی روی این بخش [{$entityPerm}] را ندارید.", 403);
        }
    }

    /**
     * POST /api/v1/bulk-operations/preview
     */
    public function preview(Request $request): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            $storeId = $this->resolveStoreContext($request);
            $entity = $request->input('entity') ?? $request->input('target_entity') ?? '';

            if (empty($entity)) {
                return $this->error('VALIDATION_ERROR', 'مشخص کردن موجودیت هدف (entity) الزامی است.', [], 422);
            }

            $this->authorizeBulk($user->id, 'bulk.preview', $entity);

            $data = $request->all();
            $data['store_id'] = $storeId;

            $preview = $this->engine->preview($storeId, $user->id, $data);

            return $this->success($preview);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('BULK_PREVIEW_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/bulk-operations/evaluate-target
     */
    public function evaluateTarget(Request $request): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            $storeId = $this->resolveStoreContext($request);
            $entity = $request->input('entity') ?? $request->input('target_entity') ?? 'products';

            $this->authorizeBulk($user->id, 'bulk.view', $entity);

            $data = $request->all();
            $data['store_id'] = $storeId;

            $result = $this->engine->evaluateTarget($storeId, $user->id, $data);

            return $this->success($result);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TARGET_EVALUATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/bulk-operations
     * Create and execute bulk operation.
     */
    public function store(Request $request): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            $storeId = $this->resolveStoreContext($request);
            $entity = $request->input('entity') ?? $request->input('target_entity') ?? '';

            if (empty($entity)) {
                return $this->error('VALIDATION_ERROR', 'مشخص کردن موجودیت هدف (entity) الزامی است.', [], 422);
            }

            $this->authorizeBulk($user->id, 'bulk.execute', $entity);

            $data = $request->all();
            $data['store_id'] = $storeId;

            // 1. Create operation in DB with pending status (or return existing if idempotent duplicate)
            $operation = $this->engine->createOperation($storeId, $user->id, $data);
            if (!empty($operation['is_cached_idempotent'])) {
                unset($operation['is_cached_idempotent']);
                return $this->success($operation, [], 200);
            }
            $opId = (int)$operation['id'];

            // If request requests step-by-step or async creation
            if ($request->input('async') || $request->input('create_only')) {
                return $this->success($operation, [], 201);
            }

            // 2. Execute operation immediately (synchronous batch execution)
            $completedOp = $this->engine->executeOperation($opId);

            // Notify user of completion
            try {
                $notifService = new \App\Services\NotificationService();
                $notifService->notifyBulkOperationFinished(
                    (int)$user->id,
                    $opId,
                    $completedOp['action_type'] ?? ($completedOp['type'] ?? 'عملیات گروهی'),
                    $completedOp['status'] ?? 'completed',
                    (int)($completedOp['total_items'] ?? 0),
                    (int)($completedOp['processed_items'] ?? 0),
                    (int)($completedOp['failed_items'] ?? 0),
                    $storeId
                );
            } catch (\Throwable $te) {
                // Non-blocking
            }

            return $this->success($completedOp, [], 201);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('BULK_EXECUTION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/bulk-operations/{id}/chunk
     * Execute a single chunk/batch of an operation for real-time progress.
     */
    public function chunk(Request $request, array $params = []): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            $storeId = $this->resolveStoreContext($request);
            $id = (int)($request->param('id') ?? $params['id'] ?? $request->query('id') ?? 0);

            if ($id <= 0) {
                return $this->error('VALIDATION_ERROR', 'شناسه عملیات نامعتبر است.', [], 400);
            }

            $op = $this->engine->getOperation($id, $storeId);
            if (!$op) {
                return $this->error('NOT_FOUND', "عملیات گروهی با شناسه {$id} در این فروشگاه یافت نشد.", [], 404);
            }

            $this->authorizeBulk($user->id, 'bulk.execute', $op['entity_type'] ?? 'products');

            $chunkSize = max(5, min(100, (int)($request->input('chunk_size') ?? 25)));
            $updatedOp = $this->engine->executeChunk($id, $chunkSize);

            // If finished, send notification
            if (!empty($updatedOp['is_done'])) {
                try {
                    $notifService = new \App\Services\NotificationService();
                    $notifService->notifyBulkOperationFinished(
                        (int)$user->id,
                        $id,
                        $updatedOp['action_type'] ?? ($updatedOp['type'] ?? 'عملیات گروهی'),
                        $updatedOp['status'] ?? 'completed',
                        (int)($updatedOp['total_items'] ?? 0),
                        (int)($updatedOp['processed_items'] ?? 0),
                        (int)($updatedOp['failed_items'] ?? 0),
                        $storeId
                    );
                } catch (\Throwable $te) {
                    // Non-blocking
                }
            }

            return $this->success($updatedOp);
        } catch (InvalidArgumentException $e) {
            return $this->error('BAD_REQUEST', $e->getMessage(), [], 400);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('BULK_CHUNK_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/bulk-operations/{id}/retry-failed
     */
    public function retryFailed(Request $request, array $params = []): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            $storeId = $this->resolveStoreContext($request);
            $id = (int)($request->param('id') ?? $params['id'] ?? $request->query('id') ?? 0);

            if ($id <= 0) {
                return $this->error('VALIDATION_ERROR', 'شناسه عملیات نامعتبر است.', [], 400);
            }

            $op = $this->engine->getOperation($id, $storeId);
            if (!$op) {
                return $this->error('NOT_FOUND', "عملیات گروهی با شناسه {$id} در این فروشگاه یافت نشد.", [], 404);
            }

            $this->authorizeBulk($user->id, 'bulk.execute', $op['entity_type'] ?? 'products');

            $newOp = $this->engine->retryFailedItems($id, $user->id);

            return $this->success($newOp, ['message' => 'عملیات تلاش مجدد برای آیتم‌های ناموفق با موفقیت ایجاد شد.'], 201);
        } catch (InvalidArgumentException $e) {
            return $this->error('BAD_REQUEST', $e->getMessage(), [], 400);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('BULK_RETRY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/bulk-operations/presets
     */
    public function presets(Request $request): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            if (!$this->rbacService->userHasPermission($user->id, 'bulk.view')) {
                return $this->error('FORBIDDEN', 'شما مجوز مشاهده الگوها را ندارید.', [], 403);
            }

            $storeId = $this->resolveStoreContext($request);
            $list = $this->engine->listPresets($storeId);

            return $this->success($list);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PRESETS_LIST_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/bulk-operations/presets
     */
    public function createPreset(Request $request): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            if (!$this->rbacService->userHasPermission($user->id, 'bulk.execute')) {
                return $this->error('FORBIDDEN', 'شما مجوز ایجاد الگوهای عملیات گروهی را ندارید.', [], 403);
            }

            $storeId = $this->resolveStoreContext($request);
            $data = $request->all();

            $preset = $this->engine->createPreset($storeId, $user->id, $data);

            return $this->success($preset, ['message' => 'الگوی عملیات با موفقیت ذخیره گردید.'], 201);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PRESET_CREATE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/bulk-operations/presets/{id}
     */
    public function deletePreset(Request $request, array $params = []): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            if (!$this->rbacService->userHasPermission($user->id, 'bulk.execute')) {
                return $this->error('FORBIDDEN', 'شما مجوز حذف الگوهای عملیات گروهی را ندارید.', [], 403);
            }

            $storeId = $this->resolveStoreContext($request);
            $id = (int)($request->param('id') ?? $params['id'] ?? $request->query('id') ?? 0);

            if ($id <= 0) {
                return $this->error('VALIDATION_ERROR', 'شناسه الگو نامعتبر است.', [], 400);
            }

            $deleted = $this->engine->deletePreset($id, $storeId);
            if (!$deleted) {
                return $this->error('NOT_FOUND', 'الگوی مورد نظر یافت نشد.', [], 404);
            }

            return $this->success(['deleted' => true], ['message' => 'الگو با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PRESET_DELETE_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/bulk-operations
     * List operations for current store.
     */
    public function index(Request $request): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            if (!$this->rbacService->userHasPermission($user->id, 'bulk.view')) {
                return $this->error('FORBIDDEN', 'شما مجوز مشاهده عملیات‌های گروهی [bulk.view] را ندارید.', [], 403);
            }

            $storeId = $this->resolveStoreContext($request);
            $filters = $request->all();

            $result = $this->engine->listOperations($storeId, $filters);

            return $this->success($result['data'], $result['meta']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('BULK_LIST_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/bulk-operations/{id}
     * Get operation details by ID.
     */
    public function show(Request $request, array $params = []): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            if (!$this->rbacService->userHasPermission($user->id, 'bulk.view')) {
                return $this->error('FORBIDDEN', 'شما مجوز مشاهده عملیات‌های گروهی [bulk.view] را ندارید.', [], 403);
            }

            $storeId = $this->resolveStoreContext($request);
            $id = (int)($request->param('id') ?? $params['id'] ?? $request->query('id') ?? 0);

            if ($id <= 0) {
                return $this->error('VALIDATION_ERROR', 'شناسه عملیات نامعتبر است.', [], 400);
            }

            $op = $this->engine->getOperation($id, $storeId);
            if (!$op) {
                return $this->error('NOT_FOUND', "عملیات گروهی با شناسه {$id} در این فروشگاه یافت نشد.", [], 404);
            }

            return $this->success($op);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('BULK_SHOW_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/bulk-operations/{id}/cancel
     * Cancel an active or pending operation.
     */
    public function cancel(Request $request, array $params = []): Response
    {
        try {
            $user = $request->getUser();
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            if (!$this->rbacService->userHasPermission($user->id, 'bulk.cancel')) {
                return $this->error('FORBIDDEN', 'شما مجوز لغو عملیات‌های گروهی [bulk.cancel] را ندارید.', [], 403);
            }

            $storeId = $this->resolveStoreContext($request);
            $id = (int)($request->param('id') ?? $params['id'] ?? $request->query('id') ?? 0);

            if ($id <= 0) {
                return $this->error('VALIDATION_ERROR', 'شناسه عملیات نامعتبر است.', [], 400);
            }

            // Ensure operation belongs to store
            $op = $this->engine->getOperation($id, $storeId);
            if (!$op) {
                return $this->error('NOT_FOUND', "عملیات گروهی با شناسه {$id} در این فروشگاه یافت نشد.", [], 404);
            }

            $cancelResult = $this->engine->cancelOperation($id, $user->id);

            return $this->success($cancelResult['operation'], ['message' => $cancelResult['message']]);
        } catch (InvalidArgumentException $e) {
            return $this->error('BAD_REQUEST', $e->getMessage(), [], 400);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('BULK_CANCEL_ERROR', $e->getMessage(), [], $code);
        }
    }
}
