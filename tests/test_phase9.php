<?php

declare(strict_types=1);

/**
 * Phase 9 — CRM Workspace Automated Test Suite
 */

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
        'headers' => $respHeaders,
        'data' => $data,
        'raw' => $raw,
    ];
}

echo "\n========================================================\n";
echo " Phase 9: CRM Workspace Automated Tests\n";
echo "========================================================\n\n";

// --- 1. Authentication & Security ---
echo "--- 1. Authentication & Security ---\n";
runTest("Unauthenticated request to CRM summary is rejected (401)", function () {
    $res = httpReq('GET', '/api/v1/crm/summary');
    if ($res['status'] !== 401) {
        throw new Exception("Expected 401, got {$res['status']}");
    }
});

// Login as admin
$loginRes = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);

if ($loginRes['status'] !== 200 || empty($loginRes['headers']['set-cookie'])) {
    echo "Fatal: Could not login as admin. Response:\n" . json_encode($loginRes) . "\n";
    exit(1);
}

$cookie = $loginRes['headers']['set-cookie'];
if (preg_match('#(crmwp_session=[^;]+)#', $cookie, $m)) {
    $sessionCookie = $m[1];
} else {
    $sessionCookie = explode(';', $cookie)[0];
}

$adminHeaders = [
    'Cookie' => $sessionCookie,
    'X-CSRF-TOKEN' => $loginRes['data']['data']['csrf_token'] ?? '',
];

runTest("Admin login succeeds (200)", function () use ($loginRes) {
    if ($loginRes['status'] !== 200) {
        throw new Exception("Expected 200");
    }
});

// Find active store ID
$storesRes = httpReq('GET', '/api/v1/stores', null, $adminHeaders);
$storeId = $storesRes['data']['data'][0]['id'] ?? 2;
$adminHeaders['X-Store-Id'] = (string)$storeId;

runTest("Resolved store ID {$storeId}", function () use ($storeId) {
    if ($storeId <= 0) throw new Exception("No active store");
});

runTest("Cross-store or invalid store rejected (404)", function () use ($adminHeaders) {
    $badHeaders = array_merge($adminHeaders, ['X-Store-Id' => '999999']);
    $res = httpReq('GET', '/api/v1/crm/summary', null, $badHeaders);
    if ($res['status'] !== 404) {
        throw new Exception("Expected 404, got {$res['status']}");
    }
});

// --- 2. CRM Summary & Dashboard KPIs ---
echo "\n--- 2. CRM Dashboard Summary & KPIs ---\n";
runTest("GET /api/v1/crm/summary returns 200 OK", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/crm/summary', null, $adminHeaders);
    if ($res['status'] !== 200) {
        throw new Exception("Expected 200, got {$res['status']}");
    }
});

runTest("Summary contains KPI metrics", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/crm/summary', null, $adminHeaders);
    $kpis = $res['data']['data']['kpis'] ?? null;
    if (!$kpis) throw new Exception("Missing kpis in summary");
    if (!isset($kpis['open_tasks'])) throw new Exception("Missing open_tasks");
    if (!isset($kpis['overdue_tasks'])) throw new Exception("Missing overdue_tasks");
    if (!isset($kpis['due_today'])) throw new Exception("Missing due_today");
    if (!isset($kpis['upcoming_tasks'])) throw new Exception("Missing upcoming_tasks");
    if (!isset($kpis['segments_count'])) throw new Exception("Missing segments_count");
    if (!isset($kpis['tags_count'])) throw new Exception("Missing tags_count");
});

runTest("Summary contains recent activities and attention tasks", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/crm/summary', null, $adminHeaders);
    $data = $res['data']['data'] ?? [];
    if (!isset($data['tasks_needing_attention'])) throw new Exception("Missing tasks_needing_attention");
    if (!isset($data['recent_activities'])) throw new Exception("Missing recent_activities");
    if (!isset($data['customers_requiring_follow_up'])) throw new Exception("Missing customers_requiring_follow_up");
});

// --- 3. Dynamic Segments ---
echo "\n--- 3. Dynamic Customer Segments ---\n";
$createdSegmentId = null;

runTest("POST /api/v1/segments creates dynamic segment (201)", function () use ($adminHeaders, &$createdSegmentId) {
    $payload = [
        'name' => 'خریداران وفادار تست فاز ۹',
        'description' => 'مشتریانی که بیش از ۲ سفارش ثبت کرده‌اند',
        'rules' => [
            'combinator' => 'AND',
            'rules' => [
                [
                    'field' => 'order_count',
                    'operator' => 'greater_than',
                    'value' => 1,
                ],
            ],
        ],
    ];
    $res = httpReq('POST', '/api/v1/segments', $payload, $adminHeaders);
    if ($res['status'] !== 201) {
        throw new Exception("Expected 201, got {$res['status']} raw: " . $res['raw']);
    }
    $createdSegmentId = (int)$res['data']['data']['id'];
    if ($createdSegmentId <= 0) throw new Exception("Invalid segment ID");
});

runTest("GET /api/v1/segments returns segments list (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/segments', null, $adminHeaders);
    if ($res['status'] !== 200 || !is_array($res['data']['data'])) {
        throw new Exception("Expected 200 with array");
    }
});

runTest("GET /api/v1/segments/{id} returns segment details (200)", function () use ($adminHeaders, $createdSegmentId) {
    $res = httpReq('GET', "/api/v1/segments/{$createdSegmentId}", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['name'] !== 'خریداران وفادار تست فاز ۹') {
        throw new Exception("Segment name mismatch");
    }
});

runTest("PATCH /api/v1/segments/{id} updates segment (200)", function () use ($adminHeaders, $createdSegmentId) {
    $payload = ['name' => 'خریداران VIP بروزرسانی‌شده'];
    $res = httpReq('PATCH', "/api/v1/segments/{$createdSegmentId}", $payload, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['name'] !== 'خریداران VIP بروزرسانی‌شده') {
        throw new Exception("Updated name mismatch");
    }
});

runTest("POST /api/v1/segments/preview evaluates rules without customer duplication (200)", function () use ($adminHeaders) {
    $payload = [
        'rules' => [
            'combinator' => 'AND',
            'rules' => [
                ['field' => 'order_count', 'operator' => 'greater_equal', 'value' => 0],
            ],
        ],
    ];
    $res = httpReq('POST', '/api/v1/segments/preview', $payload, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $data = $res['data']['data'];
    if (!isset($data['total_matching'])) throw new Exception("Missing total_matching");
    if (!isset($data['sample_customers'])) throw new Exception("Missing sample_customers");
});

// --- 4. Tags Management ---
echo "\n--- 4. Customer Tags ---\n";
$createdTagId = null;

runTest("POST /api/v1/tags creates new tag (201)", function () use ($adminHeaders, &$createdTagId) {
    $payload = [
        'name' => 'VIP_Phase9_Tag_' . rand(100, 999),
        'color' => '#10B981',
    ];
    $res = httpReq('POST', '/api/v1/tags', $payload, $adminHeaders);
    if ($res['status'] !== 201) throw new Exception("Expected 201, got {$res['status']}");
    $createdTagId = (int)$res['data']['data']['id'];
    if ($createdTagId <= 0) throw new Exception("Invalid tag ID");
});

runTest("GET /api/v1/tags returns tags list (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/tags', null, $adminHeaders);
    if ($res['status'] !== 200 || !is_array($res['data']['data'])) {
        throw new Exception("Expected 200 with tags list");
    }
});

runTest("PATCH /api/v1/tags/{id} updates tag (200)", function () use ($adminHeaders, $createdTagId) {
    $payload = ['color' => '#EF4444'];
    $res = httpReq('PATCH', "/api/v1/tags/{$createdTagId}", $payload, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['color'] !== '#EF4444') throw new Exception("Color mismatch");
});

// --- 5. Tasks Management ---
echo "\n--- 5. Tasks Management ---\n";
$createdTaskId = null;

runTest("POST /api/v1/tasks creates task (201)", function () use ($adminHeaders, &$createdTaskId) {
    $payload = [
        'title' => 'پیگیری سفارش و رضایت مشتری فاز ۹',
        'description' => 'بررسی تحویل کالا و ارائه کد تخفیف خرید بعدی',
        'priority' => 'high',
        'status' => 'pending',
        'due_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
        'customer_id' => 101,
    ];
    $res = httpReq('POST', '/api/v1/tasks', $payload, $adminHeaders);
    if ($res['status'] !== 201) throw new Exception("Expected 201, got {$res['status']} raw: " . $res['raw']);
    $createdTaskId = (int)$res['data']['data']['id'];
    if ($createdTaskId <= 0) throw new Exception("Invalid task ID");
});

runTest("GET /api/v1/tasks returns tasks list (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/tasks', null, $adminHeaders);
    if ($res['status'] !== 200 || !is_array($res['data']['data'])) {
        throw new Exception("Expected 200 with tasks array");
    }
});

runTest("GET /api/v1/tasks with view filters succeeds (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/tasks?view=upcoming', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
});

runTest("GET /api/v1/tasks/{id} returns task details (200)", function () use ($adminHeaders, $createdTaskId) {
    $res = httpReq('GET', "/api/v1/tasks/{$createdTaskId}", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['priority'] !== 'high') throw new Exception("Priority mismatch");
});

runTest("PATCH /api/v1/tasks/{id} updates task (200)", function () use ($adminHeaders, $createdTaskId) {
    $payload = ['priority' => 'urgent'];
    $res = httpReq('PATCH', "/api/v1/tasks/{$createdTaskId}", $payload, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['priority'] !== 'urgent') throw new Exception("Priority update mismatch");
});

runTest("POST /api/v1/tasks/{id}/complete marks task as completed (200)", function () use ($adminHeaders, $createdTaskId) {
    $res = httpReq('POST', "/api/v1/tasks/{$createdTaskId}/complete", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['status'] !== 'completed') throw new Exception("Status not completed");
});

runTest("POST /api/v1/tasks/{id}/reopen reopens completed task (200)", function () use ($adminHeaders, $createdTaskId) {
    $res = httpReq('POST', "/api/v1/tasks/{$createdTaskId}/reopen", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    if ($res['data']['data']['status'] !== 'in_progress') throw new Exception("Status not in_progress");
});

// --- 6. Activities Timeline ---
echo "\n--- 6. Activities Timeline ---\n";
runTest("GET /api/v1/activities returns timeline items (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/activities', null, $adminHeaders);
    if ($res['status'] !== 200 || !is_array($res['data']['data'])) {
        throw new Exception("Expected 200 with activities array");
    }
});

// --- 7. Global Unified Search ---
echo "\n--- 7. Global Unified Search ---\n";
runTest("GET /api/v1/crm/search returns categorized results (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/crm/search?q=' . urlencode('فاز'), null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $data = $res['data']['data'];
    if (!isset($data['tasks'])) throw new Exception("Missing tasks in search");
    if (!isset($data['segments'])) throw new Exception("Missing segments in search");
    if (!isset($data['tags'])) throw new Exception("Missing tags in search");
});

// --- 8. Audit Logging ---
echo "\n--- 8. Audit Logging Verification ---\n";
runTest("Audit logs recorded for segment and task mutations", function () {
    require_once __DIR__ . '/../vendor/autoload.php';
    \App\Support\Env::load(__DIR__ . '/../.env');
    \App\Support\Config::setPath(__DIR__ . '/../config');
    $pdo = \App\Database\Connection::get();

    $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('task.created', 'task.updated', 'task.completed', 'segment.created')");
    $count = (int)$stmt->fetchColumn();
    if ($count < 3) {
        throw new Exception("Expected audit logs, got {$count}");
    }
});

// --- 9. Architectural Verification (NO Local Duplication) ---
echo "\n--- 9. Architectural Verification ---\n";
runTest("NO local customer duplication tables exist", function () {
    $pdo = \App\Database\Connection::get();
    $forbidden = ['customers', 'woocommerce_customers', 'wp_customers', 'customer_profiles'];
    foreach ($forbidden as $tbl) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tbl}'");
        if ($stmt->fetch()) {
            throw new Exception("Forbidden duplication table {$tbl} exists in database!");
        }
    }
});

// --- 10. Clean-up Deletions ---
echo "\n--- 10. Clean-up Deletions ---\n";
runTest("DELETE /api/v1/tasks/{id} deletes task (200)", function () use ($adminHeaders, $createdTaskId) {
    $res = httpReq('DELETE', "/api/v1/tasks/{$createdTaskId}", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
});

runTest("DELETE /api/v1/tags/{id} deletes tag (200)", function () use ($adminHeaders, $createdTagId) {
    $res = httpReq('DELETE', "/api/v1/tags/{$createdTagId}", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
});

runTest("DELETE /api/v1/segments/{id} deletes segment (200)", function () use ($adminHeaders, $createdSegmentId) {
    $res = httpReq('DELETE', "/api/v1/segments/{$createdSegmentId}", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
});

echo "\n========================================================\n";
echo " Phase 9 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) exit(1);
