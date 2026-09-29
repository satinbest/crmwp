<?php

namespace App\Integrations\WooCommerce;

use App\Models\Store;
use App\Services\Inventory\InventoryService;

class WooCommerceApiAdapter implements WooCommerceAdapterInterface
{
    private Store $store;
    private WooCommerceClient $client;
    private CustomerAdapter $customerAdapter;
    private OrderAdapter $orderAdapter;
    private ProductAdapter $productAdapter;
    private WebhookAdapter $webhookAdapter;
    private CapabilityDetector $capabilityDetector;

    public function __construct(Store $store)
    {
        $this->store = $store;
        $creds = $store->getDecryptedCredentials();
        $this->client = new WooCommerceClient(
            $store->url,
            $creds['consumer_key'],
            $creds['consumer_secret']
        );
        $this->customerAdapter = new CustomerAdapter($this->client, (int)$store->id);
        $this->orderAdapter = new OrderAdapter($this->client, (int)$store->id);
        $this->productAdapter = new ProductAdapter($this->client);
        $this->webhookAdapter = new WebhookAdapter($this->client);
        $this->capabilityDetector = new CapabilityDetector($this->client);
    }

    public function getStoreId(): int
    {
        return (int)$this->store->id;
    }

    public function testConnection(): array
    {
        $caps = $this->detectCapabilities();

        if (!$caps['rest_api_available']) {
            throw new WooCommerceApiException(
                "اتصال به فروشگاه برقرار نشد یا REST API ووکامرس فعال نیست.",
                'API_UNAVAILABLE',
                503
            );
        }

        return [
            'connected' => true,
            'message' => 'اتصال به فروشگاه ووکامرس با موفقیت برقرار شد.',
            'woocommerce_version' => $caps['woocommerce_version'] ?? 'نامشخص',
            'wordpress_version' => $caps['wordpress_version'] ?? 'نامشخص',
            'hpos_enabled' => (bool)$caps['hpos_enabled'],
            'currency' => $caps['currency'] ?? ($this->store->currency ?: 'IRR'),
            'currency_symbol' => $caps['currency_symbol'] ?? '﷼',
            'timezone' => $caps['timezone'] ?? ($this->store->timezone ?: 'Asia/Tehran'),
            'capabilities' => $caps,
            'is_demo' => false,
            'tested_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function detectCapabilities(): array
    {
        return $this->capabilityDetector->detect();
    }

    // Customers
    public function listCustomers(array $params = []): array
    {
        return $this->customerAdapter->listCustomers($params);
    }

    public function getCustomer(int $id): ?array
    {
        return $this->customerAdapter->getCustomer($id);
    }

    public function getCustomerOrders(int $customerId, array $params = []): array
    {
        return $this->customerAdapter->getCustomerOrders($customerId, $params);
    }

    // Orders
    public function listOrders(array $params = []): array
    {
        return $this->orderAdapter->listOrders($params);
    }

    public function getOrder(int $id): ?array
    {
        return $this->orderAdapter->getOrder($id);
    }

    public function updateOrderStatus(int $id, string $status): array
    {
        return $this->orderAdapter->updateStatus($id, $status);
    }

    public function addOrderNote(int $id, string $note, bool $isCustomerNote = false): array
    {
        return $this->orderAdapter->addNote($id, $note, $isCustomerNote);
    }

    public function refundOrder(int $id, array $data): array
    {
        return $this->orderAdapter->refund($id, $data);
    }

    // Products
    public function listProducts(array $params = []): array
    {
        return $this->productAdapter->listProducts($params);
    }

    public function getProduct(int $id): ?array
    {
        return $this->productAdapter->getProduct($id);
    }

    public function createProduct(array $data): array
    {
        return $this->productAdapter->createProduct($data);
    }

    public function updateProduct(int $id, array $data): array
    {
        return $this->productAdapter->updateProduct($id, $data);
    }

    public function deleteProduct(int $id, bool $force = false): array
    {
        return $this->productAdapter->deleteProduct($id, $force);
    }

    public function listVariations(int $productId, array $params = []): array
    {
        return $this->productAdapter->listVariations($productId, $params);
    }

    public function getVariation(int $productId, int $variationId): ?array
    {
        return $this->productAdapter->getVariation($productId, $variationId);
    }

    public function updateVariation(int $productId, int $variationId, array $data): array
    {
        return $this->productAdapter->updateVariation($productId, $variationId, $data);
    }

    // Inventory
    public function getInventoryMetrics(): array
    {
        // Compute from products
        $prods = $this->productAdapter->listProducts(['per_page' => 100]);
        $items = $prods['data'] ?? [];
        $total = $prods['meta']['total'] ?? count($items);
        $inStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        foreach ($items as $p) {
            $qty = (int)($p['stock_quantity'] ?? 0);
            $status = $p['stock_status'] ?? 'instock';
            if ($status === 'outofstock' || $qty <= 0) {
                $outOfStock++;
            } elseif ($qty <= ($p['low_stock_amount'] ?? 5)) {
                $lowStock++;
            } else {
                $inStock++;
            }
        }

        return [
            'total_products' => $total,
            'instock_count' => $inStock,
            'low_stock_count' => $lowStock,
            'outofstock_count' => $outOfStock,
            'store_id' => (int)$this->store->id,
            'store_name' => $this->store->name,
            'currency' => $this->store->currency ?: 'IRR',
        ];
    }

    public function getLowStockProducts(int $limit = 20): array
    {
        $prods = $this->productAdapter->listProducts(['per_page' => 100]);
        $items = $prods['data'] ?? [];
        $result = [];
        foreach ($items as $p) {
            $qty = (int)($p['stock_quantity'] ?? 0);
            $lowThreshold = (int)($p['low_stock_amount'] ?? 5);
            if ($qty > 0 && $qty <= $lowThreshold) {
                $result[] = [
                    'product_id' => $p['id'],
                    'product_name' => $p['name'],
                    'sku' => $p['sku'],
                    'stock_quantity' => $qty,
                    'low_stock_amount' => $lowThreshold,
                    'price' => $p['price'],
                ];
            }
        }
        return array_slice($result, 0, $limit);
    }

    public function getOutOfStockProducts(int $limit = 20): array
    {
        $prods = $this->productAdapter->listProducts(['stock_status' => 'outofstock', 'per_page' => $limit]);
        $items = $prods['data'] ?? [];
        return array_map(function($p) {
            return [
                'product_id' => $p['id'],
                'product_name' => $p['name'],
                'sku' => $p['sku'],
                'stock_quantity' => 0,
                'price' => $p['price'],
            ];
        }, $items);
    }

    public function updateStock(int $productId, int $quantity, ?string $stockStatus = null): array
    {
        $data = ['stock_quantity' => $quantity, 'manage_stock' => true];
        if ($stockStatus) {
            $data['stock_status'] = $stockStatus;
        }
        return $this->productAdapter->updateProduct($productId, $data);
    }

    // Taxonomies
    public function getCategories(array $params = []): array
    {
        return $this->productAdapter->getCategories($params);
    }

    public function getTags(array $params = []): array
    {
        return $this->productAdapter->getTags($params);
    }

    public function getAttributes(): array
    {
        return $this->productAdapter->getAttributes();
    }

    // Webhooks
    public function listWebhooks(array $params = []): array
    {
        return $this->webhookAdapter->listWebhooks($params);
    }

    public function createWebhook(array $data): array
    {
        return $this->webhookAdapter->createWebhook($data);
    }

    public function deleteWebhook(int $webhookId): bool
    {
        return $this->webhookAdapter->deleteWebhook($webhookId);
    }
}
