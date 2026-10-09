<?php

namespace App\Integrations\WooCommerce;

use App\Models\Store;
use App\Support\Cache;

class DemoWooCommerceAdapter implements WooCommerceAdapterInterface
{
    private Store $store;
    private int $storeId;

    public function __construct(Store $store)
    {
        $this->store = $store;
        $this->storeId = (int)$store->id;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function testConnection(): array
    {
        $caps = $this->detectCapabilities();

        return [
            'connected' => true,
            'message' => 'اتصال آزمایشی با فروشگاه دمو (WooCommerce Demo) با موفقیت برقرار شد.',
            'woocommerce_version' => $caps['woocommerce_version'],
            'wordpress_version' => $caps['wordpress_version'],
            'hpos_enabled' => (bool)$caps['hpos_enabled'],
            'currency' => $this->store->currency ?: 'IRR',
            'currency_symbol' => $this->getCurrencySymbol(),
            'timezone' => $this->store->timezone ?: 'Asia/Tehran',
            'capabilities' => $caps,
            'is_demo' => true,
            'tested_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function detectCapabilities(): array
    {
        return [
            'rest_api_available' => true,
            'woocommerce_version' => '9.2.0',
            'wordpress_version' => '6.6.1',
            'hpos_enabled' => true,
            'currency' => $this->store->currency ?: 'IRR',
            'currency_symbol' => $this->getCurrencySymbol(),
            'timezone' => $this->store->timezone ?: 'Asia/Tehran',
            'refunds' => true,
            'coupons' => true,
            'variations' => true,
            'webhooks' => true,
            'taxes_enabled' => false,
            'shipping_enabled' => true,
        ];
    }

    private function getCurrencySymbol(): string
    {
        return match ($this->store->currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'AED',
            default => 'تومان',
        };
    }

    // ==========================================
    // Customers
    // ==========================================

    public function listCustomers(array $params = []): array
    {
        $all = $this->getDemoCustomers();
        $filtered = $this->filterCustomers($all, $params);

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 15)));
        $total = count($filtered);
        $totalPages = (int)ceil($total / $perPage);

        $paged = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return [
            'data' => $paged,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ];
    }

    public function getCustomer(int $id): ?array
    {
        $all = $this->getDemoCustomers();
        foreach ($all as $c) {
            if ((int)$c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    public function getCustomerOrders(int $customerId, array $params = []): array
    {
        $all = $this->getDemoOrders();
        $filtered = array_values(array_filter($all, fn($o) => (int)$o['customer_id'] === $customerId));

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 10)));
        $total = count($filtered);
        $totalPages = (int)ceil($total / $perPage);

        return [
            'data' => array_slice($filtered, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    // ==========================================
    // Orders
    // ==========================================

    public function listOrders(array $params = []): array
    {
        $all = $this->getDemoOrders();
        $filtered = $this->filterOrders($all, $params);

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 15)));
        $total = count($filtered);
        $totalPages = (int)ceil($total / $perPage);

        $paged = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return [
            'data' => $paged,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ];
    }

    public function getOrder(int $id): ?array
    {
        $all = $this->getDemoOrders();
        foreach ($all as $o) {
            if ((int)$o['id'] === $id) {
                return $o;
            }
        }
        return null;
    }

    public function updateOrderStatus(int $id, string $status): array
    {
        $order = $this->getOrder($id);
        if (!$order) {
            throw new \Exception("سفارش با شناسه {$id} یافت نشد.", 404);
        }

        $order['status'] = $status;
        $order['status_label'] = OrderStatusResolver::toPersian($status);
        $this->saveDemoOrder($order);

        Cache::forgetStoreType($this->storeId, 'order');
        Cache::forgetStoreType($this->storeId, 'dashboard');

        return $order;
    }

    public function addOrderNote(int $id, string $note, bool $isCustomerNote = false): array
    {
        return [
            'id' => rand(1000, 9999),
            'author' => 'مدیر سامانه',
            'date_created' => date('Y-m-d H:i:s'),
            'note' => $note,
            'customer_note' => $isCustomerNote,
        ];
    }

    public function refundOrder(int $id, array $data): array
    {
        $order = $this->getOrder($id);
        if (!$order) {
            throw new \Exception("سفارش مورد نظر یافت نشد.", 404);
        }
        $order['status'] = 'refunded';
        $order['status_label'] = 'مسترد شده';
        $this->saveDemoOrder($order);

        return [
            'id' => rand(500, 999),
            'amount' => (string)($data['amount'] ?? $order['total']),
            'reason' => $data['reason'] ?? 'درخواست استرداد مشتری',
            'date_created' => date('Y-m-d H:i:s'),
        ];
    }

    // ==========================================
    // Products
    // ==========================================

    public function listProducts(array $params = []): array
    {
        $all = $this->getDemoProducts();
        $filtered = $this->filterProducts($all, $params);

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 15)));
        $total = count($filtered);
        $totalPages = (int)ceil($total / $perPage);

        return [
            'data' => array_slice($filtered, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ];
    }

    public function getProduct(int $id): ?array
    {
        $all = $this->getDemoProducts();
        foreach ($all as $p) {
            if ((int)$p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    public function createProduct(array $data): array
    {
        $newId = 1000 + rand(1, 999);
        $symbol = $this->getCurrencySymbol();
        $price = (string)($data['regular_price'] ?? $data['price'] ?? '100000');

        $prod = [
            'id' => $newId,
            'name' => $data['name'] ?? 'محصول جدید',
            'slug' => 'product-' . $newId,
            'permalink' => "{$this->store->url}/product/{$newId}",
            'type' => $data['type'] ?? 'simple',
            'status' => $data['status'] ?? 'publish',
            'featured' => (bool)($data['featured'] ?? false),
            'sku' => $data['sku'] ?? 'SKU-' . $newId,
            'price' => $price,
            'regular_price' => $price,
            'sale_price' => (string)($data['sale_price'] ?? ''),
            'price_formatted' => number_format((float)$price) . ' ' . $symbol,
            'on_sale' => !empty($data['sale_price']),
            'purchasable' => true,
            'total_sales' => 0,
            'manage_stock' => (bool)($data['manage_stock'] ?? true),
            'stock_quantity' => isset($data['stock_quantity']) ? (int)$data['stock_quantity'] : 10,
            'stock_status' => (int)($data['stock_quantity'] ?? 10) > 0 ? 'instock' : 'outofstock',
            'low_stock_amount' => 5,
            'categories' => $data['categories'] ?? [['id' => 1, 'name' => 'عمومی', 'slug' => 'general']],
            'tags' => $data['tags'] ?? [],
            'images' => $data['images'] ?? [],
            'attributes' => $data['attributes'] ?? [],
            'variations' => [],
            'date_created' => date('Y-m-d H:i:s'),
            'date_modified' => date('Y-m-d H:i:s'),
        ];

        $this->saveDemoProduct($prod);
        return $prod;
    }

    public function updateProduct(int $id, array $data): array
    {
        $product = $this->getProduct($id);
        if (!$product) {
            throw new \Exception("محصول با شناسه {$id} یافت نشد.", 404);
        }

        foreach ($data as $k => $v) {
            $product[$k] = $v;
        }
        $product['date_modified'] = date('Y-m-d H:i:s');
        $this->saveDemoProduct($product);
        return $product;
    }

    public function deleteProduct(int $id, bool $force = false): array
    {
        $product = $this->getProduct($id);
        if (!$product) {
            throw new \Exception("محصول با شناسه {$id} یافت نشد.", 404);
        }

        $all = $this->getDemoProducts();
        $remaining = array_values(array_filter($all, fn($p) => (int)$p['id'] !== $id));
        $this->saveAllDemoProducts($remaining);

        return ['id' => $id, 'deleted' => true];
    }

    public function listVariations(int $productId, array $params = []): array
    {
        $product = $this->getProduct($productId);
        return $product['variations_data'] ?? [];
    }

    public function getVariation(int $productId, int $variationId): ?array
    {
        $vars = $this->listVariations($productId);
        foreach ($vars as $v) {
            if ((int)$v['id'] === $variationId) return $v;
        }
        return null;
    }

    public function updateVariation(int $productId, int $variationId, array $data): array
    {
        $product = $this->getProduct($productId);
        if (!$product) throw new \Exception("محصول یافت نشد.", 404);

        $vars = $product['variations_data'] ?? [];
        foreach ($vars as &$v) {
            if ((int)$v['id'] === $variationId) {
                foreach ($data as $k => $val) {
                    $v[$k] = $val;
                }
                $product['variations_data'] = $vars;
                $this->saveDemoProduct($product);
                return $v;
            }
        }
        throw new \Exception("متغیر یافت نشد.", 404);
    }

    public function batchVariations(int $productId, array $data): array
    {
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

    // ==========================================
    // Inventory
    // ==========================================

    public function getInventoryMetrics(): array
    {
        $products = $this->getDemoProducts();
        $total = count($products);
        $inStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        foreach ($products as $p) {
            $qty = $p['stock_quantity'] ?? 0;
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
            'store_id' => $this->storeId,
            'store_name' => $this->store->name,
            'currency' => $this->store->currency ?: 'IRR',
        ];
    }

    public function getLowStockProducts(int $limit = 20): array
    {
        $products = $this->getDemoProducts();
        $result = [];
        foreach ($products as $p) {
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
        $products = $this->getDemoProducts();
        $result = [];
        foreach ($products as $p) {
            $qty = (int)($p['stock_quantity'] ?? 0);
            $status = $p['stock_status'] ?? 'instock';
            if ($status === 'outofstock' || $qty <= 0) {
                $result[] = [
                    'product_id' => $p['id'],
                    'product_name' => $p['name'],
                    'sku' => $p['sku'],
                    'stock_quantity' => 0,
                    'price' => $p['price'],
                ];
            }
        }
        return array_slice($result, 0, $limit);
    }

    public function updateStock(int $productId, int $quantity, ?string $stockStatus = null): array
    {
        $product = $this->getProduct($productId);
        if (!$product) {
            throw new \Exception("محصول با شناسه {$productId} یافت نشد.", 404);
        }

        $product['stock_quantity'] = $quantity;
        $product['stock_status'] = $stockStatus ?: ($quantity > 0 ? 'instock' : 'outofstock');
        $this->saveDemoProduct($product);

        return $product;
    }

    // ==========================================
    // Taxonomies
    // ==========================================

    public function getCategories(array $params = []): array
    {
        $symbol = $this->store->currency;
        if ($symbol === 'USD') {
            return [
                ['id' => 1, 'name' => 'Smartphones', 'slug' => 'smartphones', 'count' => 5],
                ['id' => 2, 'name' => 'Laptops', 'slug' => 'laptops', 'count' => 4],
                ['id' => 3, 'name' => 'Audio & Accessories', 'slug' => 'audio', 'count' => 3],
            ];
        } elseif ($symbol === 'EUR') {
            return [
                ['id' => 1, 'name' => 'Fiction & Novels', 'slug' => 'fiction', 'count' => 4],
                ['id' => 2, 'name' => 'Philosophy & Science', 'slug' => 'philosophy', 'count' => 2],
                ['id' => 3, 'name' => 'Notebooks & Stationery', 'slug' => 'stationery', 'count' => 2],
            ];
        }

        return [
            ['id' => 1, 'name' => 'پوشاک مردانه', 'slug' => 'men-clothing', 'count' => 8],
            ['id' => 2, 'name' => 'پوشاک زنانه', 'slug' => 'women-clothing', 'count' => 7],
            ['id' => 3, 'name' => 'اکسسوری و کیف', 'slug' => 'accessories', 'count' => 5],
        ];
    }

    public function getTags(array $params = []): array
    {
        return [
            ['id' => 1, 'name' => 'ویژه', 'slug' => 'featured', 'count' => 5],
            ['id' => 2, 'name' => 'پرفروش', 'slug' => 'bestseller', 'count' => 7],
            ['id' => 3, 'name' => 'تخفیف‌دار', 'slug' => 'discount', 'count' => 4],
        ];
    }

    public function getAttributes(): array
    {
        return [
            ['id' => 1, 'name' => 'سایز', 'slug' => 'pa_size', 'type' => 'select'],
            ['id' => 2, 'name' => 'رنگ', 'slug' => 'pa_color', 'type' => 'color'],
        ];
    }

    // ==========================================
    // Webhooks
    // ==========================================

    public function listWebhooks(array $params = []): array
    {
        return [
            [
                'id' => 101,
                'name' => 'سفارش ایجاد شد (CRM)',
                'topic' => 'order.created',
                'status' => 'active',
                'delivery_url' => "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$this->storeId}",
                'date_created' => date('Y-m-d H:i:s', strtotime('-10 days')),
            ],
            [
                'id' => 102,
                'name' => 'سفارش بروزرسانی شد (CRM)',
                'topic' => 'order.updated',
                'status' => 'active',
                'delivery_url' => "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$this->storeId}",
                'date_created' => date('Y-m-d H:i:s', strtotime('-10 days')),
            ],
            [
                'id' => 103,
                'name' => 'مشتری جدید (CRM)',
                'topic' => 'customer.created',
                'status' => 'active',
                'delivery_url' => "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$this->storeId}",
                'date_created' => date('Y-m-d H:i:s', strtotime('-10 days')),
            ],
            [
                'id' => 104,
                'name' => 'محصول بروزرسانی شد (CRM)',
                'topic' => 'product.updated',
                'status' => 'active',
                'delivery_url' => "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$this->storeId}",
                'date_created' => date('Y-m-d H:i:s', strtotime('-10 days')),
            ],
        ];
    }

    public function createWebhook(array $data): array
    {
        return [
            'id' => rand(200, 999),
            'name' => $data['name'] ?? 'Webhook',
            'topic' => $data['topic'] ?? 'order.created',
            'status' => 'active',
            'delivery_url' => $data['delivery_url'] ?? '',
            'date_created' => date('Y-m-d H:i:s'),
        ];
    }

    public function deleteWebhook(int $webhookId): bool
    {
        return true;
    }

    // ==========================================
    // Internal Demo Data Generators (Per Store)
    // ==========================================

    private function getDemoCustomers(): array
    {
        $cacheKey = "demo_customers_store_{$this->storeId}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $currency = $this->store->currency ?: 'IRR';
        $customers = [];

        if ($currency === 'USD') {
            // Store B: 15 Customers (Electronics / US)
            $firstNames = ['John', 'Emily', 'Michael', 'Sarah', 'David', 'Jessica', 'James', 'Ashley', 'Robert', 'Amanda', 'William', 'Stephanie', 'Daniel', 'Melissa', 'Joseph'];
            $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson'];
            $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Austin', 'Jacksonville', 'San Jose', 'San Francisco', 'Columbus', 'Charlotte'];

            for ($i = 1; $i <= 15; $i++) {
                $fn = $firstNames[$i - 1];
                $ln = $lastNames[$i - 1];
                $customers[] = [
                    'id' => $i,
                    'first_name' => $fn,
                    'last_name' => $ln,
                    'username' => strtolower($fn . '.' . $ln),
                    'email' => strtolower($fn . '.' . $ln) . '@example.com',
                    'phone' => '+1 (555) 01' . sprintf('%02d', $i),
                    'total_spent' => (string)rand(120, 2400),
                    'total_spent_formatted' => '$' . number_format(rand(120, 2400)),
                    'orders_count' => rand(1, 6),
                    'billing' => [
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'address_1' => ($i * 12) . ' Market Street',
                        'city' => $cities[$i - 1],
                        'state' => 'NY',
                        'postcode' => '1000' . $i,
                        'country' => 'US',
                        'email' => strtolower($fn . '.' . $ln) . '@example.com',
                        'phone' => '+1 (555) 01' . sprintf('%02d', $i),
                    ],
                    'shipping' => [
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'address_1' => ($i * 12) . ' Market Street',
                        'city' => $cities[$i - 1],
                        'country' => 'US',
                    ],
                    'date_created' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
                ];
            }
        } elseif ($currency === 'EUR') {
            // Store C: 8 Customers (Books / Europe)
            $names = [
                ['Hans', 'Müller', 'Berlin', 'Germany'],
                ['Sophie', 'Dubois', 'Paris', 'France'],
                ['Marco', 'Rossi', 'Rome', 'Italy'],
                ['Elena', 'Garcia', 'Madrid', 'Spain'],
                ['Lukas', 'Weber', 'Munich', 'Germany'],
                ['Camille', 'Laurent', 'Lyon', 'France'],
                ['Matteo', 'Bianchi', 'Milan', 'Italy'],
                ['Klara', 'Schneider', 'Frankfurt', 'Germany'],
            ];

            for ($i = 1; $i <= 8; $i++) {
                [$fn, $ln, $city, $country] = $names[$i - 1];
                $customers[] = [
                    'id' => $i,
                    'first_name' => $fn,
                    'last_name' => $ln,
                    'username' => strtolower($fn . '_' . $ln),
                    'email' => strtolower($fn . '.' . $ln) . '@bookstore.eu',
                    'phone' => '+49 30 ' . rand(100000, 999999),
                    'total_spent' => (string)rand(45, 480),
                    'total_spent_formatted' => '€' . number_format(rand(45, 480)),
                    'orders_count' => rand(1, 4),
                    'billing' => [
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'address_1' => "Buchstraße {$i}",
                        'city' => $city,
                        'postcode' => '1011' . $i,
                        'country' => $country,
                        'email' => strtolower($fn . '.' . $ln) . '@bookstore.eu',
                        'phone' => '+49 30 ' . rand(100000, 999999),
                    ],
                    'shipping' => [
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'address_1' => "Buchstraße {$i}",
                        'city' => $city,
                        'country' => $country,
                    ],
                    'date_created' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
                ];
            }
        } else {
            // Store A: 30 Customers (Fashion / Iran)
            $firstNames = ['علی', 'رضا', 'محمد', 'سارا', 'مریم', 'حسین', 'زهرا', 'مهدی', 'فاطمه', 'امیر', 'نرگس', 'پیمان', 'نیلوفر', 'محسن', 'الهام', 'سعید', 'مینا', 'حمید', 'شیرین', 'بابک', 'آرزو', 'مسعود', 'فرشته', 'علیرضا', 'نسیم', 'پویا', 'پریا', 'آرش', 'شبنم', 'کامران'];
            $lastNames = ['محمدی', 'رضایی', 'حسینی', 'کریمی', 'موسوی', 'جعفری', 'قاسمی', 'صادقی', 'کاظمی', 'طاهری', 'ابراهیمی', 'اکبری', 'باقری', 'مرادی', 'نوری', 'سلیمانی', 'رستمی', 'نجفی', 'فتحی', 'حیدری', 'غفاری', 'یزدانی', 'افشار', 'سهرابی', 'شاکری', 'فرهادی', 'جمشیدی', 'خسروی', 'بهرامی', 'مقدم'];
            $cities = ['تهران', 'مشهد', 'اصفهان', 'شیراز', 'تبریز', 'کرج', 'اهواز', 'قم', 'کرمانشاه', 'ارومیه', 'رشت', 'زاهدان', 'همدان', 'یزد', 'اردبیل', 'بندرعباس', 'قزوین', 'زنجان', 'ساری', 'گرگان', 'کاشان', 'خرم‌آباد', 'سنندج', 'بوشهر', 'اراک', 'دزفول', 'سیرجان', 'بابل', 'آمل', 'کیش'];

            for ($i = 1; $i <= 30; $i++) {
                $fn = $firstNames[$i - 1];
                $ln = $lastNames[$i - 1];
                $spent = rand(450000, 18500000);
                $customers[] = [
                    'id' => $i,
                    'first_name' => $fn,
                    'last_name' => $ln,
                    'username' => 'user_' . $i,
                    'email' => "user{$i}@iranstore.ir",
                    'phone' => '0912' . sprintf('%07d', rand(1000000, 9999999)),
                    'total_spent' => (string)$spent,
                    'total_spent_formatted' => number_format($spent) . ' تومان',
                    'orders_count' => rand(1, 8),
                    'billing' => [
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'address_1' => 'خیابان آزادی، پلاک ' . ($i * 3),
                        'city' => $cities[$i - 1],
                        'state' => $cities[$i - 1],
                        'postcode' => '14' . sprintf('%08d', $i * 12345),
                        'country' => 'IR',
                        'email' => "user{$i}@iranstore.ir",
                        'phone' => '0912' . sprintf('%07d', rand(1000000, 9999999)),
                    ],
                    'shipping' => [
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'address_1' => 'خیابان آزادی، پلاک ' . ($i * 3),
                        'city' => $cities[$i - 1],
                        'country' => 'IR',
                    ],
                    'date_created' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
                ];
            }
        }

        Cache::set($cacheKey, $customers, 86400);
        return $customers;
    }

    private function getDemoOrders(): array
    {
        $cacheKey = "demo_orders_store_{$this->storeId}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $currency = $this->store->currency ?: 'IRR';
        $customers = $this->getDemoCustomers();
        $products = $this->getDemoProducts();
        $orders = [];
        $statuses = ['completed', 'processing', 'pending', 'on-hold', 'completed', 'completed', 'refunded', 'cancelled'];

        $count = match ($currency) {
            'USD' => 25,
            'EUR' => 10,
            default => 40,
        };

        for ($i = 1; $i <= $count; $i++) {
            $custIndex = ($i - 1) % count($customers);
            $cust = $customers[$custIndex];
            $status = $statuses[($i - 1) % count($statuses)];
            $prodIndex = ($i - 1) % count($products);
            $prod = $products[$prodIndex];

            $qty = rand(1, 3);
            $itemPrice = (float)($prod['price'] ?? 50000);
            $total = $itemPrice * $qty;

            $orders[] = [
                'id' => 1000 + $i,
                'order_number' => (string)(1000 + $i),
                'status' => $status,
                'status_label' => OrderStatusResolver::toPersian($status),
                'currency' => $currency,
                'currency_symbol' => $this->getCurrencySymbol(),
                'total' => (string)$total,
                'total_formatted' => number_format($total) . ' ' . $this->getCurrencySymbol(),
                'customer_id' => $cust['id'],
                'customer_name' => trim($cust['first_name'] . ' ' . $cust['last_name']),
                'customer_email' => $cust['email'],
                'customer_phone' => $cust['phone'],
                'billing' => $cust['billing'],
                'shipping' => $cust['shipping'],
                'payment_method_title' => $currency === 'IRR' ? 'درگاه بانکی شاپرک (زرین‌پال)' : 'Credit Card (Stripe)',
                'line_items' => [
                    [
                        'id' => $i * 10,
                        'name' => $prod['name'],
                        'product_id' => $prod['id'],
                        'quantity' => $qty,
                        'price' => (string)$itemPrice,
                        'total' => (string)$total,
                        'sku' => $prod['sku'],
                    ],
                ],
                'date_created' => date('Y-m-d H:i:s', strtotime("-" . ($i * 12) . " hours")),
                'date_modified' => date('Y-m-d H:i:s', strtotime("-" . ($i * 6) . " hours")),
            ];
        }

        Cache::set($cacheKey, $orders, 86400);
        return $orders;
    }

    private function getDemoProducts(): array
    {
        $cacheKey = "demo_products_store_{$this->storeId}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $currency = $this->store->currency ?: 'IRR';
        $symbol = $this->getCurrencySymbol();
        $products = [];

        if ($currency === 'USD') {
            // Store B: 12 Products (Electronics)
            $items = [
                ['Pro Max Smartphone 256GB', 'phones', 1199, 15, 'instock'],
                ['Ultra Wireless Headphones ANC', 'audio', 299, 22, 'instock'],
                ['SlimBook Laptop 16" M3', 'laptops', 1899, 8, 'instock'],
                ['Smart Watch Series 8 GPS', 'accessories', 399, 3, 'instock'], // low stock
                ['4K Gaming Monitor 27"', 'accessories', 449, 12, 'instock'],
                ['Mechanical Keyboard RGB', 'accessories', 129, 0, 'outofstock'], // out of stock
                ['Ergonomic Wireless Mouse', 'accessories', 79, 18, 'instock'],
                ['Tablet Pro 11-inch 128GB', 'tablets', 799, 7, 'instock'],
                ['True Wireless Earbuds', 'audio', 149, 4, 'instock'], // low stock
                ['Smart Home Speaker Hub', 'audio', 99, 14, 'instock'],
                ['USB-C Multi-port Adapter', 'accessories', 49, 30, 'instock'],
                ['Fast GaN Charger 100W', 'accessories', 69, 0, 'outofstock'], // out of stock
            ];

            foreach ($items as $idx => [$name, $cat, $price, $stock, $status]) {
                $id = 200 + $idx + 1;
                $products[] = [
                    'id' => $id,
                    'name' => $name,
                    'slug' => 'product-' . $id,
                    'permalink' => "{$this->store->url}/product/{$id}",
                    'type' => 'simple',
                    'status' => 'publish',
                    'featured' => $idx < 3,
                    'sku' => 'ELEC-' . sprintf('%03d', $id),
                    'price' => (string)$price,
                    'regular_price' => (string)$price,
                    'sale_price' => '',
                    'price_formatted' => '$' . number_format($price),
                    'on_sale' => false,
                    'purchasable' => true,
                    'total_sales' => rand(10, 80),
                    'manage_stock' => true,
                    'stock_quantity' => $stock,
                    'stock_status' => $status,
                    'low_stock_amount' => 5,
                    'categories' => [['id' => 1, 'name' => ucfirst($cat), 'slug' => $cat]],
                    'tags' => [['id' => 1, 'name' => 'Tech', 'slug' => 'tech']],
                    'images' => [],
                    'attributes' => [],
                    'variations' => [],
                    'date_created' => date('Y-m-d H:i:s', strtotime("-" . ($idx * 3) . " days")),
                    'date_modified' => date('Y-m-d H:i:s'),
                ];
            }
        } elseif ($currency === 'EUR') {
            // Store C: 8 Products (Books)
            $items = [
                ['The Art of Thinking Clearly', 'philosophy', 24, 18, 'instock'],
                ['Clean Code Architecture Handbook', 'tech', 45, 12, 'instock'],
                ['Classic Vintage Leather Journal', 'stationery', 29, 2, 'instock'], // low stock
                ['Fountain Pen Deluxe Edition', 'stationery', 59, 0, 'outofstock'], // out of stock
                ['Sapiens: A Brief History of Humankind', 'history', 28, 25, 'instock'],
                ['Atomic Habits Paperback', 'self-help', 19, 30, 'instock'],
                ['Kafka on the Shore Novel', 'fiction', 18, 4, 'instock'], // low stock
                ['Fine Point Drawing Pens Set', 'stationery', 16, 15, 'instock'],
            ];

            foreach ($items as $idx => [$name, $cat, $price, $stock, $status]) {
                $id = 300 + $idx + 1;
                $products[] = [
                    'id' => $id,
                    'name' => $name,
                    'slug' => 'book-' . $id,
                    'permalink' => "{$this->store->url}/product/{$id}",
                    'type' => 'simple',
                    'status' => 'publish',
                    'featured' => $idx === 0,
                    'sku' => 'BOOK-' . sprintf('%03d', $id),
                    'price' => (string)$price,
                    'regular_price' => (string)$price,
                    'sale_price' => '',
                    'price_formatted' => '€' . number_format($price),
                    'on_sale' => false,
                    'purchasable' => true,
                    'total_sales' => rand(5, 50),
                    'manage_stock' => true,
                    'stock_quantity' => $stock,
                    'stock_status' => $status,
                    'low_stock_amount' => 5,
                    'categories' => [['id' => 1, 'name' => ucfirst($cat), 'slug' => $cat]],
                    'tags' => [['id' => 1, 'name' => 'Books', 'slug' => 'books']],
                    'images' => [],
                    'attributes' => [],
                    'variations' => [],
                    'date_created' => date('Y-m-d H:i:s', strtotime("-" . ($idx * 4) . " days")),
                    'date_modified' => date('Y-m-d H:i:s'),
                ];
            }
        } else {
            // Store A: 20 Products (Fashion / Iran)
            $items = [
                ['پیراهن آستین بلند نخی مردانه', 'men-clothing', 850000, 24, 'instock'],
                ['شلوار جین راسته کلاسیک', 'men-clothing', 1250000, 18, 'instock'],
                ['مانتو کتی بهاره دکمه‌دار', 'women-clothing', 1650000, 12, 'instock'],
                ['تیشرت پنبه‌ای یقه گرد بیسیک', 'men-clothing', 450000, 35, 'instock'],
                ['کیف دوشی چرم طبیعی دست‌دوز', 'accessories', 2450000, 4, 'instock'], // low stock
                ['شال وال اسلپ طرح‌دار سنتی', 'women-clothing', 320000, 28, 'instock'],
                ['هودی جلو بسته اسپرت پاییزی', 'men-clothing', 1100000, 15, 'instock'],
                ['کفش چرم مجلسی مردانه', 'accessories', 2850000, 3, 'instock'], // low stock
                ['بارانی زنانه ضدآب کمربنددار', 'women-clothing', 2200000, 0, 'outofstock'], // out of stock
                ['کمربند چرم گاوی دورو', 'accessories', 380000, 20, 'instock'],
                ['شومیز حریر مجلسی زنانه', 'women-clothing', 980000, 16, 'instock'],
                ['پلیور بافت پاییزه گرم مردانه', 'men-clothing', 890000, 10, 'instock'],
                ['جوراب پنبه‌ای ساقدار (پک ۴ عددی)', 'accessories', 180000, 45, 'instock'],
                ['دامن پلیسه بلند زنانه', 'women-clothing', 750000, 8, 'instock'],
                ['کت تک کژوال مردانه', 'men-clothing', 3400000, 2, 'instock'], // low stock
                ['شلوار کتان کش روزمره', 'men-clothing', 950000, 22, 'instock'],
                ['روسری ابریشم توییل دست‌دوز', 'women-clothing', 680000, 14, 'instock'],
                ['کوله پشتی برزنتی لپ‌تاپ ضدآب', 'accessories', 1450000, 0, 'outofstock'], // out of stock
                ['عینک آفتابی UV400 پولارایزد', 'accessories', 1200000, 11, 'instock'],
                ['نیم‌بوت چرم زنانه پاشنه‌دار', 'women-clothing', 2900000, 6, 'instock'],
            ];

            foreach ($items as $idx => [$name, $cat, $price, $stock, $status]) {
                $id = 100 + $idx + 1;
                $products[] = [
                    'id' => $id,
                    'name' => $name,
                    'slug' => 'product-' . $id,
                    'permalink' => "{$this->store->url}/product/{$id}",
                    'type' => 'simple',
                    'status' => 'publish',
                    'featured' => $idx < 4,
                    'sku' => 'FASH-' . sprintf('%03d', $id),
                    'price' => (string)$price,
                    'regular_price' => (string)$price,
                    'sale_price' => '',
                    'price_formatted' => number_format($price) . ' تومان',
                    'on_sale' => false,
                    'purchasable' => true,
                    'total_sales' => rand(15, 120),
                    'manage_stock' => true,
                    'stock_quantity' => $stock,
                    'stock_status' => $status,
                    'low_stock_amount' => 5,
                    'categories' => [['id' => 1, 'name' => 'پوشاک و مد', 'slug' => $cat]],
                    'tags' => [['id' => 1, 'name' => 'پوشاک', 'slug' => 'clothing']],
                    'images' => [],
                    'attributes' => [],
                    'variations' => [],
                    'date_created' => date('Y-m-d H:i:s', strtotime("-" . ($idx * 3) . " days")),
                    'date_modified' => date('Y-m-d H:i:s'),
                ];
            }
        }

        Cache::set($cacheKey, $products, 86400);
        return $products;
    }

    private function saveDemoProduct(array $product): void
    {
        $all = $this->getDemoProducts();
        $found = false;
        foreach ($all as &$p) {
            if ((int)$p['id'] === (int)$product['id']) {
                $p = $product;
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($all, $product);
        }
        $this->saveAllDemoProducts($all);
    }

    private function saveAllDemoProducts(array $products): void
    {
        Cache::set("demo_products_store_{$this->storeId}", $products, 86400);
        Cache::forgetStoreType($this->storeId, 'product');
        Cache::forgetStoreType($this->storeId, 'inventory');
    }

    private function saveDemoOrder(array $order): void
    {
        $all = $this->getDemoOrders();
        foreach ($all as &$o) {
            if ((int)$o['id'] === (int)$order['id']) {
                $o = $order;
                break;
            }
        }
        Cache::set("demo_orders_store_{$this->storeId}", $all, 86400);
    }

    private function filterCustomers(array $customers, array $params): array
    {
        if (empty($params['search'])) {
            return $customers;
        }

        $term = mb_strtolower(trim((string)$params['search']));
        return array_values(array_filter($customers, function ($c) use ($term) {
            return mb_stripos($c['first_name'], $term) !== false
                || mb_stripos($c['last_name'], $term) !== false
                || mb_stripos($c['email'], $term) !== false
                || mb_stripos($c['phone'], $term) !== false;
        }));
    }

    private function filterOrders(array $orders, array $params): array
    {
        $res = $orders;

        if (!empty($params['status']) && $params['status'] !== 'all') {
            $res = array_filter($res, fn($o) => $o['status'] === $params['status']);
        }

        if (!empty($params['search'])) {
            $term = mb_strtolower(trim((string)$params['search']));
            $res = array_filter($res, function ($o) use ($term) {
                return mb_stripos($o['order_number'], $term) !== false
                    || mb_stripos($o['customer_name'], $term) !== false
                    || mb_stripos($o['customer_email'], $term) !== false;
            });
        }

        return array_values($res);
    }

    private function filterProducts(array $products, array $params): array
    {
        $res = $products;

        if (!empty($params['stock_status']) && $params['stock_status'] !== 'all') {
            $res = array_filter($res, fn($p) => ($p['stock_status'] ?? '') === $params['stock_status']);
        }

        if (!empty($params['status']) && $params['status'] !== 'all') {
            $res = array_filter($res, fn($p) => ($p['status'] ?? '') === $params['status']);
        }

        if (!empty($params['type']) && $params['type'] !== 'all') {
            $res = array_filter($res, fn($p) => ($p['type'] ?? '') === $params['type']);
        }

        $categoryIds = [];
        if (!empty($params['categories'])) {
            $categoryIds = is_array($params['categories']) ? array_map('intval', $params['categories']) : array_map('intval', explode(',', (string)$params['categories']));
        } elseif (!empty($params['category']) && $params['category'] !== 'all') {
            $categoryIds = is_array($params['category']) ? array_map('intval', $params['category']) : array_map('intval', explode(',', (string)$params['category']));
        }

        if (!empty($categoryIds)) {
            $res = array_filter($res, function ($p) use ($categoryIds) {
                $prodCatIds = array_column($p['categories'] ?? [], 'id');
                return !empty(array_intersect($prodCatIds, $categoryIds));
            });
        }

        if (!empty($params['search'])) {
            $term = mb_strtolower(trim((string)$params['search']));
            $res = array_filter($res, function ($p) use ($term) {
                return mb_stripos($p['name'], $term) !== false
                    || mb_stripos($p['sku'], $term) !== false;
            });
        }

        return array_values($res);
    }
}
