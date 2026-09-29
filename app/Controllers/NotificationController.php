<?php

namespace App\Controllers;

use App\Services\NotificationService;
use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class NotificationController extends BaseController
{
    private NotificationService $notificationService;
    private RbacService $rbacService;

    public function __construct(
        ?NotificationService $notificationService = null,
        ?RbacService $rbacService = null
    ) {
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->rbacService = $rbacService ?? new RbacService();
    }

    private function requireAuthUser(Request $request): object
    {
        $user = $this->currentUser($request);
        if (!$user) {
            throw new Exception("احراز هویت الزامی است.", 401);
        }
        return $user;
    }

    /**
     * GET /api/v1/notifications
     */
    public function index(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;

            // Resolve accessible stores for user (store isolation)
            $isAdmin = $this->rbacService->userHasRole($userId, 'admin');
            $allowedStoreIds = [];
            if (!$isAdmin) {
                $stores = $this->rbacService->getUserAccessibleStores($userId);
                $allowedStoreIds = array_column($stores, 'id');
            }

            // If user explicitly requests a specific store, validate access
            $requestedStoreId = $request->query('store_id') ?? $request->input('store_id') ?? $request->header('X-Store-Id');
            if ($requestedStoreId !== null && $requestedStoreId !== '') {
                $storeIdInt = (int)$requestedStoreId;
                if (!$isAdmin && !in_array($storeIdInt, $allowedStoreIds, true)) {
                    return $this->error('FORBIDDEN', 'شما مجوز دسترسی به این فروشگاه را ندارید.', [], 403);
                }
            }

            $status = $request->query('status') ?? $request->input('status') ?? $request->query('filter') ?? $request->input('filter');
            $type = $request->query('type') ?? $request->input('type');
            $priority = $request->query('priority') ?? $request->input('priority');

            $filters = [
                'status' => $status ?: null,
                'type' => $type ?: null,
                'priority' => $priority ?: null,
            ];
            if ($requestedStoreId !== null && $requestedStoreId !== '') {
                $filters['store_id'] = (int)$requestedStoreId;
            }

            $page = max(1, (int)($request->query('page') ?? $request->input('page', 1)));
            $perPage = max(1, min(100, (int)($request->query('per_page') ?? $request->input('per_page', 20))));

            $result = $this->notificationService->getUserNotifications(
                $userId,
                $filters,
                $page,
                $perPage,
                $allowedStoreIds
            );

            // Append unread_count to meta
            $unreadCount = $this->notificationService->getUnreadCount(
                $userId,
                $requestedStoreId ? (int)$requestedStoreId : null,
                $allowedStoreIds
            );
            $result['meta']['unread_count'] = $unreadCount;

            return $this->success($result['data'], $result['meta']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;

            $isAdmin = $this->rbacService->userHasRole($userId, 'admin');
            $allowedStoreIds = [];
            if (!$isAdmin) {
                $stores = $this->rbacService->getUserAccessibleStores($userId);
                $allowedStoreIds = array_column($stores, 'id');
            }

            $requestedStoreId = $request->query('store_id') ?? $request->input('store_id') ?? $request->header('X-Store-Id');
            if ($requestedStoreId !== null && $requestedStoreId !== '') {
                $storeIdInt = (int)$requestedStoreId;
                if (!$isAdmin && !in_array($storeIdInt, $allowedStoreIds, true)) {
                    return $this->error('FORBIDDEN', 'شما مجوز دسترسی به این فروشگاه را ندارید.', [], 403);
                }
            }

            $count = $this->notificationService->getUnreadCount(
                $userId,
                $requestedStoreId ? (int)$requestedStoreId : null,
                $allowedStoreIds
            );

            return $this->success(['unread_count' => $count]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;
            $notificationId = (int)$request->param('id');

            $notification = $this->notificationService->find($notificationId);
            if (!$notification) {
                return $this->error('NOTIFICATION_NOT_FOUND', 'اعلان مورد نظر یافت نشد.', [], 404);
            }

            // User isolation: cannot access other users' notifications
            if ((int)$notification['user_id'] !== $userId) {
                return $this->error('FORBIDDEN', 'شما مجوز دسترسی به این اعلان را ندارید.', [], 403);
            }

            $this->notificationService->markAsRead($notificationId, $userId);

            return $this->success([
                'id' => $notificationId,
                'is_read' => true,
                'read_at' => date('Y-m-d H:i:s'),
            ], ['message' => 'اعلان به عنوان خوانده‌شده علامت‌گذاری شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/notifications/read-all
     */
    public function readAll(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;

            $isAdmin = $this->rbacService->userHasRole($userId, 'admin');
            $allowedStoreIds = [];
            if (!$isAdmin) {
                $stores = $this->rbacService->getUserAccessibleStores($userId);
                $allowedStoreIds = array_column($stores, 'id');
            }

            $requestedStoreId = $request->query('store_id') ?? $request->input('store_id') ?? $request->header('X-Store-Id');
            if ($requestedStoreId !== null && $requestedStoreId !== '') {
                $storeIdInt = (int)$requestedStoreId;
                if (!$isAdmin && !in_array($storeIdInt, $allowedStoreIds, true)) {
                    return $this->error('FORBIDDEN', 'شما مجوز دسترسی به این فروشگاه را ندارید.', [], 403);
                }
            }

            $count = $this->notificationService->markAllAsRead(
                $userId,
                $requestedStoreId ? (int)$requestedStoreId : null,
                $allowedStoreIds
            );

            return $this->success([
                'marked_count' => $count,
            ], ['message' => 'تمام اعلان‌ها به عنوان خوانده‌شده علامت‌گذاری شدند.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/notifications/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;
            $notificationId = (int)$request->param('id');

            $notification = $this->notificationService->find($notificationId);
            if (!$notification) {
                return $this->error('NOTIFICATION_NOT_FOUND', 'اعلان مورد نظر یافت نشد.', [], 404);
            }

            // User isolation
            if ((int)$notification['user_id'] !== $userId) {
                return $this->error('FORBIDDEN', 'شما مجوز حذف این اعلان را ندارید.', [], 403);
            }

            $this->notificationService->delete($notificationId, $userId);

            return $this->success([
                'id' => $notificationId,
                'deleted' => true,
            ], ['message' => 'اعلان با موفقیت حذف گردید.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/notifications/preferences
     */
    public function getPreferences(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;

            $prefs = $this->notificationService->getPreferences($userId);
            return $this->success($prefs);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PUT /api/v1/notifications/preferences
     */
    public function updatePreferences(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;

            $data = $request->all();
            $updated = $this->notificationService->updatePreferences($userId, $data);

            return $this->success($updated, ['message' => 'تنظیمات اعلان‌ها با موفقیت ذخیره شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/notifications/test-generate
     * Generate demo notification (for development & testing)
     */
    public function testGenerate(Request $request): Response
    {
        try {
            $user = $this->requireAuthUser($request);
            $userId = (int)$user->id;

            $type = $request->input('type', 'system');
            $title = $request->input('title', 'اعلان آزمایشی جدید');
            $message = $request->input('message', 'این یک پیام تستی برای بررسی سیستم اعلان‌هاست.');
            $priority = $request->input('priority', 'normal');
            $actionUrl = $request->input('action_url', '/dashboard');
            $storeId = $request->input('store_id');

            $id = $this->notificationService->createForUser(
                $userId,
                $type,
                $title,
                $message,
                ['test' => true, 'timestamp' => time()],
                $storeId ? (int)$storeId : null,
                $actionUrl,
                $priority
            );

            return $this->success([
                'id' => $id,
                'created' => $id !== null,
            ], ['message' => 'اعلان آزمایشی با موفقیت ایجاد شد.'], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTIFICATION_ERROR', $e->getMessage(), [], $code);
        }
    }
}
