<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Phase 2 Automated End-to-End Test Suite
 * Tests all 17 required scenarios for WooCommerce Connection & Integration Core
 */

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

function httpReq(string $method, string $path, array $data = [], ?string $cookie = null): array {
    global $baseUrl;
    $url = $baseUrl . $path;
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ];

    if (!empty($data) || in_array($method, ['POST', 'PUT', 'PATCH'])) {
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
        'body' => $json,
        'raw_body' => $bodyStr,
        'cookies' => $cookies,
    ];
}

echo "========================================================\n";
echo " Phase 2: WooCommerce Connection & Integration Core Tests\n";
echo "========================================================\n\n";

// Login as Admin to get authenticated session
$login = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
$adminCookie = $login['cookies'];
assertTest('Admin authenticated for Phase 2 tests', $login['status'] === 200);

echo "\n--- 1. Valid Credentials & Connection Test ---\n";
$validTest = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/standard',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);

assertTest('Valid credentials return 200 OK', $validTest['status'] === 200);
assertTest('Connected is true', ($validTest['body']['data']['connected'] ?? false) === true);
assertTest('WooCommerce version detected (8.6.1)', ($validTest['body']['data']['woocommerce_version'] ?? '') === '8.6.1');
assertTest('WordPress version detected (6.4.3)', ($validTest['body']['data']['wordpress_version'] ?? '') === '6.4.3');

echo "\n--- 2. Invalid Credentials (Authentication Failure) ---\n";
$invalidTest = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/standard',
    'consumer_key' => 'ck_wrong_key',
    'consumer_secret' => 'cs_wrong_secret',
], $adminCookie);

assertTest('Invalid credentials return 401 Unauthorized', $invalidTest['status'] === 401);
assertTest('Clear Persian error message returned', str_contains($invalidTest['body']['error']['message'] ?? '', 'احراز هویت'));

echo "\n--- 3. Invalid URL Validation ---\n";
$invalidUrlTest1 = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'https://example.com/wp-admin',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);

assertTest('URL containing /wp-admin is rejected with 422', $invalidUrlTest1['status'] === 422);
assertTest('Rejection reason mentions wp-admin', str_contains($invalidUrlTest1['body']['error']['message'] ?? '', 'wp-admin'));

$invalidUrlTest2 = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'https://example.com/wp-json/wc/v3',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);

assertTest('URL containing /wp-json is rejected with 422', $invalidUrlTest2['status'] === 422);

echo "\n--- 4. Unreachable Store ---\n";
$unreachableTest = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:54321', // Port not open
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);

assertTest('Unreachable server returns 503 Service Unavailable', $unreachableTest['status'] === 503);
assertTest('Error code is CONNECTION_FAILED', ($unreachableTest['body']['error']['code'] ?? '') === 'CONNECTION_FAILED');

echo "\n--- 5. Timeout Handling ---\n";
// Create a direct client test with 1 second timeout against /timeout
$timeoutClient = new \App\Integrations\WooCommerce\WooCommerceClient('http://127.0.0.1:8001/timeout', 'ck_valid_test_key', 'cs_valid_test_secret', 1, 0);
try {
    $timeoutClient->get('/system_status');
    assertTest('Timeout test did not catch exception', false);
} catch (\App\Integrations\WooCommerce\WooCommerceApiException $e) {
    assertTest('Timeout is caught and handled', true);
    assertTest('Timeout returns CONNECTION_FAILED code', $e->getErrorCode() === 'CONNECTION_FAILED');
    assertTest('Timeout Persian message mentions Timeout', str_contains($e->getMessage(), 'Timeout') || str_contains($e->getMessage(), 'مهلت'));
}

echo "\n--- 6. HTTP 401 Handling ---\n";
$res401 = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/unauthorized',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);
assertTest('HTTP 401 returns 401 status', $res401['status'] === 401);

echo "\n--- 7. HTTP 403 Handling ---\n";
$res403 = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/forbidden',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);
assertTest('HTTP 403 returns 403 status', $res403['status'] === 403);
assertTest('HTTP 403 Persian message mentions permissions', str_contains($res403['body']['error']['message'] ?? '', 'دسترسی') || str_contains($res403['body']['error']['message'] ?? '', 'مجوز'));

echo "\n--- 8. HTTP 429 Rate Limiting & Retries ---\n";
$res429 = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/ratelimited',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);
assertTest('HTTP 429 rate limit error handled (status 429)', $res429['status'] === 429);
assertTest('HTTP 429 Persian message mentions rate limit', str_contains($res429['body']['error']['message'] ?? '', 'بیش از حد'));

echo "\n--- 9. HTTP 502 Bad Gateway Handling ---\n";
$res502 = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/badgateway',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);
assertTest('HTTP 502 returns 502 status', $res502['status'] === 502);

echo "\n--- 10. HTTP 503 Service Unavailable Handling ---\n";
$res503 = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/unavailable',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);
assertTest('HTTP 503 returns 503 status', $res503['status'] === 503);

echo "\n--- 11. Capability Detection & Dynamic Statuses ---\n";
assertTest('Capabilities contain REST API availability', isset($validTest['body']['data']['capabilities']['rest_api_available']));
assertTest('Capabilities contain currency & timezone', ($validTest['body']['data']['capabilities']['currency'] ?? '') === 'IRR');
assertTest('Capabilities contain webhooks support', ($validTest['body']['data']['capabilities']['webhooks_supported'] ?? false) === true);
$customStatuses = $validTest['body']['data']['capabilities']['custom_order_statuses'] ?? [];
assertTest('Dynamic custom statuses detected from WooCommerce', count($customStatuses) > 0);
assertTest('Includes custom status "custom-prep"', in_array('custom-prep', array_column($customStatuses, 'slug'), true));

echo "\n--- 12. HPOS Detection (HPOS-Enabled vs Legacy) ---\n";
assertTest('Standard store has HPOS enabled', ($validTest['body']['data']['hpos_enabled'] ?? false) === true);

$legacyTest = httpReq('POST', '/api/v1/stores/test-connection', [
    'url' => 'http://127.0.0.1:8001/legacy',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);

assertTest('Legacy store connection test succeeds', $legacyTest['status'] === 200);
assertTest('Legacy store has HPOS disabled', ($legacyTest['body']['data']['hpos_enabled'] ?? true) === false);
assertTest('Legacy store reports WC 7.9.0', ($legacyTest['body']['data']['woocommerce_version'] ?? '') === '7.9.0');

echo "\n--- 13. Store Creation & Editing ---\n";
$createRes = httpReq('POST', '/api/v1/stores', [
    'name' => 'فروشگاه تستی استاندارد',
    'url' => 'http://127.0.0.1:8001/standard',
    'consumer_key' => 'ck_valid_test_key',
    'consumer_secret' => 'cs_valid_test_secret',
], $adminCookie);

assertTest('Store creation returns 201 Created', $createRes['status'] === 201);
$createdStore = $createRes['body']['data'] ?? [];
$storeId = (int)($createdStore['id'] ?? 0);
assertTest('Created store has valid ID', $storeId > 0);
assertTest('Created store status is active', ($createdStore['status'] ?? '') === 'active');
assertTest('Created store has detected HPOS', ($createdStore['hpos_enabled'] ?? false) === true);

// Edit store name
$editRes = httpReq('PATCH', "/api/v1/stores/{$storeId}", [
    'name' => 'فروشگاه ویرایش‌شده تهران',
], $adminCookie);

assertTest('Store edit returns 200 OK', $editRes['status'] === 200);
assertTest('Store name successfully updated', ($editRes['body']['data']['name'] ?? '') === 'فروشگاه ویرایش‌شده تهران');

echo "\n--- 14. Store Capabilities Endpoint & Saved Test ---\n";
$capsRes = httpReq('GET', "/api/v1/stores/{$storeId}/capabilities", [], $adminCookie);
assertTest('GET /stores/{id}/capabilities returns 200 OK', $capsRes['status'] === 200);
assertTest('Saved store test /stores/{id}/test returns 200 OK', httpReq('POST', "/api/v1/stores/{$storeId}/test", [], $adminCookie)['status'] === 200);

echo "\n--- 15. Credential Masking in Responses ---\n";
$getStore = httpReq('GET', "/api/v1/stores/{$storeId}", [], $adminCookie);
$storeData = $getStore['body']['data'] ?? [];
assertTest('Consumer key is masked in response (ck_***)', str_starts_with($storeData['consumer_key_masked'] ?? '', 'ck_***'));
assertTest('Consumer secret is masked (bullets)', ($storeData['consumer_secret_masked'] ?? '') === '••••••••••••••••');

echo "\n--- 16. No Credential Leakage in API Responses ---\n";
assertTest('consumer_secret raw field NOT in API response', !isset($storeData['consumer_secret']));
assertTest('consumer_secret_encrypted NOT in API response', !isset($storeData['consumer_secret_encrypted']));
assertTest('consumer_key raw NOT in API response', !isset($storeData['consumer_key']));
assertTest('consumer_key_encrypted NOT in API response', !isset($storeData['consumer_key_encrypted']));

echo "\n--- 17. Store Deletion ---\n";
$deleteRes = httpReq('DELETE', "/api/v1/stores/{$storeId}", [], $adminCookie);
assertTest('Store deletion returns 200 OK', $deleteRes['status'] === 200);

$getDeleted = httpReq('GET', "/api/v1/stores/{$storeId}", [], $adminCookie);
assertTest('Deleted store returns 404 Not Found', $getDeleted['status'] === 404);

echo "\n--- 18. No Credential Leakage in Application Logs ---\n";
$logFiles = glob(dirname(__DIR__) . '/storage/logs/app-*.log');
$secretFoundInLogs = false;
foreach ($logFiles as $logFile) {
    $content = file_get_contents($logFile);
    if (str_contains($content, 'cs_valid_test_secret') || str_contains($content, 'cs_wrong_secret')) {
        $secretFoundInLogs = true;
        break;
    }
}
assertTest('Application logs do NOT contain raw secrets', !$secretFoundInLogs);

echo "\n--- 19. RBAC Permission Enforcement on Store Endpoints ---\n";
// Login as Manager (who does NOT have stores.manage)
$mgrLogin = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'manager',
    'password' => 'ManagerPassword123!',
]);
$mgrCookie = $mgrLogin['cookies'];

// Manager can view stores
$mgrViewStores = httpReq('GET', '/api/v1/stores', [], $mgrCookie);
assertTest('Manager can view stores (HTTP 200)', $mgrViewStores['status'] === 200);

// Manager CANNOT create stores (requires stores.manage)
$mgrCreateStore = httpReq('POST', '/api/v1/stores', [
    'name' => 'فروشگاه غیرمجاز',
    'url' => 'http://127.0.0.1:8001/standard',
    'consumer_key' => 'ck_test',
    'consumer_secret' => 'cs_test',
], $mgrCookie);
assertTest('Manager cannot create stores (HTTP 403 Forbidden)', $mgrCreateStore['status'] === 403);
assertTest('Error code is FORBIDDEN', ($mgrCreateStore['body']['error']['code'] ?? '') === 'FORBIDDEN');

echo "\n========================================================\n";
echo " Phase 2 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
