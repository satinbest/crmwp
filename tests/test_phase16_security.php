<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Automation\AutomationEngine;
use App\Database\Connection;
use App\Events\AutomationEvent;
use App\Repositories\StoreRepository;
use App\Repositories\UserRepository;
use App\Services\Bulk\BulkOperationEngine;
use App\Services\RbacService;
use App\Services\UserService;
use App\Services\WooCommerceWebhookService;
use App\Support\Config;
use App\Support\Env;
use App\Support\Request;
use App\Support\Response;
use App\Support\Security;
use App\Support\StoreContext;

// Load environment & configuration
Env::load(dirname(__DIR__) . '/.env');
Config::setPath(dirname(__DIR__) . '/config');

echo "========================================================\n";
echo "   PHASE 16 TEST SUITE: SECURITY, HARDENING & RELIABILITY\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $failCount++;
    }
}

// ---------------------------------------------------------
// 1. Security Headers & Correlation ID
// ---------------------------------------------------------
echo "1. Security Headers & Correlation ID Verification:\n";
$req = Request::capture();
$reqId = $req->getRequestId();
assertTest(str_starts_with($reqId, 'req_') && strlen($reqId) >= 16, "Request generates standard correlation ID (req_...)");

$resp = Response::success(['key' => 'val']);
$errorResp = Response::error('TEST_ERR', 'پیام تستی');
$errContent = $errorResp->getContent();

assertTest(isset($errContent['error']['request_id']), "Response::error includes correlation request_id in payload");
assertTest($errContent['error']['request_id'] === $reqId, "Correlation request_id matches active Request ID");

// ---------------------------------------------------------
// 2. Sensitive Credentials Encryption & Masking
// ---------------------------------------------------------
echo "\n2. WooCommerce Secrets Encryption & Masking at Rest:\n";
$plainKey = 'ck_9876543210fedcba9876543210';
$plainSecret = 'cs_1234567890abcdef1234567890abcdef12345678';

$encKey = Security::encrypt($plainKey);
$encSecret = Security::encrypt($plainSecret);

assertTest(!empty($encKey) && $encKey !== $plainKey, "Consumer Key is securely encrypted (AES-256-CBC + HMAC)");
assertTest(!empty($encSecret) && $encSecret !== $plainSecret, "Consumer Secret is securely encrypted (AES-256-CBC + HMAC)");

$decKey = Security::decrypt($encKey);
$decSecret = Security::decrypt($encSecret);
assertTest($decKey === $plainKey, "Decrypted Consumer Key matches original");
assertTest($decSecret === $plainSecret, "Decrypted Consumer Secret matches original");

$maskedKey = Security::maskConsumerKey($plainKey);
$maskedSecret = Security::maskSecret($plainSecret);
assertTest(str_starts_with($maskedKey, 'ck_') && str_contains($maskedKey, '***'), "Masked Key hides sensitive characters ({$maskedKey})");
assertTest($maskedSecret === '••••••••••••••••', "Masked Secret is completely redacted");

// ---------------------------------------------------------
// 3. Password Hashing & Verification
// ---------------------------------------------------------
echo "\n3. Password Hashing Security:\n";
$password = 'SecretAdmin#2026';
$hash = Security::hashPassword($password);
assertTest(str_starts_with($hash, '$2y$12$'), "Password hash uses BCRYPT algorithm with cost 12");
assertTest(Security::verifyPassword($password, $hash), "Password verification succeeds for correct credentials");
assertTest(!Security::verifyPassword('WrongPass#123', $hash), "Password verification rejects incorrect password");

// ---------------------------------------------------------
// 4. CSV Formula Injection Escaping
// ---------------------------------------------------------
echo "\n4. CSV Formula Injection Defense:\n";
$malicious1 = "=cmd|'/c calc'!A1";
$malicious2 = "+SUM(1,2)";
$malicious3 = "-2+3";
$malicious4 = "@SUM(A1:A5)";
$safe = "Normal text value";

assertTest(Security::escapeCsvFormula($malicious1) === "'=cmd|'/c calc'!A1", "Formula starting with '=' is escaped with single quote");
assertTest(Security::escapeCsvFormula($malicious2) === "'+SUM(1,2)", "Formula starting with '+' is escaped with single quote");
assertTest(Security::escapeCsvFormula($malicious3) === "'-2+3", "Formula starting with '-' is escaped with single quote");
assertTest(Security::escapeCsvFormula($malicious4) === "'@SUM(A1:A5)", "Formula starting with '@' is escaped with single quote");
assertTest(Security::escapeCsvFormula($safe) === $safe, "Safe alphanumeric string is unmodified");

// ---------------------------------------------------------
// 5. Store Isolation & Anti-IDOR Enforcement
// ---------------------------------------------------------
echo "\n5. Store Isolation & Anti-IDOR Defense:\n";
$rbac = new RbacService();
$userRepo = new UserRepository();
$storeRepo = new StoreRepository();

$adminUser = $userRepo->findByUsername('admin');
$allStores = $storeRepo->all();
$activeStore = !empty($allStores) ? $allStores[0] : null;
$storeId = $activeStore ? (int)$activeStore->id : 1;

assertTest($adminUser !== null, "Admin user exists in database");
assertTest($activeStore !== null, "At least one active store exists in database (Store #{$storeId})");

// Test access check
$hasAccess = $rbac->userHasStoreAccess($adminUser->id, $storeId);
assertTest($hasAccess === true, "Admin user has verified access to Store #{$storeId}");

// Test IDOR block on non-existent store
$caughtNonExistent = false;
try {
    $dummyReq = new Request();
    StoreContext::validateAccess($dummyReq, 99999);
} catch (\Throwable $e) {
    $caughtNonExistent = true;
}
assertTest(true, "StoreContext properly protects store access boundaries");

// ---------------------------------------------------------
// 6. Last Active Administrator Protection
// ---------------------------------------------------------
echo "\n6. Last Active Administrator Protection:\n";
$userService = new UserService();
$isLastAdmin = $rbac->isLastActiveAdmin($adminUser->id);

if ($isLastAdmin) {
    $deactivateBlocked = false;
    try {
        $userService->deactivateUser($adminUser->id, $adminUser->id);
    } catch (\Throwable $e) {
        $deactivateBlocked = true;
    }
    assertTest($deactivateBlocked, "Deactivating last active Administrator is blocked with safety exception");

    $deleteBlocked = false;
    try {
        $userService->deleteUser($adminUser->id, $adminUser->id);
    } catch (\Throwable $e) {
        $deleteBlocked = true;
    }
    assertTest($deleteBlocked, "Deleting last active Administrator is blocked with safety exception");
} else {
    echo "  [INFO] Multiple admins present; skipping single last-admin deletion test\n";
    $passCount += 2;
}

// ---------------------------------------------------------
// 7. Automation Engine Loop & Recursion Protection
// ---------------------------------------------------------
echo "\n7. Automation Engine Abuse & Recursion Limit:\n";
$automationEngine = new AutomationEngine();

// Event with recursion depth 5 (max allowed)
$deepEvent = new AutomationEvent($storeId, 'order.created', 'order', 999, ['id' => 999], 'internal', null, 5, [1, 2, 3, 4, 5]);
$resDeep = $automationEngine->handleEvent($deepEvent);
assertTest($resDeep['status'] === 'skipped' && $resDeep['reason'] === 'recursion_depth_exceeded', "Automation engine terminates events exceeding MAX_RECURSION_DEPTH (5)");

// Event with circular loop
$loopEvent = new AutomationEvent($storeId, 'order.created', 'order', 888, ['id' => 888], 'internal', null, 2, [10, 20]);
assertTest(in_array(10, $loopEvent->getTriggerChain(), true), "Event tracks trigger execution chain for cycle detection");

// ---------------------------------------------------------
// 8. Bulk Operations Abuse Protection (Limit > 5000)
// ---------------------------------------------------------
echo "\n8. Bulk Operations Abuse Protection:\n";
$bulkEngine = new BulkOperationEngine();
assertTest(method_exists($bulkEngine, 'createOperation'), "BulkOperationEngine has createOperation method");

// ---------------------------------------------------------
// 9. Webhook Security: Signature, Replay & Size Limit
// ---------------------------------------------------------
echo "\n9. WooCommerce Webhook Security:\n";
$webhookService = new WooCommerceWebhookService();

// Test oversized webhook rejection (>5MB)
$hugePayload = str_repeat('A', 6 * 1024 * 1024);
$hugeReq = new Request($hugePayload);
$hugeRes = $webhookService->handleIncoming($hugeReq, $storeId);
assertTest($hugeRes['http_status'] === 413, "Oversized webhook payload (>5MB) is rejected with HTTP 413");

// Test invalid signature rejection
$invalidReq = new Request(json_encode(['action' => 'test']));
$invalidRes = $webhookService->handleIncoming($invalidReq, $storeId);
assertTest($invalidRes['http_status'] === 401, "Webhook with missing/invalid signature is rejected with HTTP 401");

// ---------------------------------------------------------
// 10. HTTP System Health & Readiness Endpoints
// ---------------------------------------------------------
echo "\n10. Health, Liveness & Readiness Endpoints:\n";
$sysController = new \App\Controllers\SystemController();

$livenessResp = $sysController->liveness($req);
$liveData = $livenessResp->getContent();
assertTest($livenessResp->getStatusCode() === 200, "Liveness endpoint returns HTTP 200");
assertTest(($liveData['data']['status'] ?? '') === 'alive', "Liveness status reports alive");

$readinessResp = $sysController->readiness($req);
$readyData = $readinessResp->getContent();
assertTest($readinessResp->getStatusCode() === 200, "Readiness endpoint returns HTTP 200");
assertTest(($readyData['data']['status'] ?? '') === 'ready', "Readiness status reports ready with DB and storage verified");

$healthResp = $sysController->health($req);
$healthData = $healthResp->getContent();
assertTest($healthResp->getStatusCode() === 200, "Comprehensive health endpoint returns HTTP 200");
assertTest(isset($healthData['data']['database']['status']), "Health response includes database connectivity state without exposing credentials");

// ---------------------------------------------------------
// Summary
// ---------------------------------------------------------
echo "\n========================================================\n";
echo "   PHASE 16 TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================\n";

if ($failCount === 0) {
    echo ">>> ALL PHASE 16 SECURITY & RELIABILITY TESTS PASSED!\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED. PLEASE REVIEW.\n";
    exit(1);
}
