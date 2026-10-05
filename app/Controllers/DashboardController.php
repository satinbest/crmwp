<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DashboardService;
use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class DashboardController extends BaseController
{
    private DashboardService $dashboardService;
    private RbacService $rbacService;

    public function __construct(
        ?DashboardService $dashboardService = null,
        ?RbacService $rbacService = null
    ) {
        $this->dashboardService = $dashboardService ?? new DashboardService();
        $this->rbacService = $rbacService ?? new RbacService();
    }

    /**
     * GET /api/v1/dashboard/stats
     */
    public function stats(Request $request): Response
    {
        try {
            $user = $this->currentUser($request);
            if (!$user) {
                return $this->error('UNAUTHENTICATED', 'احراز هویت الزامی است.', [], 401);
            }

            // Check permissions: either dashboard.view or reports.view or admin/manager role
            $userId = (int)$user->id;
            if (!$this->rbacService->userHasPermission($userId, 'dashboard.view') &&
                !$this->rbacService->userHasPermission($userId, 'reports.view') &&
                !$this->rbacService->userHasRole($userId, 'Admin') &&
                !$this->rbacService->userHasRole($userId, 'Manager')
            ) {
                return $this->error('FORBIDDEN', 'شما مجوز مشاهده پیشخوان را ندارید.', [], 403);
            }

            $storeId = $this->resolveStoreId($request, true);
            $this->validateStoreAccess($request, $storeId);

            $period = (string)$request->query('period', 'last_30_days');
            $validPeriods = ['today', 'last_7_days', 'last_30_days', 'last_90_days', 'custom'];
            if (!in_array($period, $validPeriods, true)) {
                $period = 'last_30_days';
            }

            $after = $request->query('after') ? trim((string)$request->query('after')) : null;
            $before = $request->query('before') ? trim((string)$request->query('before')) : null;
            $refresh = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);

            $data = $this->dashboardService->getOverview($storeId, $period, $after, $before, $refresh);

            return $this->success($data);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('DASHBOARD_ERROR', $e->getMessage(), [], $code);
        }
    }
}
