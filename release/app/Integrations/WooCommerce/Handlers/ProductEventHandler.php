<?php

namespace App\Integrations\WooCommerce\Handlers;

use App\Repositories\StoreRepository;
use App\Repositories\UserRepository;
use App\Services\NotificationService;
use App\Support\Cache;
use App\Support\Logger;
use Exception;

class ProductEventHandler
{
    private NotificationService $notificationService;
    private StoreRepository $storeRepository;
    private UserRepository $userRepository;

    public function __construct(
        ?NotificationService $notificationService = null,
        ?StoreRepository $storeRepository = null,
        ?UserRepository $userRepository = null
    ) {
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    public function handle(int $storeId, string $event, array $payload): array
    {
        $productId = (int)($payload['id'] ?? ($payload['product_id'] ?? 0));
        $name = (string)($payload['name'] ?? "محصول #{$productId}");
        $manageStock = (bool)($payload['manage_stock'] ?? false);
        $stockQuantity = isset($payload['stock_quantity']) && is_numeric($payload['stock_quantity'])
            ? (int)$payload['stock_quantity']
            : null;
        $lowStockAmount = isset($payload['low_stock_amount']) && is_numeric($payload['low_stock_amount'])
            ? (int)$payload['low_stock_amount']
            : 2;

        // 1. Invalidate caches
        $invalidatedCount = 0;
        $invalidatedCount += Cache::forgetByPrefix("products_{$storeId}");
        if ($productId > 0) {
            $invalidatedCount += Cache::forgetByPrefix("product_{$storeId}_{$productId}");
        }
        $invalidatedCount += Cache::forgetByPrefix("inventory_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("dashboard_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("reports_{$storeId}");

        // 2. Check low stock notification trigger
        $notificationsSent = 0;
        if ($manageStock && $stockQuantity !== null && $stockQuantity <= $lowStockAmount && $productId > 0) {
            try {
                $users = $this->userRepository->listFiltered(['status' => 'active'], 100);
                foreach ($users as $user) {
                    $userId = (int)$user->id;
                    $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('administrator') : false;
                    if ($this->storeRepository->userHasAccess($userId, $storeId, $isAdmin)) {
                        $res = $this->notificationService->notifyLowStock(
                            $userId,
                            $productId,
                            $name,
                            $stockQuantity,
                            $lowStockAmount,
                            $storeId
                        );
                        if ($res !== null) {
                            $notificationsSent++;
                        }
                    }
                }
            } catch (Exception $e) {
                Logger::warning("Could not dispatch webhook low stock notification: " . $e->getMessage());
            }
        }

        return [
            'product_id' => $productId,
            'name' => $name,
            'invalidated_caches' => $invalidatedCount,
            'notifications_sent' => $notificationsSent,
        ];
    }
}
