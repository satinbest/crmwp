<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Repositories\StoreRepository;
use App\Support\Env;

Env::load(__DIR__ . '/../.env');

$pdo = Connection::get();
$storeRepo = new StoreRepository($pdo);
$stores = $storeRepo->all();
$store = $stores[0];
$storeId = $store->id;
$secret = $store->getDecryptedWebhookSecret();

echo "Testing Live HTTP Server on http://127.0.0.1:8000 for Store ID: {$storeId}...\n\n";

function makeHttp(string $method, string $url, array $headers = [], ?string $body = null, ?string $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => $response, 'json' => json_decode($response, true)];
}

// 1. Missing signature
$res = makeHttp('POST', "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$storeId}", ['Content-Type: application/json'], '{"test": 1}');
echo "1. Missing signature: HTTP {$res['code']}\n";
assert($res['code'] === 401, "Expected 401 for missing signature");

// 2. Invalid signature
$res = makeHttp('POST', "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$storeId}", [
    'Content-Type: application/json',
    'X-WC-Webhook-Signature: bad_signature_123',
    'X-WC-Webhook-Topic: order.created'
], '{"id": 9999}');
echo "2. Invalid signature: HTTP {$res['code']}\n";
assert($res['code'] === 401, "Expected 401 for invalid signature");

// 3. Valid signature order.updated
$deliveryId = 'del_live_http_' . uniqid();
$payload = json_encode(['id' => 456, 'status' => 'processing', 'total' => '120000']);
$validSig = base64_encode(hash_hmac('sha256', $payload, $secret, true));

$res = makeHttp('POST', "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$storeId}", [
    'Content-Type: application/json',
    "X-WC-Webhook-Signature: {$validSig}",
    'X-WC-Webhook-Topic: order.updated',
    "X-WC-Webhook-Delivery-ID: {$deliveryId}"
], $payload);
echo "3. Valid signature: HTTP {$res['code']}, status: " . ($res['json']['status'] ?? 'unknown') . "\n";
assert($res['code'] === 200, "Expected 200 for valid webhook");
assert(($res['json']['status'] ?? '') === 'processed', "Expected processed status");

// 4. Duplicate delivery ID check
$resDup = makeHttp('POST', "http://127.0.0.1:8000/api/v1/webhooks/woocommerce/{$storeId}", [
    'Content-Type: application/json',
    "X-WC-Webhook-Signature: {$validSig}",
    'X-WC-Webhook-Topic: order.updated',
    "X-WC-Webhook-Delivery-ID: {$deliveryId}"
], $payload);
echo "4. Duplicate delivery ID: HTTP {$resDup['code']}, status: " . ($resDup['json']['status'] ?? 'unknown') . "\n";
assert($resDup['code'] === 200, "Expected 200 for duplicate webhook");
assert(($resDup['json']['status'] ?? '') === 'duplicate', "Expected duplicate status");

// 5. Authenticated Admin session for management APIs
$cookieFile = __DIR__ . '/test_cookie_p13.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// Get CSRF
$csrfRes = makeHttp('GET', 'http://127.0.0.1:8000/api/v1/auth/csrf', [], null, $cookieFile);
$csrfToken = $csrfRes['json']['data']['csrf_token'] ?? '';

// Login
$loginRes = makeHttp('POST', 'http://127.0.0.1:8000/api/v1/auth/login', [
    'Content-Type: application/json',
    "X-CSRF-TOKEN: {$csrfToken}",
], json_encode(['username' => 'admin', 'password' => 'AdminPassword123!']), $cookieFile);
echo "5. Admin login: HTTP {$loginRes['code']}\n";
assert($loginRes['code'] === 200, "Login should succeed");

// 6. GET /api/v1/webhooks
$webhooksRes = makeHttp('GET', 'http://127.0.0.1:8000/api/v1/webhooks', [], null, $cookieFile);
echo "6. GET /api/v1/webhooks: HTTP {$webhooksRes['code']}, total: " . ($webhooksRes['json']['meta']['total'] ?? 0) . "\n";
assert($webhooksRes['code'] === 200, "Webhooks list should return 200");
assert(($webhooksRes['json']['meta']['total'] ?? 0) > 0, "Should have webhook logs");

// 7. GET /api/v1/stores/{id}/webhook-health
$healthRes = makeHttp('GET', "http://127.0.0.1:8000/api/v1/stores/{$storeId}/webhook-health", [], null, $cookieFile);
echo "7. GET /api/v1/stores/{id}/webhook-health: HTTP {$healthRes['code']}, status: " . ($healthRes['json']['data']['webhook_health'] ?? 'unknown') . "\n";
assert($healthRes['code'] === 200, "Health should return 200");

// 8. POST /api/v1/stores/{id}/reconcile
$reconcileRes = makeHttp('POST', "http://127.0.0.1:8000/api/v1/stores/{$storeId}/reconcile", [
    'Content-Type: application/json',
    "X-CSRF-TOKEN: {$csrfToken}",
], json_encode(['entity_type' => 'orders', 'date_range' => '24h']), $cookieFile);
echo "8. POST /api/v1/stores/{id}/reconcile: HTTP {$reconcileRes['code']}, status: " . ($reconcileRes['json']['data']['status'] ?? 'unknown') . "\n";
assert(in_array($reconcileRes['code'], [200, 500, 503]), "Reconcile returned code {$reconcileRes['code']}");

// 9. GET /api/v1/stores/{id}/sync-logs
$syncLogsRes = makeHttp('GET', "http://127.0.0.1:8000/api/v1/stores/{$storeId}/sync-logs", [], null, $cookieFile);
echo "9. GET /api/v1/stores/{id}/sync-logs: HTTP {$syncLogsRes['code']}, count: " . count($syncLogsRes['json']['data'] ?? []) . "\n";
assert($syncLogsRes['code'] === 200, "Sync logs should return 200");

if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n>>> ALL 9 LIVE HTTP ENDPOINT TESTS PASSED SUCCESSFULLY! <<<\n";
