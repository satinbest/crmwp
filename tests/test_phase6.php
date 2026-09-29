<?php

declare(strict_types=1);

/**
 * Phase 6: Products Module Automated Test Suite
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
echo " Phase 6: Products Module Automated Tests\n";
echo "========================================================\n\n";

// --- 1. Authentication & Store Context Verification ---
echo "--- 1. Authentication & Store Context ---\n";

$res = httpReq('GET', '/api/v1/products');
assertTest("Unauthenticated request is rejected (401)", $res['status'] === 401);

$loginRes = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$adminCookie = $loginRes['cookie'] ?? '';
assertTest("Admin login succeeds (200)", $loginRes['status'] === 200, $loginRes['body'] ?? '');

$storesRes = httpReq('GET', '/api/v1/stores', [], $adminCookie);
$activeStoreId = (int)($storesRes['json']['data'][0]['id'] ?? 2);
assertTest("Found active store ID {$activeStoreId}", $activeStoreId > 0);

$storeHeader = ["X-Store-Id: {$activeStoreId}"];

$res = httpReq('GET', '/api/v1/products', [], $adminCookie, $storeHeader);
assertTest("Authenticated request with store context allowed (200)", $res['status'] === 200, "Status is {$res['status']}");

$badStoreRes = httpReq('GET', '/api/v1/products', [], $adminCookie, ['X-Store-Id: 99999']);
assertTest("Request with non-existent store context is rejected (404)", $badStoreRes['status'] === 404);

// --- 2. Products List & Normalization ---
echo "\n--- 2. Products List & Normalization ---\n";
$listRes = httpReq('GET', '/api/v1/products', [], $adminCookie, $storeHeader);
assertTest("Products list returns success true", ($listRes['json']['success'] ?? false) === true);
$products = $listRes['json']['data'] ?? [];
assertTest("Products list returns products array", count($products) >= 3);

$firstProduct = $products[0] ?? [];
assertTest("Product has normalized 'id'", !empty($firstProduct['id']));
assertTest("Product has normalized 'name'", !empty($firstProduct['name']));
assertTest("Product has normalized 'slug'", !empty($firstProduct['slug']));
assertTest("Product has normalized 'type'", !empty($firstProduct['type']));
assertTest("Product has normalized 'status'", !empty($firstProduct['status']));
assertTest("Product has normalized 'sku'", isset($firstProduct['sku']));
assertTest("Product has normalized 'price'", isset($firstProduct['price']));
assertTest("Product has normalized 'regular_price'", isset($firstProduct['regular_price']));
assertTest("Product has normalized 'sale_price'", array_key_exists('sale_price', $firstProduct));
assertTest("Product has normalized 'stock_quantity'", array_key_exists('stock_quantity', $firstProduct));
assertTest("Product has normalized 'stock_status'", !empty($firstProduct['stock_status']));
assertTest("Product has normalized 'manage_stock'", array_key_exists('manage_stock', $firstProduct));
assertTest("Product has normalized 'categories' array", is_array($firstProduct['categories'] ?? null));
assertTest("Product has normalized 'tags' array", is_array($firstProduct['tags'] ?? null));
assertTest("Product has normalized 'images' array", is_array($firstProduct['images'] ?? null));
assertTest("Product has normalized 'attributes' array", is_array($firstProduct['attributes'] ?? null));
assertTest("Product has normalized 'meta' array", is_array($firstProduct['meta'] ?? null));

// Verify sensitive metadata is stripped
$metaString = json_encode($firstProduct['meta'] ?? []);
assertTest("Product metadata does not leak credentials or secret keys", !str_contains($metaString, 'cs_secret') && !str_contains($metaString, 'super_secret_token'));

// --- 3. Server-Side Search ---
echo "\n--- 3. Server-Side Search ---\n";
$searchSku = httpReq('GET', '/api/v1/products?search=ASUS-VIVO-15', [], $adminCookie, $storeHeader);
assertTest("Search for SKU 'ASUS-VIVO-15' returns matching product", ($searchSku['json']['data'][0]['id'] ?? 0) === 301);

$searchNamePersian = urlencode('هدفون بی‌سیم');
$searchName = httpReq('GET', "/api/v1/products?search={$searchNamePersian}", [], $adminCookie, $storeHeader);
assertTest("Search for Persian product name returns 200 OK", $searchName['status'] === 200);
assertTest("Search for Persian name matched product 302", ($searchName['json']['data'][0]['id'] ?? 0) === 302);

$searchId = httpReq('GET', '/api/v1/products?search=303', [], $adminCookie, $storeHeader);
assertTest("Search for ID '303' returns product 303", ($searchId['json']['data'][0]['id'] ?? 0) === 303);

// --- 4. Server-Side Pagination & Safe Limits ---
echo "\n--- 4. Server-Side Pagination & Safe Limits ---\n";
$pageRes = httpReq('GET', '/api/v1/products?page=1&per_page=2', [], $adminCookie, $storeHeader);
assertTest("Pagination page 1 with per_page 2 returns 2 items", count($pageRes['json']['data'] ?? []) === 2);
assertTest("Pagination meta has total", ($pageRes['json']['meta']['total'] ?? 0) >= 3);
assertTest("Pagination meta has total_pages", ($pageRes['json']['meta']['total_pages'] ?? 0) >= 2);

$safeLimitRes = httpReq('GET', '/api/v1/products?per_page=500', [], $adminCookie, $storeHeader);
assertTest("Safe limit caps per_page to maximum 100", ($safeLimitRes['json']['meta']['per_page'] ?? 0) <= 100);

// --- 5. Dynamic Filters ---
echo "\n--- 5. Dynamic Filters ---\n";
$typeFilter = httpReq('GET', '/api/v1/products?type=variable', [], $adminCookie, $storeHeader);
assertTest("Filter by type 'variable' returns variable products", ($typeFilter['json']['data'][0]['type'] ?? '') === 'variable');

$statusFilter = httpReq('GET', '/api/v1/products?status=draft', [], $adminCookie, $storeHeader);
assertTest("Filter by status 'draft' returns draft products", ($statusFilter['json']['data'][0]['status'] ?? '') === 'draft');

$stockFilter = httpReq('GET', '/api/v1/products?stock_status=outofstock', [], $adminCookie, $storeHeader);
assertTest("Filter by stock_status 'outofstock' returns matching products", ($stockFilter['json']['data'][0]['stock_status'] ?? '') === 'outofstock');

$catFilter = httpReq('GET', '/api/v1/products?category=1', [], $adminCookie, $storeHeader);
assertTest("Filter by category ID 1 returns matching products", count($catFilter['json']['data'] ?? []) >= 1);

// --- 6. Taxonomies: Categories, Tags, and Attributes ---
echo "\n--- 6. Taxonomies: Categories, Tags, and Attributes ---\n";
$catsRes = httpReq('GET', '/api/v1/product-categories', [], $adminCookie, $storeHeader);
assertTest("GET /product-categories returns 200", $catsRes['status'] === 200);
assertTest("Categories array is non-empty", count($catsRes['json']['data'] ?? []) >= 2);
assertTest("Category has name and id", !empty($catsRes['json']['data'][0]['name']) && !empty($catsRes['json']['data'][0]['id']));

$tagsRes = httpReq('GET', '/api/v1/product-tags', [], $adminCookie, $storeHeader);
assertTest("GET /product-tags returns 200", $tagsRes['status'] === 200);
assertTest("Tags array is non-empty", count($tagsRes['json']['data'] ?? []) >= 2);

$attrsRes = httpReq('GET', '/api/v1/product-attributes', [], $adminCookie, $storeHeader);
assertTest("GET /product-attributes returns 200", $attrsRes['status'] === 200);
assertTest("Attributes array is non-empty", count($attrsRes['json']['data'] ?? []) >= 1);

// --- 7. Single Product Retrieval & 404 Handling ---
echo "\n--- 7. Single Product Retrieval & 404 ---\n";
$singleRes = httpReq('GET', '/api/v1/products/301', [], $adminCookie, $storeHeader);
assertTest("GET /products/301 returns 200", $singleRes['status'] === 200);
assertTest("Product id is 301", ($singleRes['json']['data']['id'] ?? 0) === 301);
assertTest("Product has short_description and description", isset($singleRes['json']['data']['short_description']));

$notFoundRes = httpReq('GET', '/api/v1/products/999999', [], $adminCookie, $storeHeader);
assertTest("GET /products/999999 returns 404", $notFoundRes['status'] === 404);

// --- 8. Product Creation (POST) ---
echo "\n--- 8. Product Creation ---\n";
$createPayload = [
    'name' => 'تیشرت نخی تابستانه تست فاز ۶',
    'type' => 'simple',
    'status' => 'publish',
    'sku' => 'TEST-PHASE6-001',
    'regular_price' => '320000',
    'sale_price' => '280000',
    'manage_stock' => true,
    'stock_quantity' => 45,
    'stock_status' => 'instock',
    'description' => '<p>توضیحات کامل محصول تستی فاز ششم</p>',
    'short_description' => '<p>خلاصه تیشرت</p>',
];
$createRes = httpReq('POST', '/api/v1/products', $createPayload, $adminCookie, $storeHeader);
assertTest("POST /products creates product (201)", $createRes['status'] === 201, $createRes['body'] ?? '');
$newProductId = (int)($createRes['json']['data']['id'] ?? 0);
assertTest("Created product has valid ID", $newProductId > 0);
assertTest("Created product has correct name", ($createRes['json']['data']['name'] ?? '') === 'تیشرت نخی تابستانه تست فاز ۶');

// Verify Audit Log for creation
$stmt = $db->prepare("SELECT * FROM audit_logs WHERE store_id = :sid AND action = 'PRODUCT_CREATED' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$createAudit = $stmt->fetch();
assertTest("Audit log 'PRODUCT_CREATED' exists in database", !empty($createAudit));

// Verify Activity for creation
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'product' AND entity_id = :pid AND action_type = 'product_created' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId, ':pid' => $newProductId]);
$createActivity = $stmt->fetch();
assertTest("Activity 'product_created' exists in database", !empty($createActivity));

// --- 9. Product Update (PATCH) & Activity Diff Detection ---
echo "\n--- 9. Product Update & Change Detection ---\n";
$updatePayload = [
    'regular_price' => '350000',
    'stock_quantity' => 40,
    'status' => 'draft',
];
$updateRes = httpReq('PATCH', "/api/v1/products/{$newProductId}", $updatePayload, $adminCookie, $storeHeader);
assertTest("PATCH /products/{$newProductId} succeeds (200)", $updateRes['status'] === 200, $updateRes['body'] ?? '');
assertTest("Updated price reflected in response", (float)($updateRes['json']['data']['regular_price'] ?? 0) == 350000);
assertTest("Updated stock reflected in response", ($updateRes['json']['data']['stock_quantity'] ?? 0) === 40);

// Check price change activity
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'product' AND entity_id = :pid AND action_type = 'product_price_changed' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId, ':pid' => $newProductId]);
$priceActivity = $stmt->fetch();
assertTest("Activity 'product_price_changed' created in database", !empty($priceActivity));

// Check stock change activity
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'product' AND entity_id = :pid AND action_type = 'product_stock_changed' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId, ':pid' => $newProductId]);
$stockActivity = $stmt->fetch();
assertTest("Activity 'product_stock_changed' created in database", !empty($stockActivity));

// Check audit log for update
$stmt = $db->prepare("SELECT * FROM audit_logs WHERE store_id = :sid AND action = 'PRODUCT_UPDATED' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$updateAudit = $stmt->fetch();
assertTest("Audit log 'PRODUCT_UPDATED' exists in database", !empty($updateAudit));

// --- 10. Product Deletion (DELETE) ---
echo "\n--- 10. Product Deletion ---\n";
// Trash product (force=false)
$trashRes = httpReq('DELETE', "/api/v1/products/{$newProductId}", ['force' => false], $adminCookie, $storeHeader);
assertTest("DELETE /products/{id} without force trashes product (200)", $trashRes['status'] === 200, $trashRes['body'] ?? '');

// Permanently delete product (force=true)
$forceDeleteRes = httpReq('DELETE', "/api/v1/products/{$newProductId}", ['force' => true], $adminCookie, $storeHeader);
assertTest("DELETE /products/{id} with force=true permanently deletes product (200)", $forceDeleteRes['status'] === 200, $forceDeleteRes['body'] ?? '');

// Verify Activity for deletion
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'product' AND entity_id = :pid AND action_type = 'product_deleted' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId, ':pid' => $newProductId]);
$deleteActivity = $stmt->fetch();
assertTest("Activity 'product_deleted' exists in database", !empty($deleteActivity));

// Verify Audit Log for deletion
$stmt = $db->prepare("SELECT * FROM audit_logs WHERE store_id = :sid AND action = 'PRODUCT_DELETED' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$deleteAudit = $stmt->fetch();
assertTest("Audit log 'PRODUCT_DELETED' exists in database", !empty($deleteAudit));

// --- 11. Variations CRUD ---
echo "\n--- 11. Variations CRUD ---\n";
// List variations of variable product 302
$varsListRes = httpReq('GET', '/api/v1/products/302/variations', [], $adminCookie, $storeHeader);
assertTest("GET /products/302/variations returns variations list (200)", $varsListRes['status'] === 200);
$variations = $varsListRes['json']['data'] ?? [];
assertTest("Variations count >= 2", count($variations) >= 2);
assertTest("Variation has attributes array", is_array($variations[0]['attributes'] ?? null));
assertTest("Variation has sku and price", isset($variations[0]['sku']) && isset($variations[0]['price']));

// Get single variation
$singleVarRes = httpReq('GET', '/api/v1/products/302/variations/3021', [], $adminCookie, $storeHeader);
assertTest("GET /products/302/variations/3021 returns variation (200)", $singleVarRes['status'] === 200);
assertTest("Variation id is 3021", ($singleVarRes['json']['data']['id'] ?? 0) === 3021);

// Create variation
$createVarPayload = [
    'regular_price' => '620000',
    'sku' => 'SONY-WH-SLV-XL',
    'stock_quantity' => 12,
    'manage_stock' => true,
    'attributes' => [
        ['name' => 'رنگ', 'option' => 'سفید'],
    ],
];
$createVarRes = httpReq('POST', '/api/v1/products/302/variations', $createVarPayload, $adminCookie, $storeHeader);
assertTest("POST /products/302/variations creates variation (201)", $createVarRes['status'] === 201, $createVarRes['body'] ?? '');
$newVarId = (int)($createVarRes['json']['data']['id'] ?? 0);
assertTest("New variation has valid ID", $newVarId > 0);

// Verify Activity for variation creation
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'product' AND entity_id = 302 AND action_type = 'variation_created' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$createVarActivity = $stmt->fetch();
assertTest("Activity 'variation_created' exists in database", !empty($createVarActivity));

// Update variation
$updateVarPayload = [
    'regular_price' => '650000',
    'stock_quantity' => 9,
];
$updateVarRes = httpReq('PATCH', "/api/v1/products/302/variations/{$newVarId}", $updateVarPayload, $adminCookie, $storeHeader);
assertTest("PATCH /products/302/variations/{$newVarId} updates variation (200)", $updateVarRes['status'] === 200, $updateVarRes['body'] ?? '');
assertTest("Variation updated price matches", (float)($updateVarRes['json']['data']['regular_price'] ?? 0) == 650000);

// Verify Activity for variation update
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'product' AND entity_id = 302 AND action_type = 'variation_updated' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$updateVarActivity = $stmt->fetch();
assertTest("Activity 'variation_updated' exists in database", !empty($updateVarActivity));

// Delete variation
$deleteVarRes = httpReq('DELETE', "/api/v1/products/302/variations/{$newVarId}", ['force' => true], $adminCookie, $storeHeader);
assertTest("DELETE /products/302/variations/{$newVarId} deletes variation (200)", $deleteVarRes['status'] === 200, $deleteVarRes['body'] ?? '');

// Verify Activity for variation deletion
$stmt = $db->prepare("SELECT * FROM activities WHERE store_id = :sid AND entity_type = 'product' AND entity_id = 302 AND action_type = 'variation_deleted' ORDER BY id DESC LIMIT 1");
$stmt->execute([':sid' => $activeStoreId]);
$deleteVarActivity = $stmt->fetch();
assertTest("Activity 'variation_deleted' exists in database", !empty($deleteVarActivity));

// --- 12. Architectural & Security Verification ---
echo "\n--- 12. Architectural & Security Verification ---\n";
// Check forbidden database tables
$stmt = $db->query("SHOW TABLES LIKE '%product%'");
$productTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$forbiddenTables = [
    'products',
    'woocommerce_products',
    'product_variations',
    'woocommerce_product_variations',
    'product_categories',
    'woocommerce_product_categories',
];
$foundForbidden = array_intersect($productTables, $forbiddenTables);
assertTest("NO forbidden product synchronization tables exist in MariaDB (" . implode(', ', $forbiddenTables) . ")", empty($foundForbidden), "Found: " . implode(', ', $foundForbidden));

// Check no credentials in product responses
$productJsonString = json_encode($singleRes['json']);
assertTest("Product response does NOT leak consumer_secret or api keys", !str_contains($productJsonString, 'cs_test') && !str_contains($productJsonString, 'ck_test'));

echo "\n========================================================\n";
echo " Phase 6 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n\n";

exit($failed > 0 ? 1 : 0);
