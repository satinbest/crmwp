<?php

/**
 * Phase 8: Inventory Management Automated Integration Tests
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
echo " Phase 8: Inventory Management Automated Tests\n";
echo "========================================================\n\n";

// --- 1. Authentication & Security ---
echo "--- 1. Authentication & Security ---\n";
$res = httpReq('GET', '/api/v1/inventory');
assertTest("Unauthenticated request to inventory is rejected (401)", $res['status'] === 401);

$loginRes = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$adminCookie = $loginRes['cookie'] ?? '';
assertTest("Admin login succeeds (200)", $loginRes['status'] === 200);

$storesRes = httpReq('GET', '/api/v1/stores', [], $adminCookie);
$activeStoreId = (int)($storesRes['json']['data'][0]['id'] ?? 2);
$storeHeader = ["X-Store-Id: {$activeStoreId}"];
assertTest("Resolved store ID {$activeStoreId}", $activeStoreId > 0);

// Cross store protection
$crossStore = httpReq('GET', '/api/v1/inventory', [], $adminCookie, ['X-Store-Id: 99999']);
assertTest("Cross-store or invalid store rejected (404)", $crossStore['status'] === 404);

// --- 2. Inventory Metrics & Dashboard ---
echo "\n--- 2. Inventory Dashboard Metrics ---\n";
$metricsRes = httpReq('GET', '/api/v1/inventory/metrics', [], $adminCookie, $storeHeader);
assertTest("GET /api/v1/inventory/metrics returns 200 OK", $metricsRes['status'] === 200);
$metrics = $metricsRes['json']['data'] ?? [];
assertTest("Metrics contains total_products", isset($metrics['total_products']));
assertTest("Metrics contains instock count", isset($metrics['instock']));
assertTest("Metrics contains outofstock count", isset($metrics['outofstock']));
assertTest("Metrics contains onbackorder count", isset($metrics['onbackorder']));
assertTest("Metrics contains managing_stock count", isset($metrics['managing_stock']));

// --- 3. Inventory List & Normalization ---
echo "\n--- 3. Inventory List & Normalization ---\n";
$listRes = httpReq('GET', '/api/v1/inventory', [], $adminCookie, $storeHeader);
assertTest("GET /api/v1/inventory returns 200 OK", $listRes['status'] === 200);
$items = $listRes['json']['data'] ?? [];
assertTest("Inventory returns items array", count($items) > 0);

$sampleItem = $items[0] ?? [];
assertTest("Item has product_id", isset($sampleItem['product_id']));
assertTest("Item has product_name", !empty($sampleItem['product_name']));
assertTest("Item has sku", array_key_exists('sku', $sampleItem));
assertTest("Item has manage_stock", array_key_exists('manage_stock', $sampleItem));
assertTest("Item has stock_quantity", array_key_exists('stock_quantity', $sampleItem));
assertTest("Item has stock_status", in_array($sampleItem['stock_status'] ?? '', ['instock', 'outofstock', 'onbackorder'], true));
assertTest("Item has backorders setting", array_key_exists('backorders', $sampleItem));
assertTest("Item has low_stock_amount", array_key_exists('low_stock_amount', $sampleItem));
assertTest("Item has is_low_stock boolean", is_bool($sampleItem['is_low_stock'] ?? null));

// --- 4. Search and Filters ---
echo "\n--- 4. Search & Filters ---\n";
$searchRes = httpReq('GET', '/api/v1/inventory?search=301', [], $adminCookie, $storeHeader);
assertTest("Search by ID 301 succeeds (200)", $searchRes['status'] === 200);
$found = $searchRes['json']['data'][0] ?? null;
assertTest("Found item matching ID 301", ($found['product_id'] ?? 0) === 301);

$outOfStockRes = httpReq('GET', '/api/v1/inventory?stock_status=outofstock', [], $adminCookie, $storeHeader);
assertTest("Filter by stock_status=outofstock succeeds (200)", $outOfStockRes['status'] === 200);

$lowStockRes = httpReq('GET', '/api/v1/inventory/low-stock', [], $adminCookie, $storeHeader);
assertTest("GET /api/v1/inventory/low-stock succeeds (200)", $lowStockRes['status'] === 200);

$outOfStockEndpoint = httpReq('GET', '/api/v1/inventory/out-of-stock', [], $adminCookie, $storeHeader);
assertTest("GET /api/v1/inventory/out-of-stock succeeds (200)", $outOfStockEndpoint['status'] === 200);

// --- 5. Stock Modification (Set, Increase, Decrease) ---
echo "\n--- 5. Stock Modifications ---\n";
// Set stock
$setRes = httpReq('PATCH', '/api/v1/inventory/301/stock', [
    'operation' => 'set',
    'quantity' => 45,
], $adminCookie, $storeHeader);
assertTest("Set stock quantity to 45 returns 200", $setRes['status'] === 200);
$updated = $setRes['json']['data'] ?? [];
assertTest("Stock quantity updated to 45", (float)($updated['stock_quantity'] ?? 0) === 45.0);

// Increase stock (+15)
$incRes = httpReq('PATCH', '/api/v1/inventory/301/stock', [
    'operation' => 'increase',
    'quantity' => 15,
], $adminCookie, $storeHeader);
assertTest("Increase stock by 15 returns 200", $incRes['status'] === 200);
$updated = $incRes['json']['data'] ?? [];
assertTest("Stock quantity incremented to 60", (float)($updated['stock_quantity'] ?? 0) === 60.0);

// Decrease stock (-25)
$decRes = httpReq('PATCH', '/api/v1/inventory/301/stock', [
    'operation' => 'decrease',
    'quantity' => 25,
], $adminCookie, $storeHeader);
assertTest("Decrease stock by 25 returns 200", $decRes['status'] === 200);
$updated = $decRes['json']['data'] ?? [];
assertTest("Stock quantity decremented to 35", (float)($updated['stock_quantity'] ?? 0) === 35.0);

// Invalid quantity or operation rejected
$invalidOp = httpReq('PATCH', '/api/v1/inventory/301/stock', [
    'operation' => 'invalid_op',
    'quantity' => 10,
], $adminCookie, $storeHeader);
assertTest("Invalid operation rejected with 422", $invalidOp['status'] === 422);

// --- 6. Stock Status Modification ---
echo "\n--- 6. Stock Status Modifications ---\n";
$statusRes = httpReq('PATCH', '/api/v1/inventory/301/status', [
    'stock_status' => 'outofstock',
], $adminCookie, $storeHeader);
assertTest("Change stock status to outofstock returns 200", $statusRes['status'] === 200);
assertTest("Status updated to outofstock", ($statusRes['json']['data']['stock_status'] ?? '') === 'outofstock');

$restoreStatus = httpReq('PATCH', '/api/v1/inventory/301/status', [
    'stock_status' => 'instock',
], $adminCookie, $storeHeader);
assertTest("Restore stock status to instock returns 200", $restoreStatus['status'] === 200);

// --- 7. Inventory Configuration Updates ---
echo "\n--- 7. Inventory Configuration Updates ---\n";
$configRes = httpReq('PATCH', '/api/v1/inventory/301', [
    'manage_stock' => true,
    'low_stock_amount' => 8,
    'backorders' => 'notify',
    'sold_individually' => true,
], $adminCookie, $storeHeader);
assertTest("Update inventory configuration returns 200", $configRes['status'] === 200);
$cfg = $configRes['json']['data'] ?? [];
assertTest("low_stock_amount updated to 8", ($cfg['low_stock_amount'] ?? 0) === 8);
assertTest("backorders updated to notify", ($cfg['backorders'] ?? '') === 'notify');
assertTest("sold_individually updated to true", ($cfg['sold_individually'] ?? false) === true);

// --- 8. Variation Inventory ---
echo "\n--- 8. Variation Inventory ---\n";
$varRes = httpReq('GET', '/api/v1/inventory/302/variations/3021', [], $adminCookie, $storeHeader);
assertTest("GET variation inventory returns 200", $varRes['status'] === 200);
$varItem = $varRes['json']['data'] ?? [];
assertTest("Variation item has is_variation=true", ($varItem['is_variation'] ?? false) === true);
assertTest("Variation item has variation_id=3021", ($varItem['variation_id'] ?? 0) === 3021);

$varStockRes = httpReq('PATCH', '/api/v1/inventory/302/variations/3021', [
    'operation' => 'set',
    'quantity' => 18,
], $adminCookie, $storeHeader);
assertTest("Update variation stock returns 200", $varStockRes['status'] === 200);
assertTest("Variation stock updated to 18", (float)($varStockRes['json']['data']['stock_quantity'] ?? 0) === 18.0);

// --- 9. Audit and Activity Logging ---
echo "\n--- 9. Audit & Activity Logging ---\n";
$auditCount = $db->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('INVENTORY_STOCK_UPDATED', 'INVENTORY_STATUS_UPDATED', 'INVENTORY_CONFIG_UPDATED')")->fetchColumn();
assertTest("Audit logs recorded for inventory mutations ({$auditCount} records)", (int)$auditCount >= 3);

$actCount = $db->query("SELECT COUNT(*) FROM activities WHERE action_type IN ('inventory_stock_changed', 'inventory_status_changed', 'inventory_configuration_changed')")->fetchColumn();
assertTest("Activities recorded for inventory mutations ({$actCount} records)", (int)$actCount >= 3);

// --- 10. Architectural Constraint: NO Local Inventory Table ---
echo "\n--- 10. Architectural Verification ---\n";
$tables = $db->query("SHOW TABLES LIKE 'inventory'")->fetchAll();
assertTest("NO local 'inventory' table exists (WooCommerce is sole source of truth)", count($tables) === 0);

$tables2 = $db->query("SHOW TABLES LIKE 'product_stock'")->fetchAll();
assertTest("NO local 'product_stock' table exists", count($tables2) === 0);

$tables3 = $db->query("SHOW TABLES LIKE 'stock_movements'")->fetchAll();
assertTest("NO local 'stock_movements' table exists", count($tables3) === 0);

// --- 11. Security & Permissions ---
echo "\n--- 11. Permission Verification ---\n";
// Create user with only inventory.view
$db->exec("DELETE FROM users WHERE username = 'viewonly_inv'");
$db->exec("INSERT INTO users (username, email, password_hash, is_active, created_at, updated_at) VALUES ('viewonly_inv', 'viewonly_inv@test.local', '" . password_hash('Pass123!', PASSWORD_BCRYPT) . "', 1, NOW(), NOW())");
$viewUserId = (int)$db->lastInsertId();

// Assign role with ONLY inventory.view
$db->exec("DELETE FROM roles WHERE name = 'inv_viewer'");
$db->exec("INSERT INTO roles (name, slug, display_name, description, created_at) VALUES ('inv_viewer', 'inv-viewer', 'مشاهده‌گر انبار', 'فقط مشاهده انبار', NOW())");
$viewerRoleId = (int)$db->lastInsertId();

$permId = (int)$db->query("SELECT id FROM permissions WHERE name = 'inventory.view'")->fetchColumn();
$db->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES ({$viewerRoleId}, {$permId})");
$db->exec("INSERT INTO user_roles (user_id, role_id) VALUES ({$viewUserId}, {$viewerRoleId})");
$db->exec("INSERT IGNORE INTO user_stores (user_id, store_id) VALUES ({$viewUserId}, {$activeStoreId})");

$viewerLogin = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'viewonly_inv',
    'password' => 'Pass123!',
]);
$viewerCookie = $viewerLogin['cookie'] ?? '';

// Can view inventory
$viewerList = httpReq('GET', '/api/v1/inventory', [], $viewerCookie, $storeHeader);
assertTest("User with inventory.view can view inventory (200)", $viewerList['status'] === 200);

// Cannot modify stock
$viewerUpdate = httpReq('PATCH', '/api/v1/inventory/301/stock', ['operation' => 'set', 'quantity' => 100], $viewerCookie, $storeHeader);
assertTest("User without inventory.update cannot modify stock (403 Forbidden)", $viewerUpdate['status'] === 403);

// Cannot modify config
$viewerConfig = httpReq('PATCH', '/api/v1/inventory/301', ['manage_stock' => false], $viewerCookie, $storeHeader);
assertTest("User without inventory.manage_stock cannot modify config (403 Forbidden)", $viewerConfig['status'] === 403);

echo "\n========================================================\n";
echo " Phase 8 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";
