<?php

namespace App\Services\Inventory;

use App\Integrations\WooCommerce\ProductAdapter;
use App\Models\Store;
use App\Repositories\CustomerActivityRepository;
use App\Services\AuditService;
use Exception;
use InvalidArgumentException;

class InventoryService
{
    private AuditService $auditService;
    private CustomerActivityRepository $activityRepository;
    private static array $metricsCache = [];

    public function __construct(
        ?AuditService $auditService = null,
        ?CustomerActivityRepository $activityRepository = null
    ) {
        $this->auditService = $auditService ?? new AuditService();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
    }

    /**
     * List normalized inventory items with server-side filters, search and pagination.
     */
    public function listInventory(Store $store, array $filters = []): array
    {
        $adapter = new ProductAdapter($store);
        $stockStatusFilter = $filters['stock_status'] ?? 'all';
        $lowStockOnly = ($stockStatusFilter === 'low_stock');

        $wcParams = [
            'page' => max(1, (int)($filters['page'] ?? 1)),
            'per_page' => min(100, max(1, (int)($filters['per_page'] ?? 20))),
            'search' => trim((string)($filters['search'] ?? '')),
            'type' => $filters['type'] ?? 'all',
            'category' => $filters['category'] ?? 'all',
            'sort' => $filters['sort'] ?? 'date',
            'direction' => $filters['direction'] ?? 'desc',
        ];

        if ($stockStatusFilter !== 'all' && !$lowStockOnly) {
            $wcParams['stock_status'] = $stockStatusFilter;
        }

        // If filtering specifically for low_stock, we query items that are instock first
        if ($lowStockOnly) {
            $wcParams['stock_status'] = 'instock';
        }

        $res = $adapter->listProducts($wcParams);
        $products = $res['data'] ?? [];
        $meta = $res['meta'] ?? [];

        $normalizedList = [];
        foreach ($products as $prod) {
            $item = InventoryNormalizer::normalizeProduct($prod, (int)$store->id);

            // Filter manage_stock if specified
            if (isset($filters['manage_stock']) && $filters['manage_stock'] !== 'all') {
                $wantManage = filter_var($filters['manage_stock'], FILTER_VALIDATE_BOOLEAN);
                if ($item['manage_stock'] !== $wantManage) {
                    continue;
                }
            }

            // Filter quantity comparisons (gt, gte, lt, lte, eq)
            if (isset($filters['stock_op'], $filters['stock_val']) && is_numeric($filters['stock_val'])) {
                $val = (float)$filters['stock_val'];
                $qty = $item['stock_quantity'] ?? 0;
                $match = match ($filters['stock_op']) {
                    'gt' => $qty > $val,
                    'gte' => $qty >= $val,
                    'lt' => $qty < $val,
                    'lte' => $qty <= $val,
                    'eq' => $qty == $val,
                    default => true,
                };
                if (!$match) {
                    continue;
                }
            }

            // Filter low stock if requested
            if ($lowStockOnly && !$item['is_low_stock']) {
                continue;
            }

            $normalizedList[] = $item;
        }

        return [
            'data' => $normalizedList,
            'meta' => $meta,
        ];
    }

    /**
     * Get a single inventory item (product or variation).
     */
    public function getItem(Store $store, int $productId, ?int $variationId = null): ?array
    {
        $adapter = new ProductAdapter($store);
        $product = $adapter->getProduct($productId);
        if (!$product) {
            return null;
        }

        if ($variationId && $variationId > 0) {
            $variation = $adapter->getVariation($productId, $variationId);
            if (!$variation) {
                return null;
            }
            return InventoryNormalizer::normalizeVariation($variation, $product, (int)$store->id);
        }

        return InventoryNormalizer::normalizeProduct($product, (int)$store->id);
    }

    /**
     * Update stock quantity (set, increase, decrease) with race-condition prevention, audit and activity logging.
     */
    public function updateStock(
        Store $store,
        int $userId,
        int $productId,
        ?int $variationId,
        string $operation,
        float|int $amount
    ): array {
        if ($amount < 0) {
            throw new InvalidArgumentException("مقدار تغییر موجودی نمی‌تواند منفی باشد.");
        }

        $adapter = new ProductAdapter($store);
        $current = $this->getItem($store, $productId, $variationId);
        if (!$current) {
            throw new InvalidArgumentException("کالا یا تنوع مورد نظر یافت نشد.");
        }

        $oldQty = $current['stock_quantity'] ?? 0;
        $oldStatus = $current['stock_status'] ?? 'instock';

        $newQty = match ($operation) {
            'set' => (float)$amount,
            'increase' => (float)($oldQty + $amount),
            'decrease' => (float)max(0, $oldQty - $amount),
            default => throw new InvalidArgumentException("عملیات نامعتبر است: {$operation}"),
        };

        $updateData = [
            'manage_stock' => true,
            'stock_quantity' => $newQty,
        ];

        // Automatic status update if out of stock and backorders not allowed
        if ($newQty <= 0 && ($current['backorders'] ?? 'no') === 'no') {
            $updateData['stock_status'] = 'outofstock';
        } elseif ($newQty > 0 && $oldStatus === 'outofstock') {
            $updateData['stock_status'] = 'instock';
        }

        if ($variationId && $variationId > 0) {
            $adapter->updateVariation($productId, $variationId, $updateData);
        } else {
            $adapter->updateProduct($productId, $updateData);
        }

        $updated = $this->getItem($store, $productId, $variationId);
        $this->invalidateMetricsCache((int)$store->id);

        // Record Activity
        $targetEntityId = $variationId ?: $productId;
        $this->activityRepository->recordForProduct((int)$store->id, $userId, 'inventory_stock_changed', $targetEntityId, [
            'operation' => $operation,
            'amount' => $amount,
            'old_quantity' => $oldQty,
            'new_quantity' => $newQty,
            'old_status' => $oldStatus,
            'new_status' => $updated['stock_status'],
            'is_variation' => !empty($variationId),
        ]);

        // Record Audit Log
        $this->auditService->log(
            $userId,
            (int)$store->id,
            'INVENTORY_STOCK_UPDATED',
            $variationId ? 'variation' : 'product',
            (string)$targetEntityId,
            ['stock_quantity' => $oldQty, 'stock_status' => $oldStatus],
            ['stock_quantity' => $newQty, 'stock_status' => $updated['stock_status']]
        );

        return $updated;
    }

    /**
     * Update stock status (instock, outofstock, onbackorder).
     */
    public function updateStatus(
        Store $store,
        int $userId,
        int $productId,
        ?int $variationId,
        string $stockStatus
    ): array {
        $validStatuses = ['instock', 'outofstock', 'onbackorder'];
        if (!in_array($stockStatus, $validStatuses, true)) {
            throw new InvalidArgumentException("وضعیت انبار نامعتبر است: {$stockStatus}");
        }

        $adapter = new ProductAdapter($store);
        $current = $this->getItem($store, $productId, $variationId);
        if (!$current) {
            throw new InvalidArgumentException("کالا یا تنوع مورد نظر یافت نشد.");
        }

        $oldStatus = $current['stock_status'] ?? '';
        $updateData = ['stock_status' => $stockStatus];

        if ($variationId && $variationId > 0) {
            $adapter->updateVariation($productId, $variationId, $updateData);
        } else {
            $adapter->updateProduct($productId, $updateData);
        }

        $updated = $this->getItem($store, $productId, $variationId);
        $this->invalidateMetricsCache((int)$store->id);

        $targetEntityId = $variationId ?: $productId;
        $this->activityRepository->recordForProduct((int)$store->id, $userId, 'inventory_status_changed', $targetEntityId, [
            'old_status' => $oldStatus,
            'new_status' => $stockStatus,
            'is_variation' => !empty($variationId),
        ]);

        $this->auditService->log(
            $userId,
            (int)$store->id,
            'INVENTORY_STATUS_UPDATED',
            $variationId ? 'variation' : 'product',
            (string)$targetEntityId,
            ['stock_status' => $oldStatus],
            ['stock_status' => $stockStatus]
        );

        return $updated;
    }

    /**
     * Update inventory configurations (manage_stock, low_stock_amount, backorders, sold_individually).
     */
    public function updateConfiguration(
        Store $store,
        int $userId,
        int $productId,
        ?int $variationId,
        array $config
    ): array {
        $adapter = new ProductAdapter($store);
        $current = $this->getItem($store, $productId, $variationId);
        if (!$current) {
            throw new InvalidArgumentException("کالا یا تنوع مورد نظر یافت نشد.");
        }

        $updateData = [];
        $oldValues = [];

        if (array_key_exists('manage_stock', $config)) {
            $updateData['manage_stock'] = (bool)$config['manage_stock'];
            $oldValues['manage_stock'] = $current['manage_stock'];
        }

        if (array_key_exists('low_stock_amount', $config)) {
            $val = $config['low_stock_amount'];
            $updateData['low_stock_amount'] = ($val === null || $val === '') ? null : max(0, (int)$val);
            $oldValues['low_stock_amount'] = $current['low_stock_amount'];
        }

        if (array_key_exists('backorders', $config)) {
            $validBackorders = ['no', 'notify', 'yes'];
            if (!in_array($config['backorders'], $validBackorders, true)) {
                throw new InvalidArgumentException("تنظیم پیش‌خرید نامعتبر است.");
            }
            $updateData['backorders'] = $config['backorders'];
            $oldValues['backorders'] = $current['backorders'];
        }

        if (array_key_exists('sold_individually', $config) && !$variationId) {
            $updateData['sold_individually'] = (bool)$config['sold_individually'];
            $oldValues['sold_individually'] = $current['sold_individually'];
        }

        if (empty($updateData)) {
            throw new InvalidArgumentException("هیچ فیلد معتبری برای بروزرسانی پیکربندی انبار ارسال نشده است.");
        }

        if ($variationId && $variationId > 0) {
            $adapter->updateVariation($productId, $variationId, $updateData);
        } else {
            $adapter->updateProduct($productId, $updateData);
        }

        $updated = $this->getItem($store, $productId, $variationId);
        $this->invalidateMetricsCache((int)$store->id);

        $targetEntityId = $variationId ?: $productId;
        $this->activityRepository->recordForProduct((int)$store->id, $userId, 'inventory_configuration_changed', $targetEntityId, [
            'changed_fields' => array_keys($updateData),
            'old_values' => $oldValues,
            'new_values' => $updateData,
            'is_variation' => !empty($variationId),
        ]);

        $this->auditService->log(
            $userId,
            (int)$store->id,
            'INVENTORY_CONFIG_UPDATED',
            $variationId ? 'variation' : 'product',
            (string)$targetEntityId,
            $oldValues,
            $updateData
        );

        return $updated;
    }

    /**
     * Get inventory dashboard KPI metrics efficiently using WooCommerce count headers.
     */
    public function getDashboardMetrics(Store $store): array
    {
        $storeId = (int)$store->id;

        return \App\Support\Cache::storeRemember($storeId, 'inventory', 'metrics', \App\Support\Cache::TTL_INVENTORY, function () use ($store, $storeId) {
            $adapter = new ProductAdapter($store);

            // Fetch products in a single call (up to 100) to compute all metrics without N+1 HTTP requests
            $res = $adapter->listProducts(['per_page' => 100]);
            $items = $res['data'] ?? [];
            $meta = $res['meta'] ?? [];
            $catalogTotal = (int)($meta['total'] ?? count($items));

            $inStockTotal = 0;
            $outOfStockTotal = 0;
            $backorderTotal = 0;
            $managingCount = 0;
            $lowStockCount = 0;

            foreach ($items as $prod) {
                $item = InventoryNormalizer::normalizeProduct($prod, $storeId);
                $status = $item['stock_status'] ?? 'instock';
                if ($status === 'instock') {
                    $inStockTotal++;
                } elseif ($status === 'outofstock') {
                    $outOfStockTotal++;
                } elseif ($status === 'onbackorder') {
                    $backorderTotal++;
                }

                if (!empty($item['manage_stock'])) {
                    $managingCount++;
                    if (!empty($item['is_low_stock'])) {
                        $lowStockCount++;
                    }
                }
            }

            return [
                'total_products' => $catalogTotal,
                'instock' => $inStockTotal,
                'outofstock' => $outOfStockTotal,
                'onbackorder' => $backorderTotal,
                'low_stock' => $lowStockCount,
                'managing_stock' => $managingCount,
                'not_managing_stock' => max(0, $catalogTotal - $managingCount),
                'generated_at' => date('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Get low stock products.
     */
    public function getLowStockItems(Store $store, int $limit = 20): array
    {
        $res = $this->listInventory($store, [
            'per_page' => min(50, $limit),
            'stock_status' => 'low_stock',
        ]);
        return array_slice($res['data'] ?? [], 0, $limit);
    }

    /**
     * Get out of stock products.
     */
    public function getOutOfStockItems(Store $store, int $limit = 20): array
    {
        $res = $this->listInventory($store, [
            'per_page' => min(50, $limit),
            'stock_status' => 'outofstock',
        ]);
        return array_slice($res['data'] ?? [], 0, $limit);
    }

    public function invalidateMetricsCache(int $storeId): void
    {
        \App\Support\Cache::forgetStoreResource($storeId, 'inventory');
    }
}
