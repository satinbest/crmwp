<?php

declare(strict_types=1);

/**
 * Verification Test Suite for:
 * 1. Migration 015 & Foreign Key Integrity Fixes
 * 2. Fresh Installation on Empty Database
 * 3. Installer Local Vazirmatn Font & Offline RTL Verification
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

use App\Database\MigrationRunner;
use App\Controllers\InstallController;
use App\Support\Request;
use App\Database\Seeders\DatabaseSeeder;

$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$testDbName = 'crmwp_empty_test_db';

$passed = 0;
$failed = 0;

function assertCheck(string $title, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$title} " . ($details ? "({$details})" : "") . "\n";
        $failed++;
    }
}

echo "====================================================================\n";
echo "   PRODUCTION INSTALLATION & MIGRATION 015 VERIFICATION SUITE\n";
echo "====================================================================\n\n";

// Connect to MariaDB Server root
try {
    $serverPdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    echo "Fatal: Could not connect to MariaDB server: " . $e->getMessage() . "\n";
    exit(1);
}

// -------------------------------------------------------------------------
// TEST A: EMPTY DATABASE MIGRATION EXECUTION
// -------------------------------------------------------------------------
echo "TEST A: Running all migrations on a completely empty database...\n";

// Drop and recreate completely fresh database
$serverPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`;");
$serverPdo->exec("CREATE DATABASE `{$testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

$testPdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$testDbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$runner = new MigrationRunner($testPdo);
try {
    $applied = $runner->migrate();
    assertCheck('All migrations executed without exceptions on empty database', count($applied) === 18, 'Applied: ' . count($applied));
    assertCheck('Migration 015 was successfully applied', in_array('015_create_phase12_notifications_table', $applied, true));
} catch (Throwable $e) {
    assertCheck('All migrations executed without exceptions on empty database', false, $e->getMessage());
}

// -------------------------------------------------------------------------
// TEST C: FOREIGN KEY INTEGRITY VERIFICATION
// -------------------------------------------------------------------------
echo "\nTEST C: Verifying Foreign Key Constraint fk_notif_store...\n";

$fkStmt = $testPdo->prepare("
    SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notifications' AND CONSTRAINT_NAME = 'fk_notif_store'
");
$fkStmt->execute([$testDbName]);
$fkInfo = $fkStmt->fetch();

assertCheck('Constraint fk_notif_store exists in information_schema', !empty($fkInfo));
assertCheck('fk_notif_store links notifications.store_id -> stores.id', 
    ($fkInfo['COLUMN_NAME'] ?? '') === 'store_id' && ($fkInfo['REFERENCED_TABLE_NAME'] ?? '') === 'stores' && ($fkInfo['REFERENCED_COLUMN_NAME'] ?? '') === 'id');

// Check ON DELETE SET NULL rule in information_schema.REFERENTIAL_CONSTRAINTS
$rcStmt = $testPdo->prepare("
    SELECT DELETE_RULE
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'notifications' AND CONSTRAINT_NAME = 'fk_notif_store'
");
$rcStmt->execute([$testDbName]);
$deleteRule = $rcStmt->fetchColumn();
assertCheck('fk_notif_store has ON DELETE SET NULL', $deleteRule === 'SET NULL', "Actual: {$deleteRule}");

// Check column store_id is NULLABLE
$colStmt = $testPdo->prepare("
    SELECT IS_NULLABLE, COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'store_id'
");
$colStmt->execute([$testDbName]);
$colInfo = $colStmt->fetch();
assertCheck('notifications.store_id is nullable (YES)', ($colInfo['IS_NULLABLE'] ?? '') === 'YES');

// -------------------------------------------------------------------------
// TEST D: GLOBAL SYSTEM NOTIFICATION (store_id = NULL)
// -------------------------------------------------------------------------
echo "\nTEST D: Testing Notification with store_id = NULL (Global / System Notification)...\n";

// Create a system user to receive the notification
$testPdo->exec("INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`) VALUES ('notif_user', 'user@test.local', 'hash', 'Test', 'User');");
$userId = (int)$testPdo->lastInsertId();

try {
    $insertGlobal = $testPdo->prepare("
        INSERT INTO `notifications` (`user_id`, `store_id`, `type`, `title`, `message`, `priority`, `action_url`, `created_at`)
        VALUES (?, NULL, 'system', 'اعلان سراسری سیستم', 'پیام بروزرسانی هسته سامانه', 'low', '/dashboard', NOW())
    ");
    $insertGlobal->execute([$userId]);
    $globalNotifId = (int)$testPdo->lastInsertId();
    assertCheck('Notification with store_id = NULL saved successfully', $globalNotifId > 0);

    $savedGlobal = $testPdo->query("SELECT store_id, title FROM `notifications` WHERE id = {$globalNotifId}")->fetch();
    assertCheck('Saved notification has store_id strictly NULL', $savedGlobal['store_id'] === null);
} catch (Throwable $e) {
    assertCheck('Notification with store_id = NULL saved successfully', false, $e->getMessage());
}

// -------------------------------------------------------------------------
// TEST E: STORE-SPECIFIC NOTIFICATION & FOREIGN KEY VALIDATION
// -------------------------------------------------------------------------
echo "\nTEST E: Testing Notification linked to a valid store...\n";

// 1. Create a real store
$testPdo->exec("INSERT INTO `stores` (`name`, `url`, `consumer_key_encrypted`, `consumer_secret_encrypted`, `status`) VALUES ('فروشگاه آزمایشی', 'https://test-store.local', 'key', 'sec', 'active');");
$realStoreId = (int)$testPdo->lastInsertId();

try {
    $insertStoreNotif = $testPdo->prepare("
        INSERT INTO `notifications` (`user_id`, `store_id`, `type`, `title`, `message`, `priority`, `action_url`, `created_at`)
        VALUES (?, ?, 'order_status_changed', 'ثبت سفارش جدید', 'سفارش شماره ۵۰۱ ثبت شد', 'normal', '/orders/501', NOW())
    ");
    $insertStoreNotif->execute([$userId, $realStoreId]);
    $storeNotifId = (int)$testPdo->lastInsertId();
    assertCheck('Notification with valid store_id saved successfully', $storeNotifId > 0);

    $savedStoreNotif = $testPdo->query("SELECT store_id FROM `notifications` WHERE id = {$storeNotifId}")->fetch();
    assertCheck('Saved notification has matching store_id', (int)$savedStoreNotif['store_id'] === $realStoreId);
} catch (Throwable $e) {
    assertCheck('Notification with valid store_id saved successfully', false, $e->getMessage());
}

// 2. Verify invalid store_id STILL fails with FK violation (Integrity check)
try {
    $invalidStoreId = 99999;
    $insertInvalid = $testPdo->prepare("
        INSERT INTO `notifications` (`user_id`, `store_id`, `type`, `title`, `message`, `created_at`)
        VALUES (?, ?, 'invalid_test', 'تست نامعتبر', 'تست', NOW())
    ");
    $insertInvalid->execute([$userId, $invalidStoreId]);
    assertCheck('Foreign key correctly prevents inserting non-existent store_id', false, 'Expected FK constraint violation but insert succeeded!');
} catch (PDOException $e) {
    assertCheck('Foreign key correctly prevents inserting non-existent store_id', $e->getCode() === '23000');
}

// -------------------------------------------------------------------------
// TEST F: ON DELETE SET NULL BEHAVIOR
// -------------------------------------------------------------------------
echo "\nTEST F: Testing ON DELETE SET NULL cascading behavior...\n";

$delStmt = $testPdo->prepare("DELETE FROM `stores` WHERE id = ?");
$delStmt->execute([$realStoreId]);

$afterDeleteNotif = $testPdo->query("SELECT store_id FROM `notifications` WHERE id = {$storeNotifId}")->fetch();
assertCheck('Deleting store automatically sets notifications.store_id to NULL', $afterDeleteNotif['store_id'] === null);

// -------------------------------------------------------------------------
// TEST B: FRESH INSTALLER E2E WIZARD EXECUTION
// -------------------------------------------------------------------------
echo "\nTEST B: Running complete Fresh Installer Wizard on clean database...\n";

$installerDbName = 'crmwp_fresh_installer_test';
$serverPdo->exec("DROP DATABASE IF EXISTS `{$installerDbName}`;");
$serverPdo->exec("CREATE DATABASE `{$installerDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

// Ensure lock file and env are temporarily backed up for test
$lockPath = dirname(__DIR__) . '/storage/installed.lock';
$lockAltPath = dirname(__DIR__) . '/storage/locks/installed.lock';
$envPath = dirname(__DIR__) . '/.env';

$lockBackup = null;
if (file_exists($lockPath)) {
    $lockBackup = file_get_contents($lockPath);
    @unlink($lockPath);
}
if (file_exists($lockAltPath)) {
    @unlink($lockAltPath);
}

$envBackup = null;
if (file_exists($envPath)) {
    $envBackup = file_get_contents($envPath);
}

$installer = new InstallController();

// 1. Test database connection check API
$checkReq = new Request('GET', '/api/v1/install/check');
$checkRes = $installer->check($checkReq);
$checkData = is_array($checkRes->getContent()) ? $checkRes->getContent() : json_decode($checkRes->getContent(), true);
assertCheck('Installer environment check passes (can_install = true)', ($checkData['data']['can_install'] ?? false) === true);

// 2. Test database test API
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$dbTestReq = new Request(json_encode([
    'db_host' => $dbHost,
    'db_port' => $dbPort,
    'db_name' => $installerDbName,
    'db_user' => $dbUser,
    'db_pass' => $dbPass,
]));
$dbTestRes = $installer->testDatabase($dbTestReq);
$dbTestData = is_array($dbTestRes->getContent()) ? $dbTestRes->getContent() : json_decode($dbTestRes->getContent(), true);
assertCheck('Installer database connection test succeeds', ($dbTestData['data']['connected'] ?? false) === true, json_encode($dbTestData, JSON_UNESCAPED_UNICODE));

// 3. Test setup / installation API (Runs migrations, seeds RBAC, creates admin, generates keys)
$setupReq = new Request(json_encode([
    'db_host' => $dbHost,
    'db_port' => $dbPort,
    'db_name' => $installerDbName,
    'db_user' => $dbUser,
    'db_pass' => $dbPass,
    'admin_name' => 'مدیر تست نصب',
    'admin_username' => 'prod_admin',
    'admin_email' => 'admin@testsite.local',
    'admin_password' => 'ProdPassword1234!',
]));

$setupRes = $installer->setup($setupReq);
$setupData = is_array($setupRes->getContent()) ? $setupRes->getContent() : json_decode($setupRes->getContent(), true);

assertCheck('Installer setup API returns HTTP 200 OK', $setupRes->getStatusCode() === 200, json_encode($setupData, JSON_UNESCAPED_UNICODE));
assertCheck('Installer returns installed = true', ($setupData['data']['installed'] ?? false) === true);
assertCheck('Installer reports 18 migrations applied', ($setupData['data']['applied_migrations_count'] ?? 0) === 18);
assertCheck('Installation lock file was created', $installer->isInstalled());

// Restore original lock and env state if any
if ($lockBackup !== null) {
    file_put_contents($lockPath, $lockBackup);
} else {
    @unlink($lockPath);
    @unlink($lockAltPath);
}

if ($envBackup !== null) {
    file_put_contents($envPath, $envBackup);
}

// -------------------------------------------------------------------------
// TEST G: INSTALLER FONT & RTL TYPOGRAPHY AUDIT
// -------------------------------------------------------------------------
echo "\nTEST G: Installer Font & Persian Typography Audit...\n";

// Render installer wizard page
$wizardRes = $installer->index(new Request('GET', '/install'));
$html = $wizardRes->getContent();

assertCheck('Installer wizard returns HTTP 200', $wizardRes->getStatusCode() === 200);
assertCheck('Installer page has dir="rtl"', str_contains($html, 'dir="rtl"'));
assertCheck('Installer page has lang="fa"', str_contains($html, 'lang="fa"'));

// Zero External Font Provider Check
assertCheck('No Google Fonts reference (fonts.googleapis.com)', !str_contains($html, 'fonts.googleapis.com'));
assertCheck('No Google Fonts static reference (fonts.gstatic.com)', !str_contains($html, 'fonts.gstatic.com'));
assertCheck('No external font CDN (cdnjs, jsdelivr, unpkg)', 
    !str_contains($html, 'cdnjs.cloudflare.com') && 
    !str_contains($html, 'jsdelivr.net') && 
    !str_contains($html, 'unpkg.com'));

// Local Vazirmatn Font-Face Check
assertCheck('Installer CSS declares @font-face for Vazirmatn', str_contains($html, "@font-face") && str_contains($html, "font-family: 'Vazirmatn'"));
assertCheck('Vazirmatn font-family applied to body and form elements', str_contains($html, "font-family: 'Vazirmatn'"));

// Extract font URLs and verify actual physical files exist locally
preg_match_all('/url\(\'\/assets\/([^\']+)\'\)/', $html, $fontMatches);
$foundFonts = $fontMatches[1] ?? [];
assertCheck('Installer loads local Vazirmatn woff2 font files from /assets/', count($foundFonts) >= 9, 'Found: ' . count($foundFonts));

$allFilesExist = true;
foreach ($foundFonts as $fontFile) {
    $fullPath = dirname(__DIR__) . '/public/assets/' . $fontFile;
    if (!file_exists($fullPath) || filesize($fullPath) < 1000) {
        $allFilesExist = false;
        echo "    Missing font file: {$fullPath}\n";
    }
}
assertCheck('All referenced Vazirmatn font files exist and are valid in public/assets', $allFilesExist);

// Check RTL and Persian Typography rules
assertCheck('CSS contains direction: rtl and text-align: right', str_contains($html, 'direction: rtl') && str_contains($html, 'text-align: right'));
assertCheck('CSS contains line-height standard for Persian text', str_contains($html, 'line-height: 1.6'));
assertCheck('Technical fields have ltr-input class with direction: ltr', str_contains($html, 'class="ltr-input"') && str_contains($html, 'input.ltr-input { direction: ltr; text-align: left; }'));

// Cleanup test databases
$serverPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`;");
$serverPdo->exec("DROP DATABASE IF EXISTS `{$installerDbName}`;");

echo "\n====================================================================\n";
echo "   TOTAL RESULTS: {$passed} Passed, {$failed} Failed\n";
echo "====================================================================\n";

if ($failed === 0) {
    echo ">>> ALL PRODUCTION INSTALLATION & MIGRATION 015 TESTS PASSED!\n";
    exit(0);
} else {
    echo ">>> VERIFICATION FAILED WITH {$failed} ERRORS.\n";
    exit(1);
}
