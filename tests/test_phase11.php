<?php

declare(strict_types=1);

/**
 * Phase 11 — Team / Users / Roles & Permissions Automated Test Suite
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
echo " Phase 11: Team / Users / Roles & Permissions Tests\n";
echo "========================================================\n\n";

// --- 1. Unauthenticated Security ---
echo "--- 1. Unauthenticated Requests Rejected ---\n";
runTest("GET /api/v1/users rejected with 401", function () {
    $res = httpReq('GET', '/api/v1/users');
    if ($res['status'] !== 401) throw new Exception("Expected 401, got {$res['status']}");
});

runTest("GET /api/v1/roles rejected with 401", function () {
    $res = httpReq('GET', '/api/v1/roles');
    if ($res['status'] !== 401) throw new Exception("Expected 401, got {$res['status']}");
});

runTest("GET /api/v1/me/profile rejected with 401", function () {
    $res = httpReq('GET', '/api/v1/me/profile');
    if ($res['status'] !== 401) throw new Exception("Expected 401, got {$res['status']}");
});

// Admin login
$adminAuth = loginUser('admin', 'AdminPassword123!');
if (!$adminAuth['success']) {
    // Try demo password
    $adminAuth = loginUser('admin', 'Password123!');
}
if (!$adminAuth['success']) {
    echo "Fatal: Could not login as admin. Response: " . json_encode($adminAuth['res']) . "\n";
    exit(1);
}
$adminHeaders = $adminAuth['headers'];

runTest("Admin login succeeds (200)", function () use ($adminAuth) {
    if (!$adminAuth['success']) throw new Exception("Admin login failed");
});

// --- 2. Self Profile & Permissions ---
echo "\n--- 2. Self Profile & Permissions Endpoints ---\n";
runTest("GET /api/v1/me/profile returns current user data", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/me/profile', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $data = $res['data']['data'];
    if ($data['username'] !== 'admin') throw new Exception("Username mismatch");
    if (isset($data['password_hash'])) throw new Exception("Security violation: password_hash exposed!");
});

runTest("GET /api/v1/me/permissions returns permissions list", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/me/permissions', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $perms = $res['data']['data'];
    if (!is_array($perms) || empty($perms)) throw new Exception("Expected non-empty permissions array");
    if (!in_array('users.view', $perms, true)) throw new Exception("Missing users.view");
});

runTest("GET /api/v1/me/stores returns stores list", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/me/stores', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $stores = $res['data']['data'];
    if (!is_array($stores)) throw new Exception("Expected stores array");
});

// --- 3. User Management CRUD & Validations ---
echo "\n--- 3. Users Management (CRUD & Validations) ---\n";
$createdUserId = null;
$testUsername = 'test_sales_' . rand(1000, 9999);
$testEmail = $testUsername . '@example.com';

runTest("POST /api/v1/users validation rejects invalid input (422)", function () use ($adminHeaders) {
    // Missing required fields
    $res = httpReq('POST', '/api/v1/users', ['username' => 'ab'], $adminHeaders);
    if ($res['status'] !== 422) throw new Exception("Expected 422, got {$res['status']}");
});

runTest("POST /api/v1/users validation rejects weak password (422)", function () use ($adminHeaders) {
    $res = httpReq('POST', '/api/v1/users', [
        'username' => 'weakuser123',
        'email' => 'weak@example.com',
        'password' => '12345',
        'confirm_password' => '12345',
    ], $adminHeaders);
    if ($res['status'] !== 422) throw new Exception("Expected 422 for weak password, got {$res['status']}");
});

runTest("POST /api/v1/users creates new user (201)", function () use ($adminHeaders, $testUsername, $testEmail, &$createdUserId) {
    $payload = [
        'username' => $testUsername,
        'email' => $testEmail,
        'first_name' => 'کاربر',
        'last_name' => 'تستی',
        'password' => 'TestPass123!',
        'confirm_password' => 'TestPass123!',
        'roles' => ['sales'],
        'stores' => [2],
        'is_active' => true,
    ];
    $res = httpReq('POST', '/api/v1/users', $payload, $adminHeaders);
    if ($res['status'] !== 201) throw new Exception("Expected 201, got {$res['status']} raw: " . $res['raw']);
    $data = $res['data']['data'];
    $createdUserId = (int)$data['id'];
    if ($createdUserId <= 0) throw new Exception("Invalid created user ID");
    if ($data['username'] !== $testUsername) throw new Exception("Username mismatch");
    if (isset($data['password_hash'])) throw new Exception("password_hash exposed");
});

runTest("POST /api/v1/users duplicate email rejected (422)", function () use ($adminHeaders, $testUsername, $testEmail) {
    $payload = [
        'username' => 'another_' . rand(100, 999),
        'email' => $testEmail, // duplicate email
        'password' => 'TestPass123!',
        'confirm_password' => 'TestPass123!',
    ];
    $res = httpReq('POST', '/api/v1/users', $payload, $adminHeaders);
    if ($res['status'] !== 422) throw new Exception("Expected 422 for duplicate email, got {$res['status']}");
});

runTest("GET /api/v1/users returns users list with pagination (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/users?page=1&per_page=10', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $data = $res['data']['data'];
    $meta = $res['data']['meta'];
    if (!is_array($data) || empty($data)) throw new Exception("Expected non-empty users list");
    if (!isset($meta['total'])) throw new Exception("Missing pagination meta total");
});

runTest("GET /api/v1/users/{id} returns user details (200)", function () use ($adminHeaders, &$createdUserId) {
    $res = httpReq('GET', "/api/v1/users/{$createdUserId}", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $data = $res['data']['data'];
    if ((int)$data['id'] !== $createdUserId) throw new Exception("User ID mismatch");
});

runTest("PATCH /api/v1/users/{id} updates user details (200)", function () use ($adminHeaders, &$createdUserId) {
    $payload = [
        'first_name' => 'کاربر بروزرسانی‌شده',
        'last_name' => 'تستی جدید',
    ];
    $res = httpReq('PATCH', "/api/v1/users/{$createdUserId}", $payload, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $data = $res['data']['data'];
    if ($data['first_name'] !== 'کاربر بروزرسانی‌شده') throw new Exception("Name update failed");
});

// --- 4. User Status & Inactivation ---
echo "\n--- 4. User Activation & Status Enforcement ---\n";
runTest("POST /api/v1/users/{id}/deactivate deactivates user (200)", function () use ($adminHeaders, &$createdUserId) {
    $res = httpReq('POST', "/api/v1/users/{$createdUserId}/deactivate", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
});

runTest("Deactivated user cannot log in (403)", function () use ($testUsername) {
    $res = httpReq('POST', '/api/v1/auth/login', [
        'username' => $testUsername,
        'password' => 'TestPass123!',
    ]);
    if ($res['status'] !== 403) throw new Exception("Expected 403 for inactive user login, got {$res['status']}");
});

runTest("POST /api/v1/users/{id}/activate reactivates user (200)", function () use ($adminHeaders, &$createdUserId) {
    $res = httpReq('POST', "/api/v1/users/{$createdUserId}/activate", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
});

runTest("POST /api/v1/users/{id}/reset-password resets password (200)", function () use ($adminHeaders, &$createdUserId) {
    $res = httpReq('POST', "/api/v1/users/{$createdUserId}/reset-password", [
        'password' => 'NewResetPass123!',
    ], $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
});

runTest("Login with new reset password succeeds (200)", function () use ($testUsername) {
    $auth = loginUser($testUsername, 'NewResetPass123!');
    if (!$auth['success']) throw new Exception("Login with reset password failed");
});

runTest("GET /api/v1/users/{id}/activity returns security audit records (200)", function () use ($adminHeaders, &$createdUserId) {
    $res = httpReq('GET', "/api/v1/users/{$createdUserId}/activity", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $logs = $res['data']['data'];
    if (!is_array($logs)) throw new Exception("Expected logs array");
});

// --- 5. Roles Management & Permission Matrix ---
echo "\n--- 5. Roles Management & Permissions Matrix ---\n";
$createdRoleId = null;
$testRoleSlug = 'role_custom_' . rand(100, 999);

runTest("GET /api/v1/roles returns roles list with permission counts (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/roles', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $roles = $res['data']['data'];
    if (!is_array($roles) || empty($roles)) throw new Exception("Expected non-empty roles array");
    $sample = $roles[0];
    if (!isset($sample['slug']) || !isset($sample['permissions_count'])) {
        throw new Exception("Role missing slug or permissions_count");
    }
});

runTest("GET /api/v1/permissions returns grouped permissions (200)", function () use ($adminHeaders) {
    $res = httpReq('GET', '/api/v1/permissions', null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $groups = $res['data']['data'];
    if (!is_array($groups) || empty($groups)) throw new Exception("Expected grouped permissions array");
    if (!isset($groups[0]['group_key']) || !isset($groups[0]['permissions'])) {
        throw new Exception("Invalid permission group format");
    }
});

runTest("POST /api/v1/roles creates new custom role (201)", function () use ($adminHeaders, $testRoleSlug, &$createdRoleId) {
    $payload = [
        'name' => 'ویراستار محتوا ' . rand(10, 99),
        'slug' => $testRoleSlug,
        'display_name' => 'ویراستار محتوا',
        'description' => 'دسترسی آزمایشی فاز ۱۱',
        'status' => 'active',
        'permissions' => ['products.view', 'products.update'],
    ];
    $res = httpReq('POST', '/api/v1/roles', $payload, $adminHeaders);
    if ($res['status'] !== 201) throw new Exception("Expected 201, got {$res['status']} raw: " . $res['raw']);
    $data = $res['data']['data'];
    $createdRoleId = (int)$data['id'];
    if ($createdRoleId <= 0) throw new Exception("Invalid created role ID");
    if ($data['slug'] !== $testRoleSlug) throw new Exception("Slug mismatch");
});

runTest("GET /api/v1/roles/{id}/permissions returns role's permissions (200)", function () use ($adminHeaders, &$createdRoleId) {
    $res = httpReq('GET', "/api/v1/roles/{$createdRoleId}/permissions", null, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $perms = $res['data']['data'];
    if (!is_array($perms)) throw new Exception("Expected permissions array");
    $names = array_column($perms, 'name');
    if (!in_array('products.view', $names, true)) throw new Exception("Missing products.view in role");
});

runTest("PUT /api/v1/roles/{id}/permissions syncs permission matrix (200)", function () use ($adminHeaders, &$createdRoleId) {
    $payload = ['permissions' => ['orders.view', 'orders.update', 'orders.add_note']];
    $res = httpReq('PUT', "/api/v1/roles/{$createdRoleId}/permissions", $payload, $adminHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $newPerms = $res['data']['data'];
    $names = array_column($newPerms, 'name');
    if (!in_array('orders.view', $names, true) || in_array('products.view', $names, true)) {
        throw new Exception("Permissions sync failed");
    }
});

runTest("POST /api/v1/roles/{id}/duplicate duplicates role with permissions (201)", function () use ($adminHeaders, &$createdRoleId) {
    $payload = [
        'name' => 'نقش کپی‌شده ' . rand(100, 999),
        'slug' => 'role_dup_' . rand(1000, 9999),
    ];
    $res = httpReq('POST', "/api/v1/roles/{$createdRoleId}/duplicate", $payload, $adminHeaders);
    if ($res['status'] !== 201) throw new Exception("Expected 201, got {$res['status']}");
    $dup = $res['data']['data'];
    if ($dup['permissions_count'] !== 3) throw new Exception("Expected 3 copied permissions, got {$dup['permissions_count']}");
    // Clean up duplicate
    httpReq('DELETE', "/api/v1/roles/{$dup['id']}", null, $adminHeaders);
});

// --- 6. Last Administrator & Protected Roles Protection ---
echo "\n--- 6. Last Administrator & System Protection ---\n";
runTest("Deleting protected system role (admin) is strictly rejected (422)", function () use ($adminHeaders) {
    $res = httpReq('DELETE', '/api/v1/roles/1', null, $adminHeaders);
    if ($res['status'] !== 422) throw new Exception("Expected 422 for protected role deletion, got {$res['status']}");
});

runTest("Deactivating the last active administrator is strictly rejected (422)", function () use ($adminHeaders) {
    // Admin user id is 1
    $res = httpReq('POST', '/api/v1/users/1/deactivate', null, $adminHeaders);
    if ($res['status'] !== 422) throw new Exception("Expected 422 for last admin deactivation, got {$res['status']}");
});

runTest("Deleting the last active administrator is strictly rejected (422)", function () use ($adminHeaders) {
    $res = httpReq('DELETE', '/api/v1/users/1', null, $adminHeaders);
    if ($res['status'] !== 422) throw new Exception("Expected 422 for last admin deletion, got {$res['status']}");
});

// --- 7. Privilege Escalation Prevention ---
echo "\n--- 7. Privilege Escalation Prevention ---\n";
// Create non-admin user
$salesAuth = loginUser($testUsername, 'NewResetPass123!');
$salesHeaders = $salesAuth['headers'];

runTest("Non-admin cannot create user with Admin role (403)", function () use ($salesHeaders) {
    $payload = [
        'username' => 'hacker_' . rand(100, 999),
        'email' => 'hacker' . rand(100, 999) . '@example.com',
        'password' => 'HackerPass123!',
        'confirm_password' => 'HackerPass123!',
        'roles' => ['admin'],
    ];
    $res = httpReq('POST', '/api/v1/users', $payload, $salesHeaders);
    if ($res['status'] !== 403) throw new Exception("Expected 403, got {$res['status']}");
});

runTest("Non-admin cannot edit Admin user profile (403)", function () use ($salesHeaders) {
    $payload = ['first_name' => 'HackedName'];
    $res = httpReq('PATCH', '/api/v1/users/1', $payload, $salesHeaders);
    if ($res['status'] !== 403) throw new Exception("Expected 403 for modifying admin, got {$res['status']}");
});

runTest("Self-profile update cannot elevate own roles or permissions", function () use ($salesHeaders) {
    $payload = [
        'first_name' => 'فروشنده تاییدشده',
        'roles' => ['admin'], // malicious attempt
        'is_active' => false,
    ];
    $res = httpReq('PATCH', '/api/v1/me/profile', $payload, $salesHeaders);
    if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    $data = $res['data']['data'];
    $roleSlugs = array_column($data['roles'], 'slug');
    if (in_array('admin', $roleSlugs, true)) {
        throw new Exception("Security breach: User elevated role via self-profile!");
    }
});

// --- 8. Store-Level Isolation ---
echo "\n--- 8. Store-Level Isolation ---\n";
// Create user with access ONLY to a dummy store ID (e.g. store 9999)
$restrictedUsername = 'isolated_user_' . rand(100, 999);
$userCreateRes = httpReq('POST', '/api/v1/users', [
    'username' => $restrictedUsername,
    'email' => $restrictedUsername . '@example.com',
    'password' => 'Pass12345!',
    'confirm_password' => 'Pass12345!',
    'roles' => ['viewer'],
    'stores' => [], // NO access to store 2!
], $adminHeaders);

$isolatedUserId = (int)$userCreateRes['data']['data']['id'];
$isolatedAuth = loginUser($restrictedUsername, 'Pass12345!');
$isolatedHeaders = $isolatedAuth['headers'];

runTest("User without store access gets 403 on store-scoped orders", function () use ($isolatedHeaders) {
    $headers = array_merge($isolatedHeaders, ['X-Store-Id' => '2']);
    $res = httpReq('GET', '/api/v1/orders', null, $headers);
    if ($res['status'] !== 403) throw new Exception("Expected 403 Forbidden for unauthorized store, got {$res['status']}");
});

runTest("User without store access gets 403 on store-scoped products", function () use ($isolatedHeaders) {
    $headers = array_merge($isolatedHeaders, ['X-Store-Id' => '2']);
    $res = httpReq('GET', '/api/v1/products', null, $headers);
    if ($res['status'] !== 403) throw new Exception("Expected 403 Forbidden for unauthorized store, got {$res['status']}");
});

runTest("User without store access gets 403 on store-scoped inventory", function () use ($isolatedHeaders) {
    $headers = array_merge($isolatedHeaders, ['X-Store-Id' => '2']);
    $res = httpReq('GET', '/api/v1/inventory', null, $headers);
    if ($res['status'] !== 403) throw new Exception("Expected 403 Forbidden for unauthorized store, got {$res['status']}");
});

// Admin with store 2 succeeds
runTest("Admin with store 2 succeeds on store-scoped orders (200)", function () use ($adminHeaders) {
    $headers = array_merge($adminHeaders, ['X-Store-Id' => '2']);
    $res = httpReq('GET', '/api/v1/orders', null, $headers);
    if ($res['status'] !== 200) throw new Exception("Expected 200 for admin, got {$res['status']}");
});

// --- 9. Role-Based Permission Matrix Enforced in Backend ---
echo "\n--- 9. Role-Based Permission Enforcement ---\n";
// Demo user: viewer
$viewerAuth = loginUser('viewer', 'Password123!');
if ($viewerAuth['success']) {
    $viewerHeaders = array_merge($viewerAuth['headers'], ['X-Store-Id' => '2']);

    runTest("Viewer can view customers (200)", function () use ($viewerHeaders) {
        $res = httpReq('GET', '/api/v1/customers', null, $viewerHeaders);
        if ($res['status'] !== 200) throw new Exception("Expected 200 for viewer customers list, got {$res['status']}");
    });

    runTest("Viewer cannot modify products (403)", function () use ($viewerHeaders) {
        $res = httpReq('PATCH', '/api/v1/products/301', ['name' => 'Unauthorized Change'], $viewerHeaders);
        if ($res['status'] !== 403) throw new Exception("Expected 403 for viewer updating product, got {$res['status']}");
    });

    runTest("Viewer cannot create products (403)", function () use ($viewerHeaders) {
        $res = httpReq('POST', '/api/v1/products', ['name' => 'Forbidden Product'], $viewerHeaders);
        if ($res['status'] !== 403) throw new Exception("Expected 403 for viewer creating product, got {$res['status']}");
    });

    runTest("Viewer cannot manage users (403)", function () use ($viewerHeaders) {
        $res = httpReq('GET', '/api/v1/users', null, $viewerHeaders);
        if ($res['status'] !== 403) throw new Exception("Expected 403 for viewer accessing users, got {$res['status']}");
    });
}

// Demo user: inventory_mgr
$invAuth = loginUser('inventory_mgr', 'Password123!');
if ($invAuth['success']) {
    $invHeaders = array_merge($invAuth['headers'], ['X-Store-Id' => '2']);

    runTest("Inventory Manager can view inventory (200)", function () use ($invHeaders) {
        $res = httpReq('GET', '/api/v1/inventory', null, $invHeaders);
        if ($res['status'] !== 200) throw new Exception("Expected 200 for inventory manager, got {$res['status']}");
    });

    runTest("Inventory Manager cannot view CRM segments (403)", function () use ($invHeaders) {
        $res = httpReq('GET', '/api/v1/segments', null, $invHeaders);
        if ($res['status'] !== 403) throw new Exception("Expected 403 for inventory manager accessing CRM segments, got {$res['status']}");
    });
}

// --- 10. Audit Logging Verification ---
echo "\n--- 10. Audit Logging Verification ---\n";
runTest("Audit logs recorded for user, role and permission mutations", function () {
    require_once __DIR__ . '/../vendor/autoload.php';
    \App\Support\Env::load(__DIR__ . '/../.env');
    \App\Support\Config::setPath(__DIR__ . '/../config');
    $pdo = \App\Database\Connection::get();

    $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('user.created', 'user.updated', 'user.activated', 'user.deactivated', 'user.password_reset', 'role.created', 'role.permissions_updated')");
    $count = (int)$stmt->fetchColumn();
    if ($count < 4) {
        throw new Exception("Expected at least 4 audit logs, got {$count}");
    }
});

// --- 11. Clean-up ---
echo "\n--- 11. Clean-up ---\n";
runTest("DELETE /api/v1/users/{id} deletes test user (200)", function () use ($adminHeaders, &$createdUserId, &$isolatedUserId) {
    if ($createdUserId) {
        $res = httpReq('DELETE', "/api/v1/users/{$createdUserId}", null, $adminHeaders);
        if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    }
    if ($isolatedUserId) {
        httpReq('DELETE', "/api/v1/users/{$isolatedUserId}", null, $adminHeaders);
    }
});

runTest("DELETE /api/v1/roles/{id} deletes test role (200)", function () use ($adminHeaders, &$createdRoleId) {
    if ($createdRoleId) {
        $res = httpReq('DELETE', "/api/v1/roles/{$createdRoleId}", null, $adminHeaders);
        if ($res['status'] !== 200) throw new Exception("Expected 200, got {$res['status']}");
    }
});

echo "\n========================================================\n";
echo " Phase 11 Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n\n";

if ($failed > 0) {
    exit(1);
}
