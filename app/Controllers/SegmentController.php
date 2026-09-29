<?php

namespace App\Controllers;

use App\Models\Store;
use App\Repositories\SegmentRepository;
use App\Repositories\StoreRepository;
use App\Services\AuditService;
use App\Services\CrmService;
use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class SegmentController extends BaseController
{
    private SegmentRepository $segmentRepository;
    private CrmService $crmService;
    private StoreRepository $storeRepository;
    private RbacService $rbacService;
    private AuditService $auditService;

    public function __construct(
        ?SegmentRepository $segmentRepository = null,
        ?CrmService $crmService = null,
        ?StoreRepository $storeRepository = null,
        ?RbacService $rbacService = null,
        ?AuditService $auditService = null
    ) {
        $this->segmentRepository = $segmentRepository ?? new SegmentRepository();
        $this->crmService = $crmService ?? new CrmService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->rbacService = $rbacService ?? new RbacService();
        $this->auditService = $auditService ?? new AuditService();
    }


    private function authorizePermission(Request $request, string $permission): int
    {
        $user = $this->currentUser($request);
        if (!$user) {
            throw new Exception("احراز هویت الزامی است.", 401);
        }

        $userId = (int)$user->id;
        if (!$this->rbacService->userHasPermission($userId, $permission)) {
            throw new Exception("شما مجوز دسترسی لازم ({$permission}) را ندارید.", 403);
        }

        return $userId;
    }

    /**
     * GET /api/v1/segments
     */
    public function index(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'segments.view');
            $store = $this->resolveStore($request);

            $filters = [
                'search' => $request->query('search', ''),
            ];

            $segments = $this->segmentRepository->listByStore($store->id, $filters);
            return $this->success($segments);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SEGMENT_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/segments
     */
    public function store(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'segments.create');
            $store = $this->resolveStore($request);

            $name = trim((string)$request->input('name', ''));
            if (empty($name)) {
                return $this->error('VALIDATION_ERROR', 'نام بخش‌بندی الزامی است.', [], 422);
            }

            $rules = $request->input('rules', ['combinator' => 'AND', 'rules' => []]);
            if (is_string($rules)) {
                $rules = json_decode($rules, true) ?: ['combinator' => 'AND', 'rules' => []];
            }

            $data = [
                'store_id' => $store->id,
                'name' => $name,
                'description' => $request->input('description'),
                'rules' => $rules,
            ];

            $segment = $this->segmentRepository->create($data);

            $this->auditService->log(
                $userId,
                $store->id,
                'segment.created',
                'segment',
                (string)$segment['id'],
                [],
                $segment
            );

            return $this->success($segment, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SEGMENT_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/segments/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'segments.view');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $segment = $this->segmentRepository->findById($id, $store->id);
            if (!$segment) {
                return $this->error('NOT_FOUND', 'بخش‌بندی مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($segment);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SEGMENT_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/segments/{id}
     */
    public function update(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'segments.update');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $existing = $this->segmentRepository->findById($id, $store->id);
            if (!$existing) {
                return $this->error('NOT_FOUND', 'بخش‌بندی مورد نظر یافت نشد.', [], 404);
            }

            $data = [];
            if ($request->has('name')) {
                $name = trim((string)$request->input('name'));
                if (empty($name)) {
                    return $this->error('VALIDATION_ERROR', 'نام بخش‌بندی نمی‌تواند خالی باشد.', [], 422);
                }
                $data['name'] = $name;
            }

            if ($request->has('description')) {
                $data['description'] = $request->input('description');
            }

            if ($request->has('rules')) {
                $rules = $request->input('rules');
                $data['rules'] = is_string($rules) ? json_decode($rules, true) : $rules;
            }

            $updated = $this->segmentRepository->update($id, $data, $store->id);

            $this->auditService->log(
                $userId,
                $store->id,
                'segment.updated',
                'segment',
                (string)$id,
                $existing,
                $updated
            );

            return $this->success($updated);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SEGMENT_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/segments/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'segments.delete');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $existing = $this->segmentRepository->findById($id, $store->id);
            if (!$existing) {
                return $this->error('NOT_FOUND', 'بخش‌بندی مورد نظر یافت نشد.', [], 404);
            }

            $this->segmentRepository->delete($id, $store->id);

            $this->auditService->log(
                $userId,
                $store->id,
                'segment.deleted',
                'segment',
                (string)$id,
                $existing,
                []
            );

            return $this->success(['message' => 'بخش‌بندی با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SEGMENT_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/segments/preview
     */
    public function preview(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'segments.view');
            $store = $this->resolveStore($request);

            $rules = $request->input('rules', ['combinator' => 'AND', 'rules' => []]);
            if (is_string($rules)) {
                $rules = json_decode($rules, true) ?: ['combinator' => 'AND', 'rules' => []];
            }

            $preview = $this->crmService->previewSegment($store->id, $rules);
            return $this->success($preview);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PREVIEW_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/segments/{id}/customers
     */
    public function customers(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'segments.view');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $segment = $this->segmentRepository->findById($id, $store->id);
            if (!$segment) {
                return $this->error('NOT_FOUND', 'بخش‌بندی مورد نظر یافت نشد.', [], 404);
            }

            $preview = $this->crmService->previewSegment($store->id, $segment['rules']);
            return $this->success($preview);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SEGMENT_CUSTOMERS_ERROR', $e->getMessage(), [], $code);
        }
    }
}
