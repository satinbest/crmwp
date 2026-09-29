<?php

namespace App\Controllers;

use App\Repositories\SyncLogRepository;
use App\Services\WooCommerceReconciliationService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class ReconciliationController extends BaseController
{
    private WooCommerceReconciliationService $reconciliationService;
    private SyncLogRepository $syncLogRepository;

    public function __construct(
        ?WooCommerceReconciliationService $reconciliationService = null,
        ?SyncLogRepository $syncLogRepository = null
    ) {
        $this->reconciliationService = $reconciliationService ?? new WooCommerceReconciliationService();
        $this->syncLogRepository = $syncLogRepository ?? new SyncLogRepository();
    }

    /**
     * Trigger manual reconciliation for a store.
     * POST /api/v1/stores/{id}/reconcile
     */
    public function reconcile(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);

            $options = [
                'entity_type' => $request->input('entity_type', 'all'),
                'date_range'  => $request->input('date_range', '24h'),
                'after'       => $request->input('after'),
                'before'      => $request->input('before'),
                'batch_size'  => (int)$request->input('batch_size', 25),
            ];

            $result = $this->reconciliationService->reconcile($storeId, $options);

            return $this->success($result);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('RECONCILIATION_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * Get sync / reconciliation logs for a store.
     * GET /api/v1/stores/{id}/sync-logs
     */
    public function syncLogs(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);

            $filters = [
                'status' => $request->query('status', 'all'),
                'entity_type' => $request->query('entity_type'),
                'page' => max(1, (int)$request->query('page', 1)),
                'per_page' => max(1, min(100, (int)$request->query('per_page', 15))),
            ];

            $result = $this->syncLogRepository->list($storeId, $filters);

            return $this->success($result['data'], $result['meta']);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SYNC_LOGS_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * Get integration & webhook health status for a store.
     * GET /api/v1/stores/{id}/webhook-health
     */
    public function webhookHealth(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);

            $health = $this->reconciliationService->getIntegrationHealth($storeId);

            return $this->success($health);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('HEALTH_CHECK_FAILED', $e->getMessage(), [], $status);
        }
    }
}
