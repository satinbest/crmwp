<?php

declare(strict_types=1);

/**
 * Phase 18: Variable Products & Product Variations Bulk Edit Automated Test Suite
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
    if ($method === 'GET' && !empty($data)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
    }
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = array_merge([
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ], $extraHeaders);

    if ($method !== 'GET' && (!empty($data) || in_array($method, ['POST', 'PUT', 'PATCH'], true))) {
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
        return ['status' => $status, 'headers' => '', 'body' => '', 'json' => null, 'cookie' => ''];
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
echo " Phase 18: Variable & Variation Bulk Edit Test Suite\n";
echo "========================================================\n\n";

// Login as admin
$loginRes = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$adminCookie = $loginRes['cookie'] ?? '';
assertTest("Admin login succeeds (200)", $loginRes['status'] === 200);

// Use Store 1 (backed by mock WooCommerce server on port 8001 with full variable products & variations)
$storeHeader = ["X-Store-Id: 1"];

// --- 1. Variable Product & Variation Discovery ---
echo "--- 1. Variable Product & Variation Discovery ---\n";
$prodRes = httpReq('GET', '/api/v1/products', ['type' => 'variable'], $adminCookie, $storeHeader);
assertTest("Listing products with type=variable succeeds (200)", $prodRes['status'] === 200);
$variableProducts = array_filter($prodRes['json']['data'] ?? [], fn($p) => ($p['type'] ?? '') === 'variable');
assertTest("Found at least 1 variable product in store", count($variableProducts) >= 1);

$firstVarProd = reset($variableProducts);
$varParentId = (int)$firstVarProd['id'];
assertTest("Variable product #{$varParentId} has type 'variable'", ($firstVarProd['type'] ?? '') === 'variable');

// Fetch variations for this product
$varsRes = httpReq('GET', "/api/v1/products/{$varParentId}/variations", [], $adminCookie, $storeHeader);
assertTest("Listing variations for parent #{$varParentId} succeeds", $varsRes['status'] === 200);
$variations = $varsRes['json']['data'] ?? [];
assertTest("Parent has variations list (> 0)", count($variations) > 0);
$sampleVariation = $variations[0] ?? [];
$varId = (int)($sampleVariation['id'] ?? 0);
assertTest("Sample variation has valid ID #{$varId}", $varId > 0);

// --- 2. Target Resolution: Parent vs Variations vs Both ---
echo "\n--- 2. Target Resolution in Preview ---\n";

// Target: Parent
$previewParent = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [$varParentId]],
    'action' => ['type' => 'set_status', 'status' => 'draft', 'target' => 'parent']
], $adminCookie, $storeHeader);
assertTest("Preview with target='parent' succeeds (200)", $previewParent['status'] === 200);
$parentData = $previewParent['json']['data'] ?? [];
assertTest("Target 'parent' affected count is 1", ($parentData['affected_count'] ?? 0) === 1);
assertTest("Target 'parent' variation count is 0", ($parentData['variation_count'] ?? 0) === 0);

// Target: Variations
$previewVars = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [$varParentId]],
    'action' => ['type' => 'increase_price_percent', 'value' => 15, 'target' => 'variations']
], $adminCookie, $storeHeader);
assertTest("Preview with target='variations' succeeds (200)", $previewVars['status'] === 200);
$varsData = $previewVars['json']['data'] ?? [];
$expectedVarCount = count($variations);
assertTest("Target 'variations' affected count equals variation count ({$expectedVarCount})", ($varsData['affected_count'] ?? 0) === $expectedVarCount);
assertTest("Target 'variations' reports variation_count correctly", ($varsData['variation_count'] ?? 0) === $expectedVarCount);
assertTest("Target 'variations' sample items marked as is_variation=true", ($varsData['sample'][0]['is_variation'] ?? false) === true);

// Target: Both
$previewBoth = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [$varParentId]],
    'action' => ['type' => 'set_stock_status', 'status' => 'instock', 'target' => 'both']
], $adminCookie, $storeHeader);
assertTest("Preview with target='both' succeeds (200)", $previewBoth['status'] === 200);
$bothData = $previewBoth['json']['data'] ?? [];
assertTest("Target 'both' affected count includes parent + variations (" . (1 + $expectedVarCount) . ")", ($bothData['affected_count'] ?? 0) === (1 + $expectedVarCount));

// --- 3. Execution: Bulk Edit Variations Price ---
echo "\n--- 3. Execution: Bulk Edit Variations Price ---\n";
$origVarPrice = (float)($sampleVariation['regular_price'] ?? 100000);
$execVars = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [$varParentId]],
    'action' => [
        'type' => 'increase_price_amount',
        'value' => 20000,
        'target' => 'variations'
    ]
], $adminCookie, $storeHeader);

assertTest("Bulk operation on variations returns 201 Created", $execVars['status'] === 201);
$execVarsData = $execVars['json']['data'] ?? [];
echo "EXEC RESULT: " . json_encode($execVarsData, JSON_UNESCAPED_UNICODE) . "\n";
assertTest("Execution status is completed", ($execVarsData['status'] ?? '') === 'completed');
assertTest("Execution processed all variations successfully", ($execVarsData['success_items'] ?? 0) >= count($variations));

// Verify in WooCommerce Adapter that variation price was actually updated
$refreshedVarsRes = httpReq('GET', "/api/v1/products/{$varParentId}/variations", [], $adminCookie, $storeHeader);
$refreshedSampleVar = $refreshedVarsRes['json']['data'][0] ?? [];
$newVarPrice = (float)($refreshedSampleVar['regular_price'] ?? 0);
assertTest("Sample variation price updated correctly in WooCommerce (+20,000)", $newVarPrice === ($origVarPrice + 20000));

// --- 4. Execution: Bulk Edit Variations Stock ---
echo "\n--- 4. Execution: Bulk Edit Variations Stock ---\n";
$execStock = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [$varParentId]],
    'action' => [
        'type' => 'set_stock',
        'value' => 45,
        'target' => 'variations'
    ]
], $adminCookie, $storeHeader);

assertTest("Bulk stock update on variations returns 201 Created", $execStock['status'] === 201);
$refreshedStockVars = httpReq('GET', "/api/v1/products/{$varParentId}/variations", [], $adminCookie, $storeHeader)['json']['data'] ?? [];
$sampleStockVar = $refreshedStockVars[0] ?? [];
assertTest("Sample variation stock_quantity set to 45", (int)($sampleStockVar['stock_quantity'] ?? 0) === 45);
assertTest("Sample variation stock_status set to instock", ($sampleStockVar['stock_status'] ?? '') === 'instock');

// --- 5. Execution: Mixed Selection (Simple + Variable) ---
echo "\n--- 5. Mixed Selection Execution (Simple + Variable) ---\n";
$simples = array_values(array_filter($prodRes['json']['data'] ?? [], fn($p) => ($p['type'] ?? '') === 'simple'));
$simpleId = (int)($simples[0]['id'] ?? 0);

if ($simpleId > 0) {
    $execMixed = httpReq('POST', '/api/v1/bulk-operations', [
        'entity' => 'products',
        'selection' => ['mode' => 'ids', 'ids' => [$simpleId, $varParentId]],
        'action' => [
            'type' => 'set_stock_status',
            'status' => 'instock',
            'target' => 'both'
        ]
    ], $adminCookie, $storeHeader);

    assertTest("Mixed selection bulk operation returns 201 Created", $execMixed['status'] === 201);
    $mixedData = $execMixed['json']['data'] ?? [];
    assertTest("Mixed operation status is completed", ($mixedData['status'] ?? '') === 'completed');
    assertTest("Mixed operation processed all entities (simple + parent + variations)", ($mixedData['success_items'] ?? 0) >= (2 + count($variations)));
} else {
    echo "  [SKIP] No simple product found for mixed test\n";
}

// --- 6. Idempotency Check ---
echo "\n--- 6. Idempotency & Operation Audit ---\n";
$idemKey = 'idem-test-' . time() . '-' . rand(1000, 9999);
$idemReq1 = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [$varParentId]],
    'action' => ['type' => 'set_stock', 'value' => 50, 'target' => 'variations'],
    'idempotency_key' => $idemKey,
], $adminCookie, $storeHeader);
assertTest("Initial request with idempotency key succeeds (201)", $idemReq1['status'] === 201);
$firstOpId = $idemReq1['json']['data']['id'] ?? 0;

$idemReq2 = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [$varParentId]],
    'action' => ['type' => 'set_stock', 'value' => 50, 'target' => 'variations'],
    'idempotency_key' => $idemKey,
], $adminCookie, $storeHeader);
assertTest("Duplicate request with same idempotency key returns cached/original result", ($idemReq2['status'] === 200 || $idemReq2['status'] === 201));
$secondOpId = $idemReq2['json']['data']['id'] ?? 0;
assertTest("Duplicate request did not spawn redundant duplicate operation ID", $firstOpId === $secondOpId);

echo "\n========================================================\n";
echo " Phase 18 Variable Bulk Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
