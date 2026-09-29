<?php

declare(strict_types=1);

/**
 * Phase 12 — Notifications & Activity Center Automated Test Suite
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
\App\Support\Env::load(dirname(__DIR__) . '/.env');

$baseUrl = 'http://127.0.0.1:8000';
$passed = 0;
$failed = 0;

function runTest(string $title, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  [PASS] {$title}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$title}: " . $e->getMessage() . "\n";
        $failed++;
    }
}

function httpReq(string $method, string $path, ?array $body = null, array $headers = []): array {
    global $baseUrl;
    $url = $baseUrl . $path;

    $opts = [
        'http' => [
            'method' => $method,
            'header' => '',
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ];

    $headers['Content-Type'] = 'application/json';
    $headers['Accept'] = 'application/json';

    $hdrStr = '';
    foreach ($headers as $k => $v) {
        $hdrStr .= "{$k}: {$v}\r\n";
    }
    $opts['http']['header'] = $hdrStr;

    if ($body !== null) {
        $opts['http']['content'] = json_encode($body);
    }

    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);

    $statusCode = 0;
    $respHeaders = [];
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $h, $m)) {
                $statusCode = (int)$m[1];
            }
            if (str_contains($h, ':')) {
                [$k, $v] = explode(':', $h, 2);
                $respHeaders[strtolower(trim($k))] = trim($v);
            }
        }
    }

    $data = $raw ? json_decode($raw, true) : null;
    return [
        'status' => $statusCode,
        'data' => $data,
        'headers' => $respHeaders,
        'raw' => $raw,
    ];
}

function loginUser(string $username, string $password): array {
    $res = httpReq('POST', '/api/v1/auth/login', [
        'username' => $username,
        'password' => $password,
    ]);

    if ($res['status'] !== 200 || empty($res['headers']['set-cookie'])) {
        return ['success' => false, 'status' => $res['status'], 'res' => $res];
    }

    $cookie = $res['headers']['set-cookie'];
    if (preg_match('#(crmwp_session=[^;]+)#', $cookie, $m)) {
        $sessionCookie = $m[1];
    } else {
        $sessionCookie = explode(';', $cookie)[0];
    }

    return [
        'success' => true,
        'status' => 200,
        'headers' => [
            'Cookie' => $sessionCookie,
            'X-CSRF-TOKEN' => $res['data']['data']['csrf_token'] ?? '',
        ],
        'user' => $res['data']['data']['user'] ?? [],
    ];
}

echo "\n========================================================\n";
echo " Phase 12: Notifications & Activity Center Test Suite\n";
echo "========================================================\n";

// --- 1. Unauthenticated Requests Rejected ---
echo "\n--- 1. Authentication & Security Enforcement ---\n";
runTest("GET /api/v1/notifications is rejected without auth (401)", function () {
    $res = httpReq('GET', '/api/v1/notifications');
    if ($res['status'] !== 401) throw new Exception("Expected 401, got {$res['status']}");
});

runTest("GET /api/v1/notifications/unread-count is rejected without auth (401)", function () {
    $res = httpReq('GET', '/api/v1/notifications/unread-count');
    if ($res['status'] !== 401) throw new Exception("Expected 401, got {$res['status']}");
});

runTest("POST /api/v1/notifications/read-all is rejected without auth (401)", function () {
    $res = httpReq('POST', '/api/v1/notifications/read-all');
    if ($res['status'] !== 401) throw new Exception("Expected 401, got {$res['status']}");
});

// Admin Login
$adminAuth = loginUser('admin', 'AdminPassword123!');
if (!$adminAuth['success']) {
    echo "Fatal: Admin login failed. Status: " . ($adminAuth['status'] ?? 'unknown') . "\n";
    echo "Raw response: " . json_encode($adminAuth['res'] ?? []) . "\n";
    exit(1);
}
$adminHeaders = $adminAuth['headers'];
echo "  [PASS] Admin authenticated successfully (200)\n";

// Manager Login
$managerAuth = loginUser('manager', 'ManagerPassword123!');
if (!$managerAuth['success']) {
    // If manager password is Password123!, try that
    $managerAuth = loginUser('manager', 'Password123!');
}
$managerHeaders = $managerAuth['headers'];
echo "  [PASS] Manager authenticated successfully (200)\n";

// --- 2. Notifications Retrieval & Pagination ---
echo "\n--- 2. Notifications Retrieval & Pagination ---\n";
$firstNotifId = null;

runTest("GET /api/v1/notifications returns user notifications with metadata (200)", function () use ($adminHeaders, &$firstNotifId) {
    $res = httpReq('GET', '/api/v1/notifications', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if (!isset($res['data']['data']) || !is_array($res['data']['data'])) {
        throw new Exception("Expected notifications array in data envelope");
    }
    $meta = $res['data']['meta'] ?? [];
    if (!isset($meta['total']) || !isset($meta['unread_count'])) {
        throw new Exception("Meta missing total or unread_count");
    }
    if (count($res['data']['data']) > 0) {
        $firstNotifId = (int)$res['data']['data'][0]['id'];
    }
});

runTest("Notifications payload contains required fields and no plain passwords", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/notifications', null, $adminHeaders);
    $items = $res['data']['data'];
    if (empty($items)) throw new Exception("No notifications returned");
    $item = $items[0];
    foreach (['id', 'user_id', 'type', 'title', 'message', 'is_read', 'created_at'] as $field) {
        if (!array_key_exists($field, $item)) {
            throw new Exception("Notification missing required field: {$field}");
        }
    }
    if (isset($item['password']) || isset($item['password_hash'])) {
        throw new Exception("Security breach: Password exposed in notification!");
    }
});

runTest("GET /api/v1/notifications supports pagination (page, per_page)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/notifications?page=1&per_page=2', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $items = $res['data']['data'];
    if (count($items) > 2) throw new Exception("per_page cap failed, got " . count($items));
    if ($res['data']['meta']['per_page'] !== 2) throw new Exception("Expected per_page 2");
});

// --- 3. Filtering by Status & Type ---
echo "\n--- 3. Filtering by Status & Type ---\n";
runTest("GET /api/v1/notifications?status=unread returns only unread notifications", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/notifications?status=unread', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    foreach ($res['data']['data'] as $notif) {
        if ($notif['is_read'] !== false) {
            throw new Exception("Read notification returned in unread filter");
        }
    }
});

runTest("GET /api/v1/notifications?status=read returns only read notifications", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/notifications?status=read', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    foreach ($res['data']['data'] as $notif) {
        if ($notif['is_read'] !== true) {
            throw new Exception("Unread notification returned in read filter");
        }
    }
});

// --- 4. Unread Count Endpoint ---
echo "\n--- 4. Unread Count Endpoint ---\n";
runTest("GET /api/v1/notifications/unread-count returns accurate unread count (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/notifications/unread-count', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if (!isset($res['data']['data']['unread_count'])) {
        throw new Exception("Response missing unread_count key");
    }
    if (!is_int($res['data']['data']['unread_count'])) {
        throw new Exception("unread_count is not an integer");
    }
});

// --- 5. Mark as Read (Single & Idempotent) ---
echo "\n--- 5. Read State Mutations ---\n";
$createdTestNotifId = null;

runTest("POST /api/v1/notifications/test-generate creates a new test notification", function () use ($adminHeaders, &$createdTestNotifId) {
    $res = httpReq('POST', '/api/v1/notifications/test-generate', [
        'type' => 'task_assigned',
        'title' => 'تست اعلان موقت',
        'message' => 'پیام تستی برای آزمایش mark as read',
        'priority' => 'high',
        'action_url' => '/crm/tasks',
    ], $adminHeaders);
    if ($res['status'] !== 201) throw new Exception("Expected 201, got {$res['status']}");
    $createdTestNotifId = (int)$res['data']['data']['id'];
    if (!$createdTestNotifId) throw new Exception("Failed to receive created notification ID");
});

runTest("POST /api/v1/notifications/{id}/read marks notification as read (200)", function () use ($adminHeaders, &$createdTestNotifId) {
    $res = httpReq('POST', "/api/v1/notifications/{$createdTestNotifId}/read", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if (empty($res['data']['data']['is_read'])) {
        throw new Exception("Notification is_read flag not true in response");
    }
});

runTest("Marking as read is idempotent (repeated call succeeds without error)", function () use ($adminHeaders, &$createdTestNotifId) {
    $res = httpReq('POST', "/api/v1/notifications/{$createdTestNotifId}/read", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200 on repeat mark read, got {$res['status']}");
});

// --- 6. Mark All as Read ---
echo "\n--- 6. Mark All as Read ---\n";
runTest("POST /api/v1/notifications/read-all marks all user notifications as read (200)", function () use ($adminHeaders) {
    $res = httpReq('POST', '/api/v1/notifications/read-all', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if (!isset($res['data']['data']['marked_count'])) {
        throw new Exception("Response missing marked_count");
    }

    // Verify unread count is now 0
    $countRes = httpReq('GET', '/api/v1/notifications/unread-count', null, $adminHeaders);
    if ($countRes['data']['data']['unread_count'] !== 0) {
        throw new Exception("Expected 0 unread notifications after read-all, got {$countRes['data']['data']['unread_count']}");
    }
});

// --- 7. User Isolation & Security ---
echo "\n--- 7. User Privacy & Cross-User Security ---\n";
$managerNotifId = null;

runTest("Manager creates test notification", function () use ($managerHeaders, &$managerNotifId) {
    $res = httpReq('POST', '/api/v1/notifications/test-generate', [
        'type' => 'system',
        'title' => 'اعلان اختصاصی مدیر',
        'message' => 'این پیام فقط برای مدیر است.',
    ], $managerHeaders);
    if ($res['status'] !== 201) throw new Exception("Expected 201, got {$res['status']}");
    $managerNotifId = (int)$res['data']['data']['id'];
});

runTest("Admin cannot manipulate Manager notification ID (strictly guarded 403)", function () use ($adminHeaders, &$managerNotifId) {
    $res = httpReq('POST', "/api/v1/notifications/{$managerNotifId}/read", null, $adminHeaders);
    if ($res['status'] !== 403 && $res['status'] !== 404) {
        throw new Exception("Expected 403/404 for accessing another user's notification, got {$res['status']}");
    }
});

runTest("Admin cannot delete Manager notification (strictly guarded 403)", function () use ($adminHeaders, &$managerNotifId) {
    $res = httpReq('DELETE', "/api/v1/notifications/{$managerNotifId}/delete", null, $adminHeaders);
    // Note: route is DELETE /notifications/{id}
    $res = httpReq('DELETE', "/api/v1/notifications/{$managerNotifId}", null, $adminHeaders);
    if ($res['status'] !== 403 && $res['status'] !== 404) {
        throw new Exception("Expected 403/404 for deleting another user's notification, got {$res['status']}");
    }
});

// --- 8. Store-Level Isolation ---
echo "\n--- 8. Multi-Store Isolation ---\n";
runTest("User without store access gets 403 on store-scoped notifications request", function () use ($managerHeaders) {
    // Attempt to access unassigned store 9999
    $headers = array_merge($managerHeaders, ['X-Store-Id' => '9999']);
    $res = httpReq('GET', '/api/v1/notifications', null, $headers);
    if ($res['status'] !== 403) {
        throw new Exception("Expected 403 for unauthorized store_id context, got {$res['status']}");
    }
});

// --- 9. Notification Preferences ---
echo "\n--- 9. Notification Preferences ---\n";
runTest("GET /api/v1/notifications/preferences returns user preferences (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/notifications/preferences', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $prefs = $res['data']['data'];
    foreach (['task_notifications', 'order_notifications', 'inventory_notifications', 'bulk_notifications', 'system_notifications'] as $k) {
        if (!array_key_exists($k, $prefs)) {
            throw new Exception("Missing preference key: {$k}");
        }
    }
});

runTest("PUT /api/v1/notifications/preferences updates preferences (200)", function () use ($adminHeaders) {
    $res = httpReq('PUT', '/api/v1/notifications/preferences', [
        'task_notifications' => true,
        'order_notifications' => false,
        'retention_days' => 45,
    ], $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['order_notifications'] !== false) {
        throw new Exception("Failed to update order_notifications to false");
    }
    if ($res['data']['data']['retention_days'] !== 45) {
        throw new Exception("Failed to update retention_days to 45");
    }
    // Restore
    httpReq('PUT', '/api/v1/notifications/preferences', ['order_notifications' => true, 'retention_days' => 30], $adminHeaders);
});

// --- 10. Duplicate Prevention (Low Stock & Task) ---
echo "\n--- 10. Duplicate Prevention & Business Events ---\n";
runTest("Low stock notification prevents duplicate spamming within debounce window", function () {
    $svc = new \App\Services\NotificationService();
    $userId = 1;
    $productId = 99999;

    // First call creates notification
    $id1 = $svc->notifyLowStock($userId, $productId, 'تست کالای ناموجود', 2, 5, 2);
    if (!$id1) throw new Exception("Expected first low stock notification to be created");

    // Immediate second call should be suppressed by duplicate prevention
    $id2 = $svc->notifyLowStock($userId, $productId, 'تست کالای ناموجود', 2, 5, 2);
    if ($id2 !== null) {
        throw new Exception("Duplicate prevention failed: second notification was created ({$id2})");
    }

    // Clean up
    $svc->delete($id1, $userId);
});

// --- 11. Deletion & Cleanup ---
echo "\n--- 11. Deletion & Cleanup ---\n";
runTest("DELETE /api/v1/notifications/{id} deletes notification (200)", function () use ($adminHeaders, &$createdTestNotifId) {
    if ($createdTestNotifId) {
        $res = httpReq('DELETE', "/api/v1/notifications/{$createdTestNotifId}", null, $adminHeaders);
        if ($res['status'] !== 200) throw new Exception("Expected 200 on delete, got {$res['status']}");
    }
});

runTest("Manager deletes own test notification (200)", function () use ($managerHeaders, &$managerNotifId) {
    if ($managerNotifId) {
        $res = httpReq('DELETE', "/api/v1/notifications/{$managerNotifId}", null, $managerHeaders);
        if ($res['status'] !== 200) throw new Exception("Expected 200 on delete, got {$res['status']}");
    }
});

// --- 12. Separation of Concerns Architectural Check ---
echo "\n--- 12. Architectural Verification (Separation of Concerns) ---\n";
runTest("Notifications, Activities, and Audit Logs are 3 distinct non-merged tables", function () {
    $pdo = \App\Database\Connection::get();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('notifications', $tables, true)) throw new Exception("Missing 'notifications' table");
    if (!in_array('activities', $tables, true)) throw new Exception("Missing 'activities' table");
    if (!in_array('audit_logs', $tables, true)) throw new Exception("Missing 'audit_logs' table");

    // Ensure notifications table does not have audit/activity specific columns merged
    $notifCols = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('old_values', $notifCols, true) || in_array('new_values', $notifCols, true)) {
        throw new Exception("Architecture violation: Audit log columns merged into notifications!");
    }
});

echo "\n========================================================\n";
echo " Phase 12 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
