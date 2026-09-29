<?php

namespace App\Controllers;

use App\Models\Store;
use App\Repositories\CustomerActivityRepository;
use App\Repositories\StoreRepository;
use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class ActivityController extends BaseController
{
    private CustomerActivityRepository $activityRepository;
    private StoreRepository $storeRepository;
    private RbacService $rbacService;

    public function __construct(
        ?CustomerActivityRepository $activityRepository = null,
        ?StoreRepository $storeRepository = null,
        ?RbacService $rbacService = null
    ) {
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->rbacService = $rbacService ?? new RbacService();
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
     * GET /api/v1/activities
     */
    public function index(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'activities.view');
            $store = $this->resolveStore($request);

            $filters = [
                'entity_type' => $request->query('entity_type', ''),
                'entity_id' => $request->query('entity_id', ''),
                'action_type' => $request->query('action_type', ''),
                'limit' => $request->query('limit', 50),
                'offset' => $request->query('offset', 0),
            ];

            $activities = $this->activityRepository->listStoreActivities($store->id, $filters);
            return $this->success($activities);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ACTIVITY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/activities/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'activities.view');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $pdo = \App\Database\Connection::get();
            $stmt = $pdo->prepare("
                SELECT a.*,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username) AS user_name,
                       u.email AS user_email
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.id = :id AND a.store_id = :store_id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'store_id' => $store->id]);
            $activity = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$activity) {
                return $this->error('NOT_FOUND', 'فعالیت مورد نظر یافت نشد.', [], 404);
            }

            if (!empty($activity['details']) && is_string($activity['details'])) {
                $activity['details'] = json_decode($activity['details'], true) ?: [];
            }

            return $this->success($activity);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ACTIVITY_ERROR', $e->getMessage(), [], $code);
        }
    }
}
