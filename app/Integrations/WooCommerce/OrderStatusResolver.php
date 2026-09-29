<?php

namespace App\Integrations\WooCommerce;

use App\Repositories\StoreRepository;

class OrderStatusResolver
{
    private static array $defaultStatuses = [
        'pending' => 'در انتظار پرداخت',
        'processing' => 'در حال پردازش',
        'on-hold' => 'در انتظار بررسی',
        'completed' => 'تکمیل شده',
        'cancelled' => 'لغو شده',
        'refunded' => 'مسترد شده',
        'failed' => 'ناموفق',
        'trash' => 'زباله‌دان',
    ];

    /**
     * Get all available order statuses for a store (including custom statuses).
     */
    public static function getStatuses(int $storeId, ?StoreRepository $storeRepo = null): array
    {
        $storeRepo = $storeRepo ?? new StoreRepository();
        $store = $storeRepo->findById($storeId);

        $custom = [];
        if ($store) {
            $hasCustom = false;
            if (!empty($store->capabilities['custom_order_statuses'])) {
                foreach ($store->capabilities['custom_order_statuses'] as $item) {
                    if (!empty($item['slug'])) {
                        $slug = $item['slug'];
                        $name = $item['name'] ?? (self::$defaultStatuses[$slug] ?? $slug);
                        $custom[$slug] = $name;
                        if (!isset(self::$defaultStatuses[$slug])) {
                            $hasCustom = true;
                        }
                    }
                }
            }

            // If no custom statuses are detected yet, probe WooCommerce /reports/orders/totals
            if (!$hasCustom) {
                try {
                    $client = new WooCommerceClient($store);
                    $totals = $client->get('/reports/orders/totals');
                    if (is_array($totals)) {
                        $caps = is_array($store->capabilities) ? $store->capabilities : [];
                        $caps['custom_order_statuses'] = [];
                        foreach ($totals as $item) {
                            if (isset($item['slug'])) {
                                $slug = $item['slug'];
                                $name = $item['name'] ?? (self::$defaultStatuses[$slug] ?? $slug);
                                $custom[$slug] = $name;
                                $caps['custom_order_statuses'][] = [
                                    'slug' => $slug,
                                    'name' => $name,
                                    'total' => $item['total'] ?? 0,
                                ];
                            }
                        }
                        $storeRepo->updateCapabilities($storeId, $caps);
                    }
                } catch (\Throwable $e) {
                    // Fallback to standard
                }
            }
        }

        // Merge standard statuses with detected store statuses
        $merged = array_merge(self::$defaultStatuses, $custom);

        $result = [];
        foreach ($merged as $slug => $name) {
            $result[] = [
                'slug' => $slug,
                'name' => $name,
            ];
        }

        return $result;
    }

    /**
     * Resolve the display label for an order status slug.
     */
    public static function getLabel(string $slug, int $storeId = 0, ?StoreRepository $storeRepo = null): string
    {
        if ($storeId > 0) {
            $storeRepo = $storeRepo ?? new StoreRepository();
            $store = $storeRepo->findById($storeId);
            if ($store && !empty($store->capabilities['custom_order_statuses'])) {
                foreach ($store->capabilities['custom_order_statuses'] as $item) {
                    if (($item['slug'] ?? '') === $slug && !empty($item['name'])) {
                        return $item['name'];
                    }
                }
            }
        }

        return self::$defaultStatuses[$slug] ?? ucfirst(str_replace(['-', '_'], ' ', $slug));
    }

    public static function toPersian(string $slug): string
    {
        return self::getLabel($slug);
    }

    /**
     * Verify whether a status slug is valid for the store.
     */
    public static function isValidStatus(string $slug, int $storeId = 0, ?StoreRepository $storeRepo = null): bool
    {
        $statuses = self::getStatuses($storeId, $storeRepo);
        foreach ($statuses as $s) {
            if ($s['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }
}
