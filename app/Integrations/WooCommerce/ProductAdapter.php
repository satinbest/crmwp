<?php

namespace App\Integrations\WooCommerce;

use App\Models\Store;
use App\Support\Cache;
use Exception;

class ProductAdapter
{
    private ?WooCommerceClient $client = null;
    private int $storeId = 0;
    private ?DemoWooCommerceAdapter $demoAdapter = null;

    public function __construct(Store|WooCommerceClient $storeOrClient)
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
            $this->storeId = 0;
        }
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * List products with pagination, search, and dynamic filters.
     */
    public function listProducts(array $params = []): array
    {
        if ($this->demoAdapter !== null) {
            $demoRes = $this->demoAdapter->listProducts($params);
            $demoRes['data'] = ProductNormalizer::normalizeCollection($demoRes['data'], $this->storeId);
            return $demoRes;
        }

        $query = [
            'page' => max(1, (int)($params['page'] ?? 1)),
            'per_page' => min(100, max(1, (int)($params['per_page'] ?? 15))),
        ];

        if (!empty($params['search'])) {
            $query['search'] = trim((string)$params['search']);
        }

        if (!empty($params['status']) && $params['status'] !== 'all') {
            $query['status'] = $params['status'];
        }

        if (!empty($params['type']) && $params['type'] !== 'all') {
            $query['type'] = $params['type'];
        }

        if (!empty($params['stock_status']) && $params['stock_status'] !== 'all') {
            $query['stock_status'] = $params['stock_status'];
        }

        if (!empty($params['sku'])) {
            $query['sku'] = trim((string)$params['sku']);
        }

        if (!empty($params['include'])) {
            if (is_array($params['include'])) {
                $query['include'] = implode(',', array_filter(array_map('intval', $params['include'])));
            } else {
                $query['include'] = trim((string)$params['include']);
            }
        }

        if (!empty($params['categories'])) {
            if (is_array($params['categories'])) {
                $query['category'] = implode(',', array_filter(array_map('intval', $params['categories'])));
            } else {
                $query['category'] = trim((string)$params['categories']);
            }
        } elseif (!empty($params['category']) && $params['category'] !== 'all') {
            if (is_array($params['category'])) {
                $query['category'] = implode(',', array_filter(array_map('intval', $params['category'])));
            } else {
                $query['category'] = trim((string)$params['category']);
            }
        }

        if (!empty($params['tag']) && $params['tag'] !== 'all') {
            $query['tag'] = (int)$params['tag'];
        }

        if (isset($params['min_price']) && $params['min_price'] !== '') {
            $query['min_price'] = (string)$params['min_price'];
        }

        if (isset($params['max_price']) && $params['max_price'] !== '') {
            $query['max_price'] = (string)$params['max_price'];
        }

        if (isset($params['featured']) && $params['featured'] !== '' && $params['featured'] !== 'all') {
            $query['featured'] = filter_var($params['featured'], FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($params['sort'])) {
            $query['orderby'] = $params['sort'];
        }

        if (!empty($params['direction'])) {
            $query['order'] = strtolower($params['direction']) === 'asc' ? 'asc' : 'desc';
        }

        $fetcher = function () use ($query) {
            $rawResponse = $this->client->getWithHeaders('/products', $query);

            $headers = $rawResponse['headers'] ?? [];
            $rawProducts = is_array($rawResponse['data'] ?? null) ? $rawResponse['data'] : [];

            $total = isset($headers['x-wp-total']) ? (int)$headers['x-wp-total'] : count($rawProducts);
            $totalPages = isset($headers['x-wp-totalpages']) ? (int)$headers['x-wp-totalpages'] : (int)ceil(max(1, $total) / $query['per_page']);

            return [
                'data' => ProductNormalizer::normalizeCollection($rawProducts, $this->storeId),
                'meta' => [
                    'page' => $query['page'],
                    'per_page' => $query['per_page'],
                    'total' => $total,
                    'total_pages' => max(1, $totalPages),
                ],
            ];
        };

        if ($this->storeId > 0) {
            return Cache::storeRememberQuery($this->storeId, 'products', $query, Cache::TTL_PRODUCTS, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Get a single normalized product by ID.
     */
    public function getProduct(int $id): ?array
    {
        if ($this->demoAdapter !== null) {
            $raw = $this->demoAdapter->getProduct($id);
            return $raw ? ProductNormalizer::normalize($raw, $this->storeId) : null;
        }

        $fetcher = function () use ($id) {
            try {
                $raw = $this->client->get("/products/{$id}");
                if (!is_array($raw) || empty($raw['id'])) {
                    return null;
                }
                return ProductNormalizer::normalize($raw, $this->storeId);
            } catch (WooCommerceApiException $e) {
                if ($e->getHttpStatus() === 404) {
                    return null;
                }
                throw $e;
            }
        };

        if ($this->storeId > 0) {
            return Cache::storeRemember($this->storeId, 'product', (string)$id, Cache::TTL_PRODUCT_ITEM, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Create a product in WooCommerce.
     */
    public function createProduct(array $data): array
    {
        if ($this->storeId > 0) {
            Cache::forgetStoreResource($this->storeId, 'products');
            Cache::forgetStoreResource($this->storeId, 'inventory');
        }

        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->createProduct($data);
        }

        $raw = $this->client->post('/products', $data);
        return ProductNormalizer::normalize($raw, $this->storeId);
    }

    /**
     * Update an existing product in WooCommerce.
     */
    public function updateProduct(int $id, array $data): array
    {
        if ($this->storeId > 0) {
            Cache::forgetStoreResource($this->storeId, 'products');
            Cache::forgetStoreResource($this->storeId, 'inventory');
            Cache::forget(Cache::storeKey($this->storeId, 'product', (string)$id));
        }

        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->updateProduct($id, $data);
        }

        $raw = $this->client->put("/products/{$id}", $data);
        return ProductNormalizer::normalize($raw, $this->storeId);
    }

    /**
     * Delete a product from WooCommerce.
     */
    public function deleteProduct(int $id, bool $force = false): array
    {
        if ($this->storeId > 0) {
            Cache::forgetStoreResource($this->storeId, 'products');
            Cache::forgetStoreResource($this->storeId, 'inventory');
            Cache::forget(Cache::storeKey($this->storeId, 'product', (string)$id));
        }

        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->deleteProduct($id, $force);
        }

        $params = $force ? ['force' => 'true'] : [];
        return $this->client->delete("/products/{$id}", $params);
    }

    /**
     * List product variations.
     */
    public function listVariations(int $productId, array $params = []): array
    {
        if ($this->demoAdapter !== null) {
            $demoVars = $this->demoAdapter->listVariations($productId, $params);
            return [
                'data' => $demoVars,
                'meta' => ['total' => count($demoVars)],
            ];
        }

        $query = [
            'page' => max(1, (int)($params['page'] ?? 1)),
            'per_page' => min(100, max(1, (int)($params['per_page'] ?? 50))),
        ];

        $rawList = $this->client->get("/products/{$productId}/variations", $query);
        if (!is_array($rawList)) {
            return ['data' => [], 'meta' => ['total' => 0]];
        }

        $normalized = array_map(function ($raw) use ($productId) {
            return ProductNormalizer::normalizeVariation($raw, $productId, $this->storeId);
        }, $rawList);

        return [
            'data' => $normalized,
            'meta' => [
                'total' => count($normalized),
            ],
        ];
    }

    /**
     * Get a single variation by ID.
     */
    public function getVariation(int $productId, int $variationId): ?array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->getVariation($productId, $variationId);
        }

        try {
            $raw = $this->client->get("/products/{$productId}/variations/{$variationId}");
            if (!is_array($raw) || empty($raw['id'])) {
                return null;
            }
            return ProductNormalizer::normalizeVariation($raw, $productId, $this->storeId);
        } catch (WooCommerceApiException $e) {
            if ($e->getHttpStatus() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Create a new variation for a variable product.
     */
    public function createVariation(int $productId, array $data): array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->updateVariation($productId, rand(100, 999), $data);
        }

        $raw = $this->client->post("/products/{$productId}/variations", $data);
        return ProductNormalizer::normalizeVariation($raw, $productId, $this->storeId);
    }

    /**
     * Update a variation.
     */
    public function updateVariation(int $productId, int $variationId, array $data): array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->updateVariation($productId, $variationId, $data);
        }

        $raw = $this->client->put("/products/{$productId}/variations/{$variationId}", $data);
        return ProductNormalizer::normalizeVariation($raw, $productId, $this->storeId);
    }

    /**
     * Delete a variation.
     */
    public function deleteVariation(int $productId, int $variationId, bool $force = false): array
    {
        if ($this->demoAdapter !== null) {
            return ['id' => $variationId, 'deleted' => true];
        }

        $params = $force ? ['force' => 'true'] : [];
        return $this->client->delete("/products/{$productId}/variations/{$variationId}", $params);
    }

    /**
     * Get product categories list.
     */
    public function getCategories(array $params = []): array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->getCategories($params);
        }

        $query = [
            'page' => max(1, (int)($params['page'] ?? 1)),
            'per_page' => min(100, max(1, (int)($params['per_page'] ?? 100))),
            'hide_empty' => false,
        ];
        if (!empty($params['search'])) {
            $query['search'] = trim((string)$params['search']);
        }

        $fetcher = function () use ($query) {
            $raw = $this->client->get('/products/categories', $query);
            return is_array($raw) ? $raw : [];
        };

        if ($this->storeId > 0) {
            return Cache::storeRememberQuery($this->storeId, 'categories', $query, Cache::TTL_CATEGORIES, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Get product tags list.
     */
    public function getTags(array $params = []): array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->getTags($params);
        }

        $query = [
            'page' => max(1, (int)($params['page'] ?? 1)),
            'per_page' => min(100, max(1, (int)($params['per_page'] ?? 100))),
            'hide_empty' => false,
        ];
        if (!empty($params['search'])) {
            $query['search'] = trim((string)$params['search']);
        }

        $fetcher = function () use ($query) {
            $raw = $this->client->get('/products/tags', $query);
            return is_array($raw) ? $raw : [];
        };

        if ($this->storeId > 0) {
            return Cache::storeRememberQuery($this->storeId, 'tags', $query, Cache::TTL_CATEGORIES, $fetcher);
        }

        return $fetcher();
    }

    /**
     * Get product attributes list.
     */
    public function getAttributes(): array
    {
        if ($this->demoAdapter !== null) {
            return $this->demoAdapter->getAttributes();
        }

        $fetcher = function () {
            $raw = $this->client->get('/products/attributes');
            return is_array($raw) ? $raw : [];
        };

        if ($this->storeId > 0) {
            return Cache::storeRemember($this->storeId, 'attributes', 'all', Cache::TTL_ATTRIBUTES, $fetcher);
        }

        return $fetcher();
    }

    public function listCategories(array $params = []): array
    {
        return $this->getCategories($params);
    }

    public function listTags(array $params = []): array
    {
        return $this->getTags($params);
    }

    public function listAttributes(): array
    {
        return $this->getAttributes();
    }

    /**
     * Batch update products.
     */
    public function batch(array $data): array
    {
        if ($this->storeId > 0) {
            Cache::forgetStoreResource($this->storeId, 'products');
            Cache::forgetStoreResource($this->storeId, 'inventory');
        }

        if ($this->demoAdapter !== null) {
            $updated = [];
            foreach ($data['update'] ?? [] as $item) {
                if (!empty($item['id'])) {
                    $id = (int)$item['id'];
                    unset($item['id']);
                    $updated[] = $this->updateProduct($id, $item);
                }
            }
            return ['update' => $updated];
        }

        $raw = $this->client->post('/products/batch', $data);
        return is_array($raw) ? $raw : [];
    }

    /**
     * Batch update variations for a variable product.
     */
    public function batchVariations(int $productId, array $data): array
    {
        if ($this->storeId > 0) {
            Cache::forgetStoreResource($this->storeId, 'products');
            Cache::forgetStoreResource($this->storeId, 'inventory');
            Cache::forget(Cache::storeKey($this->storeId, 'product', (string)$productId));
        }

        if ($this->demoAdapter !== null) {
            $updated = [];
            foreach ($data['update'] ?? [] as $item) {
                if (!empty($item['id'])) {
                    $varId = (int)$item['id'];
                    unset($item['id']);
                    $updated[] = $this->updateVariation($productId, $varId, $item);
                }
            }
            return ['update' => $updated];
        }

        $raw = $this->client->post("/products/{$productId}/variations/batch", $data);
        return is_array($raw) ? $raw : [];
    }
}
