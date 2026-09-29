<?php

namespace App\Services;

use App\Repositories\NotificationRepository;

class NotificationService
{
    private NotificationRepository $repository;

    public function __construct(?NotificationRepository $repository = null)
    {
        $this->repository = $repository ?? new NotificationRepository();
    }

    /**
     * Get user notifications with filtering and pagination
     */
    public function getUserNotifications(
        int $userId,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        array $allowedStoreIds = []
    ): array {
        return $this->repository->listForUser($userId, $filters, $page, $perPage, $allowedStoreIds);
    }

    /**
     * Get total unread notifications count
     */
    public function getUnreadCount(int $userId, ?int $storeId = null, array $allowedStoreIds = []): int
    {
        return $this->repository->getUnreadCount($userId, $storeId, $allowedStoreIds);
    }

    /**
     * Create a notification with preference checks and duplicate prevention
     */
    public function createForUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        ?int $storeId = null,
        ?string $actionUrl = null,
        string $priority = 'normal'
    ): ?int {
        // 1. Check user preferences
        if (!$this->isNotificationTypeEnabled($userId, $type)) {
            return null;
        }

        // 2. Prevent duplicate notifications for low_stock and order_attention
        if ($type === 'low_stock' && isset($data['product_id'])) {
            $hasDuplicate = $this->repository->hasRecentDuplicate(
                $userId,
                $type,
                $storeId,
                'product_id',
                $data['product_id'],
                43200 // 12 hours debounce
            );
            if ($hasDuplicate) {
                return null;
            }
        } elseif ($type === 'order_attention' && isset($data['order_id'])) {
            $hasDuplicate = $this->repository->hasRecentDuplicate(
                $userId,
                $type,
                $storeId,
                'order_id',
                $data['order_id'],
                21600 // 6 hours debounce
            );
            if ($hasDuplicate) {
                return null;
            }
        } elseif ($type === 'task_assigned' && isset($data['task_id'])) {
            $hasDuplicate = $this->repository->hasRecentDuplicate(
                $userId,
                $type,
                $storeId,
                'task_id',
                $data['task_id'],
                3600 // 1 hour debounce
            );
            if ($hasDuplicate) {
                return null;
            }
        }

        return $this->repository->create([
            'user_id' => $userId,
            'store_id' => $storeId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Create notification for multiple users
     */
    public function createForUsers(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        ?int $storeId = null,
        ?string $actionUrl = null,
        string $priority = 'normal'
    ): int {
        $count = 0;
        foreach (array_unique($userIds) as $uid) {
            $res = $this->createForUser((int)$uid, $type, $title, $message, $data, $storeId, $actionUrl, $priority);
            if ($res !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(int $id, int $userId): bool
    {
        return $this->repository->markAsRead($id, $userId);
    }

    /**
     * Mark all notifications for user as read
     */
    public function markAllAsRead(int $userId, ?int $storeId = null, array $allowedStoreIds = []): int
    {
        return $this->repository->markAllAsRead($userId, $storeId, $allowedStoreIds);
    }

    /**
     * Delete a notification
     */
    public function delete(int $id, int $userId): bool
    {
        return $this->repository->delete($id, $userId);
    }

    /**
     * Find single notification by ID
     */
    public function find(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Clean old notifications based on retention days
     */
    public function cleanOldNotifications(int $days = 30): int
    {
        return $this->repository->deleteOlderThan($days);
    }

    /**
     * Get user preferences
     */
    public function getPreferences(int $userId): array
    {
        return $this->repository->getPreferences($userId);
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(int $userId, array $preferences): array
    {
        return $this->repository->updatePreferences($userId, $preferences);
    }

    /**
     * Notification Trigger: Task Assigned
     */
    public function notifyTaskAssigned(
        int $assignedUserId,
        int $taskId,
        string $taskTitle,
        ?int $storeId = null,
        ?int $orderId = null,
        ?int $customerId = null
    ): ?int {
        $message = "وظیفه «{$taskTitle}» به شما واگذار شد.";
        if ($orderId) {
            $message .= " مرتبط با سفارش #{$orderId}";
        }

        return $this->createForUser(
            $assignedUserId,
            'task_assigned',
            'وظیفه جدید تخصیص یافت',
            $message,
            [
                'task_id' => $taskId,
                'order_id' => $orderId,
                'customer_id' => $customerId,
            ],
            $storeId,
            '/crm/tasks',
            'high'
        );
    }

    /**
     * Notification Trigger: Bulk Operation Completed
     */
    public function notifyBulkOperationFinished(
        int $userId,
        int $operationId,
        string $actionType,
        string $status, // completed, failed, partial
        int $total,
        int $success,
        int $failed,
        ?int $storeId = null
    ): ?int {
        $type = 'bulk_operation_' . ($status === 'completed' && $failed === 0 ? 'completed' : ($failed > 0 && $success > 0 ? 'partial' : 'failed'));

        if ($status === 'completed' && $failed === 0) {
            $title = 'عملیات دسته‌جمعی با موفقیت تکمیل شد';
            $message = "عملیات گروهی {$actionType} با موفقیت انجام شد ({$success} مورد پردازش شد).";
            $priority = 'normal';
        } elseif ($failed > 0 && $success > 0) {
            $title = 'عملیات دسته‌جمعی با موفقیت جزئی به پایان رسید';
            $message = "عملیات گروهی {$actionType}: {$success} مورد موفق، {$failed} مورد با خطا مواجه شد.";
            $priority = 'high';
        } else {
            $title = 'خطا در اجرای عملیات دسته‌جمعی';
            $message = "عملیات گروهی {$actionType} با خطا مواجه گردید ({$failed} مورد ناموفق).";
            $priority = 'urgent';
        }

        return $this->createForUser(
            $userId,
            $type,
            $title,
            $message,
            [
                'operation_id' => $operationId,
                'action_type' => $actionType,
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
            ],
            $storeId,
            '/bulk-operations',
            $priority
        );
    }

    /**
     * Notification Trigger: Low Stock
     */
    public function notifyLowStock(
        int $userId,
        int $productId,
        string $productName,
        int $currentStock,
        int $lowStockAmount,
        ?int $storeId = null
    ): ?int {
        return $this->createForUser(
            $userId,
            'low_stock',
            'هشدار کمبود موجودی کالا',
            "موجودی کالای «{$productName}» به {$currentStock} عدد کاهش یافته است (آستانه هشدار: {$lowStockAmount}).",
            [
                'product_id' => $productId,
                'product_name' => $productName,
                'current_stock' => $currentStock,
                'low_stock_amount' => $lowStockAmount,
            ],
            $storeId,
            '/inventory',
            'urgent'
        );
    }

    /**
     * Notification Trigger: Order Attention
     */
    public function notifyOrderAttention(
        int $userId,
        int $orderId,
        string $status,
        ?string $reason = null,
        ?int $storeId = null
    ): ?int {
        $message = "سفارش #{$orderId} به وضعیت «{$status}» تغییر یافت و نیازمند بررسی است.";
        if ($reason) {
            $message .= " ({$reason})";
        }

        return $this->createForUser(
            $userId,
            'order_attention',
            'سفارش نیازمند توجه است',
            $message,
            [
                'order_id' => $orderId,
                'status' => $status,
                'reason' => $reason,
            ],
            $storeId,
            "/orders/{$orderId}",
            'high'
        );
    }

    /**
     * Check if user preferences allow given notification type
     */
    private function isNotificationTypeEnabled(int $userId, string $type): bool
    {
        $prefs = $this->getPreferences($userId);

        if (str_starts_with($type, 'task_') && empty($prefs['task_notifications'])) {
            return false;
        }
        if (str_starts_with($type, 'order_') && empty($prefs['order_notifications'])) {
            return false;
        }
        if ($type === 'low_stock' && empty($prefs['inventory_notifications'])) {
            return false;
        }
        if (str_starts_with($type, 'bulk_') && empty($prefs['bulk_notifications'])) {
            return false;
        }
        if ($type === 'system' && empty($prefs['system_notifications'])) {
            return false;
        }

        return true;
    }
}
