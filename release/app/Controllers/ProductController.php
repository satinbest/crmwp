<?php

namespace App\Controllers;

use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Repositories\StoreRepository;
use App\Services\ProductService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class ProductController extends BaseController
{
    private ProductService $productService;
    private StoreRepository $storeRepository;

    public function __construct(
        ?ProductService $productService = null,
        ?StoreRepository $storeRepository = null
    ) {
        $this->productService = $productService ?? new ProductService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }


    /**
     * GET /api/v1/products
     */
    public function index(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $allowedSort = ['id', 'title', 'date', 'price', 'popularity', 'rating', 'modified'];
            $sort = in_array($request->query('sort'), $allowedSort, true) ? $request->query('sort') : 'date';
            $direction = strtolower((string)$request->query('direction')) === 'asc' ? 'asc' : 'desc';

            $params = [
                'page' => max(1, (int)$request->query('page', 1)),
                'per_page' => max(1, min(100, (int)$request->query('per_page', 15))),
                'search' => mb_substr(trim((string)$request->query('search', '')), 0, 100),
                'status' => $request->query('status', 'all'),
                'type' => $request->query('type', 'all'),
                'stock_status' => $request->query('stock_status', 'all'),
                'category' => $request->query('category', 'all'),
                'tag' => $request->query('tag', 'all'),
                'min_price' => $request->query('min_price', ''),
                'max_price' => $request->query('max_price', ''),
                'featured' => $request->query('featured', 'all'),
                'sort' => $sort,
                'direction' => $direction,
            ];

            $result = $this->productService->listProducts($storeId, $params);

            return $this->success($result['data'], array_merge($result['meta'], [
                'store_id' => $storeId,
            ]));
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PRODUCTS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/products/{id}
     */
    public function show(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');

            $product = $this->productService->getProduct($storeId, $productId);
            if (!$product) {
                return $this->error('PRODUCT_NOT_FOUND', "محصول با شناسه {$productId} یافت نشد.", [], 404);
            }

            return $this->success($product, ['store_id' => $storeId]);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('PRODUCT_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/products
     */
    public function store(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $data = $request->input() ?: $request->all();
            unset($data['id']);
            $product = $this->productService->createProduct(
                $storeId,
                $userId,
                $data,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($product, ['message' => 'محصول جدید با موفقیت ایجاد گردید.'], 201);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('PRODUCT_CREATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * PATCH /api/v1/products/{id}
     */
    public function update(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $data = $request->input() ?: $request->all();
            unset($data['id']);
            $product = $this->productService->updateProduct(
                $storeId,
                $userId,
                $productId,
                $data,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($product, ['message' => 'مشخصات محصول با موفقیت بروزرسانی شد.']);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('PRODUCT_UPDATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * DELETE /api/v1/products/{id}
     */
    public function destroy(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $force = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);

            $result = $this->productService->deleteProduct(
                $storeId,
                $userId,
                $productId,
                $force,
                $request->getIp(),
                $request->getUserAgent()
            );

            $msg = $force ? 'محصول به صورت دائمی حذف گردید.' : 'محصول به زباله‌دان منتقل شد.';
            return $this->success($result, ['message' => $msg]);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('PRODUCT_DELETE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/products/{id}/variations
     */
    public function variations(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');

            $params = [
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
            ];

            $result = $this->productService->listVariations($storeId, $productId, $params);
            return $this->success($result['data'], array_merge($result['meta'], ['store_id' => $storeId]));
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('VARIATIONS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/products/{id}/variations/{variationId}
     */
    public function showVariation(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');
            $variationId = (int)$request->param('variationId');

            $variation = $this->productService->getVariation($storeId, $productId, $variationId);
            if (!$variation) {
                return $this->error('VARIATION_NOT_FOUND', "تنوع کالایی با شناسه {$variationId} یافت نشد.", [], 404);
            }

            return $this->success($variation, ['store_id' => $storeId]);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('VARIATION_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * POST /api/v1/products/{id}/variations
     */
    public function storeVariation(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $data = $request->input() ?: $request->all();
            unset($data['id'], $data['variationId']);
            $variation = $this->productService->createVariation(
                $storeId,
                $userId,
                $productId,
                $data,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($variation, ['message' => 'تنوع کالایی جدید با موفقیت ایجاد شد.'], 201);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('VARIATION_CREATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * PATCH /api/v1/products/{id}/variations/{variationId}
     */
    public function updateVariation(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');
            $variationId = (int)$request->param('variationId');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $data = $request->input() ?: $request->all();
            unset($data['id'], $data['variationId']);
            $variation = $this->productService->updateVariation(
                $storeId,
                $userId,
                $productId,
                $variationId,
                $data,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($variation, ['message' => 'مشخصات تنوع کالایی با موفقیت بروزرسانی شد.']);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('VARIATION_UPDATE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * DELETE /api/v1/products/{id}/variations/{variationId}
     */
    public function destroyVariation(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $productId = (int)$request->param('id');
            $variationId = (int)$request->param('variationId');
            $user = $this->currentUser($request);
            $userId = $user ? (int)$user->id : 1;

            $force = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);

            $result = $this->productService->deleteVariation(
                $storeId,
                $userId,
                $productId,
                $variationId,
                $force,
                $request->getIp(),
                $request->getUserAgent()
            );

            return $this->success($result, ['message' => 'تنوع کالایی با موفقیت حذف گردید.']);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            return $this->error('VARIATION_DELETE_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/product-categories
     */
    public function categories(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $categories = $this->productService->listCategories($storeId, $request->all());

            return $this->success($categories, [
                'total' => count($categories),
                'store_id' => $storeId,
            ]);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('CATEGORIES_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/product-tags
     */
    public function tags(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $tags = $this->productService->listTags($storeId, $request->all());

            return $this->success($tags, [
                'total' => count($tags),
                'store_id' => $storeId,
            ]);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('TAGS_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }

    /**
     * GET /api/v1/product-attributes
     */
    public function attributes(Request $request): Response
    {
        try {
            $storeId = $this->resolveStoreContext($request);
            $attributes = $this->productService->listAttributes($storeId);

            return $this->success($attributes, [
                'total' => count($attributes),
                'store_id' => $storeId,
            ]);
        } catch (WooCommerceApiException $e) {
            return $this->error(
                $e->getErrorCode() ?: 'WOOCOMMERCE_ERROR',
                $e->getMessage(),
                $e->getDetails(),
                $e->getHttpStatus()
            );
        } catch (Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('ATTRIBUTES_FETCH_FAILED', $e->getMessage(), [], $status);
        }
    }
}
