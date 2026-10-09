<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Integrations\WooCommerce\CustomerAdapter;
use App\Integrations\WooCommerce\OrderAdapter;
use App\Integrations\WooCommerce\OrderStatusResolver;
use App\Integrations\WooCommerce\ProductAdapter;
use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Repositories\SyncLogRepository;
use App\Support\Logger;
use DateTime;
use DateTimeZone;
use Exception;
use PDO;
use Throwable;

class LocalSyncService
{
    private PDO $pdo;
    private StoreRepository $storeRepo;
    private SyncLogRepository $syncLogRepo;

    /**
     * Stale threshold in seconds (6 hours).
     */
    public const STALE_THRESHOLD_SECONDS = 21600;

    /**
     * Lock timeout in seconds for crash recovery (15 minutes).
     */
    public const LOCK_TIMEOUT_SECONDS = 900;

    public function __construct(
        ?PDO $pdo = null,
        ?StoreRepository $storeRepo = null,
        ?SyncLogRepository $syncLogRepo = null
    ) {
        $this->pdo = $pdo ?? Connection::get();
        $this->storeRepo = $storeRepo ?? new StoreRepository($this->pdo);
        $this->syncLogRepo = $syncLogRepo ?? new SyncLogRepository($this->pdo);
    }

    /**
     * Get sync state metadata for a store and entity type (or all entity types).
     */
    public function getSyncState(int $storeId, ?string $entityType = null): array
    {
        $entities = ['products', 'orders', 'customers', 'categories', 'inventory'];

        $sql = "SELECT * FROM wc_local_sync_meta WHERE store_id = :store_id";
        $params = [':store_id' => $storeId];
        if ($entityType && $entityType !== 'all') {
            $sql .= " AND entity_type = :entity_type";
            $params[':entity_type'] = $entityType;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rowMap = [];
        foreach ($rows as $r) {
            $rowMap[$r['entity_type']] = $r;
        }

        $now = time();
        $result = [];

        foreach ($entities as $ent) {
            if ($entityType && $entityType !== 'all' && $entityType !== $ent) {
                continue;
            }

            $record = $rowMap[$ent] ?? null;
            $status = $record['status'] ?? 'idle';
            $lastSuccess = $record['last_successful_sync_at'] ?? null;
            $lastError = $record['last_error'] ?? null;
            $recordsCount = (int)($record['records_count'] ?? 0);
            $syncedRecords = (int)($record['synced_records'] ?? 0);
            $isComplete = (bool)($record['is_complete'] ?? false);

            $lastSuccessTs = $lastSuccess ? strtotime($lastSuccess) : null;
            $isStale = ($lastSuccessTs === null) || (($now - $lastSuccessTs) > self::STALE_THRESHOLD_SECONDS);

            // Crash recovery check: if running but started > LOCK_TIMEOUT_SECONDS ago, treat as stalled
            $lastStarted = $record['last_sync_started_at'] ?? null;
            if ($status === 'running' && $lastStarted) {
                $startedTs = strtotime($lastStarted);
                if (($now - $startedTs) > self::LOCK_TIMEOUT_SECONDS) {
                    $status = 'failed';
                    $lastError = 'عملیات قبلی به دلیل توقف فرآیند یا اتمام زمان مجاز بازیابی گردید.';
                }
            }

            // Real count in local tables if available
            $currentLocalCount = $this->getLocalTableCount($storeId, $ent);

            $result[$ent] = [
                'store_id' => $storeId,
                'entity_type' => $ent,
                'status' => $status,
                'last_sync_started_at' => $record['last_sync_started_at'] ?? null,
                'last_sync_completed_at' => $record['last_sync_completed_at'] ?? null,
                'last_successful_sync' => $lastSuccess,
                'records_count' => max($recordsCount, $currentLocalCount),
                'synced_records' => $syncedRecords,
                'is_complete' => $isComplete,
                'is_stale' => $isStale,
                'has_data' => $currentLocalCount > 0,
                'last_error' => $lastError,
                'updated_at' => $record['updated_at'] ?? null,
            ];
        }

        if ($entityType && $entityType !== 'all') {
            return $result[$entityType] ?? [
                'store_id' => $storeId,
                'entity_type' => $entityType,
                'status' => 'idle',
                'last_successful_sync' => null,
                'records_count' => 0,
                'synced_records' => 0,
                'is_complete' => false,
                'is_stale' => true,
                'has_data' => false,
                'last_error' => null,
            ];
        }

        // Overall store sync status
        $allSuccess = true;
        $anyRunning = false;
        $latestSync = null;

        foreach ($result as $item) {
            if ($item['status'] === 'running') {
                $anyRunning = true;
            }
            if ($item['is_stale'] || empty($item['last_successful_sync'])) {
                $allSuccess = false;
            }
            if ($item['last_successful_sync'] && (!$latestSync || $item['last_successful_sync'] > $latestSync)) {
                $latestSync = $item['last_successful_sync'];
            }
        }

        return [
            'store_id' => $storeId,
            'status' => $anyRunning ? 'running' : ($allSuccess ? 'completed' : 'partial'),
            'last_successful_sync' => $latestSync,
            'is_stale' => !$allSuccess,
            'entities' => $result,
        ];
    }

    /**
     * Get real count from local MariaDB table.
     */
    public function getLocalTableCount(int $storeId, string $entityType): int
    {
        $table = match ($entityType) {
            'products', 'inventory' => 'wc_local_products',
            'orders' => 'wc_local_orders',
            'customers' => 'wc_local_customers',
            'categories' => 'wc_local_categories',
            default => null,
        };

        if (!$table) {
            return 0;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE store_id = :store_id");
            $stmt->execute([':store_id' => $storeId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Acquire concurrency lock for a sync operation with crash recovery.
     */
    private function acquireLock(int $storeId, string $entityType): bool
    {
        // 1. Check existing state
        $stmt = $this->pdo->prepare("SELECT * FROM wc_local_sync_meta WHERE store_id = :store_id AND entity_type = :entity_type");
        $stmt->execute([':store_id' => $storeId, ':entity_type' => $entityType]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = time();
        if ($existing && $existing['status'] === 'running') {
            $startedTs = !empty($existing['last_sync_started_at']) ? strtotime($existing['last_sync_started_at']) : 0;
            if (($now - $startedTs) <= self::LOCK_TIMEOUT_SECONDS) {
                // Currently actively running, cannot acquire
                return false;
            }
            // Expired lock, break and continue
            Logger::warning("Breaking expired sync lock for store {$storeId}, entity {$entityType}");
        }

        // 2. Upsert running state
        $upsert = $this->pdo->prepare("
            INSERT INTO wc_local_sync_meta (
                store_id, entity_type, status, last_sync_started_at, last_error, updated_at
            ) VALUES (
                :store_id, :entity_type, 'running', NOW(), NULL, NOW()
            ) ON DUPLICATE KEY UPDATE
                status = 'running',
                last_sync_started_at = NOW(),
                last_error = NULL,
                updated_at = NOW()
        ");

        $upsert->execute([':store_id' => $storeId, ':entity_type' => $entityType]);
        return true;
    }

    /**
     * Release lock and update state upon completion or failure.
     */
    private function releaseLock(
        int $storeId,
        string $entityType,
        string $status,
        int $syncedCount = 0,
        int $totalCount = 0,
        ?string $error = null
    ): void {
        $isComplete = ($status === 'completed') ? 1 : 0;
        $successClause = ($status === 'completed') ? ", last_successful_sync_at = NOW()" : "";

        $stmt = $this->pdo->prepare("
            UPDATE wc_local_sync_meta SET
                status = :status,
                last_sync_completed_at = NOW(),
                synced_records = :synced_records,
                records_count = GREATEST(records_count, :records_count),
                is_complete = :is_complete,
                last_error = :last_error
                {$successClause},
                updated_at = NOW()
            WHERE store_id = :store_id AND entity_type = :entity_type
        ");

        $stmt->execute([
            ':status' => $status,
            ':synced_records' => $syncedCount,
            ':records_count' => max($syncedCount, $totalCount),
            ':is_complete' => $isComplete,
            ':last_error' => $error,
            ':store_id' => $storeId,
            ':entity_type' => $entityType,
        ]);
    }

    /**
     * Trigger synchronization for one entity or all entities of a store.
     */
    public function sync(int $storeId, string $entityType = 'all', bool $force = false): array
    {
        $store = $this->storeRepo->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه با شناسه {$storeId} یافت نشد.", 404);
        }

        $validEntities = ['all', 'categories', 'products', 'orders', 'customers', 'inventory'];
        if (!in_array($entityType, $validEntities, true)) {
            throw new Exception("نوع داده انتخابی برای همگام‌سازی نامعتبر است.", 422);
        }

        $entitiesToSync = match ($entityType) {
            'all' => ['categories', 'products', 'orders', 'customers'],
            'inventory' => ['products'],
            default => [$entityType],
        };

        $results = [];
        $overallSuccess = true;

        foreach ($entitiesToSync as $ent) {
            if (!$this->acquireLock($storeId, $ent)) {
                $results[$ent] = [
                    'status' => 'skipped',
                    'message' => 'همگام‌سازی برای این بخش در حال حاضر در حال اجرا است.',
                ];
                continue;
            }

            try {
                $syncRes = match ($ent) {
                    'categories' => $this->syncCategories($store),
                    'products' => $this->syncProducts($store),
                    'orders' => $this->syncOrders($store),
                    'customers' => $this->syncCustomers($store),
                    default => ['synced' => 0, 'total' => 0],
                };

                $synced = (int)($syncRes['synced'] ?? 0);
                $total = (int)($syncRes['total'] ?? $synced);

                $this->releaseLock($storeId, $ent, 'completed', $synced, $total, null);

                $results[$ent] = [
                    'status' => 'completed',
                    'synced' => $synced,
                    'total' => $total,
                ];
            } catch (Throwable $e) {
                $overallSuccess = false;
                Logger::error("Sync failed for store {$storeId}, entity {$ent}: " . $e->getMessage());
                $this->releaseLock($storeId, $ent, 'failed', 0, 0, $e->getMessage());

                $results[$ent] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Also record in sync_logs table for audit and history
        try {
            $this->syncLogRepo->create([
                'store_id' => $storeId,
                'entity_type' => $entityType,
                'direction' => 'inbound_sync',
                'status' => $overallSuccess ? 'completed' : 'partial',
                'details' => $results,
                'error_message' => $overallSuccess ? null : 'یک یا چند بخش با خطا مواجه شدند.',
            ]);
        } catch (Throwable) {
            // Ignore audit log failure
        }

        return [
            'success' => $overallSuccess,
            'store_id' => $storeId,
            'entity_type' => $entityType,
            'results' => $results,
            'state' => $this->getSyncState($storeId),
        ];
    }

    /**
     * Synchronize categories into wc_local_categories.
     */
    public function syncCategories(Store $store): array
    {
        $storeId = (int)$store->id;
        $adapter = new ProductAdapter($store);
        $catsRes = $adapter->categories(['per_page' => 100]);
        $cats = is_array($catsRes) ? $catsRes : [];

        $stmt = $this->pdo->prepare("
            INSERT INTO wc_local_categories (
                store_id, wc_id, name, slug, parent, count, raw_data, synced_at
            ) VALUES (
                :store_id, :wc_id, :name, :slug, :parent, :count, :raw_data, NOW()
            ) ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                slug = VALUES(slug),
                parent = VALUES(parent),
                count = VALUES(count),
                raw_data = VALUES(raw_data),
                synced_at = NOW()
        ");

        $count = 0;
        foreach ($cats as $cat) {
            $wcId = (int)($cat['id'] ?? 0);
            if ($wcId <= 0) {
                continue;
            }

            $stmt->execute([
                ':store_id' => $storeId,
                ':wc_id' => $wcId,
                ':name' => (string)($cat['name'] ?? ''),
                ':slug' => (string)($cat['slug'] ?? ''),
                ':parent' => (int)($cat['parent'] ?? 0),
                ':count' => (int)($cat['count'] ?? 0),
                ':raw_data' => json_encode($cat, JSON_UNESCAPED_UNICODE),
            ]);
            $count++;
        }

        return ['synced' => $count, 'total' => count($cats)];
    }

    /**
     * Synchronize products into wc_local_products.
     */
    public function syncProducts(Store $store, int $maxPages = 20, int $perPage = 50): array
    {
        $storeId = (int)$store->id;
        $adapter = new ProductAdapter($store);

        $upsertStmt = $this->pdo->prepare("
            INSERT INTO wc_local_products (
                store_id, wc_id, parent_id, name, slug, type, status, sku,
                price, regular_price, sale_price, stock_status, stock_quantity, manage_stock,
                categories, tags, attributes, date_created, date_modified, raw_data, synced_at
            ) VALUES (
                :store_id, :wc_id, :parent_id, :name, :slug, :type, :status, :sku,
                :price, :regular_price, :sale_price, :stock_status, :stock_quantity, :manage_stock,
                :categories, :tags, :attributes, :date_created, :date_modified, :raw_data, NOW()
            ) ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                name = VALUES(name),
                slug = VALUES(slug),
                type = VALUES(type),
                status = VALUES(status),
                sku = VALUES(sku),
                price = VALUES(price),
                regular_price = VALUES(regular_price),
                sale_price = VALUES(sale_price),
                stock_status = VALUES(stock_status),
                stock_quantity = VALUES(stock_quantity),
                manage_stock = VALUES(manage_stock),
                categories = VALUES(categories),
                tags = VALUES(tags),
                attributes = VALUES(attributes),
                date_created = VALUES(date_created),
                date_modified = VALUES(date_modified),
                raw_data = VALUES(raw_data),
                synced_at = NOW()
        ");

        $totalSynced = 0;
        $totalRemote = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $res = $adapter->listProducts(['page' => $page, 'per_page' => $perPage]);
            $products = $res['data'] ?? [];
            if (empty($products)) {
                break;
            }

            $totalRemote = (int)($res['meta']['total'] ?? count($products));

            foreach ($products as $prod) {
                $wcId = (int)($prod['id'] ?? 0);
                if ($wcId <= 0) {
                    continue;
                }

                $this->executeProductUpsert($upsertStmt, $storeId, $prod);
                $totalSynced++;

                // If variable product, also sync its variations
                $isVariable = ($prod['type'] ?? '') === 'variable' || !empty($prod['variations']);
                if ($isVariable) {
                    try {
                        $varsRes = $adapter->listVariations($wcId);
                        $vars = $varsRes['data'] ?? (is_array($varsRes) ? $varsRes : []);
                        foreach ($vars as $v) {
                            $v['parent_id'] = $wcId;
                            $v['type'] = 'variation';
                            if (empty($v['name'])) {
                                $v['name'] = ($prod['name'] ?? "محصول #{$wcId}") . " - متغیر #" . ($v['id'] ?? '');
                            }
                            $this->executeProductUpsert($upsertStmt, $storeId, $v);
                            $totalSynced++;
                        }
                    } catch (Throwable $varEx) {
                        Logger::warning("Could not sync variations for product #{$wcId}: " . $varEx->getMessage());
                    }
                }
            }

            if (count($products) < $perPage) {
                break;
            }
        }

        return ['synced' => $totalSynced, 'total' => max($totalSynced, $totalRemote)];
    }

    private function executeProductUpsert(\PDOStatement $stmt, int $storeId, array $p): void
    {
        $wcId = (int)($p['id'] ?? 0);
        $parentId = (int)($p['parent_id'] ?? 0);
        $name = (string)($p['name'] ?? "محصول #{$wcId}");
        $slug = (string)($p['slug'] ?? '');
        $type = (string)($p['type'] ?? 'simple');
        $status = (string)($p['status'] ?? 'publish');
        $sku = !empty($p['sku']) ? (string)$p['sku'] : null;

        $price = isset($p['price']) && $p['price'] !== '' ? (string)$p['price'] : null;
        $regPrice = isset($p['regular_price']) && $p['regular_price'] !== '' ? (string)$p['regular_price'] : null;
        $salePrice = isset($p['sale_price']) && $p['sale_price'] !== '' ? (string)$p['sale_price'] : null;

        $stockStatus = (string)($p['stock_status'] ?? 'instock');
        $stockQty = isset($p['stock_quantity']) && is_numeric($p['stock_quantity']) ? (int)$p['stock_quantity'] : null;
        $manageStock = (int)(!empty($p['manage_stock']));

        $categories = !empty($p['categories']) ? json_encode($p['categories'], JSON_UNESCAPED_UNICODE) : null;
        $tags = !empty($p['tags']) ? json_encode($p['tags'], JSON_UNESCAPED_UNICODE) : null;
        $attributes = !empty($p['attributes']) ? json_encode($p['attributes'], JSON_UNESCAPED_UNICODE) : null;

        $dateCreated = !empty($p['date_created']) ? substr(str_replace('T', ' ', $p['date_created']), 0, 19) : null;
        $dateModified = !empty($p['date_modified']) ? substr(str_replace('T', ' ', $p['date_modified']), 0, 19) : null;

        $stmt->execute([
            ':store_id' => $storeId,
            ':wc_id' => $wcId,
            ':parent_id' => $parentId,
            ':name' => $name,
            ':slug' => $slug,
            ':type' => $type,
            ':status' => $status,
            ':sku' => $sku,
            ':price' => $price,
            ':regular_price' => $regPrice,
            ':sale_price' => $salePrice,
            ':stock_status' => $stockStatus,
            ':stock_quantity' => $stockQty,
            ':manage_stock' => $manageStock,
            ':categories' => $categories,
            ':tags' => $tags,
            ':attributes' => $attributes,
            ':date_created' => $dateCreated,
            ':date_modified' => $dateModified,
            ':raw_data' => json_encode($p, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Synchronize orders into wc_local_orders.
     */
    public function syncOrders(Store $store, int $maxPages = 20, int $perPage = 50): array
    {
        $storeId = (int)$store->id;
        $adapter = new OrderAdapter($store);

        $upsertStmt = $this->pdo->prepare("
            INSERT INTO wc_local_orders (
                store_id, wc_id, order_number, customer_id, status, currency, total,
                customer_name, customer_email, items_count, date_created, date_modified, raw_data, synced_at
            ) VALUES (
                :store_id, :wc_id, :order_number, :customer_id, :status, :currency, :total,
                :customer_name, :customer_email, :items_count, :date_created, :date_modified, :raw_data, NOW()
            ) ON DUPLICATE KEY UPDATE
                order_number = VALUES(order_number),
                customer_id = VALUES(customer_id),
                status = VALUES(status),
                currency = VALUES(currency),
                total = VALUES(total),
                customer_name = VALUES(customer_name),
                customer_email = VALUES(customer_email),
                items_count = VALUES(items_count),
                date_created = VALUES(date_created),
                date_modified = VALUES(date_modified),
                raw_data = VALUES(raw_data),
                synced_at = NOW()
        ");

        $totalSynced = 0;
        $totalRemote = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $res = $adapter->listOrders(['page' => $page, 'per_page' => $perPage]);
            $orders = $res['data'] ?? [];
            if (empty($orders)) {
                break;
            }

            $totalRemote = (int)($res['meta']['total'] ?? count($orders));

            foreach ($orders as $ord) {
                $wcId = (int)($ord['id'] ?? 0);
                if ($wcId <= 0) {
                    continue;
                }

                $this->executeOrderUpsert($upsertStmt, $storeId, $ord);
                $totalSynced++;
            }

            if (count($orders) < $perPage) {
                break;
            }
        }

        return ['synced' => $totalSynced, 'total' => max($totalSynced, $totalRemote)];
    }

    private function executeOrderUpsert(\PDOStatement $stmt, int $storeId, array $o): void
    {
        $wcId = (int)($o['id'] ?? 0);
        $orderNumber = (string)($o['number'] ?? $o['id'] ?? '');
        $customerId = (int)($o['customer_id'] ?? 0);
        $status = (string)($o['status'] ?? 'pending');
        $currency = (string)($o['currency'] ?? 'IRT');
        $total = (float)($o['total'] ?? 0.0);

        $customerName = trim(($o['billing']['first_name'] ?? '') . ' ' . ($o['billing']['last_name'] ?? ''));
        if ($customerName === '' && !empty($o['customer_name'])) {
            $customerName = (string)$o['customer_name'];
        }
        $customerEmail = !empty($o['billing']['email']) ? (string)$o['billing']['email'] : (!empty($o['customer_email']) ? (string)$o['customer_email'] : null);
        $itemsCount = count($o['line_items'] ?? []);

        $dateCreated = !empty($o['date_created']) ? substr(str_replace('T', ' ', $o['date_created']), 0, 19) : null;
        $dateModified = !empty($o['date_modified']) ? substr(str_replace('T', ' ', $o['date_modified']), 0, 19) : null;

        $stmt->execute([
            ':store_id' => $storeId,
            ':wc_id' => $wcId,
            ':order_number' => $orderNumber,
            ':customer_id' => $customerId,
            ':status' => $status,
            ':currency' => $currency,
            ':total' => $total,
            ':customer_name' => $customerName ?: null,
            ':customer_email' => $customerEmail,
            ':items_count' => $itemsCount,
            ':date_created' => $dateCreated,
            ':date_modified' => $dateModified,
            ':raw_data' => json_encode($o, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Synchronize customers into wc_local_customers.
     */
    public function syncCustomers(Store $store, int $maxPages = 20, int $perPage = 50): array
    {
        $storeId = (int)$store->id;
        $adapter = new CustomerAdapter($store);

        $upsertStmt = $this->pdo->prepare("
            INSERT INTO wc_local_customers (
                store_id, wc_id, email, first_name, last_name, username, role,
                orders_count, total_spent, raw_data, synced_at
            ) VALUES (
                :store_id, :wc_id, :email, :first_name, :last_name, :username, :role,
                :orders_count, :total_spent, :raw_data, NOW()
            ) ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                username = VALUES(username),
                role = VALUES(role),
                orders_count = VALUES(orders_count),
                total_spent = VALUES(total_spent),
                raw_data = VALUES(raw_data),
                synced_at = NOW()
        ");

        $totalSynced = 0;
        $totalRemote = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $res = $adapter->listCustomers(['page' => $page, 'per_page' => $perPage]);
            $customers = $res['data'] ?? [];
            if (empty($customers)) {
                break;
            }

            $totalRemote = (int)($res['meta']['total'] ?? count($customers));

            foreach ($customers as $c) {
                $wcId = (int)($c['id'] ?? 0);
                if ($wcId <= 0) {
                    continue;
                }

                $this->executeCustomerUpsert($upsertStmt, $storeId, $c);
                $totalSynced++;
            }

            if (count($customers) < $perPage) {
                break;
            }
        }

        return ['synced' => $totalSynced, 'total' => max($totalSynced, $totalRemote)];
    }

    private function executeCustomerUpsert(\PDOStatement $stmt, int $storeId, array $c): void
    {
        $wcId = (int)($c['id'] ?? 0);
        $email = !empty($c['email']) ? (string)$c['email'] : null;
        $firstName = !empty($c['first_name']) ? (string)$c['first_name'] : null;
        $lastName = !empty($c['last_name']) ? (string)$c['last_name'] : null;
        $username = !empty($c['username']) ? (string)$c['username'] : null;
        $role = !empty($c['role']) ? (string)$c['role'] : 'customer';
        $ordersCount = (int)($c['orders_count'] ?? 0);
        $totalSpent = (float)($c['total_spent'] ?? 0.0);

        $stmt->execute([
            ':store_id' => $storeId,
            ':wc_id' => $wcId,
            ':email' => $email,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':username' => $username,
            ':role' => $role,
            ':orders_count' => $ordersCount,
            ':total_spent' => $totalSpent,
            ':raw_data' => json_encode($c, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Webhook single item upserts.
     */
    public function upsertLocalProduct(int $storeId, array $productData): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wc_local_products (
                store_id, wc_id, parent_id, name, slug, type, status, sku,
                price, regular_price, sale_price, stock_status, stock_quantity, manage_stock,
                categories, tags, attributes, date_created, date_modified, raw_data, synced_at
            ) VALUES (
                :store_id, :wc_id, :parent_id, :name, :slug, :type, :status, :sku,
                :price, :regular_price, :sale_price, :stock_status, :stock_quantity, :manage_stock,
                :categories, :tags, :attributes, :date_created, :date_modified, :raw_data, NOW()
            ) ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                name = VALUES(name),
                slug = VALUES(slug),
                type = VALUES(type),
                status = VALUES(status),
                sku = VALUES(sku),
                price = VALUES(price),
                regular_price = VALUES(regular_price),
                sale_price = VALUES(sale_price),
                stock_status = VALUES(stock_status),
                stock_quantity = VALUES(stock_quantity),
                manage_stock = VALUES(manage_stock),
                categories = VALUES(categories),
                tags = VALUES(tags),
                attributes = VALUES(attributes),
                date_created = VALUES(date_created),
                date_modified = VALUES(date_modified),
                raw_data = VALUES(raw_data),
                synced_at = NOW()
        ");

        $this->executeProductUpsert($stmt, $storeId, $productData);
    }

    public function upsertLocalOrder(int $storeId, array $orderData): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wc_local_orders (
                store_id, wc_id, order_number, customer_id, status, currency, total,
                customer_name, customer_email, items_count, date_created, date_modified, raw_data, synced_at
            ) VALUES (
                :store_id, :wc_id, :order_number, :customer_id, :status, :currency, :total,
                :customer_name, :customer_email, :items_count, :date_created, :date_modified, :raw_data, NOW()
            ) ON DUPLICATE KEY UPDATE
                order_number = VALUES(order_number),
                customer_id = VALUES(customer_id),
                status = VALUES(status),
                currency = VALUES(currency),
                total = VALUES(total),
                customer_name = VALUES(customer_name),
                customer_email = VALUES(customer_email),
                items_count = VALUES(items_count),
                date_created = VALUES(date_created),
                date_modified = VALUES(date_modified),
                raw_data = VALUES(raw_data),
                synced_at = NOW()
        ");

        $this->executeOrderUpsert($stmt, $storeId, $orderData);
    }

    public function upsertLocalCustomer(int $storeId, array $customerData): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wc_local_customers (
                store_id, wc_id, email, first_name, last_name, username, role,
                orders_count, total_spent, raw_data, synced_at
            ) VALUES (
                :store_id, :wc_id, :email, :first_name, :last_name, :username, :role,
                :orders_count, :total_spent, :raw_data, NOW()
            ) ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                username = VALUES(username),
                role = VALUES(role),
                orders_count = VALUES(orders_count),
                total_spent = VALUES(total_spent),
                raw_data = VALUES(raw_data),
                synced_at = NOW()
        ");

        $this->executeCustomerUpsert($stmt, $storeId, $customerData);
    }

    public function deleteLocalProduct(int $storeId, int $wcId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM wc_local_products WHERE store_id = :store_id AND (wc_id = :wc_id OR parent_id = :wc_id)");
        $stmt->execute([':store_id' => $storeId, ':wc_id' => $wcId]);
    }

    public function deleteLocalOrder(int $storeId, int $wcId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM wc_local_orders WHERE store_id = :store_id AND wc_id = :wc_id");
        $stmt->execute([':store_id' => $storeId, ':wc_id' => $wcId]);
    }

    public function deleteLocalCustomer(int $storeId, int $wcId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM wc_local_customers WHERE store_id = :store_id AND wc_id = :wc_id");
        $stmt->execute([':store_id' => $storeId, ':wc_id' => $wcId]);
    }

    /**
     * Retrieve local products with full search, filter, multi-category, and pagination support.
     */
    public function getLocalProducts(int $storeId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, min(100, (int)($params['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ["store_id = :store_id", "parent_id = 0"];
        $bindings = [':store_id' => $storeId];

        // Search
        if (!empty($params['search'])) {
            $term = trim((string)$params['search']);
            $where[] = "(name LIKE :search_term OR sku LIKE :search_term OR CAST(wc_id AS CHAR) = :exact_id)";
            $bindings[':search_term'] = '%' . $term . '%';
            $bindings[':exact_id'] = $term;
        }

        // Status
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $where[] = "status = :status";
            $bindings[':status'] = $params['status'];
        }

        // Type
        if (!empty($params['type']) && $params['type'] !== 'all') {
            $where[] = "type = :type";
            $bindings[':type'] = $params['type'];
        }

        // Stock Status
        if (!empty($params['stock_status']) && $params['stock_status'] !== 'all') {
            $where[] = "stock_status = :stock_status";
            $bindings[':stock_status'] = $params['stock_status'];
        }

        // Multi-category support
        $categoryIds = [];
        if (!empty($params['categories'])) {
            if (is_array($params['categories'])) {
                $categoryIds = array_filter(array_map('intval', $params['categories']));
            } elseif (is_string($params['categories'])) {
                $categoryIds = array_filter(array_map('intval', explode(',', $params['categories'])));
            }
        } elseif (!empty($params['category']) && $params['category'] !== 'all') {
            if (is_array($params['category'])) {
                $categoryIds = array_filter(array_map('intval', $params['category']));
            } else {
                $categoryIds = array_filter(array_map('intval', explode(',', (string)$params['category'])));
            }
        }

        if (!empty($categoryIds)) {
            $catClauses = [];
            foreach ($categoryIds as $idx => $catId) {
                $pName = ":cat_{$idx}";
                $catClauses[] = "JSON_CONTAINS(categories, {$pName}) = 1";
                $bindings[$pName] = json_encode(['id' => (int)$catId]);
            }
            $where[] = "(" . implode(' OR ', $catClauses) . ")";
        }

        // Price range
        if (isset($params['min_price']) && $params['min_price'] !== '') {
            $where[] = "CAST(price AS DECIMAL(14,2)) >= :min_price";
            $bindings[':min_price'] = (float)$params['min_price'];
        }
        if (isset($params['max_price']) && $params['max_price'] !== '') {
            $where[] = "CAST(price AS DECIMAL(14,2)) <= :max_price";
            $bindings[':max_price'] = (float)$params['max_price'];
        }

        // Sorting
        $allowedSort = [
            'id' => 'wc_id',
            'date' => 'date_created',
            'price' => 'CAST(price AS DECIMAL(14,2))',
            'name' => 'name',
            'title' => 'name',
            'modified' => 'date_modified',
        ];
        $sortKey = $params['sort'] ?? 'date';
        $orderCol = $allowedSort[$sortKey] ?? 'date_created';
        $direction = strtolower((string)($params['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $whereSql = implode(' AND ', $where);

        // Count query
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM wc_local_products WHERE {$whereSql}");
        $countStmt->execute($bindings);
        $total = (int)$countStmt->fetchColumn();

        // Data query
        $sql = "SELECT raw_data, synced_at FROM wc_local_products WHERE {$whereSql} ORDER BY {$orderCol} {$direction} LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $this->pdo->prepare($sql);
        $dataStmt->execute($bindings);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $lastSyncedAt = null;
        foreach ($rows as $row) {
            $decoded = json_decode($row['raw_data'] ?? '{}', true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
            if (!empty($row['synced_at'])) {
                $lastSyncedAt = $row['synced_at'];
            }
        }

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
        $syncMeta = $this->getSyncState($storeId, 'products');

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from_cache' => true,
                'last_synced_at' => $lastSyncedAt ?? $syncMeta['last_successful_sync'],
                'is_stale' => $syncMeta['is_stale'],
                'sync_status' => $syncMeta['status'],
                'empty_state' => ($total === 0),
            ],
            'sync_meta' => $syncMeta,
        ];
    }

    /**
     * Retrieve local orders with filters and pagination.
     */
    public function getLocalOrders(int $storeId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, min(100, (int)($params['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ["store_id = :store_id"];
        $bindings = [':store_id' => $storeId];

        if (!empty($params['search'])) {
            $term = trim((string)$params['search']);
            $where[] = "(order_number LIKE :search_term OR customer_name LIKE :search_term OR customer_email LIKE :search_term OR CAST(wc_id AS CHAR) = :exact_id)";
            $bindings[':search_term'] = '%' . $term . '%';
            $bindings[':exact_id'] = $term;
        }

        if (!empty($params['status']) && $params['status'] !== 'all') {
            $where[] = "status = :status";
            $bindings[':status'] = $params['status'];
        }

        if (!empty($params['customer'])) {
            $where[] = "customer_id = :customer_id";
            $bindings[':customer_id'] = (int)$params['customer'];
        }

        if (!empty($params['after'])) {
            $where[] = "date_created >= :after";
            $bindings[':after'] = substr(str_replace('T', ' ', $params['after']), 0, 19);
        }

        if (!empty($params['before'])) {
            $where[] = "date_created <= :before";
            $bindings[':before'] = substr(str_replace('T', ' ', $params['before']), 0, 19);
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM wc_local_orders WHERE {$whereSql}");
        $countStmt->execute($bindings);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT raw_data, synced_at FROM wc_local_orders WHERE {$whereSql} ORDER BY date_created DESC, wc_id DESC LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $this->pdo->prepare($sql);
        $dataStmt->execute($bindings);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $lastSyncedAt = null;
        foreach ($rows as $row) {
            $decoded = json_decode($row['raw_data'] ?? '{}', true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
            if (!empty($row['synced_at'])) {
                $lastSyncedAt = $row['synced_at'];
            }
        }

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
        $syncMeta = $this->getSyncState($storeId, 'orders');

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from_cache' => true,
                'last_synced_at' => $lastSyncedAt ?? $syncMeta['last_successful_sync'],
                'is_stale' => $syncMeta['is_stale'],
                'sync_status' => $syncMeta['status'],
                'empty_state' => ($total === 0),
            ],
            'sync_meta' => $syncMeta,
        ];
    }

    /**
     * Retrieve local customers with search and pagination.
     */
    public function getLocalCustomers(int $storeId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, min(100, (int)($params['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $where = ["store_id = :store_id"];
        $bindings = [':store_id' => $storeId];

        if (!empty($params['search'])) {
            $term = trim((string)$params['search']);
            $where[] = "(first_name LIKE :search_term OR last_name LIKE :search_term OR email LIKE :search_term OR username LIKE :search_term OR CAST(wc_id AS CHAR) = :exact_id)";
            $bindings[':search_term'] = '%' . $term . '%';
            $bindings[':exact_id'] = $term;
        }

        if (!empty($params['role']) && $params['role'] !== 'all') {
            $where[] = "role = :role";
            $bindings[':role'] = $params['role'];
        }

        $allowedSort = [
            'id' => 'wc_id',
            'orders_count' => 'orders_count',
            'total_spent' => 'total_spent',
            'email' => 'email',
            'name' => 'first_name',
        ];
        $sortKey = $params['sort'] ?? 'id';
        $orderCol = $allowedSort[$sortKey] ?? 'wc_id';
        $direction = strtolower((string)($params['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM wc_local_customers WHERE {$whereSql}");
        $countStmt->execute($bindings);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT raw_data, synced_at FROM wc_local_customers WHERE {$whereSql} ORDER BY {$orderCol} {$direction} LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $this->pdo->prepare($sql);
        $dataStmt->execute($bindings);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $lastSyncedAt = null;
        foreach ($rows as $row) {
            $decoded = json_decode($row['raw_data'] ?? '{}', true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
            if (!empty($row['synced_at'])) {
                $lastSyncedAt = $row['synced_at'];
            }
        }

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
        $syncMeta = $this->getSyncState($storeId, 'customers');

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from_cache' => true,
                'last_synced_at' => $lastSyncedAt ?? $syncMeta['last_successful_sync'],
                'is_stale' => $syncMeta['is_stale'],
                'sync_status' => $syncMeta['status'],
                'empty_state' => ($total === 0),
            ],
            'sync_meta' => $syncMeta,
        ];
    }

    /**
     * Compute dashboard analytics and charts directly from local MariaDB database.
     */
    public function getLocalDashboardOverview(Store $store, array $range): array
    {
        $storeId = (int)$store->id;

        $startIso = $range['start']->format('Y-m-d 00:00:00');
        $endIso = $range['end']->format('Y-m-d 23:59:59');
        $prevStartIso = $range['prev_start']->format('Y-m-d 00:00:00');
        $prevEndIso = $range['prev_end']->format('Y-m-d 23:59:59');

        // Current period sales & orders
        $currentStmt = $this->pdo->prepare("
            SELECT status, total, date_created, raw_data FROM wc_local_orders
            WHERE store_id = :store_id AND date_created BETWEEN :start AND :end
            ORDER BY date_created ASC
        ");
        $currentStmt->execute([':store_id' => $storeId, ':start' => $startIso, ':end' => $endIso]);
        $currentOrders = $currentStmt->fetchAll(PDO::FETCH_ASSOC);

        // Previous period sales & orders
        $prevStmt = $this->pdo->prepare("
            SELECT status, total FROM wc_local_orders
            WHERE store_id = :store_id AND date_created BETWEEN :prev_start AND :prev_end
        ");
        $prevStmt->execute([':store_id' => $storeId, ':prev_start' => $prevStartIso, ':prev_end' => $prevEndIso]);
        $prevOrders = $prevStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSales = 0.0;
        $netSales = 0.0;
        $ordersCount = count($currentOrders);
        $statusCounts = [];

        // Build chart timeline
        $timeline = [];
        $curDate = clone $range['start'];
        $diffDays = (int)$range['start']->diff($range['end'])->format('%a');
        $stepDays = max(1, (int)ceil($diffDays / 30));

        while ($curDate <= $range['end']) {
            $dStr = $curDate->format('Y-m-d');
            $timeline[$dStr] = ['date' => $dStr, 'sales' => 0.0, 'orders' => 0];
            $curDate->modify("+{$stepDays} days");
        }

        $recentOrdersList = [];
        foreach ($currentOrders as $row) {
            $st = $row['status'] ?? 'pending';
            $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
            $amt = (float)($row['total'] ?? 0.0);

            if (in_array($st, ['completed', 'processing', 'on-hold'], true)) {
                $totalSales += $amt;
                if ($st === 'completed' || $st === 'processing') {
                    $netSales += $amt;
                }
            }

            $dateKey = !empty($row['date_created']) ? substr($row['date_created'], 0, 10) : '';
            if (isset($timeline[$dateKey])) {
                $timeline[$dateKey]['sales'] += $amt;
                $timeline[$dateKey]['orders'] += 1;
            } else {
                $timeline[$dateKey] = [
                    'date' => $dateKey ?: $range['start']->format('Y-m-d'),
                    'sales' => ($timeline[$dateKey]['sales'] ?? 0.0) + $amt,
                    'orders' => ($timeline[$dateKey]['orders'] ?? 0) + 1,
                ];
            }

            $dec = json_decode($row['raw_data'] ?? '{}', true);
            if (is_array($dec)) {
                $recentOrdersList[] = $dec;
            }
        }

        ksort($timeline);
        $salesChart = array_values($timeline);

        // Previous metrics
        $prevTotalSales = 0.0;
        foreach ($prevOrders as $pRow) {
            $st = $pRow['status'] ?? '';
            if (in_array($st, ['completed', 'processing'], true)) {
                $prevTotalSales += (float)($pRow['total'] ?? 0.0);
            }
        }
        $prevOrdersCount = count($prevOrders);

        $salesChangePercent = null;
        if ($prevTotalSales > 0) {
            $salesChangePercent = round((($totalSales - $prevTotalSales) / $prevTotalSales) * 100, 1);
        }

        $ordersChangePercent = null;
        if ($prevOrdersCount > 0) {
            $ordersChangePercent = round((($ordersCount - $prevOrdersCount) / $prevOrdersCount) * 100, 1);
        }

        // Status breakdown
        $orderStatuses = [];
        $knownStatuses = OrderStatusResolver::getStatuses($storeId, $this->storeRepo);
        foreach ($knownStatuses as $slug => $label) {
            $cnt = $statusCounts[$slug] ?? 0;
            if ($cnt > 0 || in_array($slug, ['completed', 'processing', 'pending', 'cancelled'], true)) {
                $orderStatuses[] = [
                    'status' => $slug,
                    'label' => $label,
                    'count' => $cnt,
                    'percentage' => $ordersCount > 0 ? round(($cnt / $ordersCount) * 100, 1) : 0,
                    'color' => match ($slug) {
                        'completed' => '#10B981',
                        'processing' => '#3B82F6',
                        'pending' => '#F59E0B',
                        'on-hold' => '#8B5CF6',
                        'cancelled' => '#EF4444',
                        'refunded' => '#EC4899',
                        'failed' => '#DC2626',
                        default => '#6B7280',
                    },
                ];
            }
        }

        // Customer & Product counts from local DB
        $customersCount = $this->getLocalTableCount($storeId, 'customers');
        $productsCount = $this->getLocalTableCount($storeId, 'products');

        // Top customers from local DB
        $topCustStmt = $this->pdo->prepare("
            SELECT raw_data FROM wc_local_customers
            WHERE store_id = :store_id
            ORDER BY total_spent DESC, orders_count DESC LIMIT 5
        ");
        $topCustStmt->execute([':store_id' => $storeId]);
        $topCustomers = [];
        while ($cRow = $topCustStmt->fetch(PDO::FETCH_ASSOC)) {
            $dec = json_decode($cRow['raw_data'] ?? '{}', true);
            if (is_array($dec)) {
                $topCustomers[] = $dec;
            }
        }

        // Low stock products from local DB
        $lowStockStmt = $this->pdo->prepare("
            SELECT raw_data FROM wc_local_products
            WHERE store_id = :store_id AND (stock_status = 'outofstock' OR (manage_stock = 1 AND stock_quantity <= 3))
            ORDER BY stock_quantity ASC LIMIT 6
        ");
        $lowStockStmt->execute([':store_id' => $storeId]);
        $lowStockProducts = [];
        while ($pRow = $lowStockStmt->fetch(PDO::FETCH_ASSOC)) {
            $dec = json_decode($pRow['raw_data'] ?? '{}', true);
            if (is_array($dec)) {
                $lowStockProducts[] = $dec;
            }
        }

        // Recent customers
        $recentCustStmt = $this->pdo->prepare("
            SELECT raw_data FROM wc_local_customers
            WHERE store_id = :store_id
            ORDER BY wc_id DESC LIMIT 5
        ");
        $recentCustStmt->execute([':store_id' => $storeId]);
        $recentCustomers = [];
        while ($rcRow = $recentCustStmt->fetch(PDO::FETCH_ASSOC)) {
            $dec = json_decode($rcRow['raw_data'] ?? '{}', true);
            if (is_array($dec)) {
                $recentCustomers[] = $dec;
            }
        }

        usort($recentOrdersList, fn($a, $b) => strcmp((string)($b['date_created'] ?? ''), (string)($a['date_created'] ?? '')));

        return [
            'kpis' => [
                'total_sales' => $totalSales,
                'net_sales' => $netSales,
                'orders_count' => $ordersCount,
                'customers_count' => $customersCount,
                'products_count' => $productsCount,
                'pending_orders' => ($statusCounts['pending'] ?? 0) + ($statusCounts['on-hold'] ?? 0),
                'processing_orders' => $statusCounts['processing'] ?? 0,
                'completed_orders' => $statusCounts['completed'] ?? 0,
                'low_stock_count' => count($lowStockProducts),
                'sales_change_percent' => $salesChangePercent,
                'orders_change_percent' => $ordersChangePercent,
            ],
            'sales_chart' => $salesChart,
            'order_statuses' => $orderStatuses,
            'recent_orders' => array_slice($recentOrdersList, 0, 8),
            'recent_customers' => $recentCustomers,
            'top_customers' => $topCustomers,
            'low_stock_products' => $lowStockProducts,
            'from_cache' => true,
        ];
    }
}
