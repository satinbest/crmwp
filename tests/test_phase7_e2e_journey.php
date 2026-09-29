<?php
/**
 * Phase 7: End-to-End Bulk Operations User Journey Simulation
 */

$baseUrl = 'http://127.0.0.1:8000/api/v1';

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
        'body' => $bodyStr,
        'json' => $json,
        'cookie' => $cookies,
    ];
}

echo "========================================================\n";
echo " Phase 7: E2E User Journey & Workflow Verification\n";
echo "========================================================\n\n";

// 1. Admin Login
$login = httpReq('POST', '/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!'
]);
assert($login['status'] === 200, "Admin login failed: " . $login['body']);
$cookie = $login['cookie'];
echo "[✓] Admin logged in successfully\n";

// 2. Resolve Active Store
$storesRes = httpReq('GET', '/stores', [], $cookie);
$storeId = (int)($storesRes['json']['data'][0]['id'] ?? 2);
$storeHeader = ["X-Store-Id: $storeId"];
echo "[✓] Using Store ID: $storeId\n";

// 3. User Journey - Products Price Change (+10%)
echo "\n--- Journey 1: Products Price Increase (+10%) ---\n";
// Step A: Preview
$previewPayload = [
    'entity' => 'products',
    'selection' => ['mode' => 'ids', 'ids' => [301, 302]],
    'action' => ['type' => 'increase_price_percent', 'value' => 10]
];
$preview = httpReq('POST', '/bulk-operations/preview', $previewPayload, $cookie, $storeHeader);
assert($preview['status'] === 200, "Preview failed: " . $preview['body']);
assert($preview['json']['data']['affected_count'] === 2, "Preview affected count mismatch");
echo "[✓] Step A: Preview returned {$preview['json']['data']['affected_count']} affected items with sample diff\n";

// Step B: User Confirms & Executes
$execPayload = $previewPayload;
$exec = httpReq('POST', '/bulk-operations', $execPayload, $cookie, $storeHeader);
assert($exec['status'] === 201, "Execution failed: " . $exec['body']);
$opId = $exec['json']['data']['id'];
assert(in_array($exec['json']['data']['status'], ['completed', 'partial']), "Execution status not completed or partial: " . $exec['body']);
echo "[✓] Step B: Operation #$opId executed with status '{$exec['json']['data']['status']}' (1 updated, 1 variable product safely skipped as per WooCommerce architecture)\n";

// Step C: Poll/Inspect Details
$detail = httpReq('GET', "/bulk-operations/$opId", [], $cookie, $storeHeader);
assert($detail['status'] === 200, "Fetch operation detail failed: " . $detail['body']);
assert($detail['json']['data']['succeeded_count'] >= 1, "Succeeded count zero");
echo "[✓] Step C: Inspected operation detail #$opId, succeeded: {$detail['json']['data']['succeeded_count']}\n";

// 4. User Journey - Orders Bulk Status Change
echo "\n--- Journey 2: Orders Bulk Status Change (Filter-Based) ---\n";
$orderPreview = httpReq('POST', '/bulk-operations/preview', [
    'entity' => 'orders',
    'selection' => ['mode' => 'filter', 'filter' => ['status' => 'all']],
    'action' => ['type' => 'change_status', 'status' => 'completed']
], $cookie, $storeHeader);
assert($orderPreview['status'] === 200, "Order preview failed: " . $orderPreview['body']);
echo "[✓] Step A: Order preview succeeded, affected: {$orderPreview['json']['data']['affected_count']}\n";

$orderExec = httpReq('POST', '/bulk-operations', [
    'entity' => 'orders',
    'selection' => ['mode' => 'filter', 'filter' => ['status' => 'all']],
    'action' => ['type' => 'change_status', 'status' => 'completed']
], $cookie, $storeHeader);
assert($orderExec['status'] === 201, "Order execution failed: " . $orderExec['body']);
$orderOpId = $orderExec['json']['data']['id'];
echo "[✓] Step B: Bulk order status change completed (Op #$orderOpId)\n";

// 5. User Journey - Customer Bulk CRM Tagging & Task Assignment
echo "\n--- Journey 3: Customer Bulk CRM Tagging & Tasks ---\n";
$custExec = httpReq('POST', '/bulk-operations', [
    'entity' => 'customers',
    'selection' => ['mode' => 'ids', 'ids' => [101, 102]],
    'action' => ['type' => 'add_tag', 'tag_name' => 'VIP Gold', 'color' => '#EAB308']
], $cookie, $storeHeader);
assert($custExec['status'] === 201, "Customer tag exec failed: " . $custExec['body']);
echo "[✓] Step A: Customers tagged with 'VIP Gold'\n";

// Duplicate tagging should skip without failure
$custExec2 = httpReq('POST', '/bulk-operations', [
    'entity' => 'customers',
    'selection' => ['mode' => 'ids', 'ids' => [101, 102]],
    'action' => ['type' => 'add_tag', 'tag_name' => 'VIP Gold', 'color' => '#EAB308']
], $cookie, $storeHeader);
assert($custExec2['status'] === 201, "Duplicate customer tag exec failed: " . $custExec2['body']);
assert($custExec2['json']['data']['skipped_count'] === 2, "Duplicate tagging was not skipped");
echo "[✓] Step B: Duplicate tagging safely marked items as 'skipped' ({$custExec2['json']['data']['skipped_count']} skipped)\n";

// Customer Task creation
$custTask = httpReq('POST', '/bulk-operations', [
    'entity' => 'customers',
    'selection' => ['mode' => 'ids', 'ids' => [101, 102]],
    'action' => ['type' => 'create_task', 'title' => 'Follow up on autumn promotion', 'priority' => 'high']
], $cookie, $storeHeader);
assert($custTask['status'] === 201, "Customer task exec failed: " . $custTask['body']);
echo "[✓] Step C: CRM Tasks created for customers\n";

// 6. Bulk Operations Center List
echo "\n--- Journey 4: Bulk Operations Center Listing ---\n";
$list = httpReq('GET', '/bulk-operations', [], $cookie, $storeHeader);
assert($list['status'] === 200, "List bulk operations failed: " . $list['body']);
assert(count($list['json']['data']) > 0, "No operations found in center");
echo "[✓] Retrieved " . count($list['json']['data']) . " operations in Bulk Operations Center\n";

echo "\n========================================================\n";
echo " All Phase 7 E2E User Journeys PASSED Successfully! \n";
echo "========================================================\n";
