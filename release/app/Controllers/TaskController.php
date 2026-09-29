<?php

namespace App\Controllers;

use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Services\RbacService;
use App\Services\TaskService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class TaskController extends BaseController
{
    private TaskService $taskService;
    private StoreRepository $storeRepository;
    private RbacService $rbacService;

    public function __construct(
        ?TaskService $taskService = null,
        ?StoreRepository $storeRepository = null,
        ?RbacService $rbacService = null
    ) {
        $this->taskService = $taskService ?? new TaskService();
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
     * GET /api/v1/tasks
     */
    public function index(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tasks.view');
            $store = $this->resolveStore($request);

            $filters = [
                'view' => $request->query('view', 'all'),
                'status' => $request->query('status', ''),
                'priority' => $request->query('priority', ''),
                'assigned_to' => $request->query('assigned_to', ''),
                'customer_id' => $request->query('customer_id', ''),
                'order_id' => $request->query('order_id', ''),
                'search' => $request->query('search', ''),
                'limit' => $request->query('limit', 50),
                'offset' => $request->query('offset', 0),
            ];

            $tasks = $this->taskService->listTasks($store->id, $filters, $userId);
            return $this->success($tasks);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASK_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/tasks
     */
    public function store(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tasks.create');
            $store = $this->resolveStore($request);

            $data = $request->all();
            if (empty($data['title'])) {
                return $this->error('VALIDATION_ERROR', 'عنوان وظیفه الزامی است.', [], 422);
            }

            // If assigning to another user, check tasks.assign permission
            if (!empty($data['assigned_to']) && (int)$data['assigned_to'] !== $userId) {
                if (!$this->rbacService->userHasPermission($userId, 'tasks.assign')) {
                    return $this->error('FORBIDDEN', 'شما مجوز ارجاع وظیفه به دیگران را ندارید.', [], 403);
                }
            }

            $task = $this->taskService->createTask($store->id, $data, $userId);

            // Notify assigned user if different from creator
            try {
                if (!empty($task['assigned_user_id']) && (int)$task['assigned_user_id'] !== $userId) {
                    $notifService = new \App\Services\NotificationService();
                    $notifService->notifyTaskAssigned(
                        (int)$task['assigned_user_id'],
                        (int)$task['id'],
                        $task['title'] ?? 'وظیفه جدید',
                        (int)$store->id,
                        !empty($task['wc_order_id']) ? (int)$task['wc_order_id'] : null,
                        !empty($task['wc_customer_id']) ? (int)$task['wc_customer_id'] : null
                    );
                }
            } catch (\Throwable $te) {
                // Non-blocking
            }

            return $this->success($task, [], 201);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASK_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/tasks/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'tasks.view');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $task = $this->taskService->getTask($id, $store->id);
            if (!$task) {
                return $this->error('NOT_FOUND', 'وظیفه مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($task);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASK_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/tasks/{id}
     */
    public function update(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tasks.update');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $data = $request->all();

            // Check assign permission if reassigning
            if (isset($data['assigned_to']) || isset($data['assigned_user_id'])) {
                $targetUser = $data['assigned_to'] ?? $data['assigned_user_id'];
                if ($targetUser && (int)$targetUser !== $userId) {
                    if (!$this->rbacService->userHasPermission($userId, 'tasks.assign')) {
                        return $this->error('FORBIDDEN', 'شما مجوز ارجاع وظیفه را ندارید.', [], 403);
                    }
                }
            }

            $task = $this->taskService->updateTask($id, $store->id, $data, $userId);
            if (!$task) {
                return $this->error('NOT_FOUND', 'وظیفه مورد نظر یافت نشد.', [], 404);
            }

            // Notify newly assigned user if reassigned
            if (isset($data['assigned_to']) || isset($data['assigned_user_id'])) {
                $targetUser = (int)($data['assigned_to'] ?? $data['assigned_user_id']);
                if ($targetUser && $targetUser !== $userId) {
                    try {
                        $notifService = new \App\Services\NotificationService();
                        $notifService->notifyTaskAssigned(
                            $targetUser,
                            (int)$task['id'],
                            $task['title'] ?? 'وظیفه پیگیری',
                            (int)$store->id,
                            !empty($task['wc_order_id']) ? (int)$task['wc_order_id'] : null,
                            !empty($task['wc_customer_id']) ? (int)$task['wc_customer_id'] : null
                        );
                    } catch (\Throwable $te) {}
                }
            }

            return $this->success($task);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASK_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/tasks/{id}/complete
     */
    public function complete(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tasks.update');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $task = $this->taskService->completeTask($id, $store->id, $userId);
            if (!$task) {
                return $this->error('NOT_FOUND', 'وظیفه مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($task);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASK_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * POST /api/v1/tasks/{id}/reopen
     */
    public function reopen(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tasks.update');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $task = $this->taskService->reopenTask($id, $store->id, $userId);
            if (!$task) {
                return $this->error('NOT_FOUND', 'وظیفه مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($task);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASK_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * DELETE /api/v1/tasks/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'tasks.delete');
            $store = $this->resolveStore($request);
            $id = (int)$request->getRouteParam('id');

            $deleted = $this->taskService->deleteTask($id, $store->id, $userId);
            if (!$deleted) {
                return $this->error('NOT_FOUND', 'وظیفه مورد نظر یافت نشد.', [], 404);
            }

            return $this->success(['message' => 'وظیفه با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASK_ERROR', $e->getMessage(), [], $code);
        }
    }
}
