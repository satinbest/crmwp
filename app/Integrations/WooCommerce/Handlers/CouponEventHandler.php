<?php

namespace App\Integrations\WooCommerce\Handlers;

use App\Support\Cache;

class CouponEventHandler
{
    public function handle(int $storeId, string $event, array $payload): array
    {
        $couponId = (int)($payload['id'] ?? ($payload['coupon_id'] ?? 0));
        $code = (string)($payload['code'] ?? '');

        // Invalidate caches
        $invalidatedCount = 0;
        $invalidatedCount += Cache::forgetByPrefix("orders_{$storeId}");
        $invalidatedCount += Cache::forgetByPrefix("reports_{$storeId}");

        return [
            'coupon_id' => $couponId,
            'code' => $code,
            'invalidated_caches' => $invalidatedCount,
        ];
    }
}
