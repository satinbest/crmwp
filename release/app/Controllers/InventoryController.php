<?php

namespace App\Controllers;

use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Services\Inventory\InventoryService;
use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;
use Exception;
use InvalidArgumentException;

class InventoryController extends BaseController
{
    private InventoryService $inventoryService;
    private StoreRepository $storeRepository;
    private RbacService $rbacService;

    public function __construct(
        ?InventoryService $inventoryService = null,
        ?StoreRepository $storeRepository = null,
        ?RbacService $rbacService = null
    ) {
        $this->inventoryService = $inventoryService ?? new InventoryService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->rbacService = $rbacService ?? new RbacService();
    }


    private function authorizePermission(Request $request, string $permission): int
    {
        $user = $request->getUser();
        if (!$user) {
            throw new Exception("احراز هویت الزامی است.", 401);
        }

        if (!$this->rbacService->userHasPermission($user->id, $permission)) {
            throw new Exception("شما مجوز دسترسی لازم [{$permission}] را ندارید.", 403);
        }

        return (int)$user->id;
    }

    /**
     * GET /api/v1/inventory
     */
    public function index(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'inventory.view');
            $store = $this->resolveStore($request);

            $allowedSort = ['id', 'name', 'date', 'price', 'stock_quantity', 'stock_status'];
            $sort = in_array($request->query('sort'), $allowedSort, true) ? $request->query('sort') : 'date';
            $direction = strtolower((string)$request->query('direction')) === 'asc' ? 'asc' : 'desc';

            $filters = [
                'page' => max(1, (int)$request->query('page', 1)),
                'per_page' => max(1, min(100, (int)$request->query('per_page', 20))),
                'search' => mb_substr(trim((string)$request->query('search', '')), 0, 100),
                'stock_status' => $request->query('stock_status', 'all'),
                'manage_stock' => $request->query('manage_stock', 'all'),
                'type' => $request->query('type', 'all'),
                'category' => $request->query('category', 'all'),
                'stock_op' => $request->query('stock_op', ''),
                'stock_val' => $request->query('stock_val', ''),
                'sort' => $sort,
                'direction' => $direction,
            ];

            $result = $this->inventoryService->listInventory($store, $filters);
            return $this->success($result['data'], $result['meta']);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/inventory/metrics
     */
    public function metrics(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'inventory.view');
            $store = $this->resolveStore($request);

            $metrics = $this->inventoryService->getDashboardMetrics($store);
            return $this->success($metrics);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/inventory/low-stock
     */
    public function lowStock(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'inventory.view');
            $store = $this->resolveStore($request);
            $limit = min(50, max(1, (int)$request->query('limit', 20)));

            $items = $this->inventoryService->getLowStockItems($store, $limit);
            return $this->success($items);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/inventory/out-of-stock
     */
    public function outOfStock(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'inventory.view');
            $store = $this->resolveStore($request);
            $limit = min(50, max(1, (int)$request->query('limit', 20)));

            $items = $this->inventoryService->getOutOfStockItems($store, $limit);
            return $this->success($items);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/inventory/{productId}
     */
    public function show(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'inventory.view');
            $store = $this->resolveStore($request);
            $productId = (int)($request->param('productId') ?? $request->query('productId') ?? 0);

            if ($productId <= 0) {
                return $this->error('VALIDATION_ERROR', 'شناسه محصول نامعتبر است.', [], 400);
            }

            $item = $this->inventoryService->getItem($store, $productId);
            if (!$item) {
                return $this->error('NOT_FOUND', 'محصول مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($item);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/inventory/{productId}/stock
     */
    public function updateStock(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'inventory.update');
            $store = $this->resolveStore($request);
            $productId = (int)($request->param('productId') ?? 0);

            $operation = strtolower(trim((string)$request->input('operation', 'set')));
            $amount = (float)$request->input('quantity', $request->input('amount', 0));
            $variationId = $request->input('variation_id') ? (int)$request->input('variation_id') : null;

            if (!in_array($operation, ['set', 'increase', 'decrease'], true)) {
                return $this->error('VALIDATION_ERROR', 'عملیات موجودی نامعتبر است. مقادیر مجاز: set, increase, decrease', [], 422);
            }

            $updated = $this->inventoryService->updateStock($store, $userId, $productId, $variationId, $operation, $amount);

            // Notify if stock is low or out of stock
            try {
                if (!empty($updated['is_low_stock']) || ($updated['stock_status'] ?? '') === 'outofstock') {
                    $notifService = new \App\Services\NotificationService();
                    $notifService->notifyLowStock(
                        $userId,
                        (int)$productId,
                        $updated['product_name'] ?? 'محصول',
                        (int)($updated['stock_quantity'] ?? 0),
                        (int)($updated['low_stock_amount'] ?? 5),
                        (int)$store->id
                    );
                }
            } catch (\Throwable $te) {}

            return $this->success($updated, ['message' => 'موجودی انبار با موفقیت بروزرسانی شد.']);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/inventory/{productId}/status
     */
    public function updateStatus(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'inventory.update');
            $store = $this->resolveStore($request);
            $productId = (int)($request->param('productId') ?? 0);

            $status = (string)$request->input('stock_status', $request->input('status', ''));
            $variationId = $request->input('variation_id') ? (int)$request->input('variation_id') : null;

            if (empty($status)) {
                return $this->error('VALIDATION_ERROR', 'وضعیت موجودی الزامی است.', [], 422);
            }

            $updated = $this->inventoryService->updateStatus($store, $userId, $productId, $variationId, $status);
            return $this->success($updated, ['message' => 'وضعیت انبار با موفقیت تغییر یافت.']);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/inventory/{productId}
     * Update configuration (manage_stock, low_stock_amount, backorders, sold_individually).
     */
    public function updateConfig(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'inventory.manage_stock');
            $store = $this->resolveStore($request);
            $productId = (int)($request->param('productId') ?? 0);

            $config = $request->all();
            $variationId = $request->input('variation_id') ? (int)$request->input('variation_id') : null;

            $updated = $this->inventoryService->updateConfiguration($store, $userId, $productId, $variationId, $config);
            return $this->success($updated, ['message' => 'پیکربندی انبارداری کالا با موفقیت ذخیره شد.']);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/inventory/{productId}/variations/{variationId}
     */
    public function showVariation(Request $request): Response
    {
        try {
            $this->authorizePermission($request, 'inventory.view');
            $store = $this->resolveStore($request);
            $productId = (int)($request->param('productId') ?? 0);
            $variationId = (int)($request->param('variationId') ?? 0);

            $item = $this->inventoryService->getItem($store, $productId, $variationId);
            if (!$item) {
                return $this->error('NOT_FOUND', 'تنوع کالای مورد نظر یافت نشد.', [], 404);
            }

            return $this->success($item);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * PATCH /api/v1/inventory/{productId}/variations/{variationId}
     */
    public function updateVariation(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'inventory.update');
            $store = $this->resolveStore($request);
            $productId = (int)($request->param('productId') ?? 0);
            $variationId = (int)($request->param('variationId') ?? 0);

            // Check if stock quantity, stock status, or config
            $body = $request->all();
            if (isset($body['operation']) || isset($body['quantity'])) {
                $op = strtolower(trim((string)$request->input('operation', 'set')));
                $qty = (float)$request->input('quantity', 0);
                $updated = $this->inventoryService->updateStock($store, $userId, $productId, $variationId, $op, $qty);
                return $this->success($updated, ['message' => 'موجودی تنوع با موفقیت بروزرسانی شد.']);
            }

            if (isset($body['stock_status'])) {
                $updated = $this->inventoryService->updateStatus($store, $userId, $productId, $variationId, (string)$body['stock_status']);
                return $this->success($updated, ['message' => 'وضعیت موجودی تنوع با موفقیت تغییر یافت.']);
            }

            $updated = $this->inventoryService->updateConfiguration($store, $userId, $productId, $variationId, $body);
            return $this->success($updated, ['message' => 'پیکربندی تنوع با موفقیت ذخیره شد.']);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), [], 422);
        } catch (WooCommerceApiException $e) {
            return $this->error('WOOCOMMERCE_API_ERROR', $e->getMessage(), [], $e->getHttpStatus());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('INVENTORY_ERROR', $e->getMessage(), [], $code);
        }
    }
}
