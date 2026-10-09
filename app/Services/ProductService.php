<?php

namespace App\Services;

use App\Integrations\WooCommerce\ProductAdapter;
use App\Repositories\CustomerActivityRepository;
use App\Repositories\StoreRepository;
use Exception;

class ProductService
{
    private StoreRepository $storeRepository;
    private CustomerActivityRepository $activityRepository;
    private AuditService $auditService;

    public function __construct(
        ?StoreRepository $storeRepository = null,
        ?CustomerActivityRepository $activityRepository = null,
        ?AuditService $auditService = null
    ) {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Resolve store and get initialized ProductAdapter.
     */
    public function getProductAdapter(int $storeId): ProductAdapter
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه با شناسه {$storeId} یافت نشد.", 404);
        }

        return new ProductAdapter($store);
    }

    /**
     * List products with search, pagination, and filters.
     */
    public function listProducts(int $storeId, array $params = []): array
    {
        $bypass = !empty($params['bypass_cache']) || !empty($params['fresh']);
        if (!$bypass) {
            $localSync = new LocalSyncService();
            $meta = $localSync->getSyncState($storeId, 'products');
            $localCount = $localSync->getLocalTableCount($storeId, 'products');

            // If local records exist or initial sync was completed, serve from local cache
            if ($localCount > 0 || ($meta['status'] === 'completed' && $meta['last_successful_sync'] !== null)) {
                return $localSync->getLocalProducts($storeId, $params);
            }
        }

        $adapter = $this->getProductAdapter($storeId);
        return $adapter->listProducts($params);
    }

    /**
     * Get a single product by ID.
     */
    public function getProduct(int $storeId, int $id): ?array
    {
        $adapter = $this->getProductAdapter($storeId);
        return $adapter->getProduct($id);
    }

    /**
     * Create a new product.
     */
    public function createProduct(int $storeId, int $userId, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new Exception("نام محصول الزامی است.", 422);
        }

        $adapter = $this->getProductAdapter($storeId);
        $product = $adapter->createProduct($data);

        // Record Activity
        $this->activityRepository->recordForProduct($storeId, $userId, 'product_created', $product['id'], [
            'name' => $product['name'],
            'type' => $product['type'],
            'sku' => $product['sku'],
            'price' => $product['price'],
        ]);

        // Record Audit Log
        $this->auditService->log(
            $userId,
            $storeId,
            'PRODUCT_CREATED',
            'product',
            (string)$product['id'],
            null,
            ['name' => $product['name'], 'sku' => $product['sku'], 'price' => $product['price']],
            $ip,
            $userAgent
        );

        return $product;
    }

    /**
     * Update an existing product.
     */
    public function updateProduct(int $storeId, int $userId, int $id, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $adapter = $this->getProductAdapter($storeId);
        $existing = $adapter->getProduct($id);
        if (!$existing) {
            throw new Exception("محصول با شناسه {$id} یافت نشد.", 404);
        }

        // Only send changed or explicitly provided fields
        $payload = [];
        $allowedFields = [
            'name', 'slug', 'type', 'status', 'featured', 'catalog_visibility',
            'description', 'short_description', 'sku', 'price', 'regular_price',
            'sale_price', 'date_on_sale_from', 'date_on_sale_to', 'manage_stock',
            'stock_quantity', 'stock_status', 'backorders', 'low_stock_amount',
            'weight', 'dimensions', 'categories', 'tags', 'images', 'attributes',
            'default_attributes', 'external_url', 'button_text', 'meta_data'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        $updated = $adapter->updateProduct($id, $payload);

        // Detect specific changes for granular activities
        $priceChanged = false;
        if (isset($payload['regular_price']) && (float)$payload['regular_price'] !== (float)$existing['regular_price']) {
            $priceChanged = true;
        }
        if (isset($payload['sale_price']) && (float)($payload['sale_price'] ?? 0) !== (float)($existing['sale_price'] ?? 0)) {
            $priceChanged = true;
        }

        $stockChanged = false;
        if (isset($payload['stock_quantity']) && (int)$payload['stock_quantity'] !== (int)$existing['stock_quantity']) {
            $stockChanged = true;
        }
        if (isset($payload['stock_status']) && $payload['stock_status'] !== $existing['stock_status']) {
            $stockChanged = true;
        }

        if ($priceChanged) {
            $this->activityRepository->recordForProduct($storeId, $userId, 'product_price_changed', $id, [
                'old_regular_price' => $existing['regular_price'],
                'new_regular_price' => $updated['regular_price'],
                'old_sale_price' => $existing['sale_price'],
                'new_sale_price' => $updated['sale_price'],
            ]);
        }

        if ($stockChanged) {
            $this->activityRepository->recordForProduct($storeId, $userId, 'product_stock_changed', $id, [
                'old_quantity' => $existing['stock_quantity'],
                'new_quantity' => $updated['stock_quantity'],
                'old_status' => $existing['stock_status'],
                'new_status' => $updated['stock_status'],
            ]);
        }

        $this->activityRepository->recordForProduct($storeId, $userId, 'product_updated', $id, [
            'changed_fields' => array_keys($payload),
        ]);

        // Record Audit Log
        $this->auditService->log(
            $userId,
            $storeId,
            'PRODUCT_UPDATED',
            'product',
            (string)$id,
            array_intersect_key($existing, $payload),
            array_intersect_key($updated, $payload),
            $ip,
            $userAgent
        );

        return $updated;
    }

    /**
     * Delete a product (trash or permanent).
     */
    public function deleteProduct(int $storeId, int $userId, int $id, bool $force = false, ?string $ip = null, ?string $userAgent = null): array
    {
        $adapter = $this->getProductAdapter($storeId);
        $existing = $adapter->getProduct($id);
        if (!$existing) {
            throw new Exception("محصول با شناسه {$id} یافت نشد.", 404);
        }

        $result = $adapter->deleteProduct($id, $force);

        $this->activityRepository->recordForProduct($storeId, $userId, 'product_deleted', $id, [
            'name' => $existing['name'],
            'force' => $force,
        ]);

        $this->auditService->log(
            $userId,
            $storeId,
            'PRODUCT_DELETED',
            'product',
            (string)$id,
            ['name' => $existing['name'], 'sku' => $existing['sku']],
            ['deleted' => true, 'force' => $force],
            $ip,
            $userAgent
        );

        return $result;
    }

    /**
     * List variations for a variable product.
     */
    public function listVariations(int $storeId, int $productId, array $params = []): array
    {
        $adapter = $this->getProductAdapter($storeId);
        return $adapter->listVariations($productId, $params);
    }

    /**
     * Get single variation.
     */
    public function getVariation(int $storeId, int $productId, int $variationId): ?array
    {
        $adapter = $this->getProductAdapter($storeId);
        return $adapter->getVariation($productId, $variationId);
    }

    /**
     * Create a variation.
     */
    public function createVariation(int $storeId, int $userId, int $productId, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $adapter = $this->getProductAdapter($storeId);
        $variation = $adapter->createVariation($productId, $data);

        $this->activityRepository->recordForProduct($storeId, $userId, 'variation_created', $productId, [
            'variation_id' => $variation['id'],
            'sku' => $variation['sku'],
            'price' => $variation['price'],
        ]);

        $this->auditService->log(
            $userId,
            $storeId,
            'PRODUCT_VARIATION_CREATED',
            'product_variation',
            (string)$variation['id'],
            null,
            ['product_id' => $productId, 'sku' => $variation['sku'], 'price' => $variation['price']],
            $ip,
            $userAgent
        );

        return $variation;
    }

    /**
     * Update a variation.
     */
    public function updateVariation(int $storeId, int $userId, int $productId, int $variationId, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $adapter = $this->getProductAdapter($storeId);
        $existing = $adapter->getVariation($productId, $variationId);
        if (!$existing) {
            throw new Exception("تنوع کالایی با شناسه {$variationId} یافت نشد.", 404);
        }

        $variation = $adapter->updateVariation($productId, $variationId, $data);

        $this->activityRepository->recordForProduct($storeId, $userId, 'variation_updated', $productId, [
            'variation_id' => $variationId,
            'changed_fields' => array_keys($data),
        ]);

        $this->auditService->log(
            $userId,
            $storeId,
            'PRODUCT_VARIATION_UPDATED',
            'product_variation',
            (string)$variationId,
            $existing,
            $variation,
            $ip,
            $userAgent
        );

        return $variation;
    }

    /**
     * Delete a variation.
     */
    public function deleteVariation(int $storeId, int $userId, int $productId, int $variationId, bool $force = false, ?string $ip = null, ?string $userAgent = null): array
    {
        $adapter = $this->getProductAdapter($storeId);
        $result = $adapter->deleteVariation($productId, $variationId, $force);

        $this->activityRepository->recordForProduct($storeId, $userId, 'variation_deleted', $productId, [
            'variation_id' => $variationId,
            'force' => $force,
        ]);

        $this->auditService->log(
            $userId,
            $storeId,
            'PRODUCT_VARIATION_DELETED',
            'product_variation',
            (string)$variationId,
            ['product_id' => $productId],
            ['deleted' => true, 'force' => $force],
            $ip,
            $userAgent
        );

        return $result;
    }

    /**
     * List categories.
     */
    public function listCategories(int $storeId, array $params = []): array
    {
        $adapter = $this->getProductAdapter($storeId);
        return $adapter->listCategories($params);
    }

    /**
     * List tags.
     */
    public function listTags(int $storeId, array $params = []): array
    {
        $adapter = $this->getProductAdapter($storeId);
        return $adapter->listTags($params);
    }

    /**
     * List attributes.
     */
    public function listAttributes(int $storeId): array
    {
        $adapter = $this->getProductAdapter($storeId);
        return $adapter->listAttributes();
    }
}
