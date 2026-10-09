<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StoreRepository;
use App\Services\LocalSyncService;
use App\Support\Request;
use App\Support\Response;
use Exception;
use Throwable;

class SyncController extends BaseController
{
    private LocalSyncService $syncService;
    private StoreRepository $storeRepository;

    public function __construct(
        ?LocalSyncService $syncService = null,
        ?StoreRepository $storeRepository = null
    ) {
        $this->syncService = $syncService ?? new LocalSyncService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }

    /**
     * GET /api/v1/stores/{id}/sync/status
     * Retrieve local cache and sync status for a store.
     */
    public function status(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);

            $entityType = $request->query('entity', 'all');
            $state = $this->syncService->getSyncState($storeId, $entityType);

            return $this->success($state, [
                'store_id' => $storeId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SYNC_STATUS_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/stores/{id}/sync/start
     * Trigger manual synchronization of local cache from WooCommerce.
     */
    public function start(Request $request): Response
    {
        try {
            $storeId = (int)$request->param('id');
            $this->validateStoreAccess($request, $storeId);

            $entityType = $request->input('entity', 'all');
            $force = (bool)$request->input('force', false);

            $result = $this->syncService->sync($storeId, $entityType, $force);

            return $this->success($result, [
                'message' => 'همگام‌سازی با موفقیت انجام شد.',
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SYNC_START_FAILED', $e->getMessage(), [], $status);
        }
    }
}
