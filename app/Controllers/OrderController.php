<?php

namespace App\Controllers;

use App\Integrations\WooCommerce\OrderStatusResolver;
use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Repositories\StoreRepository;
use App\Services\OrderService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class OrderController extends BaseController
{
    private OrderService $orderService;
    private StoreRepository $storeRepository;

    public function __construct(
        ?OrderService $orderService = null,
        ?StoreRepository $storeRepository = null
    ) {
        $this->orderService = $orderService ?? new OrderService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }


    /**
     * GET /api/v1/orders/statuses
     */
    public function statuses(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $statuses = OrderStatusResolver::getStatuses($storeId, $this->storeRepository);
            return $this->success($statuses, [
                'total' => count($statuses),
                'store_id' => $storeId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('STATUSES_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/orders
     */
    public function index(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $allowedSort = ['id', 'date', 'total', 'status', 'title', 'modified'];
            $sort = in_array($request->query('sort'), $allowedSort, true) ? $request->query('sort') : 'date';
            $direction = strtolower((string)$request->query('direction')) === 'asc' ? 'asc' : 'desc';

            $params = [
                'page' => max(1, (int)$request->query('page', 1)),
                'per_page' => max(1, min(100, (int)$request->query('per_page', 15))),
                'search' => mb_substr(trim((string)$request->query('search', '')), 0, 100),
                'status' => $request->query('status', 'all'),
                'customer' => $request->query('customer', ''),
                'date_preset' => $request->query('date_preset', ''),
                'after' => $request->query('after', ''),
                'before' => $request->query('before', ''),
                'sort' => $sort,
                'direction' => $direction,
            ];

            $result = $this->orderService->listOrders($storeId, $params);

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
            return $this->error('ORDERS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/orders/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');

            if ($orderId <= 0) {
                return $this->error('INVALID_ORDER_ID', 'شناسه سفارش نامعتبر است.', [], 400);
            }

            $order = $this->orderService->getOrder($storeId, $orderId);

            if (!$order) {
                return $this->error('ORDER_NOT_FOUND', 'سفارش مورد نظر در فروشگاه یافت نشد.', [], 404);
            }

            return $this->success($order, [
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
            return $this->error('ORDER_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * PATCH /api/v1/orders/{id}/status
     */
    public function updateStatus(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $status = (string)$request->input('status', '');
            if (trim($status) === '') {
                return $this->error('VALIDATION_ERROR', 'وضعیت جدید سفارش الزامی است.', ['status' => 'فیلد وضعیت نمی‌تواند خالی باشد.'], 422);
            }

            $updated = $this->orderService->updateOrderStatus(
                $storeId,
                $orderId,
                $userId,
                $status,
                $request->getIp(),
                $request->getUserAgent()
            );

            // Notify on critical order status change
            try {
                if (in_array($status, ['cancelled', 'failed', 'refunded', 'on-hold'], true)) {
                    $notifService = new \App\Services\NotificationService();
                    $notifService->createForUser(
                        $userId,
                        'order_attention',
                        'تغییر وضعیت مهم سفارش #' . $orderId,
                        "وضعیت سفارش شماره {$orderId} به «{$status}» تغییر یافت.",
                        ['order_id' => $orderId, 'status' => $status],
                        $storeId,
                        '/orders/' . $orderId,
                        'high'
                    );
                }
            } catch (\Throwable $te) {}

            return $this->success($updated, ['message' => 'وضعیت سفارش با موفقیت در ووکامرس به‌روزرسانی شد.']);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('STATUS_UPDATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/orders/{id}/notes
     */
    public function notes(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');

            $notes = $this->orderService->listOrderNotes($storeId, $orderId);

            return $this->success($notes, [
                'total' => count($notes),
                'store_id' => $storeId,
                'order_id' => $orderId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ORDER_NOTES_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/orders/{id}/notes
     */
    public function createNote(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $note = (string)$request->input('note', '');
            $customerNote = (bool)$request->input('customer_note', false);

            if (trim($note) === '') {
                return $this->error('VALIDATION_ERROR', 'متن یادداشت سفارش الزامی است.', ['note' => 'متن یادداشت نمی‌تواند خالی باشد.'], 422);
            }

            $created = $this->orderService->createOrderNote(
                $storeId,
                $orderId,
                $userId,
                $note,
                $customerNote,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($created, ['message' => 'یادداشت با موفقیت در ووکامرس ثبت گردید.'], 201);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('ORDER_NOTE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/orders/{id}/refund
     */
    public function refund(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $amount = (float)$request->input('amount', 0.0);
            $reason = (string)$request->input('reason', '');
            $apiRefund = (bool)$request->input('api_refund', true);
            $lineItems = is_array($request->input('line_items')) ? $request->input('line_items') : [];

            if ($amount <= 0) {
                return $this->error('VALIDATION_ERROR', 'مبلغ استرداد باید بزرگتر از صفر باشد.', ['amount' => 'مبلغ استرداد نامعتبر است.'], 422);
            }

            $refund = $this->orderService->createRefund(
                $storeId,
                $orderId,
                $userId,
                $amount,
                $reason,
                $apiRefund,
                $lineItems,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($refund, ['message' => 'استرداد وجه با موفقیت در ووکامرس ثبت گردید.'], 201);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $code = $status === 422 ? 'INVALID_REFUND_AMOUNT' : 'REFUND_FAILED';
            return $this->error($code, $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/orders/{id}/activities
     */
    public function activities(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');
            $limit = min(100, max(1, (int)$request->query('limit', 50)));

            $activities = $this->orderService->listOrderActivities($storeId, $orderId, $limit);

            return $this->success($activities, [
                'total' => count($activities),
                'store_id' => $storeId,
                'order_id' => $orderId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ACTIVITIES_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/orders/{id}/tasks
     */
    public function tasks(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');

            $tasks = $this->orderService->listOrderTasks($storeId, $orderId);

            return $this->success($tasks, [
                'total' => count($tasks),
                'store_id' => $storeId,
                'order_id' => $orderId,
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TASKS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/orders/{id}/tasks
     */
    public function createTask(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $orderId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $title = (string)$request->input('title', '');
            if (trim($title) === '') {
                return $this->error('VALIDATION_ERROR', 'عنوان وظیفه الزامی است.', ['title' => 'عنوان نمی‌تواند خالی باشد.'], 422);
            }

            $task = $this->orderService->createOrderTask(
                $storeId,
                $orderId,
                $userId,
                $request->all(),
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($task, ['message' => 'وظیفه با موفقیت برای این سفارش ایجاد گردید.'], 201);
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('TASK_CREATE_FAILED', $e->getMessage(), [], $status);
        }
    }
}
