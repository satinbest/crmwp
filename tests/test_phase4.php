<?php

declare(strict_types=1);

/**
 * Phase 4: Customers Module Automated Test Suite
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
\App\Support\Config::setPath(__DIR__ . '/../config');
\App\Support\Logger::setLogDir(__DIR__ . '/../storage/logs');

use App\Database\Connection;
use App\Integrations\WooCommerce\CustomerNormalizer;
use App\Integrations\WooCommerce\OrderNormalizer;

$baseUrl = 'http://127.0.0.1:8000';
$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$details}\n";
        $failed++;
    }
}

function httpReq(string $method, string $path, array $data = [], ?string $cookie = null, array $extraHeaders = []): array {
    global $baseUrl;
    $url = $baseUrl . $path;
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = array_merge([
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ], $extraHeaders);

    if (!empty($data) || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($raw === false) {
        return [
            'status' => $status,
            'headers' => '',
            'body' => '',
            'json' => null,
            'cookie' => '',
        ];
    }

    $headerStr = substr($raw, 0, $headerSize);
    $bodyStr = substr($raw, $headerSize);

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headerStr, $matches);
    $cookies = !empty($matches[1]) ? implode('; ', $matches[1]) : '';

    $json = json_decode($bodyStr, true);

    return [
        'status' => $status,
        'headers' => $headerStr,
        'body' => $bodyStr,
        'json' => $json,
        'cookie' => $cookies,
    ];
}

echo "\n========================================================\n";
echo " Phase 4: Customers Module Automated Tests\n";
echo "========================================================\n\n";

// --- 1. Authentication & Store Context Verification ---
echo "--- 1. Authentication & Store Context ---\n";

// Unauthenticated request
$res = httpReq('GET', '/api/v1/customers');
assertTest("Unauthenticated request is rejected (401)", $res['status'] === 401);

// Login as admin
$loginRes = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$adminCookie = $loginRes['cookie'] ?? '';
assertTest("Admin login succeeds", $loginRes['status'] === 200, $loginRes['body']);

// Check active store
$storesRes = httpReq('GET', '/api/v1/stores', [], $adminCookie);
$activeStoreId = (int)($storesRes['json']['data'][0]['id'] ?? 2);
assertTest("Found active store ID {$activeStoreId}", $activeStoreId > 0);

$storeHeader = ["X-Store-Id: {$activeStoreId}"];

// Authenticated request with store context allowed
$res = httpReq('GET', '/api/v1/customers', [], $adminCookie, $storeHeader);
assertTest("Authenticated request with store context allowed (200)", $res['status'] === 200);

// Request with non-existent store context
$badStoreRes = httpReq('GET', '/api/v1/customers', [], $adminCookie, ['X-Store-Id: 99999']);
assertTest("Request with invalid store context is rejected (404)", $badStoreRes['status'] === 404);

// --- 2. Customer List & Normalization ---
echo "\n--- 2. Customer List & Normalization ---\n";
$listRes = httpReq('GET', '/api/v1/customers', [], $adminCookie, $storeHeader);
assertTest("Customer list returns success true", ($listRes['json']['success'] ?? false) === true);
$customers = $listRes['json']['data'] ?? [];
assertTest("Customer list returns non-empty array", count($customers) >= 4);

$firstCustomer = $customers[0] ?? [];
assertTest("Customer has normalized 'id'", !empty($firstCustomer['id']));
assertTest("Customer has normalized 'full_name'", !empty($firstCustomer['full_name']));
assertTest("Customer has normalized 'email'", !empty($firstCustomer['email']));
assertTest("Customer has normalized 'billing' array", is_array($firstCustomer['billing']));
assertTest("Customer has normalized 'orders_count'", isset($firstCustomer['orders_count']));
assertTest("Customer has normalized 'total_spent'", isset($firstCustomer['total_spent']));
assertTest("Customer has normalized 'average_order_value'", isset($firstCustomer['average_order_value']));

// --- 3. Server-Side Search ---
echo "\n--- 3. Server-Side Customer Search ---\n";
$searchQuery = urlencode('حسین');
$searchRes = httpReq('GET', "/api/v1/customers?search={$searchQuery}", [], $adminCookie, $storeHeader);
assertTest("Search for 'حسین' returns 200 OK", $searchRes['status'] === 200, "Status is {$searchRes['status']}");
$searchData = $searchRes['json']['data'] ?? [];
assertTest("Search returns matched customer", count($searchData) >= 1 && str_contains($searchData[0]['full_name'], 'حسین'));

$searchEmail = httpReq('GET', '/api/v1/customers?search=sara', [], $adminCookie, $storeHeader);
assertTest("Search for email/username 'sara' returns Sara", count($searchEmail['json']['data'] ?? []) >= 1);

// --- 4. Server-Side Pagination & Sorting ---
echo "\n--- 4. Server-Side Pagination & Sorting ---\n";
$pageRes = httpReq('GET', '/api/v1/customers?page=1&per_page=2', [], $adminCookie, $storeHeader);
assertTest("Pagination page 1 with per_page 2 returns 2 items", count($pageRes['json']['data'] ?? []) === 2);
assertTest("Pagination meta has total", ($pageRes['json']['meta']['total'] ?? 0) >= 4);
assertTest("Pagination meta has total_pages", ($pageRes['json']['meta']['total_pages'] ?? 0) >= 2);

$sortRes = httpReq('GET', '/api/v1/customers?sort=id&direction=asc', [], $adminCookie, $storeHeader);
assertTest("Sort by ID ascending returns 200", $sortRes['status'] === 200);

// --- 5. Single Customer Lookup ---
echo "\n--- 5. Single Customer Lookup ---\n";
$cust101 = httpReq('GET', '/api/v1/customers/101', [], $adminCookie, $storeHeader);
assertTest("GET /customers/101 returns 200 OK", $cust101['status'] === 200);
assertTest("Customer 101 has correct ID", ($cust101['json']['data']['id'] ?? 0) === 101);
assertTest("Customer 101 has orders_count > 0", ($cust101['json']['data']['orders_count'] ?? 0) > 0);

$missingCust = httpReq('GET', '/api/v1/customers/9999', [], $adminCookie, $storeHeader);
assertTest("GET /customers/9999 returns 404 Not Found", $missingCust['status'] === 404);
assertTest("Missing customer error code is CUSTOMER_NOT_FOUND", ($missingCust['json']['error']['code'] ?? '') === 'CUSTOMER_NOT_FOUND');

// --- 6. Customer Orders ---
echo "\n--- 6. Customer Orders from WooCommerce ---\n";
$ordersRes = httpReq('GET', '/api/v1/customers/101/orders', [], $adminCookie, $storeHeader);
assertTest("GET /customers/101/orders returns 200 OK", $ordersRes['status'] === 200);
$ordersData = $ordersRes['json']['data'] ?? [];
assertTest("Customer 101 has orders returned", count($ordersData) >= 1);
$firstOrder = $ordersData[0] ?? [];
assertTest("Order has normalized 'number'", !empty($firstOrder['number']));
assertTest("Order has normalized 'status_label'", !empty($firstOrder['status_label']));
assertTest("Order has normalized 'total'", isset($firstOrder['total']));

// Customer with no orders (customer 103)
$noOrdersRes = httpReq('GET', '/api/v1/customers/103/orders', [], $adminCookie, $storeHeader);
assertTest("Customer 103 with no orders returns empty list", count($noOrdersRes['json']['data'] ?? []) === 0);

// --- 7. Customer Notes (CRM Local Data) ---
echo "\n--- 7. Customer Notes (CRM Local Storage & CRUD) ---\n";
$createNoteRes = httpReq('POST', '/api/v1/customers/101/notes', [
    'content' => 'مشتری درخواست تخفیف برای خرید سازمانی داشت.',
], $adminCookie, $storeHeader);
assertTest("Create note returns 201 Created", $createNoteRes['status'] === 201);
$createdNote = $createNoteRes['json']['data'] ?? [];
$noteId = (int)($createdNote['id'] ?? 0);
assertTest("Created note has valid ID", $noteId > 0);
assertTest("Created note has author_name", !empty($createdNote['author_name']));

// List notes
$notesListRes = httpReq('GET', '/api/v1/customers/101/notes', [], $adminCookie, $storeHeader);
assertTest("List notes returns 200 OK", $notesListRes['status'] === 200);
assertTest("Notes list includes created note", count($notesListRes['json']['data'] ?? []) >= 1);

// Edit note
$editNoteRes = httpReq('PATCH', "/api/v1/customers/101/notes/{$noteId}", [
    'content' => 'مشتری درخواست تخفیف ۵ درصدی برای سفارش دوم داشت.',
], $adminCookie, $storeHeader);
assertTest("Edit note returns 200 OK", $editNoteRes['status'] === 200);
assertTest("Edited note content is updated", str_contains($editNoteRes['json']['data']['content'] ?? '', '۵ درصدی'));

// Cross-store protection / IDOR
$crossStoreRes = httpReq('PATCH', "/api/v1/customers/102/notes/{$noteId}", [
    'content' => 'تلاش برای دسترسی غیرمجاز',
], $adminCookie, $storeHeader);
assertTest("Accessing note with wrong customer ID returns 404 (IDOR protection)", $crossStoreRes['status'] === 404);

// Delete note
$deleteNoteRes = httpReq('DELETE', "/api/v1/customers/101/notes/{$noteId}", [], $adminCookie, $storeHeader);
assertTest("Delete note returns 200 OK", $deleteNoteRes['status'] === 200);

// --- 8. Customer Tags (CRM Local Storage) ---
echo "\n--- 8. Customer Tags (CRM Local Storage & Tagging) ---\n";
$addTagRes = httpReq('POST', '/api/v1/customers/101/tags', [
    'name' => 'مشتری وفادار',
    'color' => '#10B981',
], $adminCookie, $storeHeader);
assertTest("Add tag returns 201 Created", $addTagRes['status'] === 201);
$tagId = (int)($addTagRes['json']['data']['id'] ?? 0);
assertTest("Added tag has valid ID", $tagId > 0);

// List tags
$tagsListRes = httpReq('GET', '/api/v1/customers/101/tags', [], $adminCookie, $storeHeader);
assertTest("List tags returns 200 OK", $tagsListRes['status'] === 200);
$tagsData = $tagsListRes['json']['data'] ?? [];
assertTest("Tags list includes 'مشتری وفادار'", count(array_filter($tagsData, fn($t) => $t['name'] === 'مشتری وفادار')) === 1);

// Remove tag
$removeTagRes = httpReq('DELETE', "/api/v1/customers/101/tags/{$tagId}", [], $adminCookie, $storeHeader);
assertTest("Remove tag returns 200 OK", $removeTagRes['status'] === 200);

// --- 9. Customer Tasks (CRM Tasks) ---
echo "\n--- 9. Customer Tasks (Creation & Status Update) ---\n";
$createTaskRes = httpReq('POST', '/api/v1/customers/101/tasks', [
    'title' => 'پیگیری فاکتور رسمی',
    'priority' => 'high',
    'status' => 'pending',
], $adminCookie, $storeHeader);
assertTest("Create task returns 201 Created", $createTaskRes['status'] === 201);
$taskId = (int)($createTaskRes['json']['data']['id'] ?? 0);
assertTest("Task ID is valid", $taskId > 0);

// List tasks
$tasksListRes = httpReq('GET', '/api/v1/customers/101/tasks', [], $adminCookie, $storeHeader);
assertTest("List tasks returns 200 OK", $tasksListRes['status'] === 200);

// Update task status to completed
$updateTaskRes = httpReq('PATCH', "/api/v1/customers/101/tasks/{$taskId}", [
    'status' => 'completed',
], $adminCookie, $storeHeader);
assertTest("Update task status returns 200 OK", $updateTaskRes['status'] === 200);
assertTest("Task status is now completed", ($updateTaskRes['json']['data']['status'] ?? '') === 'completed');

// --- 10. Customer Activities Timeline ---
echo "\n--- 10. Customer Activity Timeline ---\n";
$actRes = httpReq('GET', '/api/v1/customers/101/activities', [], $adminCookie, $storeHeader);
assertTest("GET /customers/101/activities returns 200 OK", $actRes['status'] === 200);
$acts = $actRes['json']['data'] ?? [];
assertTest("Activities recorded for customer operations", count($acts) >= 3);
$actionTypes = array_column($acts, 'action_type');
assertTest("Activities include 'task_created'", in_array('task_created', $actionTypes, true));
assertTest("Activities include 'tag_added'", in_array('tag_added', $actionTypes, true));

// --- 11. Normalization Edge Cases (Unit Tests) ---
echo "\n--- 11. Normalization Edge Cases (Unit Verification) ---\n";
// Normalization with null fields
$rawNull = [
    'id' => 999,
    'email' => 'test@example.com',
    'first_name' => null,
    'last_name' => null,
    'username' => 'testuser',
    'billing' => null,
    'shipping' => null,
    'orders_count' => null,
    'total_spent' => null,
    'meta_data' => null,
];
$normNull = CustomerNormalizer::normalize($rawNull, 2);
assertTest("Normalization tolerates null fields gracefully", is_array($normNull));
assertTest("Full name falls back to username when names are null", $normNull['full_name'] === 'testuser');
assertTest("Billing is initialized to safe empty array", is_array($normNull['billing']) && $normNull['billing']['city'] === '');
assertTest("Orders count defaults to 0 when null", $normNull['orders_count'] === 0);
assertTest("Total spent defaults to 0.0 when null", $normNull['total_spent'] === 0.0);

// Order Normalizer
$rawOrder = [
    'id' => 501,
    'number' => '501',
    'status' => 'processing',
    'total' => '150000',
    'line_items' => [
        ['quantity' => 2],
        ['quantity' => 3],
    ],
];
$normOrder = OrderNormalizer::normalize($rawOrder, 2);
assertTest("Order status translated to Persian label", $normOrder['status_label'] === 'در حال پردازش');
assertTest("Order items count aggregated correctly (2+3=5)", $normOrder['items_count'] === 5);

// --- 12. Security & Source of Truth Verification ---
echo "\n--- 12. Architecture & Security Verification ---\n";
$pdo = Connection::get();
$tablesStmt = $pdo->query("SHOW TABLES LIKE 'customers'");
$customerTableFound = $tablesStmt->fetchAll();
assertTest("Critical Architecture Rule: NO local customers table created in database", count($customerTableFound) === 0);

$wcCustTable = $pdo->query("SHOW TABLES LIKE 'woocommerce_customers'")->fetchAll();
assertTest("Critical Architecture Rule: NO woocommerce_customers table created", count($wcCustTable) === 0);

// Check Audit Logs
$auditStmt = $pdo->query("SELECT * FROM audit_logs WHERE store_id = {$activeStoreId} ORDER BY id DESC LIMIT 10");
$recentAudits = $auditStmt->fetchAll();
assertTest("Audit logs generated for customer operations", count($recentAudits) > 0);
$auditActions = array_column($recentAudits, 'action');
assertTest("Audit logs include CUSTOMER_TASK_CREATED or CUSTOMER_NOTE_CREATED", in_array('CUSTOMER_TASK_CREATED', $auditActions, true) || in_array('CUSTOMER_NOTE_CREATED', $auditActions, true));

// Ensure no WooCommerce secrets in audit logs
$hasSecretInLogs = false;
foreach ($recentAudits as $log) {
    $rawLog = json_encode($log);
    if (str_contains($rawLog, 'cs_valid_test_secret') || str_contains($rawLog, 'ck_valid_test_key')) {
        $hasSecretInLogs = true;
        break;
    }
}
assertTest("No WooCommerce consumer secrets logged in audit logs", !$hasSecretInLogs);

echo "\n========================================================\n";
echo " Phase 4 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n\n";

exit($failed > 0 ? 1 : 0);
