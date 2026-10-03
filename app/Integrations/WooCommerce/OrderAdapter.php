<?php

namespace App\Integrations\WooCommerce;

use App\Models\Store;
use App\Support\Cache;
use App\Support\Logger;
use Exception;

class OrderAdapter
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
     * List orders from WooCommerce with filters, search, and pagination.
     */
    public function listOrders(array $params = []): array
    {
        if ($this->demoAdapter !== null) {
            $demoRes = $this->demoAdapter->listOrders($params);
            $demoRes['data'] = OrderNormalizer::normalizeCollection($demoRes['data'], $this->storeId);
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

        // Status filter
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $wcParams['status'] = $params['status'];
        }

        // Customer filter
        if (!empty($params['customer'])) {
            $wcParams['customer'] = (int)$params['customer'];
        }

        // Date range filters (ISO 8601)
        if (!empty($params['after'])) {
            $wcParams['after'] = $params['after'];
        }
        if (!empty($params['before'])) {
            $wcParams['before'] = $params['before'];
        }

        // Sorting
        if (!empty($params['sort'])) {
            $wcParams['orderby'] = match ($params['sort']) {
                'date', 'date_created' => 'date',
                'id' => 'id',
                'title' => 'title',
                default => 'date',
            };
        }
        if (!empty($params['direction'])) {
            $dir = strtolower($params['direction']);
            $wcParams['order'] = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
        }

        $fetcher = function () use ($wcParams, $page, $perPage) {
            $response = $this->client->getWithHeaders('/orders', $wcParams);
            $rawList = is_array($response['data']) ? $response['data'] : [];
            $headers = $response['headers'] ?? [];

            $total = isset($headers['x-wp-total']) ? (int)$headers['x-wp-total'] : count($rawList);
            $totalPages = isset($headers['x-wp-totalpages']) ? (int)$headers['x-wp-totalpages'] : (int)ceil($total / $perPage);

            $normalized = OrderNormalizer::normalizeCollection($rawList, $this->storeId);

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
            return Cache::storeRememberQuery($this->storeId, 'orders', $wcParams, Cache::TTL_ORDERS, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Retrieve single order details by ID from WooCommerce.
     */
    public function getOrder(int $orderId): ?array
    {
        if ($this->demoAdapter !== null) {
            $raw = $this->demoAdapter->getOrder($orderId);
            return $raw ? OrderNormalizer::normalize($raw, $this->storeId) : null;
        }

        $fetcher = function () use ($orderId) {
            try {
                $raw = $this->client->get("/orders/{$orderId}");
                if (empty($raw) || !is_array($raw) || empty($raw['id'])) {
                    return null;
                }

                return OrderNormalizer::normalize($raw, $this->storeId);
            } catch (WooCommerceApiException $e) {
                if ($e->getHttpStatus() === 404) {
                    return null;
                }
                throw $e;
            }
        };

        if ($this->storeId > 0) {
            return Cache::storeRemember($this->storeId, 'order', (string)$orderId, Cache::TTL_ORDERS, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Update order status in WooCommerce.
     */
    public function updateStatus(int $orderId, string $status): array
    {
        if ($this->storeId > 0) {
            Cache::forgetStoreResource($this->storeId, 'orders');
            Cache::forgetStoreResource($this->storeId, 'dashboard');
            Cache::forgetStoreResource($this->storeId, 'reports');
            Cache::forgetStoreResource($this->storeId, 'crm_summary');
            Cache::forget(Cache::storeKey($this->storeId, 'order', (string)$orderId));
        }

        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->updateOrderStatus($orderId, $status);
        }

        $raw = $this->client->put("/orders/{$orderId}", [
            'status' => $status,
        ]);

        return OrderNormalizer::normalize($raw, $this->storeId);
    }

    /**
     * Update order details in WooCommerce.
     */
    public function updateOrder(int $orderId, array $data): array
    {
        if ($this->storeId > 0) {
            Cache::forgetStoreResource($this->storeId, 'orders');
            Cache::forgetStoreResource($this->storeId, 'dashboard');
            Cache::forgetStoreResource($this->storeId, 'reports');
            Cache::forgetStoreResource($this->storeId, 'crm_summary');
            Cache::forget(Cache::storeKey($this->storeId, 'order', (string)$orderId));
        }

        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->updateOrderStatus($orderId, $data['status'] ?? 'processing');
        }

        $raw = $this->client->put("/orders/{$orderId}", $data);
        return OrderNormalizer::normalize($raw, $this->storeId);
    }

    /**
     * Batch create, update or delete orders in WooCommerce.
     */
    public function batch(array $payload): array
    {
        if ($this->demoAdapter !== null) {
            $updated = [];
            foreach ($payload['update'] ?? [] as $item) {
                if (!empty($item['id'])) {
                    $id = (int)$item['id'];
                    if (isset($item['status'])) {
                        $updated[] = $this->updateStatus($id, $item['status']);
                    }
                }
            }
            return ['update' => $updated];
        }

        return $this->client->post('/orders/batch', $payload);
    }

    /**
     * Get notes for an order from WooCommerce.
     */
    public function listOrderNotes(int $orderId): array
    {
        if ($this->demoAdapter !== null) {
            return [
                [
                    'id' => 1,
                    'author' => 'سیستم فروشگاه',
                    'date_created' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'note' => 'وضعیت سفارش به در حال انجام تغییر یافت.',
                    'customer_note' => false,
                ]
            ];
        }

        try {
            $rawNotes = $this->client->get("/orders/{$orderId}/notes");
            if (!is_array($rawNotes)) {
                return [];
            }

            return array_map(function ($n) {
                return [
                    'id' => (int)($n['id'] ?? 0),
                    'author' => (string)($n['author'] ?? 'سیستم ووکامرس'),
                    'date_created' => $n['date_created'] ?? null,
                    'note' => (string)($n['note'] ?? ''),
                    'customer_note' => (bool)($n['customer_note'] ?? false),
                ];
            }, $rawNotes);
        } catch (WooCommerceApiException $e) {
            if ($e->getHttpStatus() === 404) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Add a note to an order in WooCommerce.
     */
    public function createOrderNote(int $orderId, string $note, bool $customerNote = false): array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->addOrderNote($orderId, $note, $customerNote);
        }

        $raw = $this->client->post("/orders/{$orderId}/notes", [
            'note' => $note,
            'customer_note' => $customerNote,
        ]);

        return [
            'id' => (int)($raw['id'] ?? 0),
            'author' => (string)($raw['author'] ?? 'کاربر CRM'),
            'date_created' => $raw['date_created'] ?? date('Y-m-d H:i:s'),
            'note' => (string)($raw['note'] ?? $note),
            'customer_note' => (bool)($raw['customer_note'] ?? $customerNote),
        ];
    }

    /**
     * Process an order refund in WooCommerce.
     */
    public function createRefund(int $orderId, float $amount, string $reason = '', bool $apiRefund = true, array $lineItems = []): array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->refundOrder($orderId, ['amount' => $amount, 'reason' => $reason]);
        }

        $payload = [
            'amount' => (string)$amount,
            'reason' => $reason,
            'api_refund' => $apiRefund,
        ];

        if (!empty($lineItems)) {
            $payload['line_items'] = $lineItems;
        }

        $raw = $this->client->post("/orders/{$orderId}/refunds", $payload);

        return [
            'id' => (int)($raw['id'] ?? 0),
            'amount' => abs((float)($raw['amount'] ?? $amount)),
            'reason' => (string)($raw['reason'] ?? $reason),
            'date_created' => $raw['date_created'] ?? date('Y-m-d H:i:s'),
            'refunded_by' => (int)($raw['refunded_by'] ?? 0),
        ];
    }

    public function refund(int $orderId, array $data): array
    {
        return $this->createRefund(
            $orderId,
            (float)($data['amount'] ?? 0),
            (string)($data['reason'] ?? ''),
            (bool)($data['api_refund'] ?? true),
            $data['line_items'] ?? []
        );
    }
}
