<?php

$passed = 0;
$failed = 0;

function assertApi(bool $condition, string $testName) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$testName}\n";
        $passed++;
    } else {
        echo " [FAIL] {$testName}\n";
        $failed++;
    }
}

echo "==============================================\n";
echo " Starting Phase 15 API & Security Test Suite\n";
echo "==============================================\n\n";

$baseUrl = 'http://127.0.0.1:8000/api/v1';

// 1. Authenticate as Admin
$loginPayload = json_encode(['username' => 'admin', 'password' => 'AdminPassword123!']);
$ch = curl_init("{$baseUrl}/auth/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $loginPayload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_HEADER => true,
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

// Extract cookie
preg_match('/Set-Cookie:\s*([^;]+)/mi', $headers, $matches);
$cookie = $matches[1] ?? '';
assertApi(!empty($cookie), "API Auth: Admin logged in and obtained session cookie");

// Helper for requests
function apiRequest(string $method, string $path, array $data = [], ?string $cookie = null, array $customHeaders = []) {
    global $baseUrl;
    $ch = curl_init("{$baseUrl}{$path}");
    $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $customHeaders);
    if ($cookie) {
        $headers[] = "Cookie: {$cookie}";
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (in_array(strtoupper($method), ['POST', 'PATCH', 'PUT']) && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'data' => json_decode($raw, true), 'raw' => $raw];
}

// 2. GET /automations with store context (Store A = 2)
$listRes = apiRequest('GET', '/automations', [], $cookie, ['X-Store-Id: 2']);
assertApi($listRes['code'] === 200, "API: GET /automations returns 200 OK");
assertApi(is_array($listRes['data']['data']) && count($listRes['data']['data']) >= 5, "API: Seeded 5 automations listed successfully");

$firstAutoId = (int)$listRes['data']['data'][0]['id'];

// 3. GET /automations/schema
$schemaRes = apiRequest('GET', '/automations/schema', [], $cookie, ['X-Store-Id: 2']);
assertApi($schemaRes['code'] === 200, "API: GET /automations/schema returns 200 OK");
assertApi(!empty($schemaRes['data']['data']['triggers']), "API: Schema returns supported triggers");
assertApi(!empty($schemaRes['data']['data']['actions']), "API: Schema returns available actions");

// 4. POST /automations/{id}/test (Dry Run)
$testRes = apiRequest('POST', "/automations/{$firstAutoId}/test", [], $cookie, ['X-Store-Id: 2']);
assertApi($testRes['code'] === 200, "API: POST /automations/{id}/test (Dry Run) returns 200 OK");
assertApi($testRes['data']['data']['would_run'] === true, "API: Dry run simulated would_run: true");

// 5. POST /automations/{id}/run (Manual Run)
$runRes = apiRequest('POST', "/automations/{$firstAutoId}/run", [], $cookie, ['X-Store-Id: 2']);
assertApi($runRes['code'] === 200, "API: POST /automations/{id}/run (Manual Run) returns 200 OK");
assertApi(!empty($runRes['data']['data']['run_id']), "API: Manual run created run ID");

// 6. GET /automations/{id}/runs (History)
$runsRes = apiRequest('GET', "/automations/{$firstAutoId}/runs", [], $cookie, ['X-Store-Id: 2']);
assertApi($runsRes['code'] === 200, "API: GET /automations/{id}/runs returns 200 OK");
assertApi(count($runsRes['data']['data']) >= 1, "API: Runs history contains execution records");

// 7. POST /automations (Create custom automation)
$newAutoPayload = [
    'name' => 'ارسال پیامک تشکر پس از ثبت سفارش',
    'description' => 'تست ساخت خودکار از طریق API',
    'status' => 'active',
    'trigger_type' => 'order.created',
    'conditions' => [
        'operator' => 'AND',
        'conditions' => [
            ['field' => 'order.total', 'operator' => 'greater_than', 'value' => 1000000]
        ]
    ],
    'actions' => [
        ['type' => 'create_notification', 'config' => ['title' => 'تشکر از سفارش', 'message' => 'سفارش {{order.number}} ثبت شد.']]
    ]
];
$createRes = apiRequest('POST', '/automations', $newAutoPayload, $cookie, ['X-Store-Id: 2']);
assertApi($createRes['code'] === 201, "API: POST /automations created new automation (201 Created)");
$createdId = (int)($createRes['data']['data']['id'] ?? 0);

// 8. PATCH /automations/{id} (Update)
$updateRes = apiRequest('PATCH', "/automations/{$createdId}", ['name' => 'ارسال پیامک تشکر (ویرایش‌شده)'], $cookie, ['X-Store-Id: 2']);
assertApi($updateRes['code'] === 200 && $updateRes['data']['data']['name'] === 'ارسال پیامک تشکر (ویرایش‌شده)', "API: PATCH /automations/{id} updated successfully");

// 9. Disable / Enable Toggle
$disRes = apiRequest('POST', "/automations/{$createdId}/disable", [], $cookie, ['X-Store-Id: 2']);
assertApi($disRes['code'] === 200 && $disRes['data']['data']['status'] === 'inactive', "API: POST /automations/{id}/disable changed status to inactive");

$enRes = apiRequest('POST', "/automations/{$createdId}/enable", [], $cookie, ['X-Store-Id: 2']);
assertApi($enRes['code'] === 200 && $enRes['data']['data']['status'] === 'active', "API: POST /automations/{id}/enable changed status to active");

// 10. Financial Safety Security: Reject automatic refund action
$dangerPayload = [
    'name' => 'خطرناک استرداد خودکار',
    'trigger_type' => 'order.created',
    'actions' => [
        ['type' => 'change_order_status', 'config' => ['new_status' => 'refunded']]
    ]
];
$dangerRes = apiRequest('POST', '/automations', $dangerPayload, $cookie, ['X-Store-Id: 2']);
assertApi($dangerRes['code'] === 422, "Security & Financial Safety: Reject dangerous refund automation with 422 Unprocessable Entity");

// 11. DELETE /automations/{id}
$delRes = apiRequest('DELETE', "/automations/{$createdId}", [], $cookie, ['X-Store-Id: 2']);
assertApi($delRes['code'] === 200, "API: DELETE /automations/{id} deleted successfully");

// 12. Anti-IDOR Security: Authenticate as user with restricted store access
// Viewer (user_id=24) has access ONLY to store 2 and 10, NOT store 9
$viewerLogin = json_encode(['username' => 'viewer', 'password' => 'ViewerPassword123!']);
$ch = curl_init("{$baseUrl}/auth/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $viewerLogin,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_HEADER => true,
]);
$respViewer = curl_exec($ch);
$hSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$hStr = substr($respViewer, 0, $hSize);
curl_close($ch);
preg_match('/Set-Cookie:\s*([^;]+)/mi', $hStr, $vMatches);
$viewerCookie = $vMatches[1] ?? '';

// Try to access Store 9 (Unauthorized for Viewer) -> Must return 403 Forbidden
$idorRes = apiRequest('GET', '/automations', [], $viewerCookie, ['X-Store-Id: 9']);
assertApi($idorRes['code'] === 403, "Security Anti-IDOR: Unauthorized store access returns 403 Forbidden");

echo "\n==============================================\n";
echo " API Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "==============================================\n";

if ($failed > 0) {
    exit(1);
}
