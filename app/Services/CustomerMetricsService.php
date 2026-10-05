<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Support\Cache;
use App\Support\Logger;
use Throwable;

class CustomerMetricsService
{
    /**
     * Order statuses considered valid for customer's total order count.
     * Excludes cancelled, failed, trash, and draft checkouts.
     */
    public const VALID_ORDER_STATUSES = [
        'completed',
        'processing',
        'on-hold',
        'pending',
    ];

    /**
     * Order statuses strictly considered for total money spent (paid revenue).
     */
    public const PAID_ORDER_STATUSES = [
        'completed',
        'processing',
    ];

    /**
     * Calculate metrics (orders_count, total_spent, average_order_value, last_order_date)
     * for a single customer directly from WooCommerce REST API with full pagination support.
     *
     * @return array{orders_count: int, total_spent: float, average_order_value: float, last_order_date: ?string}
     */
    public function calculateForCustomer(
        WooCommerceClient $client,
        int $storeId,
        int $customerId,
        ?string $email = null
    ): array {
        if ($customerId <= 0) {
            return [
                'orders_count' => 0,
                'total_spent' => 0.0,
                'average_order_value' => 0.0,
                'last_order_date' => null,
            ];
        }

        if ($storeId > 0) {
            $cached = Cache::storeGet($storeId, 'customer_metrics', (string)$customerId);
            if (is_array($cached) && isset($cached['orders_count'], $cached['total_spent'])) {
                return $cached;
            }
        }

        $orders = $this->fetchAllOrdersForCustomer($client, $customerId);

        $metrics = $this->aggregateOrders($orders);

        if ($storeId > 0) {
            // Cache metrics for 5 minutes (TTL_CUSTOMERS = 300)
            Cache::storeSet($storeId, 'customer_metrics', (string)$customerId, $metrics, Cache::TTL_CUSTOMERS);
        }

        return $metrics;
    }

    /**
     * Fetch all orders belonging to a customer from WooCommerce API, handling pagination.
     */
    public function fetchAllOrdersForCustomer(WooCommerceClient $client, int $customerId): array
    {
        $allOrders = [];
        $page = 1;
        $perPage = 100; // Maximum allowed by standard WooCommerce REST API
        $maxPages = 20; // Safeguard limit (up to 2000 orders per customer)

        do {
            try {
                $response = $client->getWithHeaders('/orders', [
                    'customer' => $customerId,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                $rawOrders = is_array($response['data']) ? $response['data'] : [];
                if (empty($rawOrders)) {
                    break;
                }

                foreach ($rawOrders as $order) {
                    $allOrders[] = $order;
                }

                $headers = $response['headers'] ?? [];
                $totalPages = isset($headers['x-wp-totalpages']) ? (int)$headers['x-wp-totalpages'] : 1;

                if ($page >= $totalPages || count($rawOrders) < $perPage) {
                    break;
                }

                $page++;
            } catch (WooCommerceApiException $e) {
                Logger::warning("Failed to fetch customer orders page {$page}", [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
                break;
            } catch (Throwable $e) {
                Logger::error("Unexpected error fetching orders for customer {$customerId}: " . $e->getMessage());
                break;
            }
        } while ($page <= $maxPages);

        return $allOrders;
    }

    /**
     * Aggregate orders into count, total spent, average order value, and last order date.
     */
    public function aggregateOrders(array $orders): array
    {
        $validOrdersCount = 0;
        $totalSpent = 0.0;
        $lastOrderDate = null;

        foreach ($orders as $order) {
            $status = strtolower((string)($order['status'] ?? ''));
            $orderTotal = (float)($order['total'] ?? 0.0);
            $orderDate = $order['date_created'] ?? null;

            // Track last order date
            if ($orderDate && ($lastOrderDate === null || strtotime($orderDate) > strtotime($lastOrderDate))) {
                $lastOrderDate = $orderDate;
            }

            // Check if status is a valid customer order
            if (in_array($status, self::VALID_ORDER_STATUSES, true)) {
                $validOrdersCount++;
            }

            // Check if status contributes to total spent (revenue)
            if (in_array($status, self::PAID_ORDER_STATUSES, true)) {
                $totalSpent += $orderTotal;
            }
        }

        $avgOrderValue = $validOrdersCount > 0 ? round($totalSpent / $validOrdersCount, 2) : 0.0;

        return [
            'orders_count' => $validOrdersCount,
            'total_spent' => round($totalSpent, 2),
            'average_order_value' => $avgOrderValue,
            'last_order_date' => $lastOrderDate,
        ];
    }

    /**
     * Efficiently enrich a page of customers with real order metrics without causing N+1 storm.
     * Uses store-level caching and on-demand pagination aggregation.
     */
    public function enrichCustomersList(
        WooCommerceClient $client,
        int $storeId,
        array &$customers
    ): void {
        if (empty($customers)) {
            return;
        }

        $customersToFetch = [];

        // Check cache first for each customer in current page
        foreach ($customers as $index => &$cust) {
            $custId = (int)($cust['id'] ?? 0);
            if ($custId <= 0) {
                continue;
            }

            // If customer record already came with non-zero orders_count from a plugin/HPOS sync
            if (!empty($cust['orders_count']) && (int)$cust['orders_count'] > 0 && !empty($cust['total_spent'])) {
                continue;
            }

            $cached = $storeId > 0 ? Cache::storeGet($storeId, 'customer_metrics', (string)$custId) : null;
            if (is_array($cached) && isset($cached['orders_count'], $cached['total_spent'])) {
                $cust['orders_count'] = $cached['orders_count'];
                $cust['total_spent'] = $cached['total_spent'];
                $cust['average_order_value'] = $cached['average_order_value'];
                if (!empty($cached['last_order_date'])) {
                    $cust['last_order_date'] = $cached['last_order_date'];
                }
            } else {
                $customersToFetch[$custId] = $index;
            }
        }
        unset($cust);

        if (empty($customersToFetch)) {
            return;
        }

        // For uncached customers in current page, compute their real stats via WooCommerce API
        foreach ($customersToFetch as $custId => $index) {
            $metrics = $this->calculateForCustomer($client, $storeId, $custId);
            $customers[$index]['orders_count'] = $metrics['orders_count'];
            $customers[$index]['total_spent'] = $metrics['total_spent'];
            $customers[$index]['average_order_value'] = $metrics['average_order_value'];
            if (!empty($metrics['last_order_date'])) {
                $customers[$index]['last_order_date'] = $metrics['last_order_date'];
            }
        }
    }
}
