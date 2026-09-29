<?php

namespace App\Integrations\WooCommerce\Handlers;

use App\Repositories\CustomerActivityRepository;
use App\Repositories\StoreRepository;
use App\Repositories\UserRepository;
use App\Services\NotificationService;
use App\Support\Cache;
use App\Support\Logger;
use Exception;

class OrderEventHandler
{
    private CustomerActivityRepository $activityRepository;
    private NotificationService $notificationService;
    private StoreRepository $storeRepository;
    private UserRepository $userRepository;

    public function __construct(
        ?CustomerActivityRepository $activityRepository = null,
        ?NotificationService $notificationService = null,
        ?StoreRepository $storeRepository = null,
        ?UserRepository $userRepository = null
    ) {
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    public function handle(int $storeId, string $event, array $payload): array
    {
        $orderId = (int)($payload['id'] ?? ($payload['order_id'] ?? 0));
        $customerId = (int)($payload['customer_id'] ?? 0);
        $status = strtolower(trim((string)($payload['status'] ?? '')));
        $total = isset($payload['total']) ? (float)$payload['total'] : null;
        $currency = (string)($payload['currency'] ?? 'IRR');

        // 1. Invalidate caches (Optimized cache clearing)
        $invalidatedCount = 0;
        $invalidatedCount += Cache::forgetByPrefix("orders_{$storeId}");
        if ($orderId > 0) {
            $invalidatedCount += Cache::forgetByPrefix("order_{$storeId}_{$orderId}");
        }
        if ($customerId > 0) {
            $invalidatedCount += Cache::forgetByPrefix("customer_{$storeId}_{$customerId}");
        }
        $invalidatedCount += Cache::forgetByPrefix("inventory_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("dashboard_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("reports_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("crm_summary_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("segment_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("segments_{$storeId}");

        // 2. Record CRM Business Activity (No duplicate activities)
        $activityCreated = false;
        if ($customerId > 0 && in_array($event, ['order.created', 'order.updated'], true)) {
            $actionType = $event === 'order.created' ? 'order_created' : 'order_status_changed';
            $details = [
                'order_id' => $orderId,
                'status' => $status,
                'total' => $total,
                'currency' => $currency,
                'source' => 'woocommerce_webhook',
            ];

            try {
                $this->activityRepository->record($storeId, null, $actionType, $customerId, $details);
                $activityCreated = true;
            } catch (Exception $e) {
                Logger::warning("Could not record customer activity for webhook: " . $e->getMessage());
            }
        }

        // 3. Notification triggers (Attention required for failed/refunded/cancelled/on-hold orders)
        $notificationCount = 0;
        $attentionStatuses = ['failed', 'cancelled', 'refunded', 'on-hold'];
        if (in_array($status, $attentionStatuses, true) && $orderId > 0) {
            try {
                $users = $this->userRepository->listFiltered(['status' => 'active'], 100);
                foreach ($users as $user) {
                    $userId = (int)$user->id;
                    $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('administrator') : false;
                    if ($this->storeRepository->userHasAccess($userId, $storeId, $isAdmin)) {
                        $res = $this->notificationService->notifyOrderAttention(
                            $userId,
                            $orderId,
                            $status,
                            "ثبت شده از طریق وب‌هوک ووکامرس",
                            $storeId
                        );
                        if ($res !== null) {
                            $notificationCount++;
                        }
                    }
                }
            } catch (Exception $e) {
                Logger::warning("Could not dispatch webhook order notification: " . $e->getMessage());
            }
        }

        return [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'status' => $status,
            'invalidated_caches' => $invalidatedCount,
            'activity_created' => $activityCreated,
            'notifications_sent' => $notificationCount,
        ];
    }
}
