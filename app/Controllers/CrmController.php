<?php

namespace App\Controllers;

use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Services\CrmService;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\ProductService;
use App\Services\RbacService;
use App\Support\Request;
use App\Support\Response;
use Exception;

class CrmController extends BaseController
{
    private CrmService $crmService;
    private StoreRepository $storeRepository;
    private RbacService $rbacService;
    private ?CustomerService $customerService;
    private ?ProductService $productService;
    private ?OrderService $orderService;

    public function __construct(
        ?CrmService $crmService = null,
        ?StoreRepository $storeRepository = null,
        ?RbacService $rbacService = null,
        ?CustomerService $customerService = null,
        ?ProductService $productService = null,
        ?OrderService $orderService = null
    ) {
        $this->crmService = $crmService ?? new CrmService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->rbacService = $rbacService ?? new RbacService();
        $this->customerService = $customerService;
        $this->productService = $productService;
        $this->orderService = $orderService;
    }


    private function authorizePermission(Request $request, string $permission): int
    {
        $user = $this->currentUser($request);
        if (!$user) {
            throw new Exception("احراز هویت الزامی است.", 401);
        }

        $userId = (int)$user->id;
        if (!$this->rbacService->userHasPermission($userId, $permission)) {
            throw new Exception("شما مجوز دسترسی لازم ({$permission}) را ندارید.", 403);
        }

        return $userId;
    }

    /**
     * GET /api/v1/crm or /api/v1/crm/summary
     */
    public function summary(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'crm.view');
            $store = $this->resolveStore($request);

            $summary = $this->crmService->getSummary($store->id, $userId);
            return $this->success($summary);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('CRM_ERROR', $e->getMessage(), [], $code);
        }
    }

    /**
     * GET /api/v1/crm/search
     * Global unified search across Customers, Orders, Products, Tasks, Segments, Tags
     */
    public function search(Request $request): Response
    {
        try {
            $userId = $this->authorizePermission($request, 'crm.view');
            $store = $this->resolveStore($request);
            $query = trim((string)$request->query('q', ''));

            if (empty($query) || mb_strlen($query) < 2) {
                return $this->success([
                    'customers' => [],
                    'orders' => [],
                    'products' => [],
                    'tasks' => [],
                    'segments' => [],
                    'tags' => [],
                ]);
            }

            $pdo = \App\Database\Connection::get();
            $param = '%' . $query . '%';

            // 1. Search Tasks
            $stmt = $pdo->prepare("
                SELECT id, title, priority, status, due_date
                FROM tasks
                WHERE store_id = :store_id AND (title LIKE :q1 OR description LIKE :q2)
                LIMIT 5
            ");
            $stmt->execute(['store_id' => $store->id, 'q1' => $param, 'q2' => $param]);
            $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 2. Search Segments
            $stmt = $pdo->prepare("
                SELECT id, name, description
                FROM segments
                WHERE store_id = :store_id AND (name LIKE :q1 OR description LIKE :q2)
                LIMIT 5
            ");
            $stmt->execute(['store_id' => $store->id, 'q1' => $param, 'q2' => $param]);
            $segments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Search Tags
            $stmt = $pdo->prepare("
                SELECT id, name, color
                FROM tags
                WHERE store_id = :store_id AND name LIKE :q
                LIMIT 5
            ");
            $stmt->execute(['store_id' => $store->id, 'q' => $param]);
            $tags = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 4. Search Customers (via CustomerService if available)
            $customers = [];
            try {
                $cs = $this->customerService ?? new CustomerService();
                $res = $cs->listCustomers($store->id, ['search' => $query, 'per_page' => 5]);
                $customers = array_map(fn($c) => [
                    'id' => $c['id'],
                    'name' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['username'] ?? ''),
                    'email' => $c['email'] ?? '',
                ], $res['data'] ?? []);
            } catch (\Throwable $e) {}

            // 5. Search Products
            $products = [];
            try {
                $ps = $this->productService ?? new ProductService();
                $res = $ps->listProducts($store->id, ['search' => $query, 'per_page' => 5]);
                $products = array_map(fn($p) => [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'sku' => $p['sku'] ?? '',
                    'price' => $p['price'] ?? 0,
                ], $res['data'] ?? []);
            } catch (\Throwable $e) {}

            return $this->success([
                'customers' => $customers,
                'products' => $products,
                'tasks' => $tasks,
                'segments' => $segments,
                'tags' => $tags,
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error('SEARCH_ERROR', $e->getMessage(), [], $code);
        }
    }
}
