<?php

declare(strict_types=1);

/**
 * Mock WooCommerce Server for Automated Integration Testing
 */

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Parse authorization header
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

// Check scenarios by URL prefix
if (str_starts_with($path, '/timeout')) {
    sleep(16);
    exit;
}

if (str_starts_with($path, '/unauthorized')) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 'woocommerce_rest_cannot_view',
        'message' => 'Sorry, you cannot list resources.',
        'data' => ['status' => 401],
    ]);
    exit;
}

if (str_starts_with($path, '/forbidden')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 'woocommerce_rest_cannot_edit',
        'message' => 'Consumer key does not have write access.',
        'data' => ['status' => 403],
    ]);
    exit;
}

if (str_starts_with($path, '/ratelimited')) {
    // If request has header or query param "retry=1", succeed, else 429
    static $attempts = 0;
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    header('Retry-After: 1');
    echo json_encode([
        'code' => 'woocommerce_rate_limit_exceeded',
        'message' => 'Rate limit exceeded.',
        'data' => ['status' => 429],
    ]);
    exit;
}

if (str_starts_with($path, '/badgateway')) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 'bad_gateway',
        'message' => 'Bad Gateway from upstream server.',
        'data' => ['status' => 502],
    ]);
    exit;
}

if (str_starts_with($path, '/unavailable')) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 'service_unavailable',
        'message' => 'Service Unavailable. Store is in maintenance mode.',
        'data' => ['status' => 503],
    ]);
    exit;
}

// Check for invalid credentials
if ($user !== 'ck_valid_test_key' || $pass !== 'cs_valid_test_secret') {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 'woocommerce_rest_cannot_view',
        'message' => 'Consumer key or secret is invalid.',
        'data' => ['status' => 401],
    ]);
    exit;
}

// Is legacy scenario?
$isLegacy = str_starts_with($path, '/legacy');

// Handle standard endpoints
header('Content-Type: application/json; charset=utf-8');

if (str_contains($path, '/system_status')) {
    echo json_encode([
        'environment' => [
            'version' => $isLegacy ? '7.9.0' : '8.6.1',
            'wp_version' => $isLegacy ? '6.2.2' : '6.4.3',
            'php_version' => '8.2.14',
            'server_info' => 'nginx/1.24.0',
        ],
        'database' => [
            'hpos_enabled' => !$isLegacy,
            'wc_database_version' => $isLegacy ? '7.9.0' : '8.6.1',
        ],
        'features' => [
            'custom_order_tables' => [
                'enabled' => !$isLegacy,
            ],
        ],
        'settings' => [
            'currency' => 'IRR',
            'currency_symbol' => '﷼',
            'timezone' => 'Asia/Tehran',
            'order_storage' => !$isLegacy ? 'custom' : 'legacy',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (str_contains($path, '/webhooks')) {
    echo json_encode([
        [
            'id' => 1,
            'name' => 'Order Created Hook',
            'status' => 'active',
            'topic' => 'order.created',
        ]
    ]);
    exit;
}

if (str_contains($path, '/reports/orders/totals')) {
    echo json_encode([
        ['slug' => 'pending', 'name' => 'در انتظار پرداخت', 'total' => '12'],
        ['slug' => 'processing', 'name' => 'در حال پردازش', 'total' => '45'],
        ['slug' => 'custom-prep', 'name' => 'در حال بسته‌بندی سفارشی', 'total' => '8'],
        ['slug' => 'completed', 'name' => 'تکمیل شده', 'total' => '320'],
        ['slug' => 'cancelled', 'name' => 'لغو شده', 'total' => '5'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fixture Customers
$fixtureCustomers = [
    [
        'id' => 101,
        'date_created' => '2024-01-15T10:20:30',
        'date_modified' => '2024-03-01T12:00:00',
        'email' => 'hossein@example.com',
        'first_name' => 'حسین',
        'last_name' => 'کرمی',
        'role' => 'customer',
        'username' => 'hkarami',
        'avatar_url' => '',
        'billing' => [
            'first_name' => 'حسین',
            'last_name' => 'کرمی',
            'company' => 'فناوران پیشرو',
            'address_1' => 'خیابان آزادی، کوچه پنجم، پلاک ۱۲',
            'address_2' => 'واحد ۴',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1458963214',
            'country' => 'IR',
            'email' => 'hossein@example.com',
            'phone' => '09123456789',
        ],
        'shipping' => [
            'first_name' => 'حسین',
            'last_name' => 'کرمی',
            'company' => 'فناوران پیشرو',
            'address_1' => 'خیابان آزادی، کوچه پنجم، پلاک ۱۲',
            'address_2' => 'واحد ۴',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1458963214',
            'country' => 'IR',
            'phone' => '09123456789',
        ],
        'is_paying_customer' => true,
        'orders_count' => 12,
        'total_spent' => '45000000',
        'meta_data' => [
            ['id' => 1, 'key' => '_last_order_date', 'value' => '2024-03-01T12:00:00'],
            ['id' => 2, 'key' => 'customer_loyalty_tier', 'value' => 'gold'],
        ],
    ],
    [
        'id' => 102,
        'date_created' => '2024-02-10T14:15:00',
        'date_modified' => '2024-02-28T16:30:00',
        'email' => 'sara.mohammadi@example.com',
        'first_name' => 'سارا',
        'last_name' => 'محمدی',
        'role' => 'customer',
        'username' => 'sara_m',
        'avatar_url' => '',
        'billing' => [
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'company' => '',
            'address_1' => 'بلوار وکیل‌آباد، پلاک ۸۰',
            'address_2' => '',
            'city' => 'مشهد',
            'state' => 'KHD',
            'postcode' => '9177894561',
            'country' => 'IR',
            'email' => 'sara.mohammadi@example.com',
            'phone' => '09351234567',
        ],
        'shipping' => [
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'company' => '',
            'address_1' => 'بلوار وکیل‌آباد، پلاک ۸۰',
            'address_2' => '',
            'city' => 'مشهد',
            'state' => 'KHD',
            'postcode' => '9177894561',
            'country' => 'IR',
            'phone' => '09351234567',
        ],
        'is_paying_customer' => true,
        'orders_count' => 5,
        'total_spent' => '12500000',
        'meta_data' => [
            ['id' => 3, 'key' => '_last_order_date', 'value' => '2024-02-28T16:30:00'],
        ],
    ],
    [
        'id' => 103,
        'date_created' => '2024-03-05T09:00:00',
        'date_modified' => '2024-03-05T09:00:00',
        'email' => 'ali.rezaei@example.com',
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'role' => 'customer',
        'username' => 'alirez',
        'avatar_url' => '',
        'billing' => [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'company' => '',
            'address_1' => 'چهارباغ عباسی',
            'address_2' => '',
            'city' => 'اصفهان',
            'state' => 'ESF',
            'postcode' => '8145678912',
            'country' => 'IR',
            'email' => 'ali.rezaei@example.com',
            'phone' => '09139876543',
        ],
        'shipping' => [],
        'is_paying_customer' => false,
        'orders_count' => 0,
        'total_spent' => '0',
        'meta_data' => [],
    ],
    [
        'id' => 104,
        'date_created' => '2023-05-12T11:45:00',
        'date_modified' => '2024-03-15T18:00:00',
        'email' => 'maryam.ahmadi@example.com',
        'first_name' => 'مریم',
        'last_name' => 'احمدی',
        'role' => 'customer',
        'username' => 'm_ahmadi',
        'avatar_url' => '',
        'billing' => [
            'first_name' => 'مریم',
            'last_name' => 'احمدی',
            'company' => 'بازرگانی احمدی',
            'address_1' => 'خیابان زند، کوچه ۱۲',
            'address_2' => 'پلاک ۴',
            'city' => 'شیراز',
            'state' => 'FRS',
            'postcode' => '7134567890',
            'country' => 'IR',
            'email' => 'maryam.ahmadi@example.com',
            'phone' => '09171239876',
        ],
        'shipping' => [
            'first_name' => 'مریم',
            'last_name' => 'احمدی',
            'company' => 'بازرگانی احمدی',
            'address_1' => 'خیابان زند، کوچه ۱۲',
            'address_2' => 'پلاک ۴',
            'city' => 'شیراز',
            'state' => 'FRS',
            'postcode' => '7134567890',
            'country' => 'IR',
            'phone' => '09171239876',
        ],
        'is_paying_customer' => true,
        'orders_count' => 84,
        'total_spent' => '320000000',
        'meta_data' => [
            ['id' => 4, 'key' => '_last_order_date', 'value' => '2024-03-15T18:00:00'],
            ['id' => 5, 'key' => 'vip_member', 'value' => 'yes'],
        ],
    ],
    [
        'id' => 105,
        'date_created' => '2024-03-20T08:30:00',
        'date_modified' => '2024-03-20T08:30:00',
        'email' => 'mehdi.taghavi@example.com',
        'first_name' => '',
        'last_name' => '',
        'role' => 'customer',
        'username' => 'mehdi_t',
        'avatar_url' => '',
        'billing' => null,
        'shipping' => null,
        'is_paying_customer' => false,
        'orders_count' => 0,
        'total_spent' => '0',
        'meta_data' => null,
    ],
];

// Fixture Orders
$fixtureOrders = [
    [
        'id' => 1001,
        'number' => '1001',
        'customer_id' => 101,
        'status' => 'processing',
        'date_created' => '2024-03-01T12:00:00',
        'total' => '25000000',
        'subtotal' => '24850000',
        'shipping_total' => '150000',
        'total_tax' => '0',
        'discount_total' => '0',
        'currency' => 'IRR',
        'payment_method' => 'zarinpal',
        'payment_method_title' => 'پرداخت آنلاین زرین‌پال',
        'billing' => [
            'first_name' => 'حسین',
            'last_name' => 'کرمی',
            'company' => 'فناوران پیشرو',
            'address_1' => 'خیابان آزادی، کوچه پنجم، پلاک ۱۲',
            'address_2' => 'واحد ۴',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1458963214',
            'country' => 'IR',
            'email' => 'hossein@example.com',
            'phone' => '09123456789',
        ],
        'shipping' => [
            'first_name' => 'حسین',
            'last_name' => 'کرمی',
            'company' => 'فناوران پیشرو',
            'address_1' => 'خیابان آزادی، کوچه پنجم، پلاک ۱۲',
            'address_2' => 'واحد ۴',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1458963214',
            'country' => 'IR',
            'phone' => '09123456789',
        ],
        'line_items' => [
            [
                'id' => 1,
                'name' => 'لپ‌تاپ ایسوس Vivobook',
                'sku' => 'ASUS-VIVO-15',
                'price' => '25000000',
                'quantity' => 1,
                'total' => '25000000',
                'subtotal' => '25000000',
                'total_tax' => '0',
                'variation_id' => 0,
                'meta_data' => [],
            ],
        ],
        'shipping_lines' => [
            ['id' => 1, 'method_title' => 'پست پیشتاز', 'total' => '150000'],
        ],
        'refunds' => [],
        'meta_data' => [
            ['id' => 1, 'key' => 'delivery_time', 'value' => 'صبح'],
        ],
    ],
    [
        'id' => 1002,
        'number' => '1002',
        'customer_id' => 101,
        'status' => 'processing',
        'date_created' => '2024-03-10T15:30:00',
        'total' => '20000000',
        'subtotal' => '19880000',
        'shipping_total' => '120000',
        'total_tax' => '0',
        'discount_total' => '0',
        'currency' => 'IRR',
        'payment_method' => 'bacs',
        'payment_method_title' => 'کارت به کارت',
        'billing' => [
            'first_name' => 'حسین',
            'last_name' => 'کرمی',
            'company' => 'فناوران پیشرو',
            'address_1' => 'خیابان آزادی',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1458963214',
            'country' => 'IR',
            'email' => 'hossein@example.com',
            'phone' => '09123456789',
        ],
        'shipping' => [
            'first_name' => 'حسین',
            'last_name' => 'کرمی',
            'company' => 'فناوران پیشرو',
            'address_1' => 'خیابان آزادی',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1458963214',
            'country' => 'IR',
            'phone' => '09123456789',
        ],
        'line_items' => [
            [
                'id' => 2,
                'name' => 'هدفون بی‌سیم سونی',
                'sku' => 'SONY-WH-1000',
                'price' => '10000000',
                'quantity' => 2,
                'total' => '20000000',
                'subtotal' => '20000000',
                'total_tax' => '0',
                'variation_id' => 450,
                'meta_data' => [
                    ['id' => 10, 'key' => 'رنگ', 'value' => 'مشکی'],
                    ['id' => 11, 'key' => 'گارانتی', 'value' => '۱۸ ماهه آواژنگ'],
                ],
            ],
        ],
        'shipping_lines' => [
            ['id' => 2, 'method_title' => 'تیپاکس', 'total' => '120000'],
        ],
        'refunds' => [],
        'meta_data' => [],
    ],
    [
        'id' => 1003,
        'number' => '1003',
        'customer_id' => 102,
        'status' => 'completed',
        'date_created' => '2024-02-28T16:30:00',
        'total' => '12500000',
        'subtotal' => '12420000',
        'shipping_total' => '80000',
        'total_tax' => '0',
        'discount_total' => '0',
        'currency' => 'IRR',
        'payment_method' => 'mellat',
        'payment_method_title' => 'درگاه پرداخت ملت',
        'billing' => [
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'company' => '',
            'address_1' => 'بلوار وکیل‌آباد',
            'city' => 'مشهد',
            'state' => 'KHD',
            'postcode' => '9177894561',
            'country' => 'IR',
            'email' => 'sara@example.com',
            'phone' => '09351234567',
        ],
        'shipping' => [
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'company' => '',
            'address_1' => 'بلوار وکیل‌آباد',
            'city' => 'مشهد',
            'state' => 'KHD',
            'postcode' => '9177894561',
            'country' => 'IR',
            'phone' => '09351234567',
        ],
        'line_items' => [
            [
                'id' => 3,
                'name' => 'کیبورد مکانیکی ریزر',
                'sku' => 'RZ-HUNTS-01',
                'price' => '12500000',
                'quantity' => 1,
                'total' => '12500000',
                'subtotal' => '12500000',
                'total_tax' => '0',
                'variation_id' => 0,
                'meta_data' => [],
            ],
        ],
        'shipping_lines' => [
            ['id' => 3, 'method_title' => 'پست سفارشی', 'total' => '80000'],
        ],
        'refunds' => [],
        'meta_data' => [],
    ],
    [
        'id' => 1004,
        'number' => '1004',
        'customer_id' => 104,
        'status' => 'custom-prep',
        'date_created' => '2024-03-15T18:00:00',
        'total' => '85000000',
        'subtotal' => '84800000',
        'shipping_total' => '200000',
        'total_tax' => '0',
        'discount_total' => '0',
        'currency' => 'IRR',
        'payment_method' => 'bacs',
        'payment_method_title' => 'حواله بانکی پایا',
        'billing' => [
            'first_name' => 'رضا',
            'last_name' => 'مرادی',
            'company' => 'توسعه وب',
            'address_1' => 'خیابان میرداماد',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1918965412',
            'country' => 'IR',
            'email' => 'reza@example.com',
            'phone' => '09121112233',
        ],
        'shipping' => [
            'first_name' => 'رضا',
            'last_name' => 'مرادی',
            'company' => 'توسعه وب',
            'address_1' => 'خیابان میرداماد',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1918965412',
            'country' => 'IR',
            'phone' => '09121112233',
        ],
        'line_items' => [
            [
                'id' => 4,
                'name' => 'مانیتور منحنی سامسونگ ۳۴ اینچ',
                'sku' => 'SAM-CURVE-34',
                'price' => '42500000',
                'quantity' => 2,
                'total' => '85000000',
                'subtotal' => '85000000',
                'total_tax' => '0',
                'variation_id' => 0,
                'meta_data' => [],
            ],
        ],
        'shipping_lines' => [
            ['id' => 4, 'method_title' => 'باربری اختصاصی', 'total' => '200000'],
        ],
        'refunds' => [],
        'meta_data' => [],
    ],
    [
        'id' => 1005,
        'number' => '1005',
        'customer_id' => 0, // Guest Order
        'status' => 'completed',
        'date_created' => '2024-03-22T11:00:00',
        'total' => '4500000',
        'subtotal' => '4450000',
        'shipping_total' => '50000',
        'total_tax' => '0',
        'discount_total' => '0',
        'currency' => 'IRR',
        'payment_method' => 'cod',
        'payment_method_title' => 'پرداخت در محل',
        'billing' => [
            'first_name' => 'مهمان',
            'last_name' => 'فروشگاه',
            'company' => '',
            'address_1' => 'خیابان ولیعصر',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1234567890',
            'country' => 'IR',
            'email' => 'guest@example.com',
            'phone' => '09191112233',
        ],
        'shipping' => [
            'first_name' => 'مهمان',
            'last_name' => 'فروشگاه',
            'company' => '',
            'address_1' => 'خیابان ولیعصر',
            'city' => 'تهران',
            'state' => 'THR',
            'postcode' => '1234567890',
            'country' => 'IR',
            'phone' => '09191112233',
        ],
        'line_items' => [
            [
                'id' => 5,
                'name' => 'موس پد گیمینگ لاجیتک',
                'sku' => 'LOGI-PAD-G',
                'price' => '4500000',
                'quantity' => 1,
                'total' => '4500000',
                'subtotal' => '4500000',
                'total_tax' => '0',
                'variation_id' => 0,
                'meta_data' => [],
            ],
        ],
        'shipping_lines' => [
            ['id' => 5, 'method_title' => 'پیک موتوری', 'total' => '50000'],
        ],
        'refunds' => [],
        'meta_data' => [],
    ],
];

// Single Customer: /customers/{id}
if (preg_match('#/customers/(\d+)#', $path, $matches)) {
    $custId = (int)$matches[1];
    foreach ($fixtureCustomers as $cust) {
        if ($cust['id'] === $custId) {
            echo json_encode($cust, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode([
        'code' => 'woocommerce_rest_customer_invalid_id',
        'message' => 'Invalid customer ID.',
        'data' => ['status' => 404],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Customers List: /customers
if (str_contains($path, '/customers')) {
    $search = trim($_GET['search'] ?? '');
    $role = $_GET['role'] ?? 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, (int)($_GET['per_page'] ?? 10));

    $filtered = array_filter($fixtureCustomers, function ($c) use ($search, $role) {
        if ($role !== 'all' && ($c['role'] ?? '') !== $role) {
            return false;
        }
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $fn = mb_strtolower($c['first_name'] ?? '');
            $ln = mb_strtolower($c['last_name'] ?? '');
            $em = mb_strtolower($c['email'] ?? '');
            $un = mb_strtolower($c['username'] ?? '');
            if (!str_contains($fn, $searchLower) && !str_contains($ln, $searchLower) && !str_contains($em, $searchLower) && !str_contains($un, $searchLower)) {
                return false;
            }
        }
        return true;
    });

    $total = count($filtered);
    $totalPages = (int)ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    $paged = array_slice(array_values($filtered), $offset, $perPage);

    header("X-WP-Total: {$total}");
    header("X-WP-TotalPages: {$totalPages}");
    echo json_encode(array_values($paged), JSON_UNESCAPED_UNICODE);
    exit;
}

// Orders storage file for mock persistence
$mockOrdersFile = sys_get_temp_dir() . '/mock_wc_orders_phase5_v2.json';
if (!file_exists($mockOrdersFile)) {
    file_put_contents($mockOrdersFile, json_encode($fixtureOrders, JSON_UNESCAPED_UNICODE));
}
$savedOrders = json_decode(file_get_contents($mockOrdersFile), true) ?: $fixtureOrders;

// Order Notes storage
$mockNotesFile = sys_get_temp_dir() . '/mock_wc_notes_phase5_v2.json';
if (!file_exists($mockNotesFile)) {
    file_put_contents($mockNotesFile, json_encode([
        1001 => [
            [
                'id' => 1,
                'author' => 'سیستم ووکامرس',
                'date_created' => '2024-03-01T12:00:00',
                'note' => 'وضعیت سفارش از در حال بررسی به تکمیل شده تغییر یافت.',
                'customer_note' => false,
            ],
            [
                'id' => 2,
                'author' => 'پشتیبانی فروشگاه',
                'date_created' => '2024-03-01T12:05:00',
                'note' => 'کد رهگیری پستی مرسوله: 19845612345678901234',
                'customer_note' => true,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE));
}
$savedNotes = json_decode(file_get_contents($mockNotesFile), true) ?: [];

// Single Order Notes: /orders/{id}/notes
if (preg_match('#/orders/(\d+)/notes#', $path, $matches)) {
    $orderId = (int)$matches[1];
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $newNote = [
            'id' => rand(100, 9999),
            'author' => 'مدیر سامانه',
            'date_created' => date('Y-m-d\TH:i:s'),
            'note' => (string)($body['note'] ?? ''),
            'customer_note' => (bool)($body['customer_note'] ?? false),
        ];
        $savedNotes[$orderId][] = $newNote;
        file_put_contents($mockNotesFile, json_encode($savedNotes, JSON_UNESCAPED_UNICODE));
        http_response_code(201);
        echo json_encode($newNote, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $notes = $savedNotes[$orderId] ?? [];
    echo json_encode($notes, JSON_UNESCAPED_UNICODE);
    exit;
}

// Single Order Refunds: /orders/{id}/refunds
if (preg_match('#/orders/(\d+)/refunds#', $path, $matches)) {
    $orderId = (int)$matches[1];
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $amount = (float)($body['amount'] ?? 0.0);
    $reason = (string)($body['reason'] ?? '');

    // Simulate gateway failure test
    if ($reason === 'fail_gateway' || $amount > 500000000) {
        http_response_code(400);
        echo json_encode([
            'code' => 'woocommerce_rest_refund_failed',
            'message' => 'درگاه پرداخت با خطا مواجه شد و امکان استرداد وجه آنلاین وجود ندارد.',
            'data' => ['status' => 400],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $refundId = rand(3000, 9999);
    $newRefund = [
        'id' => $refundId,
        'amount' => (string)$amount,
        'reason' => $reason,
        'date_created' => date('Y-m-d\TH:i:s'),
        'refunded_by' => 1,
    ];

    foreach ($savedOrders as &$ord) {
        if ($ord['id'] === $orderId) {
            $ord['refunds'][] = [
                'id' => $refundId,
                'reason' => $reason,
                'total' => '-' . $amount,
            ];
            break;
        }
    }
    file_put_contents($mockOrdersFile, json_encode($savedOrders, JSON_UNESCAPED_UNICODE));

    http_response_code(201);
    echo json_encode($newRefund, JSON_UNESCAPED_UNICODE);
    exit;
}

// Single Order: /orders/{id}
if (preg_match('#/orders/(\d+)#', $path, $matches)) {
    $orderId = (int)$matches[1];

    if ($method === 'PUT' || $method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $found = null;
        foreach ($savedOrders as &$ord) {
            if ($ord['id'] === $orderId || (int)($ord['number'] ?? 0) === $orderId) {
                if (isset($body['status'])) {
                    $ord['status'] = $body['status'];
                }
                $found = $ord;
                break;
            }
        }
        if ($found) {
            file_put_contents($mockOrdersFile, json_encode($savedOrders, JSON_UNESCAPED_UNICODE));
            echo json_encode($found, JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(404);
        echo json_encode([
            'code' => 'woocommerce_rest_shop_order_invalid_id',
            'message' => 'Invalid order ID.',
            'data' => ['status' => 404],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    foreach ($savedOrders as $ord) {
        if ($ord['id'] === $orderId || (int)($ord['number'] ?? 0) === $orderId) {
            echo json_encode($ord, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode([
        'code' => 'woocommerce_rest_shop_order_invalid_id',
        'message' => 'Invalid order ID.',
        'data' => ['status' => 404],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Orders Batch Endpoint
if (str_contains($path, '/orders/batch')) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $updates = $body['update'] ?? [];
    $updatedResults = [];
    foreach ($updates as $u) {
        $uId = (int)($u['id'] ?? 0);
        foreach ($savedOrders as &$ord) {
            if ($ord['id'] === $uId || (int)($ord['number'] ?? 0) === $uId) {
                foreach ($u as $k => $val) {
                    if ($k !== 'id') {
                        $ord[$k] = $val;
                    }
                }
                $updatedResults[] = $ord;
                break;
            }
        }
    }
    unset($ord);
    file_put_contents($mockOrdersFile, json_encode($savedOrders, JSON_UNESCAPED_UNICODE));
    echo json_encode(['update' => $updatedResults], JSON_UNESCAPED_UNICODE);
    exit;
}

// Orders Endpoint (List with search, status, pagination)
if (str_contains($path, '/orders')) {
    $custFilter = isset($_GET['customer']) && $_GET['customer'] !== '' ? (int)$_GET['customer'] : null;
    $statusFilter = isset($_GET['status']) && $_GET['status'] !== 'all' ? $_GET['status'] : null;
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, (int)($_GET['per_page'] ?? 15));

    $filteredOrders = array_filter($savedOrders, function ($o) use ($custFilter, $statusFilter, $search) {
        if ($custFilter !== null && $o['customer_id'] !== $custFilter) {
            return false;
        }
        if ($statusFilter !== null && $o['status'] !== $statusFilter) {
            return false;
        }
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $num = (string)($o['number'] ?? $o['id']);
            $idStr = (string)$o['id'];
            $custEmail = mb_strtolower($o['billing']['email'] ?? '');
            $custName = mb_strtolower(($o['billing']['first_name'] ?? '') . ' ' . ($o['billing']['last_name'] ?? ''));
            if (!str_contains($num, $searchLower) && !str_contains($idStr, $searchLower) && !str_contains($custEmail, $searchLower) && !str_contains($custName, $searchLower)) {
                return false;
            }
        }
        return true;
    });

    $total = count($filteredOrders);
    $totalPages = (int)ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    $paged = array_slice(array_values($filteredOrders), $offset, $perPage);

    header("X-WP-Total: {$total}");
    header("X-WP-TotalPages: {$totalPages}");
    echo json_encode($paged, JSON_UNESCAPED_UNICODE);
    exit;
}

// Product categories fixture
$fixtureCategories = [
    ['id' => 1, 'name' => 'لپ‌تاپ و اولترابوک', 'slug' => 'laptops', 'count' => 12],
    ['id' => 2, 'name' => 'لوازم جانبی کامپیوتر', 'slug' => 'accessories', 'count' => 45],
    ['id' => 3, 'name' => 'تجهیزات صوتی', 'slug' => 'audio', 'count' => 8],
];

// Product tags fixture
$fixtureTags = [
    ['id' => 1, 'name' => 'تخفیف ویژه', 'slug' => 'special-offer', 'count' => 5],
    ['id' => 2, 'name' => 'ارسال فوری', 'slug' => 'express-delivery', 'count' => 18],
    ['id' => 3, 'name' => 'پرفروش', 'slug' => 'best-seller', 'count' => 22],
];

// Product attributes fixture
$fixtureAttributes = [
    ['id' => 1, 'name' => 'رنگ', 'slug' => 'pa_color', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true],
    ['id' => 2, 'name' => 'گارانتی', 'slug' => 'pa_warranty', 'type' => 'select', 'order_by' => 'name', 'has_archives' => false],
    ['id' => 3, 'name' => 'حافظه داخلی', 'slug' => 'pa_storage', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true],
];

// Fixture Products
$fixtureProducts = [
    [
        'id' => 301,
        'name' => 'لپ‌تاپ ایسوس ویووبوک ۱۵ اینچی',
        'slug' => 'asus-vivobook-15',
        'permalink' => 'https://example.com/product/asus-vivobook-15',
        'type' => 'simple',
        'status' => 'publish',
        'featured' => true,
        'catalog_visibility' => 'visible',
        'description' => '<p>لپ‌تاپ با کارایی بالا و طراحی سبک و مدرن مناسب امور روزمره و مهندسی.</p>',
        'short_description' => '<p>پردازنده Core i7 نسل ۱۳ با ۱۶ گیگابایت رم پرسرعت.</p>',
        'sku' => 'ASUS-VIVO-15',
        'price' => '23500000',
        'regular_price' => '25000000',
        'sale_price' => '23500000',
        'on_sale' => true,
        'manage_stock' => true,
        'stock_quantity' => 8,
        'stock_status' => 'instock',
        'backorders' => 'no',
        'low_stock_amount' => 2,
        'weight' => '1.7',
        'dimensions' => ['length' => '36', 'width' => '23', 'height' => '1.9'],
        'categories' => [
            ['id' => 1, 'name' => 'لپ‌تاپ و اولترابوک', 'slug' => 'laptops']
        ],
        'tags' => [
            ['id' => 1, 'name' => 'تخفیف ویژه', 'slug' => 'special-offer'],
            ['id' => 3, 'name' => 'پرفروش', 'slug' => 'best-seller']
        ],
        'images' => [
            ['id' => 10, 'src' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=500', 'name' => 'لپ‌تاپ ایسوس', 'alt' => 'لپ‌تاپ ایسوس']
        ],
        'attributes' => [
            [
                'id' => 2,
                'name' => 'گارانتی',
                'position' => 0,
                'visible' => true,
                'variation' => false,
                'options' => ['۲۴ ماهه سازگار', '۱۸ ماهه آواژنگ']
            ]
        ],
        'default_attributes' => [],
        'variations' => [],
        'meta_data' => [
            ['id' => 1, 'key' => '_custom_warranty_period', 'value' => '24_months'],
            ['id' => 2, 'key' => 'origin_country', 'value' => 'تایوان']
        ],
        'date_created' => '2024-01-10T10:00:00',
        'date_modified' => '2024-03-01T15:30:00',
    ],
    [
        'id' => 302,
        'name' => 'هدفون بی‌سیم سونی WH-1000XM5',
        'slug' => 'sony-wh-1000xm5',
        'permalink' => 'https://example.com/product/sony-wh-1000xm5',
        'type' => 'variable',
        'status' => 'publish',
        'featured' => false,
        'catalog_visibility' => 'visible',
        'description' => '<p>پیشرفته‌ترین سیستم نویزکنسلینگ و کیفیت صدای های‌رزولوشن بی‌نظیر.</p>',
        'short_description' => '<p>هدفون دور گوشی با شارژدهی ۳۰ ساعته.</p>',
        'sku' => 'SONY-WH-1000',
        'price' => '20000000',
        'regular_price' => '20000000',
        'sale_price' => '',
        'on_sale' => false,
        'manage_stock' => false,
        'stock_quantity' => null,
        'stock_status' => 'instock',
        'backorders' => 'no',
        'low_stock_amount' => null,
        'weight' => '0.25',
        'dimensions' => ['length' => '20', 'width' => '18', 'height' => '8'],
        'categories' => [
            ['id' => 3, 'name' => 'تجهیزات صوتی', 'slug' => 'audio'],
            ['id' => 2, 'name' => 'لوازم جانبی کامپیوتر', 'slug' => 'accessories']
        ],
        'tags' => [
            ['id' => 3, 'name' => 'پرفروش', 'slug' => 'best-seller']
        ],
        'images' => [
            ['id' => 11, 'src' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500', 'name' => 'هدفون سونی', 'alt' => 'هدفون سونی']
        ],
        'attributes' => [
            [
                'id' => 1,
                'name' => 'رنگ',
                'position' => 0,
                'visible' => true,
                'variation' => true,
                'options' => ['مشکی', 'نقره‌ای']
            ]
        ],
        'default_attributes' => [
            ['id' => 1, 'name' => 'رنگ', 'option' => 'مشکی']
        ],
        'variations' => [3021, 3022],
        'meta_data' => [],
        'date_created' => '2024-02-05T12:00:00',
        'date_modified' => '2024-03-05T18:00:00',
    ],
    [
        'id' => 303,
        'name' => 'کتاب برنامه‌نویسی مدرن وب',
        'slug' => 'modern-web-book',
        'permalink' => 'https://example.com/product/modern-web-book',
        'type' => 'external',
        'status' => 'publish',
        'featured' => false,
        'catalog_visibility' => 'visible',
        'description' => '<p>راهنمای جامع معماری کلاینت-سرور و توسعه نرم‌افزارهای مدرن.</p>',
        'short_description' => '<p>چاپ سوم، ویرایش سال ۲۰۲۴.</p>',
        'sku' => 'BOOK-MODERN-WEB',
        'price' => '450000',
        'regular_price' => '450000',
        'sale_price' => '',
        'on_sale' => false,
        'manage_stock' => false,
        'stock_quantity' => null,
        'stock_status' => 'instock',
        'external_url' => 'https://example.com/publisher',
        'button_text' => 'مشاهده در سایت ناشر',
        'categories' => [],
        'tags' => [],
        'images' => [],
        'attributes' => [],
        'default_attributes' => [],
        'variations' => [],
        'meta_data' => [],
        'date_created' => '2024-02-20T09:00:00',
        'date_modified' => '2024-02-20T09:00:00',
    ],
    [
        'id' => 304,
        'name' => 'مانیتور اولتراواید سامسونگ ۳۴ اینچ',
        'slug' => 'samsung-ultrawide-34',
        'permalink' => 'https://example.com/product/samsung-ultrawide-34',
        'type' => 'simple',
        'status' => 'draft',
        'featured' => false,
        'catalog_visibility' => 'hidden',
        'description' => '<p>پنل خمیده با رزولوشن WQHD و نرخ نوسازی ۱۶۵ هرتز.</p>',
        'short_description' => '<p>مانیتور مخصوص طراحی و گیمینگ حرفه‌ای.</p>',
        'sku' => 'SAM-UW-34',
        'price' => '42000000',
        'regular_price' => '42000000',
        'sale_price' => '',
        'on_sale' => false,
        'manage_stock' => true,
        'stock_quantity' => 0,
        'stock_status' => 'outofstock',
        'backorders' => 'no',
        'low_stock_amount' => 1,
        'categories' => [
            ['id' => 2, 'name' => 'لوازم جانبی کامپیوتر', 'slug' => 'accessories']
        ],
        'tags' => [],
        'images' => [],
        'attributes' => [],
        'default_attributes' => [],
        'variations' => [],
        'meta_data' => [],
        'date_created' => '2024-03-01T14:00:00',
        'date_modified' => '2024-03-02T10:00:00',
    ],
];

// Fixture Variations
$fixtureVariations = [
    302 => [
        [
            'id' => 3021,
            'parent_id' => 302,
            'sku' => 'SONY-WH-BLK',
            'status' => 'publish',
            'regular_price' => '20000000',
            'sale_price' => '',
            'price' => '20000000',
            'on_sale' => false,
            'manage_stock' => true,
            'stock_quantity' => 15,
            'stock_status' => 'instock',
            'backorders' => 'no',
            'weight' => '0.25',
            'dimensions' => ['length' => '20', 'width' => '18', 'height' => '8'],
            'attributes' => [
                ['id' => 1, 'name' => 'رنگ', 'option' => 'مشکی']
            ],
            'meta_data' => [],
            'date_created' => '2024-02-05T12:10:00',
            'date_modified' => '2024-02-05T12:10:00',
        ],
        [
            'id' => 3022,
            'parent_id' => 302,
            'sku' => 'SONY-WH-SLV',
            'status' => 'publish',
            'regular_price' => '21000000',
            'sale_price' => '20500000',
            'price' => '20500000',
            'on_sale' => true,
            'manage_stock' => true,
            'stock_quantity' => 4,
            'stock_status' => 'instock',
            'backorders' => 'no',
            'weight' => '0.25',
            'dimensions' => ['length' => '20', 'width' => '18', 'height' => '8'],
            'attributes' => [
                ['id' => 1, 'name' => 'رنگ', 'option' => 'نقره‌ای']
            ],
            'meta_data' => [],
            'date_created' => '2024-02-05T12:15:00',
            'date_modified' => '2024-02-05T12:15:00',
        ],
    ],
];

// Product persistence files
$mockProductsFile = sys_get_temp_dir() . '/mock_wc_products_phase6.json';
if (!file_exists($mockProductsFile)) {
    file_put_contents($mockProductsFile, json_encode($fixtureProducts, JSON_UNESCAPED_UNICODE));
}
$savedProducts = json_decode(file_get_contents($mockProductsFile), true) ?: $fixtureProducts;

$mockVariationsFile = sys_get_temp_dir() . '/mock_wc_variations_phase6.json';
if (!file_exists($mockVariationsFile)) {
    file_put_contents($mockVariationsFile, json_encode($fixtureVariations, JSON_UNESCAPED_UNICODE));
}
$savedVariations = json_decode(file_get_contents($mockVariationsFile), true) ?: $fixtureVariations;

// Categories endpoint: /products/categories
if (str_contains($path, '/products/categories')) {
    echo json_encode($fixtureCategories, JSON_UNESCAPED_UNICODE);
    exit;
}

// Tags endpoint: /products/tags
if (str_contains($path, '/products/tags')) {
    echo json_encode($fixtureTags, JSON_UNESCAPED_UNICODE);
    exit;
}

// Attributes endpoint: /products/attributes
if (str_contains($path, '/products/attributes')) {
    echo json_encode($fixtureAttributes, JSON_UNESCAPED_UNICODE);
    exit;
}

// Variations routes: /products/{id}/variations/{variationId}
if (preg_match('#/products/(\d+)/variations/(\d+)#', $path, $matches)) {
    $prodId = (int)$matches[1];
    $varId = (int)$matches[2];

    if ($method === 'PUT' || $method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        if (!isset($savedVariations[$prodId])) {
            $savedVariations[$prodId] = [];
        }

        foreach ($savedVariations[$prodId] as &$v) {
            if ((int)$v['id'] === $varId) {
                foreach ($body as $k => $val) {
                    $v[$k] = $val;
                }
                if (isset($body['regular_price']) && !isset($body['price'])) {
                    $v['price'] = (string)$body['regular_price'];
                }
                $v['date_modified'] = date('Y-m-d\TH:i:s');
                file_put_contents($mockVariationsFile, json_encode($savedVariations, JSON_UNESCAPED_UNICODE));
                echo json_encode($v, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        http_response_code(404);
        echo json_encode([
            'code' => 'woocommerce_rest_product_variation_invalid_id',
            'message' => 'Invalid variation ID.',
            'data' => ['status' => 404],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'DELETE') {
        if (!empty($savedVariations[$prodId])) {
            foreach ($savedVariations[$prodId] as $idx => $v) {
                if ((int)$v['id'] === $varId) {
                    $deleted = $v;
                    unset($savedVariations[$prodId][$idx]);
                    $savedVariations[$prodId] = array_values($savedVariations[$prodId]);
                    file_put_contents($mockVariationsFile, json_encode($savedVariations, JSON_UNESCAPED_UNICODE));
                    echo json_encode($deleted, JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }

        http_response_code(404);
        echo json_encode([
            'code' => 'woocommerce_rest_product_variation_invalid_id',
            'message' => 'Invalid variation ID.',
            'data' => ['status' => 404],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!empty($savedVariations[$prodId])) {
        foreach ($savedVariations[$prodId] as $v) {
            if ((int)$v['id'] === $varId) {
                echo json_encode($v, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    http_response_code(404);
    echo json_encode([
        'code' => 'woocommerce_rest_product_variation_invalid_id',
        'message' => 'Invalid variation ID.',
        'data' => ['status' => 404],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Variations Batch: /products/{id}/variations/batch
if (preg_match('#/products/(\d+)/variations/batch#', $path, $matches)) {
    $prodId = (int)$matches[1];
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $updatedResults = [];

    if (!isset($savedVariations[$prodId])) {
        $savedVariations[$prodId] = [];
    }

    if (!empty($body['update']) && is_array($body['update'])) {
        foreach ($body['update'] as $item) {
            $itemId = (int)($item['id'] ?? 0);
            foreach ($savedVariations[$prodId] as &$v) {
                if ((int)$v['id'] === $itemId) {
                    foreach ($item as $k => $val) {
                        if ($k !== 'id') {
                            $v[$k] = $val;
                        }
                    }
                    if (isset($item['regular_price']) && !isset($item['price'])) {
                        $v['price'] = (string)$item['regular_price'];
                    }
                    $v['date_modified'] = date('Y-m-d\TH:i:s');
                    $updatedResults[] = $v;
                    break;
                }
            }
        }
    }

    file_put_contents($mockVariationsFile, json_encode($savedVariations, JSON_UNESCAPED_UNICODE));
    echo json_encode([
        'create' => [],
        'update' => $updatedResults,
        'delete' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Variations List & Create: /products/{id}/variations
if (preg_match('#/products/(\d+)/variations$#', $path, $matches) || preg_match('#/products/(\d+)/variations\?#', $path, $matches)) {
    $prodId = (int)$matches[1];

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $newId = rand(4000, 9999);
        $newVar = array_merge([
            'id' => $newId,
            'parent_id' => $prodId,
            'sku' => '',
            'status' => 'publish',
            'regular_price' => '0',
            'sale_price' => '',
            'price' => '0',
            'manage_stock' => false,
            'stock_quantity' => null,
            'stock_status' => 'instock',
            'attributes' => [],
            'meta_data' => [],
            'date_created' => date('Y-m-d\TH:i:s'),
            'date_modified' => date('Y-m-d\TH:i:s'),
        ], $body);

        $savedVariations[$prodId][] = $newVar;
        file_put_contents($mockVariationsFile, json_encode($savedVariations, JSON_UNESCAPED_UNICODE));

        // Add to parent product variations list if not present
        foreach ($savedProducts as &$p) {
            if ($p['id'] === $prodId) {
                if (!in_array($newId, $p['variations'] ?? [])) {
                    $p['variations'][] = $newId;
                    file_put_contents($mockProductsFile, json_encode($savedProducts, JSON_UNESCAPED_UNICODE));
                }
                break;
            }
        }

        http_response_code(201);
        echo json_encode($newVar, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $vars = $savedVariations[$prodId] ?? [];
    $total = count($vars);
    header("X-WP-Total: {$total}");
    header("X-WP-TotalPages: 1");
    echo json_encode(array_values($vars), JSON_UNESCAPED_UNICODE);
    exit;
}

// Single Product: /products/{id}
if (preg_match('#/products/(\d+)#', $path, $matches)) {
    $prodId = (int)$matches[1];

    if ($method === 'PUT' || $method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $found = null;
        foreach ($savedProducts as &$prod) {
            if ($prod['id'] === $prodId) {
                foreach ($body as $k => $val) {
                    $prod[$k] = $val;
                }
                if (isset($body['regular_price']) && !isset($body['price'])) {
                    $prod['price'] = (string)$body['regular_price'];
                }
                $prod['date_modified'] = date('Y-m-d\TH:i:s');
                $found = $prod;
                break;
            }
        }

        if ($found) {
            file_put_contents($mockProductsFile, json_encode($savedProducts, JSON_UNESCAPED_UNICODE));
            echo json_encode($found, JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(404);
        echo json_encode([
            'code' => 'woocommerce_rest_product_invalid_id',
            'message' => 'Invalid product ID.',
            'data' => ['status' => 404],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'DELETE') {
        $force = (isset($_GET['force']) && ($_GET['force'] === 'true' || $_GET['force'] === '1' || $_GET['force'] === true));
        $found = null;
        foreach ($savedProducts as $idx => &$prod) {
            if ($prod['id'] === $prodId) {
                $found = $prod;
                if ($force) {
                    unset($savedProducts[$idx]);
                    $savedProducts = array_values($savedProducts);
                } else {
                    $prod['status'] = 'trash';
                    $found['status'] = 'trash';
                }
                file_put_contents($mockProductsFile, json_encode($savedProducts, JSON_UNESCAPED_UNICODE));
                break;
            }
        }
        unset($prod);

        if ($found) {
            echo json_encode($found, JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(404);
        echo json_encode([
            'code' => 'woocommerce_rest_product_invalid_id',
            'message' => 'Invalid product ID.',
            'data' => ['status' => 404],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    foreach ($savedProducts as $prod) {
        if ($prod['id'] === $prodId) {
            echo json_encode($prod, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode([
        'code' => 'woocommerce_rest_product_invalid_id',
        'message' => 'Invalid product ID.',
        'data' => ['status' => 404],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Products Batch Endpoint
if (str_contains($path, '/products/batch')) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $updates = $body['update'] ?? [];
    $updatedResults = [];
    foreach ($updates as $u) {
        $pId = (int)($u['id'] ?? 0);
        foreach ($savedProducts as &$prod) {
            if ($prod['id'] === $pId) {
                foreach ($u as $k => $val) {
                    if ($k !== 'id') {
                        $prod[$k] = $val;
                    }
                }
                if (isset($u['regular_price']) && !isset($u['price'])) {
                    $prod['price'] = (string)$u['regular_price'];
                }
                $prod['date_modified'] = date('Y-m-d\TH:i:s');
                $updatedResults[] = $prod;
                break;
            }
        }
    }
    unset($prod);
    file_put_contents($mockProductsFile, json_encode($savedProducts, JSON_UNESCAPED_UNICODE));
    echo json_encode(['update' => $updatedResults], JSON_UNESCAPED_UNICODE);
    exit;
}

// Products list & create: /products
if (str_contains($path, '/products')) {
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $newId = rand(1000, 9999);
        $newProd = array_merge([
            'id' => $newId,
            'name' => '',
            'slug' => 'product-' . $newId,
            'permalink' => 'https://example.com/product/product-' . $newId,
            'type' => 'simple',
            'status' => 'publish',
            'featured' => false,
            'catalog_visibility' => 'visible',
            'description' => '',
            'short_description' => '',
            'sku' => '',
            'price' => '0',
            'regular_price' => '0',
            'sale_price' => '',
            'on_sale' => false,
            'manage_stock' => false,
            'stock_quantity' => null,
            'stock_status' => 'instock',
            'backorders' => 'no',
            'categories' => [],
            'tags' => [],
            'images' => [],
            'attributes' => [],
            'default_attributes' => [],
            'variations' => [],
            'meta_data' => [],
            'date_created' => date('Y-m-d\TH:i:s'),
            'date_modified' => date('Y-m-d\TH:i:s'),
        ], $body);

        $savedProducts[] = $newProd;
        file_put_contents($mockProductsFile, json_encode($savedProducts, JSON_UNESCAPED_UNICODE));

        http_response_code(201);
        echo json_encode($newProd, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Filters for list
    $search = trim($_GET['search'] ?? '');
    $type = $_GET['type'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $stockStatus = $_GET['stock_status'] ?? 'all';
    $cat = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $tag = isset($_GET['tag']) ? (int)$_GET['tag'] : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, (int)($_GET['per_page'] ?? 15));

    $filtered = array_filter($savedProducts, function ($p) use ($search, $type, $status, $stockStatus, $cat, $tag) {
        if ($type !== 'all' && ($p['type'] ?? '') !== $type) {
            return false;
        }
        if ($status !== 'all' && ($p['status'] ?? '') !== $status) {
            return false;
        }
        if ($stockStatus !== 'all' && ($p['stock_status'] ?? '') !== $stockStatus) {
            return false;
        }
        if ($cat !== null) {
            $catIds = array_column($p['categories'] ?? [], 'id');
            if (!in_array($cat, $catIds)) {
                return false;
            }
        }
        if ($tag !== null) {
            $tagIds = array_column($p['tags'] ?? [], 'id');
            if (!in_array($tag, $tagIds)) {
                return false;
            }
        }
        if ($search !== '') {
            $s = mb_strtolower($search);
            $name = mb_strtolower($p['name'] ?? '');
            $sku = mb_strtolower($p['sku'] ?? '');
            $idStr = (string)$p['id'];
            if (!str_contains($name, $s) && !str_contains($sku, $s) && !str_contains($idStr, $s)) {
                return false;
            }
        }
        return true;
    });

    $total = count($filtered);
    $totalPages = (int)ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    $paged = array_slice(array_values($filtered), $offset, $perPage);

    header("X-WP-Total: {$total}");
    header("X-WP-TotalPages: {$totalPages}");
    echo json_encode(array_values($paged), JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback index
echo json_encode([
    'namespace' => 'wc/v3',
    'routes' => [
        '/wc/v3' => ['supports' => ['GET']],
        '/wc/v3/customers' => ['supports' => ['GET', 'POST']],
        '/wc/v3/orders' => ['supports' => ['GET', 'POST']],
        '/wc/v3/products' => ['supports' => ['GET', 'POST']],
    ]
]);



