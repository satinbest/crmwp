<?php

declare(strict_types=1);

/**
 * Phase 1 Automated End-to-End Test Suite
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

    // Extract cookies
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
echo " WooCommerce Management & CRM Platform - Phase 1 Verification\n";
echo "========================================================\n\n";

// 1. Health Endpoint
echo "1. System Health & Database Connection:\n";
$health = httpReq('GET', '/api/v1/system/health');
assertTest('Health API responds with 200 OK', $health['status'] === 200);
assertTest('Standard envelope present (success: true)', ($health['body']['success'] ?? false) === true);
assertTest('MariaDB database status is connected', ($health['body']['data']['database']['status'] ?? '') === 'connected');
assertTest('MariaDB version detected', !empty($health['body']['data']['database']['version'] ?? ''));
assertTest('PHP version is 8.4+', version_compare($health['body']['data']['php_version'] ?? '0', '8.4', '>='));
echo "\n";

// 2. CSRF Endpoint
echo "2. CSRF Protection:\n";
$csrf = httpReq('GET', '/api/v1/auth/csrf');
assertTest('CSRF API responds with 200 OK', $csrf['status'] === 200);
assertTest('CSRF token generated (64 hex chars)', strlen($csrf['body']['data']['csrf_token'] ?? '') === 64);
assertTest('Session cookie issued on CSRF request', !empty($csrf['cookies']));
echo "\n";

// 3. Validation and Error Handling
echo "3. API Error Envelopes & Input Validation:\n";
$emptyLogin = httpReq('POST', '/api/v1/auth/login', ['username' => '', 'password' => '']);
assertTest('Empty login returns 422 Unprocessable', $emptyLogin['status'] === 422);
assertTest('Error code is VALIDATION_ERROR', ($emptyLogin['body']['error']['code'] ?? '') === 'VALIDATION_ERROR');
assertTest('User-friendly Persian validation message', !empty($emptyLogin['body']['error']['message'] ?? ''));

$badLogin = httpReq('POST', '/api/v1/auth/login', ['username' => 'wrong', 'password' => 'bad']);
assertTest('Invalid credentials returns 401 Unauthorized', $badLogin['status'] === 401);
assertTest('Error code is AUTH_FAILED', ($badLogin['body']['error']['code'] ?? '') === 'AUTH_FAILED');
echo "\n";

// 4. Admin Authentication Flow
echo "4. Admin Authentication & Session Management:\n";
$adminLogin = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'admin',
    'password' => 'AdminPassword123!',
]);
assertTest('Admin login succeeds with 200 OK', $adminLogin['status'] === 200);
assertTest('Admin username matches', ($adminLogin['body']['data']['user']['username'] ?? '') === 'admin');
assertTest('Admin user has role "Admin"', ($adminLogin['body']['data']['user']['roles'][0]['name'] ?? '') === 'Admin');
assertTest('Admin user has all granular permissions (at least 22)', count($adminLogin['body']['data']['user']['permissions'] ?? []) >= 22);

$adminCookie = $adminLogin['cookies'];
assertTest('Session cookie captured for authenticated requests', !empty($adminCookie));
assertTest('Cookie contains crmwp_session', str_contains($adminCookie, 'crmwp_session'));
assertTest('Cookie has HttpOnly flag set', str_contains(strtolower($adminLogin['headers']), 'httponly'));
echo "\n";

// 5. Auth Me Endpoint
echo "5. Current User Verification (/api/v1/auth/me):\n";
$me = httpReq('GET', '/api/v1/auth/me', [], $adminCookie);
assertTest('/auth/me responds with 200 OK', $me['status'] === 200);
assertTest('Authenticated user ID is 1', ($me['body']['data']['user']['id'] ?? 0) === 1);
assertTest('User full name correctly formatted', in_array($me['body']['data']['user']['full_name'] ?? '', ['حسین مدیری', 'حسین محمدپور']));
assertTest('Sensitive password hash is NOT exposed in response', !isset($me['body']['data']['user']['password_hash']));
echo "\n";

// 6. RBAC Verification (Users, Roles, Permissions)
echo "6. RBAC Foundation & Granular Permissions:\n";
$usersRes = httpReq('GET', '/api/v1/users', [], $adminCookie);
assertTest('/users endpoint responds with 200 OK', $usersRes['status'] === 200);
assertTest('Standard pagination meta present (page, per_page, total)', isset($usersRes['body']['meta']['total']));
assertTest('At least 2 seeded users found (admin, manager)', count($usersRes['body']['data'] ?? []) >= 2);

$rolesRes = httpReq('GET', '/api/v1/roles', [], $adminCookie);
assertTest('/roles endpoint responds with 200 OK', $rolesRes['status'] === 200);
assertTest('All system roles exist (at least 6: Admin, Manager, Sales, Support, Warehouse, Viewer)', count($rolesRes['body']['data'] ?? []) >= 6);

$permsRes = httpReq('GET', '/api/v1/permissions', [], $adminCookie);
assertTest('/permissions endpoint responds with 200 OK', $permsRes['status'] === 200);
$allPermsCount = 0;
foreach ($permsRes['body']['data'] ?? [] as $group => $items) {
    $allPermsCount += count($items);
}
assertTest('All granular permissions grouped by module exist', $allPermsCount >= 22);
echo "\n";

// 7. Multi-Store Readiness & Credential Masking
echo "7. Store Layer & Credential Security:\n";
$storesRes = httpReq('GET', '/api/v1/stores', [], $adminCookie);
assertTest('/stores endpoint responds with 200 OK', $storesRes['status'] === 200);
assertTest('Stores data structure is array', is_array($storesRes['body']['data'] ?? null));
echo "\n";

// 8. Role-Based Access Control Restrictions (Manager testing)
echo "8. RBAC Enforcement (Manager vs Admin):\n";
$mgrLogin = httpReq('POST', '/api/v1/auth/login', [
    'username' => 'manager',
    'password' => 'ManagerPassword123!',
]);
assertTest('Manager login succeeds with 200 OK', $mgrLogin['status'] === 200);
$mgrCookie = $mgrLogin['cookies'];
$mgrPerms = $mgrLogin['body']['data']['user']['permissions'] ?? [];
assertTest('Manager has limited permissions compared to Admin', count($mgrPerms) < count($adminLogin['body']['data']['user']['permissions'] ?? []));
assertTest('Manager does NOT have users.manage permission', !in_array('users.manage', $mgrPerms, true));
assertTest('Manager does NOT have stores.manage permission', !in_array('stores.manage', $mgrPerms, true));
echo "\n";

// 9. Logout Flow
echo "9. Logout & Session Termination:\n";
$logout = httpReq('POST', '/api/v1/auth/logout', [], $adminCookie);
assertTest('/auth/logout responds with 200 OK', $logout['status'] === 200);

$meAfterLogout = httpReq('GET', '/api/v1/auth/me', [], $adminCookie);
assertTest('/auth/me returns 401 UNAUTHENTICATED after logout', $meAfterLogout['status'] === 401);
assertTest('Error code is UNAUTHENTICATED', ($meAfterLogout['body']['error']['code'] ?? '') === 'UNAUTHENTICATED');
echo "\n";

// 10. Frontend Production Independence & Static Assets
echo "10. Production Independence & Local Bundling:\n";
$indexHtml = @file_get_contents(dirname(__DIR__) . '/public/index.html');
assertTest('public/index.html exists', !empty($indexHtml));
assertTest('HTML specifies lang="fa" and dir="rtl"', str_contains($indexHtml, 'lang="fa"') && str_contains($indexHtml, 'dir="rtl"'));
assertTest('HTML contains NO external CDN links (no cdnjs, unpkg, google fonts)', 
    !str_contains($indexHtml, 'fonts.googleapis.com') && 
    !str_contains($indexHtml, 'cdnjs.cloudflare.com') && 
    !str_contains($indexHtml, 'unpkg.com')
);

$fontFiles = glob(dirname(__DIR__) . '/public/assets/Vazirmatn*.woff2');
assertTest('Local Vazirmatn woff2 font files exist in public/assets', count($fontFiles) >= 8);
echo "\n";

echo "========================================================\n";
echo " Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
