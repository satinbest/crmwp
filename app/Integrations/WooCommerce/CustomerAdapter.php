<?php

namespace App\Integrations\WooCommerce;

use App\Models\Store;
use App\Support\Cache;
use App\Support\Logger;
use Exception;

class CustomerAdapter
{
    private ?WooCommerceClient $client = null;
    private int $storeId = 0;
    private ?DemoWooCommerceAdapter $demoAdapter = null;

    public function __construct(Store|WooCommerceClient $storeOrClient, int $storeId = 0)
    {
        if ($storeOrClient instanceof Store) {
            $this->storeId = (int)$storeOrClient->id;
            if ($storeOrClient->isDemo()) {
                $this->demoAdapter = new DemoWooCommerceAdapter($storeOrClient);
                return;
            }
            $creds = $storeOrClient->getDecryptedCredentials();
            $this->client = new WooCommerceClient(
                $storeOrClient->url,
                $creds['consumer_key'],
                $creds['consumer_secret']
            );
        } else {
            $this->client = $storeOrClient;
            $this->storeId = $storeId;
            if ($storeId > 0) {
                $repo = new \App\Repositories\StoreRepository();
                $s = $repo->findById($storeId);
                if ($s && $s->isDemo()) {
                    $this->demoAdapter = new DemoWooCommerceAdapter($s);
                }
            }
        }
    }

    /**
     * List customers from WooCommerce with pagination, sorting, and search.
     */
    public function listCustomers(array $params = []): array
    {
        if ($this->demoAdapter !== null) {
            $demoRes = $this->demoAdapter->listCustomers($params);
            $demoRes['data'] = CustomerNormalizer::normalizeCollection($demoRes['data'], $this->storeId);
            return $demoRes;
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 15)));

        $wcParams = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        // Search support
        if (!empty($params['search'])) {
            $wcParams['search'] = trim($params['search']);
        }

        // Sorting support
        if (!empty($params['sort'])) {
            $wcParams['orderby'] = match ($params['sort']) {
                'name' => 'name',
                'date_created', 'registered_date' => 'registered_date',
                'id' => 'id',
                default => 'id',
            };
        }

        if (!empty($params['direction'])) {
            $dir = strtolower($params['direction']);
            $wcParams['order'] = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
        }

        // Role filter
        if (!empty($params['role']) && $params['role'] !== 'all') {
            $wcParams['role'] = $params['role'];
        }

        $fetcher = function () use ($wcParams, $page, $perPage) {
            $response = $this->client->getWithHeaders('/customers', $wcParams);
            $rawList = is_array($response['data']) ? $response['data'] : [];
            $headers = $response['headers'] ?? [];

            $total = isset($headers['x-wp-total']) ? (int)$headers['x-wp-total'] : count($rawList);
            $totalPages = isset($headers['x-wp-totalpages']) ? (int)$headers['x-wp-totalpages'] : (int)ceil($total / $perPage);

            $normalized = CustomerNormalizer::normalizeCollection($rawList, $this->storeId);

            // Enrich customer list with real aggregated metrics directly from WooCommerce API
            $metricsService = new \App\Services\CustomerMetricsService();
            $metricsService->enrichCustomersList($this->client, $this->storeId, $normalized);

            return [
                'data' => $normalized,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ],
            ];
        };

        if ($this->storeId > 0) {
            return Cache::storeRememberQuery($this->storeId, 'customers', $wcParams, Cache::TTL_CUSTOMERS, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Retrieve single customer by ID from WooCommerce.
     */
    public function getCustomer(int $id): ?array
    {
        if ($this->demoAdapter !== null) {
            $raw = $this->demoAdapter->getCustomer($id);
            return $raw ? CustomerNormalizer::normalize($raw, $this->storeId) : null;
        }

        $fetcher = function () use ($id) {
            try {
                $raw = $this->client->get("/customers/{$id}");
                if (empty($raw) || !is_array($raw) || empty($raw['id'])) {
                    return null;
                }

                $customer = CustomerNormalizer::normalize($raw, $this->storeId);

                // Enrich single customer details with real aggregated metrics directly from WooCommerce API
                $metricsService = new \App\Services\CustomerMetricsService();
                $metrics = $metricsService->calculateForCustomer($this->client, $this->storeId, $id, $customer['email'] ?? null);
                if ($metrics['orders_count'] > 0 || (int)$customer['orders_count'] === 0) {
                    $customer['orders_count'] = $metrics['orders_count'];
                    $customer['total_spent'] = $metrics['total_spent'];
                    $customer['average_order_value'] = $metrics['average_order_value'];
                    if (!empty($metrics['last_order_date'])) {
                        $customer['last_order_date'] = $metrics['last_order_date'];
                    }
                }

                return $customer;
            } catch (WooCommerceApiException $e) {
                if ($e->getHttpStatus() === 404) {
                    return null;
                }
                throw $e;
            }
        };

        if ($this->storeId > 0) {
            return Cache::storeRemember($this->storeId, 'customer', (string)$id, Cache::TTL_CUSTOMERS, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Retrieve orders for a specific customer from WooCommerce.
     */
    public function getCustomerOrders(int $customerId, array $params = []): array
    {
        if ($this->demoAdapter !== null) {
            $demoRes = $this->demoAdapter->getCustomerOrders($customerId, $params);
            $demoRes['data'] = OrderNormalizer::normalizeCollection($demoRes['data'], $this->storeId);
            return $demoRes;
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(50, max(1, (int)($params['per_page'] ?? 10)));

        $wcParams = [
            'customer' => $customerId,
            'page' => $page,
            'per_page' => $perPage,
        ];

        try {
            $response = $this->client->getWithHeaders('/orders', $wcParams);
            $rawOrders = is_array($response['data']) ? $response['data'] : [];
            $headers = $response['headers'] ?? [];

            $total = isset($headers['x-wp-total']) ? (int)$headers['x-wp-total'] : count($rawOrders);
            $totalPages = isset($headers['x-wp-totalpages']) ? (int)$headers['x-wp-totalpages'] : (int)ceil($total / $perPage);

            $normalized = OrderNormalizer::normalizeCollection($rawOrders, $this->storeId);

            return [
                'data' => $normalized,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ],
            ];
        } catch (WooCommerceApiException $e) {
            Logger::error("Failed to fetch customer orders from WooCommerce", [
                'store_id' => $this->storeId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
