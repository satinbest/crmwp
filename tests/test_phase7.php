<?php

declare(strict_types=1);

/**
 * Phase 7: Bulk Operations Engine Automated Test Suite
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
\App\Support\Config::setPath(__DIR__ . '/../config');
\App\Support\Logger::setLogDir(__DIR__ . '/../storage/logs');

use App\Database\Connection;

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

$db = Connection::get();

echo "\n========================================================\n";
echo " Phase 7: Bulk Operations Engine Automated Tests\n";
echo "========================================================\n\n";

// --- 1. Authentication & Security ---
echo "--- 1. Authentication & Security ---\n";

$res = httpReq('POST', '/api/v1/bulk-operations/preview', ['entity' => 'products']);
assertTest("Unauthenticated request to preview is rejected (401)", $res['status'] === 401);

$res = httpReq('POST', '/api/v1/bulk-operations', ['entity' => 'products']);
assertTest("Unauthenticated request to execute is rejected (401)", $res['status'] === 401);

// Admin login
$loginRes = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$adminCookie = $loginRes['cookie'] ?? '';
assertTest("Admin login succeeds (200)", $loginRes['status'] === 200);

// Get active store
$storesRes = httpReq('GET', '/api/v1/stores', [], $adminCookie);
$activeStoreId = (int)($storesRes['json']['data'][0]['id'] ?? 2);
assertTest("Found active store ID {$activeStoreId}", $activeStoreId > 0);
$storeHeader = ["X-Store-Id: {$activeStoreId}"];

// Cross-store protection: non-existent store context rejected
$crossStoreRes = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'increase_price_percent', 'value' => 10],
], $adminCookie, ['X-Store-Id: 99999']);
assertTest("Cross-store or non-existent store context is rejected (404)", $crossStoreRes['status'] === 404);

// --- 2. Product Bulk Preview: Correct Count & Zero Mutation ---
echo "\n--- 2. Product Bulk Preview ---\n";

// Get original product 301 price
$origProd = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
$origRegularPrice = $origProd['regular_price'] ?? '';

$previewRes = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'store_id' => $activeStoreId,
    'entity' => 'products',
    'selection' => [
        'mode' => 'ids',
        'ids' => [301, 302],
    ],
    'action' => [
        'type' => 'increase_price_percent',
        'value' => 10,
    ],
], $adminCookie, $storeHeader);

assertTest("Preview request returns 200 OK", $previewRes['status'] === 200, $previewRes['body'] ?? '');
$previewData = $previewRes['json']['data'] ?? [];
assertTest("Preview reports correct affected count (2)", ($previewData['affected_count'] ?? 0) === 2);
assertTest("Preview contains sample items", count($previewData['sample'] ?? []) === 2);
assertTest("Preview contains warnings array", is_array($previewData['warnings'] ?? null));

// Verify that product 301 regular price did NOT change during preview!
$prodAfterPreview = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Preview is read-only and caused NO mutation in WooCommerce", ($prodAfterPreview['regular_price'] ?? '') === $origRegularPrice);

// Invalid action validation
$invalidActionRes = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'forbidden_unknown_action', 'value' => 10],
], $adminCookie, $storeHeader);
assertTest("Invalid action type is rejected with 422 Unprocessable Entity", $invalidActionRes['status'] === 422);

// --- 3. Product Price Bulk Operations ---
echo "\n--- 3. Product Price Bulk Operations ---\n";

// A. Increase Regular Price Fixed Amount (+500,000)
$execRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => [
        'mode' => 'ids',
        'ids' => [301],
    ],
    'action' => [
        'type' => 'increase_price_amount',
        'value' => 500000,
    ],
], $adminCookie, $storeHeader);

assertTest("Product price increase execution returns 201 Created", $execRes['status'] === 201, $execRes['body'] ?? '');
$opData = $execRes['json']['data'] ?? [];
$opId = $opData['id'] ?? 0;
assertTest("Operation created with ID {$opId}", $opId > 0);
assertTest("Operation status is completed", ($opData['status'] ?? '') === 'completed');
assertTest("Operation processed 1 item successfully", ($opData['success_items'] ?? 0) === 1);

// Verify change reflected in WooCommerce
$updatedProd = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
$expectedPrice = (float)$origRegularPrice + 500000;
assertTest("Product 301 price correctly updated in WooCommerce", (float)($updatedProd['regular_price'] ?? 0) === $expectedPrice);

// B. Set Sale Price
$saleRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'set_sale_price', 'value' => '48000000'],
], $adminCookie, $storeHeader);
assertTest("Set sale price succeeds (201)", $saleRes['status'] === 201);
$prodWithSale = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Sale price updated to 48000000", (float)($prodWithSale['sale_price'] ?? 0) === 48000000.0);

// C. Clear Sale Price
$clearSaleRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'clear_sale_price'],
], $adminCookie, $storeHeader);
assertTest("Clear sale price succeeds (201)", $clearSaleRes['status'] === 201);
$prodClearedSale = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Sale price is now empty", ($prodClearedSale['sale_price'] ?? '') === '');

// --- 4. Product Inventory Bulk Operations ---
echo "\n--- 4. Product Inventory Bulk Operations ---\n";

// Set stock
$stockRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'set_stock', 'value' => 25],
], $adminCookie, $storeHeader);
assertTest("Set stock quantity succeeds (201)", $stockRes['status'] === 201);
$prodStock = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Product 301 stock_quantity is now 25", (int)($prodStock['stock_quantity'] ?? 0) === 25);

// Increase stock
$incStockRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'increase_stock', 'value' => 5],
], $adminCookie, $storeHeader);
assertTest("Increase stock quantity succeeds (201)", $incStockRes['status'] === 201);
$prodStockInc = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Product 301 stock_quantity is now 30", (int)($prodStockInc['stock_quantity'] ?? 0) === 30);

// Decrease stock
$decStockRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'decrease_stock', 'value' => 10],
], $adminCookie, $storeHeader);
assertTest("Decrease stock quantity succeeds (201)", $decStockRes['status'] === 201);
$prodStockDec = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Product 301 stock_quantity is now 20", (int)($prodStockDec['stock_quantity'] ?? 0) === 20);

// Set stock status
$statusRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'set_stock_status', 'status' => 'instock'],
], $adminCookie, $storeHeader);
assertTest("Set stock status succeeds (201)", $statusRes['status'] === 201);

// --- 5. Product Taxonomy & Status Bulk Operations ---
echo "\n--- 5. Product Taxonomy & Status Bulk Operations ---\n";

// Add Category 2 (Accessories) without removing Category 1
$addCatRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'add_category', 'category_id' => 2],
], $adminCookie, $storeHeader);
assertTest("Add category succeeds (201)", $addCatRes['status'] === 201);
$prodCats = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data']['categories'] ?? [];
$catIds = array_column($prodCats, 'id');
assertTest("Existing categories preserved while adding category 2", in_array(2, $catIds));

// Add Tag 3
$addTagRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'add_tag', 'tag_id' => 3],
], $adminCookie, $storeHeader);
assertTest("Add tag succeeds (201)", $addTagRes['status'] === 201);

// Set Status to draft
$statusChangeRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'set_status', 'status' => 'draft'],
], $adminCookie, $storeHeader);
assertTest("Set status to draft succeeds (201)", $statusChangeRes['status'] === 201);
$prodDraft = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Product status is now draft", ($prodDraft['status'] ?? '') === 'draft');

// Restore to publish
httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'set_status', 'status' => 'publish'],
], $adminCookie, $storeHeader);

// --- 6. Order Bulk Operations ---
echo "\n--- 6. Order Bulk Operations ---\n";

// Change status
$orderStatusRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'orders',
    'selection' => ['mode' => 'ids', 'ids' => [1002]],
    'action' => ['type' => 'change_status', 'status' => 'completed'],
], $adminCookie, $storeHeader);
assertTest("Order status bulk change succeeds (201)", $orderStatusRes['status'] === 201, $orderStatusRes['body'] ?? '');
$ordUpdated = httpReq('GET', '/api/v1/orders/1002', [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Order 1002 status changed to completed in WooCommerce", ($ordUpdated['status'] ?? '') === 'completed');

// Add order note
$noteRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'orders',
    'selection' => ['mode' => 'ids', 'ids' => [1001, 1002]],
    'action' => [
        'type' => 'add_note',
        'note' => 'پیگیری گروهی سفارشات در فاز ۷ سامانه انجام شد.',
        'customer_note' => false,
    ],
], $adminCookie, $storeHeader);
assertTest("Bulk add note to orders succeeds (201)", $noteRes['status'] === 201);

// Blind Refund is FORBIDDEN in bulk
$refundBulkRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'orders',
    'selection' => ['mode' => 'ids', 'ids' => [1001]],
    'action' => ['type' => 'change_status', 'status' => 'refunded'],
], $adminCookie, $storeHeader);
assertTest("Blind bulk refund is strictly rejected (422)", $refundBulkRes['status'] === 422);

// --- 7. Customer CRM Bulk Operations ---
echo "\n--- 7. Customer CRM Bulk Operations ---\n";

// Cleanup tag from previous test runs to ensure idempotency
$db->exec("DELETE ct FROM customer_tags ct JOIN tags t ON ct.tag_id = t.id WHERE t.name = 'مشتریان ویژه فاز ۷'");
$db->exec("DELETE FROM tags WHERE name = 'مشتریان ویژه فاز ۷'");

// Add CRM tag
$custTagRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'customers',
    'selection' => ['mode' => 'ids', 'ids' => [101, 102]],
    'action' => [
        'type' => 'add_tag',
        'tag_name' => 'مشتریان ویژه فاز ۷',
        'color' => '#10B981',
    ],
], $adminCookie, $storeHeader);
assertTest("Bulk add CRM tag to customers succeeds (201)", $custTagRes['status'] === 201, $custTagRes['body'] ?? '');
assertTest("Both customers tagged successfully", ($custTagRes['json']['data']['success_items'] ?? 0) === 2);

// Re-running same tag triggers duplicate prevention (marked skipped)
$dupTagRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'customers',
    'selection' => ['mode' => 'ids', 'ids' => [101]],
    'action' => [
        'type' => 'add_tag',
        'tag_name' => 'مشتریان ویژه فاز ۷',
    ],
], $adminCookie, $storeHeader);
assertTest("Duplicate tag assignment is safely skipped without error", ($dupTagRes['json']['data']['skipped_items'] ?? 0) === 1);

// Create task
$taskRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'customers',
    'selection' => ['mode' => 'ids', 'ids' => [101, 102]],
    'action' => [
        'type' => 'create_task',
        'title' => 'تماس تلفنی جهت هماهنگی ارسال کالا',
        'priority' => 'high',
        'due_date' => '2026-10-01 10:00:00',
    ],
], $adminCookie, $storeHeader);
assertTest("Bulk create task for customers succeeds (201)", $taskRes['status'] === 201);
assertTest("2 tasks created in MariaDB", ($taskRes['json']['data']['success_items'] ?? 0) === 2);

// --- 8. Filter-Based Selection ---
echo "\n--- 8. Filter-Based Selection ---\n";

$filterPreviewRes = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'filter' => [
        'status' => 'publish',
        'stock_status' => 'instock',
    ],
    'action' => [
        'type' => 'increase_price_percent',
        'value' => 5,
    ],
], $adminCookie, $storeHeader);

assertTest("Filter-based preview succeeds (200)", $filterPreviewRes['status'] === 200);
$filterCount = $filterPreviewRes['json']['data']['affected_count'] ?? 0;
assertTest("Filter-based affected count > 0 ({$filterCount} records)", $filterCount > 0);

// --- 9. Operation Management: Index, Show, Cancel, Audit Logs ---
echo "\n--- 9. Operation Management, Show, Cancel & Audit Logs ---\n";

// GET /api/v1/bulk-operations
$listOpsRes = httpReq('GET', '/api/v1/bulk-operations', [], $adminCookie, $storeHeader);
assertTest("List bulk operations returns 200 OK", $listOpsRes['status'] === 200);
$opsList = $listOpsRes['json']['data'] ?? [];
assertTest("Bulk operations list contains operations", count($opsList) >= 3);

// GET /api/v1/bulk-operations/{id}
$latestOpId = $opsList[0]['id'] ?? 1;
$showOpRes = httpReq('GET', "/api/v1/bulk-operations/{$latestOpId}", [], $adminCookie, $storeHeader);
assertTest("Show bulk operation details returns 200 OK", $showOpRes['status'] === 200);
$showData = $showOpRes['json']['data'] ?? [];
assertTest("Operation detail has progress percent", isset($showData['percent']));
assertTest("Operation detail has items array", is_array($showData['items'] ?? null));

// Verify Cancellation
$pendingOpStmt = $db->prepare("
    INSERT INTO bulk_operations (
        store_id, user_id, type, action_type, target_entity,
        status, total_items, processed_items, success_items, failed_items,
        payload, created_at, updated_at
    ) VALUES (
        :store_id, 1, 'test_cancel', 'test_cancel', 'product',
        'pending', 10, 0, 0, 0,
        '{}', NOW(), NOW()
    )
");
$pendingOpStmt->execute(['store_id' => $activeStoreId]);
$cancelTestId = (int)$db->lastInsertId();

$cancelRes = httpReq('POST', "/api/v1/bulk-operations/{$cancelTestId}/cancel", [], $adminCookie, $storeHeader);
assertTest("Cancel operation returns 200 OK", $cancelRes['status'] === 200);
$cancelledData = httpReq('GET', "/api/v1/bulk-operations/{$cancelTestId}", [], $adminCookie, $storeHeader)['json']['data'] ?? [];
assertTest("Operation status is cancelled", ($cancelledData['status'] ?? '') === 'cancelled');

// Verify Audit Log
$auditCheck = $db->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('BULK_OPERATION_CREATED', 'BULK_OPERATION_EXECUTED', 'BULK_OPERATION_CANCELLED')")->fetchColumn();
assertTest("Audit logs recorded for bulk operations ({$auditCheck} records)", (int)$auditCheck >= 3);

// Verify NO credentials leaked in responses or logs
$respString = json_encode($showData);
assertTest("Operation detail does not leak consumer_secret or api keys", !str_contains($respString, 'cs_valid_test_secret') && !str_contains($respString, 'consumer_secret_encrypted'));

// --- 10. Entity-Specific Permission Enforcement ---
echo "\n--- 10. Entity-Specific Permission Enforcement ---\n";

// Create a restricted user with 'bulk.execute' but WITHOUT 'products.bulk'
$restrictedPassword = \App\Support\Security::hashPassword('RestrictedPass123!');
$db->prepare("
    INSERT INTO users (username, email, password_hash, first_name, last_name, is_active)
    VALUES ('restricted_user', 'restricted@crmwp.local', :pass, 'کاربر', 'محدود', 1)
    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1
")->execute(['pass' => $restrictedPassword]);

$restrictedUserId = (int)$db->query("SELECT id FROM users WHERE username = 'restricted_user'")->fetchColumn();

// Create role with bulk.execute only
$db->exec("INSERT IGNORE INTO roles (name, display_name, description) VALUES ('RestrictedRole', 'نقش بدون دسترسی محصول', 'فقط اجرای بالک بدون دسترسی محصول')");
$restrictedRoleId = (int)$db->query("SELECT id FROM roles WHERE name = 'RestrictedRole'")->fetchColumn();

$bulkExecutePermId = (int)$db->query("SELECT id FROM permissions WHERE name = 'bulk.execute'")->fetchColumn();
$bulkViewPermId = (int)$db->query("SELECT id FROM permissions WHERE name = 'bulk.view'")->fetchColumn();

$db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$restrictedRoleId]);
$db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?), (?, ?)")
    ->execute([$restrictedRoleId, $bulkExecutePermId, $restrictedRoleId, $bulkViewPermId]);

$db->prepare("DELETE FROM user_roles WHERE user_id = :user_id")->execute(['user_id' => $restrictedUserId]);
$db->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)")
    ->execute(['u' => $restrictedUserId, 'r' => $restrictedRoleId]);

// Login as restricted user
$restrLogin = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'restricted_user',
    'password' => 'RestrictedPass123!',
]);
$restrCookie = $restrLogin['cookie'] ?? '';

// Attempt product bulk execution as restricted user
$restrExecRes = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301]],
    'action' => ['type' => 'increase_price_percent', 'value' => 10],
], $restrCookie, $storeHeader);

assertTest("User with 'bulk.execute' but lacking 'products.bulk' is rejected (403 Forbidden)", $restrExecRes['status'] === 403, "Status: {$restrExecRes['status']}");

echo "\n========================================================\n";
echo " Phase 7 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
