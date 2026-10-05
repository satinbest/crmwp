<?php

declare(strict_types=1);

/**
 * Phase 17 Verification Test Suite:
 * Production Deployment, Installer, Unified Design System & Local Assets
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

$baseUrl = 'http://127.0.0.1:8000';
$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description} " . ($detail ? "({$detail})" : "") . "\n";
        $failed++;
    }
}

function httpReq(string $method, string $path, array $data = [], string $cookie = ''): array {
    global $baseUrl;
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Accept: application/json'];
    if (!empty($data) || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $headersText = substr($response, 0, $headerSize);
    $bodyText = substr($response, $headerSize);
    $body = json_decode($bodyText, true) ?? $bodyText;

    return ['status' => $status, 'headers' => $headersText, 'body' => $body];
}

echo "========================================================\n";
echo "   PHASE 17 TEST SUITE: PRODUCTION DEPLOYMENT & UI POLISH\n";
echo "========================================================\n\n";

// 1. Unified Button Design System & Component Verification
echo "1. Button Design Tokens & Component Verification:\n";
$appCss = file_get_contents(__DIR__ . '/../resources/css/app.css');
assertTest('app.css contains --btn-primary-bg design token', str_contains($appCss, '--btn-primary-bg'));
assertTest('app.css contains --btn-secondary-bg design token', str_contains($appCss, '--btn-secondary-bg'));
assertTest('app.css contains --btn-danger-bg design token', str_contains($appCss, '--btn-danger-bg'));
assertTest('app.css contains unified .btn class definition', str_contains($appCss, '.btn {'));
assertTest('app.css contains standardized .btn-refresh toolbar class', str_contains($appCss, '.btn-refresh {'));
assertTest('app.css includes protection against white hover glitch', str_contains($appCss, 'button.hover\\:bg-white:hover'));

$buttonVue = __DIR__ . '/../resources/js/components/ui/Button.vue';
assertTest('Button.vue component exists', file_exists($buttonVue));

$refreshVue = __DIR__ . '/../resources/js/components/ui/RefreshButton.vue';
assertTest('RefreshButton.vue component exists', file_exists($refreshVue));

$mainJs = file_get_contents(__DIR__ . '/../resources/js/main.js');
assertTest('RefreshButton is registered globally in main.js', str_contains($mainJs, 'RefreshButton'));
echo "\n";

// 2. Local Font & Zero External Asset Verification
echo "2. Local Typography & Asset Independence:\n";
$indexHtml = file_get_contents(__DIR__ . '/../public/index.html');
assertTest('public/index.html contains NO google fonts reference', !str_contains($indexHtml, 'fonts.googleapis.com'));
assertTest('public/index.html contains NO external font CDNs (cdnjs, jsdelivr, unpkg)', 
    !str_contains($indexHtml, 'cdnjs.cloudflare.com') && 
    !str_contains($indexHtml, 'jsdelivr.net') && 
    !str_contains($indexHtml, 'unpkg.com'));

$assetsDir = __DIR__ . '/../public/assets';
$fontFiles = glob($assetsDir . '/Vazirmatn-*.woff2');
assertTest('All 9 local Vazirmatn font weights exist in public/assets', count($fontFiles) === 9);

$cspString = \App\Support\Csp::getHeaderString();
assertTest('CSP restricts font-src to self and data:', str_contains($cspString, "font-src 'self' data:"));
echo "\n";

// 3. Central Application Versioning
echo "3. Central Application Versioning:\n";
$appConfig = require __DIR__ . '/../config/app.php';
assertTest('Application version is set centrally in config/app.php', !empty($appConfig['version']));
assertTest('Application version is 1.1.0', $appConfig['version'] === '1.1.0');
echo "\n";

// 4. Installer Environment Check API
echo "4. Production Installer Environment Check (/api/v1/install/check):\n";
$chkRes = httpReq('GET', '/api/v1/install/check');
assertTest('/install/check responds with 200 OK', $chkRes['status'] === 200);
assertTest('Installer detects PHP version 8.2+', ($chkRes['body']['data']['php_ok'] ?? false) === true);
assertTest('Installer verifies required extensions (pdo, openssl, mbstring, curl, etc.)', ($chkRes['body']['data']['extensions_ok'] ?? false) === true);
assertTest('Installer verifies writable storage directories', ($chkRes['body']['data']['directories_ok'] ?? false) === true);
assertTest('Installer overall can_install flag is true', ($chkRes['body']['data']['can_install'] ?? false) === true);
echo "\n";

// 5. Installer Database Connection Test API
echo "5. Production Installer Database Test API (/api/v1/install/database):\n";
$dbTestValid = httpReq('POST', '/api/v1/install/database', [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'crmwp',
    'db_user' => 'root',
    'db_pass' => '',
]);
assertTest('Valid database connection test succeeds with 200 OK', $dbTestValid['status'] === 200);
assertTest('Connected flag is true', ($dbTestValid['body']['data']['connected'] ?? false) === true);
assertTest('MariaDB version detected in test', !empty($dbTestValid['body']['data']['db_version'] ?? ''));

$dbTestBad = httpReq('POST', '/api/v1/install/database', [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'non_existent_crmwp_db_xyz',
    'db_user' => 'root',
    'db_pass' => '',
]);
assertTest('Invalid database returns error (400 Bad Request)', $dbTestBad['status'] === 400);
echo "\n";

// 6. Installer Web Wizard Page
echo "6. Production Installer Web Page (/install):\n";
$installPage = httpReq('GET', '/install');
assertTest('/install web route returns HTTP 200 OK', $installPage['status'] === 200);
assertTest('/install page contains Persian installation title', str_contains((string)$installPage['body'], 'نصب و راه‌اندازی سامانه مدیریت و CRM ووکامرس'));
assertTest('/install page contains step indicators', str_contains((string)$installPage['body'], 'step-indicators'));
echo "\n";

// 7. Installer Lock & Reinstall Protection
echo "7. Installation Lock & Reinstall Protection:\n";
$lockPath = __DIR__ . '/../storage/installed.lock';
$lockAltPath = __DIR__ . '/../storage/locks/installed.lock';

// Create temporary lock to test locking mechanism
file_put_contents($lockPath, json_encode(['test' => true, 'installed_at' => date('Y-m-d H:i:s')]));

$lockedCheck = httpReq('GET', '/api/v1/install/check');
assertTest('Locked installer blocks /api/v1/install/check with 403 Forbidden', $lockedCheck['status'] === 403);
assertTest('Error code is ALREADY_INSTALLED', ($lockedCheck['body']['error']['code'] ?? '') === 'ALREADY_INSTALLED');

$lockedSetup = httpReq('POST', '/api/v1/install/setup', ['db_name' => 'crmwp']);
assertTest('Locked installer blocks /api/v1/install/setup with 403 Forbidden', $lockedSetup['status'] === 403);

$lockedPage = httpReq('GET', '/install');
assertTest('/install displays already installed message when locked', str_contains((string)$lockedPage['body'], 'سیستم قبلاً با موفقیت نصب شده است'));

// Remove temporary lock for development readiness
if (file_exists($lockPath)) {
    unlink($lockPath);
}
if (file_exists($lockAltPath)) {
    unlink($lockAltPath);
}
echo "\n";

// 8. Storage Structure & Permissions
echo "8. Storage Security & Web Server Rules:\n";
assertTest('storage/cache directory exists', is_dir(__DIR__ . '/../storage/cache'));
assertTest('storage/logs directory exists', is_dir(__DIR__ . '/../storage/logs'));
assertTest('storage/uploads directory exists', is_dir(__DIR__ . '/../storage/uploads'));
assertTest('storage/locks directory exists', is_dir(__DIR__ . '/../storage/locks'));
assertTest('storage/.htaccess exists preventing direct web access', file_exists(__DIR__ . '/../storage/.htaccess'));

$storageHtaccess = file_get_contents(__DIR__ . '/../storage/.htaccess');
assertTest('storage/.htaccess contains Deny from all or Require all denied', 
    str_contains($storageHtaccess, 'Require all denied') || str_contains($storageHtaccess, 'Deny from all'));

assertTest('public/.htaccess exists with rewrite rules', file_exists(__DIR__ . '/../public/.htaccess'));
$publicHtaccess = file_get_contents(__DIR__ . '/../public/.htaccess');
assertTest('public/.htaccess contains RewriteEngine On', str_contains($publicHtaccess, 'RewriteEngine On'));
echo "\n";

// 9. Cron CLI & Concurrency Locking
echo "9. Cron CLI & Concurrency Locking:\n";
assertTest('cron.php CLI script exists', file_exists(__DIR__ . '/../cron.php'));
$cronExecOutput = shell_exec('php ' . escapeshellarg(__DIR__ . '/../cron.php') . ' 2>&1');
assertTest('cron.php executes cleanly via CLI', str_contains($cronExecOutput, 'Status: success') || str_contains($cronExecOutput, 'Background tasks finished successfully'));
assertTest('cron.php processes scheduled automations', str_contains($cronExecOutput, 'Automations processed') || str_contains($cronExecOutput, 'Running scheduled automations'));
echo "\n";

echo "========================================================\n";
echo "   PHASE 17 TEST RESULTS: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed === 0) {
    echo ">>> ALL PHASE 17 PRODUCTION & DEPLOYMENT TESTS PASSED!\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED.\n";
    exit(1);
}
