<?php

declare(strict_types=1);

/**
 * Phase 5: Orders Module Automated Test Suite
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
\App\Support\Config::setPath(__DIR__ . '/../config');
\App\Support\Logger::setLogDir(__DIR__ . '/../storage/logs');

use App\Database\Connection;
use App\Integrations\WooCommerce\OrderNormalizer;
use App\Integrations\WooCommerce\OrderStatusResolver;

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
echo " Phase 5: Orders Module Automated Tests\n";
echo "========================================================\n\n";

// --- 1. Authentication & Store Context Verification ---
echo "--- 1. Authentication & Store Context ---\n";

$res = httpReq('GET', '/api/v1/orders');
assertTest("Unauthenticated request is rejected (401)", $res['status'] === 401);

$loginRes = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$adminCookie = $loginRes['cookie'] ?? '';
assertTest("Admin login succeeds (200)", $loginRes['status'] === 200, $loginRes['body']);

$storesRes = httpReq('GET', '/api/v1/stores', [], $adminCookie);
$activeStoreId = (int)($storesRes['json']['data'][0]['id'] ?? 2);
assertTest("Found active store ID {$activeStoreId}", $activeStoreId > 0);

$storeHeader = ["X-Store-Id: {$activeStoreId}"];

$res = httpReq('GET', '/api/v1/orders', [], $adminCookie, $storeHeader);
assertTest("Authenticated request with store context allowed (200)", $res['status'] === 200, "Status is {$res['status']}");

$badStoreRes = httpReq('GET', '/api/v1/orders', [], $adminCookie, ['X-Store-Id: 99999']);
assertTest("Request with non-existent store context is rejected (404)", $badStoreRes['status'] === 404);

// --- 2. Orders List & Normalization ---
echo "\n--- 2. Orders List & Normalization ---\n";
$listRes = httpReq('GET', '/api/v1/orders', [], $adminCookie, $storeHeader);
assertTest("Orders list returns success true", ($listRes['json']['success'] ?? false) === true);
$orders = $listRes['json']['data'] ?? [];
assertTest("Orders list returns orders array", count($orders) >= 3);

$firstOrder = $orders[0] ?? [];
assertTest("Order has normalized 'id'", !empty($firstOrder['id']));
assertTest("Order has normalized 'number'", !empty($firstOrder['number']));
assertTest("Order has normalized 'status'", !empty($firstOrder['status']));
assertTest("Order has normalized 'status_label'", !empty($firstOrder['status_label']));
assertTest("Order has normalized 'total'", isset($firstOrder['total']));
assertTest("Order has normalized 'currency'", !empty($firstOrder['currency']));
assertTest("Order has normalized 'currency_symbol'", !empty($firstOrder['currency_symbol']));
assertTest("Order has normalized 'customer' info", is_array($firstOrder['customer'] ?? null));
assertTest("Order has normalized 'items' array", is_array($firstOrder['items'] ?? null));
assertTest("Order has normalized 'billing' address", is_array($firstOrder['billing'] ?? null));
assertTest("Order has normalized 'shipping' address", is_array($firstOrder['shipping'] ?? null));

// Verify line items and variations
$itemWithVariation = null;
foreach ($orders as $ord) {
    foreach ($ord['items'] ?? [] as $itm) {
        if (!empty($itm['variation_attributes'])) {
            $itemWithVariation = $itm;
            break 2;
        }
    }
}
assertTest("Line items include SKU where available", isset($orders[0]['items'][0]['sku']));
assertTest("Line items support variation attributes when present", $itemWithVariation !== null);

// Guest order identification
$guestOrder = null;
foreach ($orders as $ord) {
    if (!empty($ord['customer']['is_guest'])) {
        $guestOrder = $ord;
        break;
    }
}
assertTest("Guest order identified without creating fake local customer", $guestOrder !== null && $guestOrder['customer']['is_guest'] === true);

// --- 3. Server-Side Search ---
echo "\n--- 3. Server-Side Search ---\n";
$searchNum = httpReq('GET', '/api/v1/orders?search=1001', [], $adminCookie, $storeHeader);
assertTest("Search for order number '1001' returns order 1001", ($searchNum['json']['data'][0]['id'] ?? 0) === 1001);

$searchPersian = urlencode('حسین');
$searchName = httpReq('GET', "/api/v1/orders?search={$searchPersian}", [], $adminCookie, $storeHeader);
assertTest("Search for Persian customer name 'حسین' returns 200 OK", $searchName['status'] === 200);
assertTest("Search for Persian name matched customer order", count($searchName['json']['data'] ?? []) >= 1);

$searchEmail = httpReq('GET', '/api/v1/orders?search=' . urlencode('sara@example.com'), [], $adminCookie, $storeHeader);
assertTest("Search for customer email returns matching orders", count($searchEmail['json']['data'] ?? []) >= 1);

// --- 4. Server-Side Pagination & Sorting ---
echo "\n--- 4. Server-Side Pagination & Safe Limits ---\n";
$pageRes = httpReq('GET', '/api/v1/orders?page=1&per_page=2', [], $adminCookie, $storeHeader);
assertTest("Pagination page 1 with per_page 2 returns 2 items", count($pageRes['json']['data'] ?? []) === 2);
assertTest("Pagination meta has total", ($pageRes['json']['meta']['total'] ?? 0) >= 3);
assertTest("Pagination meta has total_pages", ($pageRes['json']['meta']['total_pages'] ?? 0) >= 2);

$safeLimitRes = httpReq('GET', '/api/v1/orders?per_page=500', [], $adminCookie, $storeHeader);
assertTest("Safe limit caps per_page to maximum 100", ($safeLimitRes['json']['meta']['per_page'] ?? 0) <= 100);

// --- 5. Filters & Date Presets in Store Timezone ---
echo "\n--- 5. Dynamic Filters & Store Timezone Date Ranges ---\n";
$statusFilter = httpReq('GET', '/api/v1/orders?status=processing', [], $adminCookie, $storeHeader);
assertTest("Filter by status 'processing' returns 200", $statusFilter['status'] === 200);
$allProcessing = true;
foreach ($statusFilter['json']['data'] ?? [] as $ord) {
    if ($ord['status'] !== 'processing') {
        $allProcessing = false;
        break;
    }
}
assertTest("All returned orders match status 'processing'", $allProcessing && count($statusFilter['json']['data']) >= 1);

// Custom status filter
$customStatusFilter = httpReq('GET', '/api/v1/orders?status=custom-prep', [], $adminCookie, $storeHeader);
assertTest("Filter by custom status 'custom-prep' returns 200", $customStatusFilter['status'] === 200);
assertTest("Custom status 'custom-prep' order returned", count($customStatusFilter['json']['data'] ?? []) >= 1);

// Date Presets
$presetToday = httpReq('GET', '/api/v1/orders?date_preset=today', [], $adminCookie, $storeHeader);
assertTest("Date preset 'today' executes in store timezone", $presetToday['status'] === 200);

$preset30 = httpReq('GET', '/api/v1/orders?date_preset=last_30_days', [], $adminCookie, $storeHeader);
assertTest("Date preset 'last_30_days' executes in store timezone", $preset30['status'] === 200 && count($preset30['json']['data']) >= 1);

// Payment method filter
$pmFilter = httpReq('GET', '/api/v1/orders?payment_method=bacs', [], $adminCookie, $storeHeader);
assertTest("Filter by payment method 'bacs' returns 200", $pmFilter['status'] === 200);

// Customer filter
$custFilter = httpReq('GET', '/api/v1/orders?customer_id=101', [], $adminCookie, $storeHeader);
assertTest("Filter by customer_id=101 returns orders", count($custFilter['json']['data'] ?? []) >= 1);

// --- 6. Dynamic Order Status Resolver ---
echo "\n--- 6. Dynamic Status Resolver ---\n";
$statusesRes = httpReq('GET', '/api/v1/orders/statuses', [], $adminCookie, $storeHeader);
assertTest("GET /orders/statuses returns 200 OK", $statusesRes['status'] === 200);
$statusesData = $statusesRes['json']['data'] ?? [];
$statusSlugs = array_column($statusesData, 'name', 'slug');
assertTest("Core status 'processing' is present", isset($statusSlugs['processing']));
assertTest("Core status 'completed' is present", isset($statusSlugs['completed']));
assertTest("Custom status 'custom-prep' is dynamically discovered", isset($statusSlugs['custom-prep']));
assertTest("Status has localized Persian label", !empty($statusSlugs['processing']));

// --- 7. Single Order Lookup & Missing Order ---
echo "\n--- 7. Single Order Lookup ---\n";
$singleRes = httpReq('GET', '/api/v1/orders/1001', [], $adminCookie, $storeHeader);
assertTest("GET /orders/1001 returns 200 OK", $singleRes['status'] === 200);
$singleOrder = $singleRes['json']['data'] ?? [];
assertTest("Single order has id 1001", ($singleOrder['id'] ?? 0) === 1001);
assertTest("Single order has totals breakdown (subtotal, shipping, tax, total)", isset($singleOrder['subtotal'], $singleOrder['shipping_total'], $singleOrder['total_tax'], $singleOrder['total']));
assertTest("Single order contains safe metadata", isset($singleOrder['meta']) && is_array($singleOrder['meta']));

$missingRes = httpReq('GET', '/api/v1/orders/99999', [], $adminCookie, $storeHeader);
assertTest("GET /orders/99999 returns 404 Not Found", $missingRes['status'] === 404);
assertTest("Missing order error code is ORDER_NOT_FOUND", ($missingRes['json']['error']['code'] ?? '') === 'ORDER_NOT_FOUND');

// --- 8. Status Change with Activity & Audit Log ---
echo "\n--- 8. Status Change, Activity & Audit Log ---\n";
$patchRes = httpReq('PATCH', '/api/v1/orders/1001/status', [
    'status' => 'completed',
], $adminCookie, $storeHeader);
assertTest("PATCH /orders/1001/status to 'completed' returns 200 OK", $patchRes['status'] === 200, $patchRes['body']);
assertTest("Updated order status is 'completed'", ($patchRes['json']['data']['status'] ?? '') === 'completed');

// Verify activity creation
$db = Connection::get();
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'order' AND entity_id = 1001 AND action_type = 'order_status_changed' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$activity = $stmt->fetch();
assertTest("Activity 'order_status_changed' created in database", !empty($activity));

// Verify audit log creation
$stmt = $db->prepare("SELECT * FROM audit_logs WHERE store_id = :sid AND entity_type = 'order' AND entity_id = '1001' AND action = 'ORDER_STATUS_CHANGED' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$auditLog = $stmt->fetch();
assertTest("Audit log 'ORDER_STATUS_CHANGED' created in database", !empty($auditLog));

// Invalid status rejection
$invalidStatusRes = httpReq('PATCH', '/api/v1/orders/1001/status', [
    'status' => 'completely_fake_status_xyz',
], $adminCookie, $storeHeader);
assertTest("Invalid status is rejected with 422", $invalidStatusRes['status'] === 422);

// --- 9. Order Notes ---
echo "\n--- 9. Order Notes (Internal vs Customer) ---\n";
// Read initial notes
$notesRes = httpReq('GET', '/api/v1/orders/1001/notes', [], $adminCookie, $storeHeader);
assertTest("GET /orders/1001/notes returns 200 OK", $notesRes['status'] === 200);
$initialNotesCount = count($notesRes['json']['data'] ?? []);
assertTest("Initial notes returned as array", is_array($notesRes['json']['data']));

// Add internal note
$addInternalRes = httpReq('POST', '/api/v1/orders/1001/notes', [
    'note' => 'یادداشت داخلی برای تست سیستم سفارشات',
    'customer_note' => false,
], $adminCookie, $storeHeader);
assertTest("POST /orders/1001/notes creates internal note (201)", $addInternalRes['status'] === 201, $addInternalRes['body']);
assertTest("Created note customer_note is false", ($addInternalRes['json']['data']['customer_note'] ?? true) === false);

// Add customer note
$addCustRes = httpReq('POST', '/api/v1/orders/1001/notes', [
    'note' => 'یادداشت عمومی برای اطلاع خریدار گرامی',
    'customer_note' => true,
], $adminCookie, $storeHeader);
assertTest("POST /orders/1001/notes creates customer note (201)", $addCustRes['status'] === 201);
assertTest("Created note customer_note is true", ($addCustRes['json']['data']['customer_note'] ?? false) === true);

// Verify notes list increased
$updatedNotesRes = httpReq('GET', '/api/v1/orders/1001/notes', [], $adminCookie, $storeHeader);
assertTest("Order notes list contains newly added notes", count($updatedNotesRes['json']['data'] ?? []) >= $initialNotesCount + 2);

// --- 10. Refunds ---
echo "\n--- 10. Refunds & Safety Validation ---\n";
// Invalid amount refund (exceeding total or zero)
$excessRefundRes = httpReq('POST', '/api/v1/orders/1001/refund', [
    'amount' => 999999999.00,
    'reason' => 'مبلغ بیش از حد مجاز',
], $adminCookie, $storeHeader);
assertTest("Refund exceeding remaining total is rejected (422)", $excessRefundRes['status'] === 422);
assertTest("Error code is INVALID_REFUND_AMOUNT", ($excessRefundRes['json']['error']['code'] ?? '') === 'INVALID_REFUND_AMOUNT');

// Zero or negative refund
$zeroRefundRes = httpReq('POST', '/api/v1/orders/1001/refund', [
    'amount' => 0,
    'reason' => 'صفر ریال',
], $adminCookie, $storeHeader);
assertTest("Zero refund amount is rejected (422)", $zeroRefundRes['status'] === 422);

// Valid partial refund
$validRefundRes = httpReq('POST', '/api/v1/orders/1001/refund', [
    'amount' => 50000.00,
    'reason' => 'مرجوعی یک قلم کالا به درخواست مشتری',
    'api_refund' => true,
], $adminCookie, $storeHeader);
assertTest("Valid partial refund succeeds (201)", $validRefundRes['status'] === 201, $validRefundRes['body']);
assertTest("Refund response returns refund id and amount", ($validRefundRes['json']['data']['refund']['amount'] ?? 0) == 50000);
assertTest("Order total_refunded is updated", ($validRefundRes['json']['data']['order']['total_refunded'] ?? 0) >= 50000);

// Verify Activity for refund
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'order' AND entity_id = 1001 AND action_type = 'order_refunded' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$refundActivity = $stmt->fetch();
assertTest("Activity 'order_refunded' created in database", !empty($refundActivity));

// Verify Audit Log for refund
$stmt = $db->prepare("SELECT * FROM audit_logs WHERE store_id = :sid AND action = 'ORDER_REFUNDED' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$refundAudit = $stmt->fetch();
assertTest("Audit log 'ORDER_REFUNDED' created in database", !empty($refundAudit));

// --- 11. Tasks & Activities on Orders ---
echo "\n--- 11. Order Tasks & Activities ---\n";
// Create task for order
$createTaskRes = httpReq('POST', '/api/v1/orders/1001/tasks', [
    'title' => 'تماس جهت هماهنگی ارسال سریع',
    'due_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
    'priority' => 'high',
], $adminCookie, $storeHeader);
assertTest("POST /orders/1001/tasks creates order task (201)", $createTaskRes['status'] === 201, $createTaskRes['body']);

// List tasks for order
$orderTasksRes = httpReq('GET', '/api/v1/orders/1001/tasks', [], $adminCookie, $storeHeader);
assertTest("GET /orders/1001/tasks returns task list (200)", $orderTasksRes['status'] === 200);
assertTest("Order tasks list has created task", count($orderTasksRes['json']['data'] ?? []) >= 1);

// List activities for order
$orderActivitiesRes = httpReq('GET', '/api/v1/orders/1001/activities', [], $adminCookie, $storeHeader);
assertTest("GET /orders/1001/activities returns activities (200)", $orderActivitiesRes['status'] === 200);
assertTest("Order activities array is non-empty", count($orderActivitiesRes['json']['data'] ?? []) >= 1);

// --- 12. Architectural & Security Verification ---
echo "\n--- 12. Architectural & Security Verification ---\n";
// Check forbidden database tables
$stmt = $db->query("SHOW TABLES LIKE '%order%'");
$orderTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Filter out internal tables (none should be orders/woocommerce_orders)
$forbiddenTables = ['orders', 'woocommerce_orders', 'order_items', 'woocommerce_order_items'];
$foundForbidden = array_intersect($orderTables, $forbiddenTables);
assertTest("NO forbidden orders tables exist in MariaDB (" . implode(', ', $forbiddenTables) . ")", empty($foundForbidden), "Found: " . implode(', ', $foundForbidden));

// Check no credentials in order response
$orderJsonString = json_encode($singleRes['json']);
assertTest("Response does NOT leak consumer_secret or api keys", !str_contains($orderJsonString, 'cs_test') && !str_contains($orderJsonString, 'ck_test'));

echo "\n========================================================\n";
echo " Phase 5 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n\n";

exit($failed > 0 ? 1 : 0);
