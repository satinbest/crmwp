<?php

namespace App\Services;

use App\Integrations\WooCommerce\CustomerAdapter;
use App\Integrations\WooCommerce\OrderAdapter;
use App\Integrations\WooCommerce\ProductAdapter;
use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Repositories\StoreRepository;
use App\Repositories\SyncLogRepository;
use App\Repositories\WebhookLogRepository;
use App\Support\Cache;
use App\Support\Logger;
use DateTime;
use DateTimeZone;
use Exception;

class WooCommerceReconciliationService
{
    private StoreRepository $storeRepository;
    private SyncLogRepository $syncLogRepository;
    private WebhookLogRepository $webhookLogRepository;

    public function __construct(
        ?StoreRepository $storeRepository = null,
        ?SyncLogRepository $syncLogRepository = null,
        ?WebhookLogRepository $webhookLogRepository = null
    ) {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->syncLogRepository = $syncLogRepository ?? new SyncLogRepository();
        $this->webhookLogRepository = $webhookLogRepository ?? new WebhookLogRepository();
    }

    private function getClient(int $storeId): WooCommerceClient
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه با شناسه {$storeId} یافت نشد.", 404);
        }

        $creds = $store->getDecryptedCredentials();
        return new WooCommerceClient(
            $store->url,
            $creds['consumer_key'],
            $creds['consumer_secret']
        );
    }

    /**
     * Reconcile WooCommerce state for a given store.
     *
     * @param int $storeId
     * @param array $options [
     *     'entity_type' => 'all' | 'orders' | 'products' | 'customers' | 'inventory',
     *     'date_range'  => '24h' | '7d' | '30d' | 'all' | 'custom',
     *     'after'       => ?string ISO 8601,
     *     'before'      => ?string ISO 8601,
     *     'batch_size'  => int (default 25),
     * ]
     * @return array
     */
    public function reconcile(int $storeId, array $options = []): array
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه با شناسه {$storeId} یافت نشد.", 404);
        }

        $entityType = $options['entity_type'] ?? 'all';
        $dateRange = $options['date_range'] ?? '24h';
        $batchSize = max(5, min(100, (int)($options['batch_size'] ?? 25)));

        // Create running sync log
        $syncLogId = $this->syncLogRepository->create([
            'store_id' => $storeId,
            'entity_type' => $entityType,
            'direction' => 'inbound_reconcile',
            'status' => 'running',
            'details' => [
                'options' => $options,
                'started_at' => date('Y-m-d H:i:s'),
            ],
        ]);

        $results = [
            'entity_type' => $entityType,
            'date_range' => $dateRange,
            'orders' => ['inspected' => 0, 'cache_cleared' => 0],
            'products' => ['inspected' => 0, 'cache_cleared' => 0],
            'customers' => ['inspected' => 0, 'cache_cleared' => 0],
            'inventory' => ['inspected' => 0, 'cache_cleared' => 0],
            'errors' => [],
        ];

        $totalProcessed = 0;
        $totalFailed = 0;

        try {
            $client = $this->getClient($storeId);

            // Reconcile Orders
            if (in_array($entityType, ['all', 'orders'], true)) {
                $orderResult = $this->reconcileOrders($client, $storeId, $store->timezone, $options, $batchSize);
                $results['orders'] = $orderResult;
                $totalProcessed += $orderResult['inspected'];
                if (!empty($orderResult['error'])) {
                    $totalFailed++;
                    $results['errors'][] = $orderResult['error'];
                }
            }

            // Reconcile Products & Inventory
            if (in_array($entityType, ['all', 'products', 'inventory'], true)) {
                $productResult = $this->reconcileProducts($client, $storeId, $options, $batchSize);
                $results['products'] = $productResult;
                $results['inventory'] = ['inspected' => $productResult['inspected']];
                $totalProcessed += $productResult['inspected'];
                if (!empty($productResult['error'])) {
                    $totalFailed++;
                    $results['errors'][] = $productResult['error'];
                }
            }

            // Reconcile Customers
            if (in_array($entityType, ['all', 'customers'], true)) {
                $customerResult = $this->reconcileCustomers($client, $storeId, $options, $batchSize);
                $results['customers'] = $customerResult;
                $totalProcessed += $customerResult['inspected'];
                if (!empty($customerResult['error'])) {
                    $totalFailed++;
                    $results['errors'][] = $customerResult['error'];
                }
            }

            // Invalidate dashboard and reports caches
            Cache::forgetByPrefix("dashboard_{$storeId}");
            Cache::forgetByPrefix("reports_{$storeId}");
            Cache::forgetByPrefix("crm_summary_{$storeId}");

            $status = $totalFailed === 0 ? 'completed' : ($totalProcessed > 0 ? 'partial' : 'failed');

            // Update store's last_sync_at
            $this->storeRepository->update($storeId, [
                'last_sync_at' => date('Y-m-d H:i:s'),
                'status' => 'active',
            ]);

            $this->syncLogRepository->update($syncLogId, [
                'status' => $status,
                'processed' => $totalProcessed,
                'failed' => $totalFailed,
                'details' => $results,
                'error_message' => !empty($results['errors']) ? implode(' | ', $results['errors']) : null,
            ]);

            return [
                'success' => $status !== 'failed',
                'status' => $status,
                'sync_log_id' => $syncLogId,
                'processed' => $totalProcessed,
                'failed' => $totalFailed,
                'details' => $results,
            ];
        } catch (Exception $e) {
            $totalFailed++;
            $results['errors'][] = $e->getMessage();

            $this->syncLogRepository->update($syncLogId, [
                'status' => 'failed',
                'processed' => $totalProcessed,
                'failed' => $totalFailed,
                'details' => $results,
                'error_message' => $e->getMessage(),
            ]);

            Logger::error("Reconciliation failed for store {$storeId}: " . $e->getMessage());

            throw $e;
        }
    }

    private function reconcileOrders(WooCommerceClient $client, int $storeId, string $timezone, array $options, int $batchSize): array
    {
        $after = $this->resolveDateFilter($options, $timezone);
        $params = [
            'per_page' => $batchSize,
            'page' => 1,
            'orderby' => 'date',
            'order' => 'desc',
        ];
        if ($after) {
            $params['after'] = $after;
        }

        $inspected = 0;
        try {
            $response = $client->requestWithHeaders('GET', '/orders', $params);
            $orders = $response['data'] ?? [];
            $inspected = count($orders);

            // Invalidate relevant order caches to ensure freshest data
            Cache::forgetByPrefix("orders_{$storeId}");
            foreach ($orders as $order) {
                if (isset($order['id'])) {
                    Cache::forgetByPrefix("order_{$storeId}_{$order['id']}");
                }
            }

            return [
                'inspected' => $inspected,
                'cache_cleared' => $inspected,
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'inspected' => $inspected,
                'error' => "Orders sync error: " . $e->getMessage(),
                'success' => false,
            ];
        }
    }

    private function reconcileProducts(WooCommerceClient $client, int $storeId, array $options, int $batchSize): array
    {
        $params = [
            'per_page' => $batchSize,
            'page' => 1,
            'orderby' => 'date',
            'order' => 'desc',
        ];

        $inspected = 0;
        try {
            $response = $client->requestWithHeaders('GET', '/products', $params);
            $products = $response['data'] ?? [];
            $inspected = count($products);

            // Invalidate products & inventory caches
            Cache::forgetByPrefix("products_{$storeId}");
            Cache::forgetByPrefix("inventory_{$storeId}");
            foreach ($products as $p) {
                if (isset($p['id'])) {
                    Cache::forgetByPrefix("product_{$storeId}_{$p['id']}");
                }
            }

            return [
                'inspected' => $inspected,
                'cache_cleared' => $inspected,
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'inspected' => $inspected,
                'error' => "Products sync error: " . $e->getMessage(),
                'success' => false,
            ];
        }
    }

    private function reconcileCustomers(WooCommerceClient $client, int $storeId, array $options, int $batchSize): array
    {
        $params = [
            'per_page' => $batchSize,
            'page' => 1,
            'role' => 'all',
        ];

        $inspected = 0;
        try {
            $response = $client->requestWithHeaders('GET', '/customers', $params);
            $customers = $response['data'] ?? [];
            $inspected = count($customers);

            // Invalidate customers & segments caches
            Cache::forgetByPrefix("customers_{$storeId}");
            Cache::forgetByPrefix("segment_{$storeId}");
            Cache::forgetByPrefix("segments_{$storeId}");
            foreach ($customers as $c) {
                if (isset($c['id'])) {
                    Cache::forgetByPrefix("customer_{$storeId}_{$c['id']}");
                }
            }

            return [
                'inspected' => $inspected,
                'cache_cleared' => $inspected,
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'inspected' => $inspected,
                'error' => "Customers sync error: " . $e->getMessage(),
                'success' => false,
            ];
        }
    }

    /**
     * Compute comprehensive Integration Health for store.
     */
    public function getIntegrationHealth(int $storeId): array
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه یافت نشد.", 404);
        }

        $webhookStats = $this->webhookLogRepository->getHealthStats($storeId);
        $syncStats = $this->syncLogRepository->getHealthStats($storeId);

        return [
            'store_id' => $storeId,
            'store_name' => $store->name,
            'store_url' => $store->url,
            'status' => $store->status,
            'woocommerce_version' => $store->woocommerce_version ?: $store->wc_version,
            'wordpress_version' => $store->wordpress_version ?: $store->wp_version,
            'last_connection_check' => $store->last_connection_check,
            'is_connected' => $store->status === 'active',
            'webhook_health' => $webhookStats['health_status'],
            'total_webhooks' => $webhookStats['total_webhooks'],
            'processed_webhooks' => $webhookStats['processed_count'],
            'failed_webhooks_24h' => $webhookStats['failed_24h'],
            'last_webhook_at' => $webhookStats['last_webhook_at'],
            'last_processed_webhook_at' => $webhookStats['last_processed_at'],
            'avg_processing_time_ms' => $webhookStats['avg_processing_time_ms'],
            'last_reconciliation_at' => $syncStats['last_reconciliation_at'] ?: $store->last_sync_at,
            'total_reconciliations' => $syncStats['total_syncs'],
            'completed_reconciliations' => $syncStats['completed'],
        ];
    }

    private function resolveDateFilter(array $options, string $timezone): ?string
    {
        $range = $options['date_range'] ?? '24h';
        $tz = new DateTimeZone($timezone ?: 'Asia/Tehran');

        if ($range === '24h') {
            $dt = new DateTime('-24 hours', $tz);
            return $dt->format('Y-m-d\TH:i:s');
        } elseif ($range === '7d') {
            $dt = new DateTime('-7 days', $tz);
            return $dt->format('Y-m-d\TH:i:s');
        } elseif ($range === '30d') {
            $dt = new DateTime('-30 days', $tz);
            return $dt->format('Y-m-d\TH:i:s');
        } elseif ($range === 'custom' && !empty($options['after'])) {
            return $options['after'];
        }

        return null;
    }
}
