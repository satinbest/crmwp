<?php

namespace App\Integrations\WooCommerce\Handlers;

use App\Repositories\CustomerActivityRepository;
use App\Support\Cache;
use App\Support\Logger;
use Exception;

class CustomerEventHandler
{
    private CustomerActivityRepository $activityRepository;

    public function __construct(?CustomerActivityRepository $activityRepository = null)
    {
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
    }

    public function handle(int $storeId, string $event, array $payload): array
    {
        $customerId = (int)($payload['id'] ?? ($payload['customer_id'] ?? 0));
        $email = (string)($payload['email'] ?? '');
        $firstName = (string)($payload['first_name'] ?? '');
        $lastName = (string)($payload['last_name'] ?? '');
        $fullName = trim("{$firstName} {$lastName}") ?: ($payload['username'] ?? "مشتری #{$customerId}");

        // 1. Invalidate caches
        $invalidatedCount = 0;
        $invalidatedCount += Cache::forgetByPrefix("customers_{$storeId}");
        if ($customerId > 0) {
            $invalidatedCount += Cache::forgetByPrefix("customer_{$storeId}_{$customerId}");
        }
        $invalidatedCount += Cache::forgetByPrefix("segment_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("segments_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("crm_summary_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("dashboard_{$storeId}");

        // 2. CRM Business Activity
        $activityCreated = false;
        if ($customerId > 0 && $event === 'customer.created') {
            try {
                $this->activityRepository->record($storeId, null, 'customer_registered', $customerId, [
                    'customer_id' => $customerId,
                    'name' => $fullName,
                    'email' => $email,
                    'source' => 'woocommerce_webhook',
                ]);
                $activityCreated = true;
            } catch (Exception $e) {
                Logger::warning("Could not record customer registered activity: " . $e->getMessage());
            }
        }

        return [
            'customer_id' => $customerId,
            'name' => $fullName,
            'invalidated_caches' => $invalidatedCount,
            'activity_created' => $activityCreated,
        ];
    }
}
