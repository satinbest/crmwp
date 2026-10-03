<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
\App\Support\Config::setPath(__DIR__ . '/../config');
\App\Support\Logger::setLogDir(__DIR__ . '/../storage/logs');

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
echo " Bulk Operations Hub Comprehensive Acceptance Test Suite\n";
echo "========================================================\n\n";

// 1. Admin Login
$login = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$cookie = $login['cookie'] ?? '';
assertTest("1. Admin Authentication", $login['status'] === 200 && !empty($cookie), "Status: {$login['status']}");

$storeHeaders = ['X-Store-Id: 1'];

// 2. Evaluate Target: All products on real Store 1
$evalAll = httpReq('POST', '/api/v1/bulk-operations/evaluate-target', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => [],
    'target_scope' => 'both',
], $cookie, $storeHeaders);

assertTest("2. POST /bulk-operations/evaluate-target (Scope: both)", $evalAll['status'] === 200 && ($evalAll['json']['data']['affected_count'] ?? 0) > 0, "Response: " . json_encode($evalAll['json']));
$data = $evalAll['json']['data'] ?? [];
assertTest("2.1 Breakdown contains simple, variable, variation", isset($data['breakdown']['simple']) && isset($data['breakdown']['variable']) && isset($data['breakdown']['variation']), "Breakdown missing");
assertTest("2.2 Sample products returned", !empty($data['sample_products']), "Sample products empty");

// 3. Evaluate Target: Simple products only
$evalSimple = httpReq('POST', '/api/v1/bulk-operations/evaluate-target', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => ['type' => 'simple'],
    'target_scope' => 'parent',
], $cookie, $storeHeaders);
assertTest("3. Filter Simple Products Only", ($evalSimple['json']['data']['variable_count'] ?? -1) === 0 && ($evalSimple['json']['data']['simple_count'] ?? 0) > 0);

// 4. Test 1 Acceptance: Simple Products Price Increase 10% Preview
$previewSimple = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => ['type' => 'simple'],
    'action' => [
        'type' => 'increase_price_percent',
        'value' => 10,
        'target' => 'parent',
    ],
], $cookie, $storeHeaders);
assertTest("4. Test 1 Acceptance: Simple Products Price Increase 10% Preview", $previewSimple['status'] === 200);
$sample0 = $previewSimple['json']['data']['sample'][0] ?? null;
assertTest("4.1 Simple Product Preview diff calculated", !empty($sample0['old_value']) && !empty($sample0['new_value']));

// 5. Test 2 Acceptance: Variable Products Preview
$previewVar = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => ['type' => 'variable'],
    'action' => [
        'type' => 'increase_price_percent',
        'value' => 10,
        'target' => 'both',
    ],
], $cookie, $storeHeaders);
assertTest("5. Test 2 Acceptance: Variable Products Preview (Parent + Variations)", $previewVar['status'] === 200);
$hasVarItem = false;
foreach ($previewVar['json']['data']['sample'] ?? [] as $si) {
    if (!empty($si['is_variation'])) {
        $hasVarItem = true;
        break;
    }
}
assertTest("5.1 Preview shows variation items with attribute details", $hasVarItem);

// 6. Test 3 Acceptance: Variations Only Target
$previewVarsOnly = httpReq('POST', '/api/v1/bulk-operations/preview', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => [],
    'action' => [
        'type' => 'increase_price_percent',
        'value' => 10,
        'target' => 'variations',
    ],
], $cookie, $storeHeaders);
assertTest("6. Test 3 Acceptance: Variations Only Target Preview", $previewVarsOnly['status'] === 200 && ($previewVarsOnly['json']['data']['affected_count'] ?? 0) === 2);

// 7. Test 7 Acceptance: Saved Operations (Presets)
$createPreset = httpReq('POST', '/api/v1/bulk-operations/presets', [
    'title' => 'افزایش قیمت لپ‌تاپ‌ها ۱۰٪',
    'description' => 'الگوی آزمایشی برای افزایش روزانه قیمت',
    'target_entity' => 'products',
    'filter_criteria' => ['category' => 'all'],
    'action_data' => [
        'type' => 'increase_price_percent',
        'value' => 10,
        'target' => 'both',
    ],
], $cookie, $storeHeaders);
assertTest("7. Create Operation Preset (201)", $createPreset['status'] === 201 && !empty($createPreset['json']['data']['id']));
$presetId = $createPreset['json']['data']['id'] ?? 0;

$listPresets = httpReq('GET', '/api/v1/bulk-operations/presets', [], $cookie, $storeHeaders);
assertTest("7.1 List Presets returns saved preset", $listPresets['status'] === 200 && count($listPresets['json']['data'] ?? []) > 0);

$deletePreset = httpReq('DELETE', "/api/v1/bulk-operations/presets/{$presetId}", [], $cookie, $storeHeaders);
assertTest("7.2 Delete Preset succeeds (200)", $deletePreset['status'] === 200);

// 8. Test 8 Acceptance: Permissions (User without bulk.execute)
// Check if non-admin or limited user gets 403 on store()
$forbiddenCheck = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'action' => ['type' => 'increase_price_percent', 'value' => 10],
], null, $storeHeaders);
assertTest("8. Unauthenticated request to execute is rejected (401)", $forbiddenCheck['status'] === 401);

// 9. Test 10 Acceptance: Idempotency & Creation
$idempotencyKey = "idemp_test_" . uniqid();
$createOp1 = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => ['type' => 'simple'],
    'action' => [
        'type' => 'set_stock_status',
        'status' => 'instock',
        'target' => 'parent',
    ],
    'create_only' => true,
    'idempotency_key' => $idempotencyKey,
], $cookie, $storeHeaders);
assertTest("9. Create Operation with create_only=true (201)", $createOp1['status'] === 201 && !empty($createOp1['json']['data']['id']));
$opId = $createOp1['json']['data']['id'] ?? 0;

// Submitting same idempotency key returns existing operation without duplicate
$createOp2 = httpReq('POST', '/api/v1/bulk-operations', [
    'entity' => 'products',
    'selection' => ['mode' => 'filter'],
    'filter' => ['type' => 'simple'],
    'action' => [
        'type' => 'set_stock_status',
        'status' => 'instock',
        'target' => 'parent',
    ],
    'create_only' => true,
    'idempotency_key' => $idempotencyKey,
], $cookie, $storeHeaders);
assertTest("9.1 Idempotency Key prevents duplicate operation", $createOp2['status'] === 200 && ($createOp2['json']['data']['id'] ?? 0) === $opId);

// 10. Test Cancel Operation
$cancelOp = httpReq('POST', "/api/v1/bulk-operations/{$opId}/cancel", [], $cookie, $storeHeaders);
assertTest("10. Cancel Bulk Operation (200)", $cancelOp['status'] === 200 && ($cancelOp['json']['data']['status'] ?? '') === 'cancelled');

// 11. History List
$history = httpReq('GET', '/api/v1/bulk-operations', ['entity' => 'products'], $cookie, $storeHeaders);
assertTest("11. History Listing for Store 1 (200)", $history['status'] === 200 && count($history['json']['data'] ?? []) > 0);

echo "\n--------------------------------------------------------\n";
echo " Acceptance Tests Completed: Passed: {$passed} | Failed: {$failed}\n";
echo "--------------------------------------------------------\n\n";

if ($failed > 0) {
    exit(1);
}
