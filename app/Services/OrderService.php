<?php

namespace App\Services;

use App\Integrations\WooCommerce\OrderAdapter;
use App\Integrations\WooCommerce\OrderStatusResolver;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Repositories\CustomerActivityRepository;
use App\Repositories\CustomerTaskRepository;
use App\Repositories\StoreRepository;
use App\Support\Logger;
use DateTime;
use DateTimeZone;
use Exception;

class OrderService
{
    private StoreRepository $storeRepository;
    private CustomerActivityRepository $activityRepository;
    private CustomerTaskRepository $taskRepository;
    private AuditService $auditService;

    public function __construct(
        ?StoreRepository $storeRepository = null,
        ?CustomerActivityRepository $activityRepository = null,
        ?CustomerTaskRepository $taskRepository = null,
        ?AuditService $auditService = null
    ) {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->taskRepository = $taskRepository ?? new CustomerTaskRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Resolve WooCommerce OrderAdapter with secure store credentials.
     */
    public function getOrderAdapter(int $storeId): OrderAdapter
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        return new OrderAdapter($store);
    }

    /**
     * List orders from WooCommerce with filters, search, and pagination.
     */
    public function listOrders(int $storeId, array $params = []): array
    {
        $store = $this->storeRepository->findById($storeId);
        $timezone = $store ? ($store->timezone ?: 'Asia/Tehran') : 'Asia/Tehran';

        // Process date filters respecting store timezone (Section 8)
        if (!empty($params['date_preset'])) {
            $range = $this->resolveDatePreset($params['date_preset'], $timezone);
            if ($range) {
                $params['after'] = $range['after'];
                $params['before'] = $range['before'];
            }
        } elseif (!empty($params['after']) || !empty($params['before'])) {
            // Ensure ISO 8601 formatting in store timezone
            if (!empty($params['after'])) {
                $params['after'] = $this->formatStoreIsoDate($params['after'], $timezone, false);
            }
            if (!empty($params['before'])) {
                $params['before'] = $this->formatStoreIsoDate($params['before'], $timezone, true);
            }
        }

        $adapter = $this->getOrderAdapter($storeId);
        return $adapter->listOrders($params);
    }

    /**
     * Retrieve single order details.
     */
    public function getOrder(int $storeId, int $orderId): ?array
    {
        $adapter = $this->getOrderAdapter($storeId);
        return $adapter->getOrder($orderId);
    }

    /**
     * Update order status with audit log, activity and validation.
     */
    public function updateOrderStatus(int $storeId, int $orderId, int $userId, string $status, ?string $ip = null, ?string $userAgent = null): array
    {
        $cleanStatus = trim(strtolower($status));
        if ($cleanStatus === '') {
            throw new Exception("وضعیت جدید سفارش الزامی است.", 422);
        }

        if (!OrderStatusResolver::isValidStatus($cleanStatus, $storeId, $this->storeRepository)) {
            throw new Exception("وضعیت انتخابی «{$cleanStatus}» در این فروشگاه پشتیبانی نمی‌شود.", 422);
        }

        $adapter = $this->getOrderAdapter($storeId);
        $existing = $adapter->getOrder($orderId);
        if (!$existing) {
            throw new Exception("سفارش مورد نظر یافت نشد.", 404);
        }

        $oldStatus = $existing['status'];
        $oldStatusLabel = $existing['status_label'];
        $newStatusLabel = OrderStatusResolver::getLabel($cleanStatus, $storeId, $this->storeRepository);

        // Send status update to WooCommerce
        $updated = $adapter->updateStatus($orderId, $cleanStatus);

        // Record CRM Activity
        $this->activityRepository->recordForOrder($storeId, $userId, 'order_status_changed', $orderId, [
            'old_status' => $oldStatus,
            'new_status' => $cleanStatus,
            'old_label' => $oldStatusLabel,
            'new_label' => $newStatusLabel,
        ]);

        // Record Audit Log
        $this->auditService->log(
            $userId,
            $storeId,
            'ORDER_STATUS_CHANGED',
            'order',
            (string)$orderId,
            ['status' => $oldStatus],
            ['status' => $cleanStatus],
            $ip,
            $userAgent
        );

        return $updated;
    }

    /**
     * List notes for an order directly from WooCommerce.
     */
    public function listOrderNotes(int $storeId, int $orderId): array
    {
        $adapter = $this->getOrderAdapter($storeId);
        return $adapter->listOrderNotes($orderId);
    }

    /**
     * Add a note to an order in WooCommerce.
     */
    public function createOrderNote(int $storeId, int $orderId, int $userId, string $note, bool $customerNote = false, ?string $ip = null, ?string $userAgent = null): array
    {
        $cleanNote = trim($note);
        if ($cleanNote === '') {
            throw new Exception("متن یادداشت نمی‌تواند خالی باشد.", 422);
        }

        $adapter = $this->getOrderAdapter($storeId);
        $created = $adapter->createOrderNote($orderId, $cleanNote, $customerNote);

        // Record Activity
        $this->activityRepository->recordForOrder($storeId, $userId, 'order_note_added', $orderId, [
            'note_id' => $created['id'],
            'customer_note' => $customerNote,
            'excerpt' => mb_substr($cleanNote, 0, 80) . (mb_strlen($cleanNote) > 80 ? '...' : ''),
        ]);

        // Record Audit Log
        $this->auditService->log(
            $userId,
            $storeId,
            'ORDER_NOTE_CREATED',
            'order_note',
            (string)$created['id'],
            null,
            ['order_id' => $orderId, 'customer_note' => $customerNote, 'note' => $cleanNote],
            $ip,
            $userAgent
        );

        return $created;
    }

    /**
     * Process order refund with safety checks, activity, and audit logging.
     */
    public function createRefund(
        int $storeId,
        int $orderId,
        int $userId,
        float $amount,
        string $reason = '',
        bool $apiRefund = true,
        array $lineItems = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        if ($amount <= 0) {
            throw new Exception("مبلغ استرداد باید بزرگتر از صفر باشد.", 422);
        }

        $adapter = $this->getOrderAdapter($storeId);
        $order = $adapter->getOrder($orderId);
        if (!$order) {
            throw new Exception("سفارش مورد نظر جهت استرداد یافت نشد.", 404);
        }

        $remainingTotal = $order['total'] - ($order['refunded_total'] ?? 0.0);
        if ($amount > $remainingTotal + 0.01) {
            throw new Exception("مبلغ استرداد نمی‌تواند بیشتر از مانده مبلغ سفارش ({$remainingTotal}) باشد.", 422);
        }

        $refund = $adapter->createRefund($orderId, $amount, $reason, $apiRefund, $lineItems);

        // Record Activity
        $this->activityRepository->recordForOrder($storeId, $userId, 'order_refunded', $orderId, [
            'refund_id' => $refund['id'],
            'amount' => $amount,
            'reason' => $reason,
        ]);

        // Record Audit Log
        $this->auditService->log(
            $userId,
            $storeId,
            'ORDER_REFUNDED',
            'order_refund',
            (string)$refund['id'],
            ['remaining_before' => $remainingTotal],
            ['amount' => $amount, 'reason' => $reason, 'api_refund' => $apiRefund],
            $ip,
            $userAgent
        );

        // Fetch refreshed order with updated financial totals
        $updatedOrder = $adapter->getOrder($orderId);

        return [
            'refund' => $refund,
            'order' => $updatedOrder,
        ];
    }

    /**
     * List order activities.
     */
    public function listOrderActivities(int $storeId, int $orderId, int $limit = 50): array
    {
        return $this->activityRepository->listByOrder($storeId, $orderId, $limit);
    }

    /**
     * List order tasks.
     */
    public function listOrderTasks(int $storeId, int $orderId): array
    {
        return $this->taskRepository->listByOrder($storeId, $orderId);
    }

    /**
     * Create task for order.
     */
    public function createOrderTask(int $storeId, int $orderId, int $userId, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new Exception("عنوان وظیفه الزامی است.", 422);
        }

        $task = $this->taskRepository->create([
            'store_id' => $storeId,
            'order_id' => $orderId,
            'created_by_user_id' => $userId,
            'assigned_user_id' => !empty($data['assigned_user_id']) ? (int)$data['assigned_user_id'] : null,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'priority' => in_array($data['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $data['priority'] : 'medium',
            'status' => in_array($data['status'] ?? '', ['pending', 'in_progress', 'completed', 'cancelled'], true) ? $data['status'] : 'pending',
            'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
        ]);

        $this->activityRepository->recordForOrder($storeId, $userId, 'order_task_created', $orderId, [
            'task_id' => $task['id'],
            'title' => $task['title'],
        ]);

        $this->auditService->log(
            $userId,
            $storeId,
            'ORDER_TASK_CREATED',
            'task',
            (string)$task['id'],
            null,
            ['order_id' => $orderId, 'title' => $task['title']],
            $ip,
            $userAgent
        );

        return $task;
    }

    /**
     * Resolve date presets to start and end ISO 8601 strings in store timezone.
     */
    private function resolveDatePreset(string $preset, string $timezoneName): ?array
    {
        try {
            $tz = new DateTimeZone($timezoneName);
        } catch (\Exception $e) {
            $tz = new DateTimeZone('Asia/Tehran');
        }

        $now = new DateTime('now', $tz);

        switch ($preset) {
            case 'today':
                $start = (clone $now)->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                break;
            case 'yesterday':
                $start = (clone $now)->modify('-1 day')->setTime(0, 0, 0);
                $end = (clone $start)->setTime(23, 59, 59);
                break;
            case 'last_7_days':
                $start = (clone $now)->modify('-7 days')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                break;
            case 'last_30_days':
                $start = (clone $now)->modify('-30 days')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                break;
            case 'this_month':
                $start = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                break;
            case 'last_month':
                $start = (clone $now)->modify('first day of last month')->setTime(0, 0, 0);
                $end = (clone $now)->modify('last day of last month')->setTime(23, 59, 59);
                break;
            default:
                return null;
        }

        return [
            'after' => $start->format('Y-m-d\TH:i:s'),
            'before' => $end->format('Y-m-d\TH:i:s'),
        ];
    }

    private function formatStoreIsoDate(string $dateStr, string $timezoneName, bool $endOfDay): string
    {
        try {
            $tz = new DateTimeZone($timezoneName);
            $dt = new DateTime($dateStr, $tz);
            if ($endOfDay && strlen($dateStr) <= 10) {
                $dt->setTime(23, 59, 59);
            } elseif (!$endOfDay && strlen($dateStr) <= 10) {
                $dt->setTime(0, 0, 0);
            }
            return $dt->format('Y-m-d\TH:i:s');
        } catch (\Exception $e) {
            return $dateStr;
        }
    }
}
