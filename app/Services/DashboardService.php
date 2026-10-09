<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\WooCommerce\DemoWooCommerceAdapter;
use App\Integrations\WooCommerce\OrderStatusResolver;
use App\Integrations\WooCommerce\WooCommerceAdapterFactory;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\Store;
use App\Repositories\CustomerActivityRepository;
use App\Repositories\StoreRepository;
use App\Support\Cache;
use App\Support\Logger;
use DateTime;
use DateTimeZone;
use Throwable;

class DashboardService
{
    private StoreRepository $storeRepository;
    private CustomerActivityRepository $activityRepository;

    public function __construct(
        ?StoreRepository $storeRepository = null,
        ?CustomerActivityRepository $activityRepository = null
    ) {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
    }

    /**
     * Retrieve aggregated dashboard statistics for a given store and time period.
     */
    public function getOverview(
        int $storeId,
        string $period = 'last_30_days',
        ?string $customAfter = null,
        ?string $customBefore = null,
        bool $forceRefresh = false
    ): array {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new \Exception("فروشگاه با شناسه {$storeId} یافت نشد.", 404);
        }

        $timezoneName = $store->timezone ?: 'Asia/Tehran';
        $range = $this->resolveDateRange($period, $timezoneName, $customAfter, $customBefore);

        $cacheKey = "overview_{$period}_" . md5(($customAfter ?? '') . '_' . ($customBefore ?? ''));
        if (!$forceRefresh) {
            $cached = Cache::storeGet($storeId, 'dashboard_stats', $cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $currency = $store->currency ?: 'IRR';
        $currencySymbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'AED',
            default => 'تومان',
        };

        // If store is not active and not demo, return an informative disconnected state
        if (!$store->isDemo() && $store->status !== 'active') {
            $result = [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'url' => $store->url,
                    'is_demo' => false,
                    'connected' => false,
                    'currency' => $currency,
                    'currency_symbol' => $currencySymbol,
                    'timezone' => $timezoneName,
                ],
                'period' => [
                    'key' => $period,
                    'label' => $range['period_label'],
                    'after' => $range['after_iso'],
                    'before' => $range['before_iso'],
                ],
                'kpis' => [
                    'total_sales' => 0.0,
                    'net_sales' => 0.0,
                    'orders_count' => 0,
                    'customers_count' => 0,
                    'products_count' => 0,
                    'pending_orders' => 0,
                    'processing_orders' => 0,
                    'completed_orders' => 0,
                    'low_stock_count' => 0,
                    'sales_change_percent' => null,
                    'orders_change_percent' => null,
                ],
                'sales_chart' => [],
                'order_statuses' => [],
                'recent_orders' => [],
                'recent_customers' => [],
                'top_customers' => [],
                'low_stock_products' => [],
                'recent_activities' => $this->activityRepository->listStoreActivities($storeId, ['limit' => 8]),
            ];

            return $result;
        }

        $localSync = new LocalSyncService();
        $localOrdersCount = $localSync->getLocalTableCount($storeId, 'orders');
        $localProductsCount = $localSync->getLocalTableCount($storeId, 'products');

        if (!$forceRefresh && ($localOrdersCount > 0 || $localProductsCount > 0)) {
            $data = $localSync->getLocalDashboardOverview($store, $range);
        } else {
            $adapter = WooCommerceAdapterFactory::create($store);

            if ($adapter instanceof DemoWooCommerceAdapter) {
                $data = $this->calculateDemoStats($store, $adapter, $range);
            } else {
                $data = $this->calculateLiveWooStats($store, $adapter, $range);
            }
        }

        $syncMeta = $localSync->getSyncState($storeId, 'orders');
        $data['sync_meta'] = $syncMeta;

        $data['store'] = [
            'id' => $store->id,
            'name' => $store->name,
            'url' => $store->url,
            'is_demo' => (bool)$store->isDemo(),
            'connected' => true,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
            'timezone' => $timezoneName,
        ];

        $data['period'] = [
            'key' => $period,
            'label' => $range['period_label'],
            'after' => $range['after_iso'],
            'before' => $range['before_iso'],
        ];

        // Enrich with recent CRM / Audit activities
        $data['recent_activities'] = $this->activityRepository->listStoreActivities($storeId, ['limit' => 8]);

        // Cache for 60 seconds
        Cache::storeSet($storeId, 'dashboard_stats', $cacheKey, $data, 60);

        return $data;
    }

    /**
     * Resolve date ranges with previous period for comparisons.
     */
    private function resolveDateRange(
        string $period,
        string $timezoneName,
        ?string $customAfter = null,
        ?string $customBefore = null
    ): array {
        try {
            $tz = new DateTimeZone($timezoneName);
        } catch (Throwable) {
            $tz = new DateTimeZone('Asia/Tehran');
        }

        $now = new DateTime('now', $tz);
        $periodLabel = '۳۰ روز اخیر';

        switch ($period) {
            case 'today':
                $start = (clone $now)->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                $prevStart = (clone $start)->modify('-1 day');
                $prevEnd = (clone $end)->modify('-1 day');
                $periodLabel = 'امروز';
                break;

            case 'last_7_days':
                $start = (clone $now)->modify('-6 days')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                $prevStart = (clone $start)->modify('-7 days');
                $prevEnd = (clone $start)->modify('-1 second');
                $periodLabel = '۷ روز اخیر';
                break;

            case 'last_30_days':
                $start = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                $prevStart = (clone $start)->modify('-30 days');
                $prevEnd = (clone $start)->modify('-1 second');
                $periodLabel = '۳۰ روز اخیر';
                break;

            case 'last_90_days':
                $start = (clone $now)->modify('-89 days')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                $prevStart = (clone $start)->modify('-90 days');
                $prevEnd = (clone $start)->modify('-1 second');
                $periodLabel = '۹۰ روز اخیر';
                break;

            case 'custom':
                if (!empty($customAfter)) {
                    $start = new DateTime($customAfter, $tz);
                    $start->setTime(0, 0, 0);
                } else {
                    $start = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
                }
                if (!empty($customBefore)) {
                    $end = new DateTime($customBefore, $tz);
                    $end->setTime(23, 59, 59);
                } else {
                    $end = (clone $now)->setTime(23, 59, 59);
                }

                $diffDays = max(1, (int)$start->diff($end)->format('%a'));
                $prevStart = (clone $start)->modify("-{$diffDays} days");
                $prevEnd = (clone $start)->modify('-1 second');
                $periodLabel = 'بازه دلخواه';
                break;

            default:
                $start = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                $prevStart = (clone $start)->modify('-30 days');
                $prevEnd = (clone $start)->modify('-1 second');
                $periodLabel = '۳۰ روز اخیر';
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'prev_start' => $prevStart,
            'prev_end' => $prevEnd,
            'after_iso' => $start->format('Y-m-d\TH:i:s'),
            'before_iso' => $end->format('Y-m-d\TH:i:s'),
            'prev_after_iso' => $prevStart->format('Y-m-d\TH:i:s'),
            'prev_before_iso' => $prevEnd->format('Y-m-d\TH:i:s'),
            'period_label' => $periodLabel,
        ];
    }

    /**
     * Compute statistics for a Demo WooCommerce store.
     */
    private function calculateDemoStats(Store $store, DemoWooCommerceAdapter $adapter, array $range): array
    {
        $allOrdersRes = $adapter->listOrders(['per_page' => 100]);
        $allOrders = $allOrdersRes['data'] ?? [];

        $allCustomersRes = $adapter->listCustomers(['per_page' => 100]);
        $allCustomers = $allCustomersRes['data'] ?? [];

        $allProductsRes = $adapter->listProducts(['per_page' => 100]);
        $allProducts = $allProductsRes['data'] ?? [];

        $lowStock = $adapter->getLowStockProducts(6);
        $outOfStock = $adapter->getOutOfStockProducts(6);
        $inventoryAlerts = array_merge($lowStock, $outOfStock);

        $startTimestamp = $range['start']->getTimestamp();
        $endTimestamp = $range['end']->getTimestamp();
        $prevStartTimestamp = $range['prev_start']->getTimestamp();
        $prevEndTimestamp = $range['prev_end']->getTimestamp();

        $currentOrders = [];
        $previousOrders = [];

        foreach ($allOrders as $o) {
            $createdTs = !empty($o['date_created']) ? strtotime($o['date_created']) : 0;
            if ($createdTs >= $startTimestamp && $createdTs <= $endTimestamp) {
                $currentOrders[] = $o;
            } elseif ($createdTs >= $prevStartTimestamp && $createdTs <= $prevEndTimestamp) {
                $previousOrders[] = $o;
            }
        }

        // If demo orders are sparse in the selected date range, use up to 25 recent demo orders
        // so demo testing always gives realistic numbers and curves
        if (empty($currentOrders)) {
            $currentOrders = array_slice($allOrders, 0, 25);
        }

        $totalSales = 0.0;
        $netSales = 0.0;
        $ordersCount = count($currentOrders);
        $statusCounts = [];

        $timeline = [];
        // Initialize timeline days between start and end (max 31 points)
        $curDate = clone $range['start'];
        $diffDays = (int)$range['start']->diff($range['end'])->format('%a');
        $stepDays = max(1, (int)ceil($diffDays / 30));

        while ($curDate <= $range['end']) {
            $dStr = $curDate->format('Y-m-d');
            $timeline[$dStr] = ['date' => $dStr, 'sales' => 0.0, 'orders' => 0];
            $curDate->modify("+{$stepDays} days");
        }

        foreach ($currentOrders as $ord) {
            $st = $ord['status'] ?? 'pending';
            $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;

            $amt = (float)($ord['total'] ?? 0.0);
            if (in_array($st, ['completed', 'processing', 'on-hold'], true)) {
                $totalSales += $amt;
                if ($st === 'completed' || $st === 'processing') {
                    $netSales += $amt;
                }
            }

            $dateKey = !empty($ord['date_created']) ? substr($ord['date_created'], 0, 10) : '';
            if (isset($timeline[$dateKey])) {
                $timeline[$dateKey]['sales'] += $amt;
                $timeline[$dateKey]['orders'] += 1;
            } else {
                // If date was outside initialized points, attach to nearest or add
                $timeline[$dateKey] = [
                    'date' => $dateKey ?: $range['start']->format('Y-m-d'),
                    'sales' => ($timeline[$dateKey]['sales'] ?? 0.0) + $amt,
                    'orders' => ($timeline[$dateKey]['orders'] ?? 0) + 1,
                ];
            }
        }

        ksort($timeline);
        $salesChart = array_values($timeline);

        // Previous period metrics for comparison
        $prevTotalSales = 0.0;
        foreach ($previousOrders as $pOrd) {
            $st = $pOrd['status'] ?? '';
            if (in_array($st, ['completed', 'processing'], true)) {
                $prevTotalSales += (float)($pOrd['total'] ?? 0.0);
            }
        }
        $prevOrdersCount = count($previousOrders);

        $salesChangePercent = null;
        if ($prevTotalSales > 0) {
            $salesChangePercent = round((($totalSales - $prevTotalSales) / $prevTotalSales) * 100, 1);
        }

        $ordersChangePercent = null;
        if ($prevOrdersCount > 0) {
            $ordersChangePercent = round((($ordersCount - $prevOrdersCount) / $prevOrdersCount) * 100, 1);
        }

        // Build status breakdown
        $orderStatuses = [];
        $knownStatuses = OrderStatusResolver::getStatuses((int)$store->id);
        foreach ($knownStatuses as $slug => $label) {
            $cnt = $statusCounts[$slug] ?? 0;
            if ($cnt > 0 || in_array($slug, ['completed', 'processing', 'pending', 'cancelled'], true)) {
                $orderStatuses[] = [
                    'status' => $slug,
                    'label' => $label,
                    'count' => $cnt,
                    'percentage' => $ordersCount > 0 ? round(($cnt / $ordersCount) * 100, 1) : 0,
                    'color' => $this->getStatusColor($slug),
                ];
            }
        }

        // Top customers by total spent
        $sortedCustomers = $allCustomers;
        usort($sortedCustomers, fn($a, $b) => ((float)($b['total_spent'] ?? 0)) <=> ((float)($a['total_spent'] ?? 0)));
        $topCustomers = array_slice($sortedCustomers, 0, 5);

        // Recent customers
        $recentCustomers = array_slice($allCustomers, 0, 5);

        return [
            'kpis' => [
                'total_sales' => $totalSales,
                'net_sales' => $netSales,
                'orders_count' => $ordersCount,
                'customers_count' => count($allCustomers),
                'products_count' => count($allProducts),
                'pending_orders' => ($statusCounts['pending'] ?? 0) + ($statusCounts['on-hold'] ?? 0),
                'processing_orders' => $statusCounts['processing'] ?? 0,
                'completed_orders' => $statusCounts['completed'] ?? 0,
                'low_stock_count' => count($inventoryAlerts),
                'sales_change_percent' => $salesChangePercent,
                'orders_change_percent' => $ordersChangePercent,
            ],
            'sales_chart' => $salesChart,
            'order_statuses' => $orderStatuses,
            'recent_orders' => array_slice($currentOrders, 0, 8),
            'recent_customers' => $recentCustomers,
            'top_customers' => $topCustomers,
            'low_stock_products' => array_slice($inventoryAlerts, 0, 6),
        ];
    }

    /**
     * Compute statistics for a Live WooCommerce store via REST API.
     */
    private function calculateLiveWooStats(Store $store, $adapter, array $range): array
    {
        $creds = $store->getDecryptedCredentials();
        $client = new WooCommerceClient(
            $store->url,
            $creds['consumer_key'] ?? '',
            $creds['consumer_secret'] ?? '',
            15
        );

        $salesChart = [];
        $totalSales = 0.0;
        $netSales = 0.0;
        $ordersCount = 0;
        $salesChangePercent = null;
        $ordersChangePercent = null;

        $dateMin = $range['start']->format('Y-m-d');
        $dateMax = $range['end']->format('Y-m-d');

        // 1. Try WooCommerce reports/sales
        try {
            $reports = $client->get('reports/sales', [
                'date_min' => $dateMin,
                'date_max' => $dateMax,
            ]);

            if (is_array($reports) && !empty($reports[0])) {
                $rep = $reports[0];
                $totalSales = (float)($rep['total_sales'] ?? 0.0);
                $netSales = (float)($rep['net_sales'] ?? 0.0);
                $ordersCount = (int)($rep['total_orders'] ?? 0);

                if (!empty($rep['totals']) && is_array($rep['totals'])) {
                    foreach ($rep['totals'] as $d => $val) {
                        $salesChart[] = [
                            'date' => (string)$d,
                            'sales' => (float)($val['sales'] ?? 0.0),
                            'orders' => (int)($val['orders'] ?? 0),
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            Logger::warning("WooCommerce reports/sales failed, falling back to orders query", [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }

        // If reports/sales was empty or failed, fetch orders directly
        if (empty($salesChart)) {
            try {
                $ordersRes = $adapter->listOrders([
                    'after' => $range['after_iso'],
                    'before' => $range['before_iso'],
                    'per_page' => 100,
                ]);
                $orders = $ordersRes['data'] ?? [];
                $ordersCount = count($orders);

                $timeline = [];
                foreach ($orders as $ord) {
                    $amt = (float)($ord['total'] ?? 0.0);
                    $st = $ord['status'] ?? '';
                    if (in_array($st, ['completed', 'processing', 'on-hold'], true)) {
                        $totalSales += $amt;
                        if ($st === 'completed' || $st === 'processing') {
                            $netSales += $amt;
                        }
                    }

                    $dKey = !empty($ord['date_created']) ? substr($ord['date_created'], 0, 10) : '';
                    if ($dKey) {
                        if (!isset($timeline[$dKey])) {
                            $timeline[$dKey] = ['date' => $dKey, 'sales' => 0.0, 'orders' => 0];
                        }
                        $timeline[$dKey]['sales'] += $amt;
                        $timeline[$dKey]['orders'] += 1;
                    }
                }
                ksort($timeline);
                $salesChart = array_values($timeline);
            } catch (Throwable $e) {
                Logger::error("Direct orders fetch failed for dashboard", ['error' => $e->getMessage()]);
            }
        }

        // 2. Fetch order statuses breakdown
        $statusCounts = [];
        try {
            $totals = $client->get('reports/orders/totals');
            if (is_array($totals)) {
                foreach ($totals as $item) {
                    if (!empty($item['slug'])) {
                        $statusCounts[$item['slug']] = (int)($item['total'] ?? 0);
                    }
                }
            }
        } catch (Throwable) {
            // Fallback
        }

        $orderStatuses = [];
        $knownStatuses = OrderStatusResolver::getStatuses((int)$store->id);
        $totalStatusOrders = array_sum($statusCounts);
        foreach ($knownStatuses as $slug => $label) {
            $cnt = $statusCounts[$slug] ?? 0;
            if ($cnt > 0 || in_array($slug, ['completed', 'processing', 'pending', 'cancelled'], true)) {
                $orderStatuses[] = [
                    'status' => $slug,
                    'label' => $label,
                    'count' => $cnt,
                    'percentage' => $totalStatusOrders > 0 ? round(($cnt / $totalStatusOrders) * 100, 1) : 0,
                    'color' => $this->getStatusColor($slug),
                ];
            }
        }

        // 3. Customers and Products total counts
        $customersCount = 0;
        $recentCustomers = [];
        try {
            $custRes = $adapter->listCustomers([
                'page' => 1,
                'per_page' => 5,
                'orderby' => 'registered_date',
                'order' => 'desc',
            ]);
            $customersCount = (int)($custRes['meta']['total'] ?? 0);
            $recentCustomers = $custRes['data'] ?? [];
        } catch (Throwable) {
            //
        }

        $productsCount = 0;
        try {
            $prodRes = $adapter->listProducts(['page' => 1, 'per_page' => 1]);
            $productsCount = (int)($prodRes['meta']['total'] ?? 0);
        } catch (Throwable) {
            //
        }

        // 4. Low stock products
        $lowStockProducts = [];
        try {
            $lowStockProducts = $adapter->getLowStockProducts(6);
        } catch (Throwable) {
            //
        }

        // 5. Recent orders
        $recentOrders = [];
        try {
            $recentOrdersRes = $adapter->listOrders(['page' => 1, 'per_page' => 6]);
            $recentOrders = $recentOrdersRes['data'] ?? [];
        } catch (Throwable) {
            //
        }

        return [
            'kpis' => [
                'total_sales' => $totalSales,
                'net_sales' => $netSales,
                'orders_count' => $ordersCount,
                'customers_count' => $customersCount,
                'products_count' => $productsCount,
                'pending_orders' => ($statusCounts['pending'] ?? 0) + ($statusCounts['on-hold'] ?? 0),
                'processing_orders' => $statusCounts['processing'] ?? 0,
                'completed_orders' => $statusCounts['completed'] ?? 0,
                'low_stock_count' => count($lowStockProducts),
                'sales_change_percent' => $salesChangePercent,
                'orders_change_percent' => $ordersChangePercent,
            ],
            'sales_chart' => $salesChart,
            'order_statuses' => $orderStatuses,
            'recent_orders' => $recentOrders,
            'recent_customers' => $recentCustomers,
            'top_customers' => $recentCustomers,
            'low_stock_products' => $lowStockProducts,
        ];
    }

    /**
     * Map order status slug to semantic UI color.
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'emerald',
            'processing' => 'blue',
            'pending', 'on-hold' => 'amber',
            'cancelled' => 'slate',
            'refunded', 'failed' => 'rose',
            default => 'indigo',
        };
    }
}
