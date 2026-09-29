<?php

namespace App\Controllers;

use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Repositories\StoreRepository;
use App\Services\CustomerService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class CustomerController extends BaseController
{
    private CustomerService $customerService;
    private StoreRepository $storeRepository;

    public function __construct(
        ?CustomerService $customerService = null,
        ?StoreRepository $storeRepository = null
    ) {
        $this->customerService = $customerService ?? new CustomerService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }


    /**
     * GET /api/v1/customers
     */
    public function index(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $allowedSort = ['id', 'name', 'registered_date', 'orders_count', 'total_spent', 'email', 'date'];
            $sort = in_array($request->query('sort'), $allowedSort, true) ? $request->query('sort') : 'id';
            $direction = strtolower((string)$request->query('direction')) === 'asc' ? 'asc' : 'desc';

            $params = [
                'page' => max(1, (int)$request->query('page', 1)),
                'per_page' => max(1, min(100, (int)$request->query('per_page', 15))),
                'search' => mb_substr(trim((string)$request->query('search', '')), 0, 100),
                'sort' => $sort,
                'direction' => $direction,
                'role' => $request->query('role', 'all'),
            ];

            $result = $this->customerService->listCustomers($storeId, $params);

            return $this->success($result['data'], array_merge($result['meta'], [
                'store_id' => $storeId,
            ]));
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('CUSTOMER_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/customers/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');

            if ($customerId <= 0) {
                return $this->error('INVALID_CUSTOMER_ID', 'شناسه مشتری نامعتبر است.', [], 400);
            }

            $customer = $this->customerService->getCustomer($storeId, $customerId);

            if (!$customer) {
                return $this->error('CUSTOMER_NOT_FOUND', 'مشتری مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($customer, [
                'store_id' => $storeId,
            ]);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('CUSTOMER_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/customers/{id}/orders
     */
    public function orders(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');

            if ($customerId <= 0) {
                return $this->error('INVALID_CUSTOMER_ID', 'شناسه مشتری نامعتبر است.', [], 400);
            }

            $params = [
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 10),
            ];

            $orders = $this->customerService->getCustomerOrders($storeId, $customerId, $params);

            return $this->success($orders['data'], array_merge($orders['meta'], [
                'store_id' => $storeId,
                'customer_id' => $customerId,
            ]));
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ORDERS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/customers/{id}/activities
     */
    public function activities(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');

            $limit = min(100, max(1, (int)$request->query('limit', 50)));
            $activities = $this->customerService->listActivities($storeId, $customerId, $limit);

            return $this->success($activities, [
                'total' => count($activities),
                'store_id' => $storeId,
                'customer_id' => $customerId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ACTIVITIES_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/customers/{id}/notes
     */
    public function notes(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');

            $notes = $this->customerService->listNotes($storeId, $customerId);

            return $this->success($notes, [
                'total' => count($notes),
                'store_id' => $storeId,
                'customer_id' => $customerId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('NOTES_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/customers/{id}/notes
     */
    public function createNote(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $content = (string)$request->input('content', '');
            if (trim($content) === '') {
                return $this->error('VALIDATION_ERROR', 'متن یادداشت الزامی است.', ['content' => 'محتوا نمی‌تواند خالی باشد.'], 422);
            }

            $note = $this->customerService->createNote(
                $storeId,
                $customerId,
                $userId,
                $content,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($note, ['message' => 'یادداشت با موفقیت ثبت شد.'], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('NOTE_CREATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * PATCH /api/v1/customers/{id}/notes/{noteId}
     */
    public function updateNote(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');
            $noteId = (int)$request->param('noteId');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $content = (string)$request->input('content', '');
            if (trim($content) === '') {
                return $this->error('VALIDATION_ERROR', 'متن یادداشت الزامی است.', ['content' => 'محتوا نمی‌تواند خالی باشد.'], 422);
            }

            $updated = $this->customerService->updateNote(
                $storeId,
                $customerId,
                $noteId,
                $userId,
                $content,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($updated, ['message' => 'یادداشت با موفقیت ویرایش شد.']);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('NOTE_UPDATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * DELETE /api/v1/customers/{id}/notes/{noteId}
     */
    public function deleteNote(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');
            $noteId = (int)$request->param('noteId');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $this->customerService->deleteNote(
                $storeId,
                $customerId,
                $noteId,
                $userId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success(null, ['message' => 'یادداشت با موفقیت حذف شد.']);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('NOTE_DELETE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/customers/{id}/tags
     */
    public function tags(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');

            $tags = $this->customerService->listTags($storeId, $customerId);

            return $this->success($tags, [
                'total' => count($tags),
                'store_id' => $storeId,
                'customer_id' => $customerId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAGS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/customers/{id}/tags
     */
    public function addTag(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $name = (string)$request->input('name', '');
            $color = (string)$request->input('color', '#4F46E5');

            if (trim($name) === '') {
                return $this->error('VALIDATION_ERROR', 'نام برچسب الزامی است.', ['name' => 'نام برچسب نمی‌تواند خالی باشد.'], 422);
            }

            $tag = $this->customerService->addTag(
                $storeId,
                $customerId,
                $userId,
                $name,
                $color,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($tag, ['message' => 'برچسب با موفقیت به مشتری اضافه شد.'], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('TAG_ADD_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * DELETE /api/v1/customers/{id}/tags/{tagId}
     */
    public function removeTag(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');
            $tagId = (int)$request->param('tagId');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $this->customerService->removeTag(
                $storeId,
                $customerId,
                $tagId,
                $userId,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success(null, ['message' => 'برچسب با موفقیت از مشتری حذف شد.']);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('TAG_REMOVE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/customers/{id}/tasks
     */
    public function tasks(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');

            $tasks = $this->customerService->listTasks($storeId, $customerId);

            return $this->success($tasks, [
                'total' => count($tasks),
                'store_id' => $storeId,
                'customer_id' => $customerId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASKS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/customers/{id}/tasks
     */
    public function createTask(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $title = (string)$request->input('title', '');
            if (trim($title) === '') {
                return $this->error('VALIDATION_ERROR', 'عنوان وظیفه الزامی است.', ['title' => 'عنوان وظیفه نمی‌تواند خالی باشد.'], 422);
            }

            $task = $this->customerService->createTask(
                $storeId,
                $customerId,
                $userId,
                $request->all(),
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($task, ['message' => 'وظیفه با موفقیت ایجاد شد.'], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('TASK_CREATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * PATCH /api/v1/customers/{id}/tasks/{taskId}
     */
    public function updateTask(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $customerId = (int)$request->param('id');
            $taskId = (int)$request->param('taskId');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $updated = $this->customerService->updateTask(
                $storeId,
                $customerId,
                $taskId,
                $userId,
                $request->all(),
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($updated, ['message' => 'وضعیت وظیفه با موفقیت به‌روزرسانی شد.']);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('TASK_UPDATE_FAILED', $e->getMessage(), [], $status);
        }
    }
}
