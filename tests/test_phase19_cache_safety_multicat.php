<?php

declare(strict_types=1);

/**
 * Phase 19: Local Cache, Controlled Sync, Multi-category Bulk, and JSON Price Safety Test Suite
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
\App\Support\Config::setPath(__DIR__ . '/../config');
\App\Support\Logger::setLogDir(__DIR__ . '/../storage/logs');

use App\Database\Connection;
use App\Services\LocalSyncService;
use App\Services\Bulk\PriceBackupService;
use App\Services\Bulk\ProductBulkHandler;
use App\Services\Bulk\BulkOperationEngine;

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

echo "=== شروع آزمون‌های خودکار فاز ۱۹ (Local Cache, Multi-category, Price Safety) ===\n\n";

$pdo = Connection::get();
$syncService = new LocalSyncService();
$backupService = new PriceBackupService();
$productHandler = new ProductBulkHandler();
$engine = new BulkOperationEngine();

// Get or insert a test store
$stmt = $pdo->query("SELECT id FROM stores LIMIT 1");
$store = $stmt->fetch();
$storeId = (int) ($store['id'] ?? 1);

// Get admin user
$userStmt = $pdo->query("SELECT id FROM users LIMIT 1");
$user = $userStmt->fetch();
$userId = (int) ($user['id'] ?? 1);

// ==========================================
// TEST 1: Local Cache & Store Isolation
// ==========================================
echo "1. آزمون Local Cache و ایزولاسیون فروشگاه‌ها:\n";

// Ensure second store exists in stores table for strict FK integrity
$otherStore = $pdo->query("SELECT id FROM stores WHERE id != {$storeId} LIMIT 1")->fetch();
if (!$otherStore) {
    $pdo->exec("INSERT INTO stores (name, url, consumer_key, consumer_secret, status, created_at) VALUES ('فروشگاه دوم آزمایشی', 'https://store2.test', 'ck_test', 'cs_test', 'active', NOW())");
    $otherStoreId = (int)$pdo->lastInsertId();
} else {
    $otherStoreId = (int)$otherStore['id'];
}

$pdo->exec("DELETE FROM wc_local_products WHERE store_id IN ({$storeId}, {$otherStoreId})");
$pdo->exec("DELETE FROM wc_local_sync_meta WHERE store_id IN ({$storeId}, {$otherStoreId})");

// Ingest sample product into storeId
$sampleProd1 = [
    'id' => 101,
    'name' => 'گوشی هوشمند سامسونگ مدل آزمایشی',
    'type' => 'simple',
    'status' => 'publish',
    'regular_price' => '15000000',
    'sale_price' => '14200000',
    'price' => '14200000',
    'manage_stock' => true,
    'stock_quantity' => 12,
    'stock_status' => 'instock',
    'categories' => [
        ['id' => 10, 'name' => 'موبایل'],
        ['id' => 20, 'name' => 'لوازم دیجیتال'],
    ],
    'date_modified' => date('Y-m-d H:i:s'),
];

$syncService->upsertLocalProduct($storeId, $sampleProd1);

// Ingest sample product into otherStoreId
$sampleProdOther = [
    'id' => 202,
    'name' => 'محصول فروشگاه دیگر',
    'type' => 'simple',
    'status' => 'publish',
    'regular_price' => '500000',
    'sale_price' => '',
    'price' => '500000',
    'manage_stock' => false,
    'stock_quantity' => null,
    'stock_status' => 'instock',
    'categories' => [['id' => 99, 'name' => 'متفرقه']],
    'date_modified' => date('Y-m-d H:i:s'),
];
$syncService->upsertLocalProduct($otherStoreId, $sampleProdOther);

// Verify query on storeId does NOT return otherStoreId items
$localRes1 = $syncService->getLocalProducts($storeId, ['page' => 1, 'per_page' => 50]);
$ids1 = array_column($localRes1['data'], 'id');
assertTest('ایزولاسیون کامل فروشگاه: محصولات فروشگاه دیگر در فروشگاه جاری ظاهر نمی‌شوند',
    in_array(101, $ids1, true) && !in_array(202, $ids1, true),
    'محصولات فروشگاه دیگر نباید نشت کنند'
);

// Verify query on otherStoreId
$localResOther = $syncService->getLocalProducts($otherStoreId, ['page' => 1, 'per_page' => 50]);
$idsOther = array_column($localResOther['data'], 'id');
assertTest('ایزولاسیون کامل فروشگاه: داده‌های فروشگاه دوم مستقل هستند',
    in_array(202, $idsOther, true) && !in_array(101, $idsOther, true),
    'داده‌ها تفکیک نشده‌اند'
);

// ==========================================
// TEST 2: Sync Status Metadata & Lock Management
// ==========================================
echo "\n2. آزمون وضعیت Sync Metadata، قفل‌گذاری و انقضای قفل:\n";

// Upsert a sync meta state directly or via state
$syncMetaStmt = $pdo->prepare("
    INSERT INTO wc_local_sync_meta (store_id, entity_type, status, last_sync_started_at, records_count, is_complete, updated_at)
    VALUES (:store_id, 'products', 'running', NOW(), 1, 0, NOW())
    ON DUPLICATE KEY UPDATE status = 'running', last_sync_started_at = NOW(), records_count = 1
");
$syncMetaStmt->execute([':store_id' => $storeId]);

$meta = $syncService->getSyncState($storeId, 'products');
assertTest('متادیتای همگام‌سازی دارای وضعیت جاری و ساختار استاندارد است',
    isset($meta['store_id'], $meta['status']) && $meta['status'] === 'running',
    'متادیتا یافت نشد یا وضعیت صحیح نیست'
);

// Check stale recovery: update last_sync_started_at to 20 minutes ago
$oldTime = date('Y-m-d H:i:s', time() - 1200);
$pdo->exec("UPDATE wc_local_sync_meta SET last_sync_started_at = '{$oldTime}' WHERE store_id = {$storeId} AND entity_type = 'products'");

$metaStalled = $syncService->getSyncState($storeId, 'products');
assertTest('بازیابی قفل معلق: عملیات متوقف‌شده قدیمی به عنوان شکست‌خورده شناسایی و آزاد می‌شود',
    $metaStalled['status'] === 'failed',
    'عملیات معلق باید به عنوان خطا بازیابی شود'
);

// ==========================================
// TEST 3: Multi-Category Filter & Deduplication
// ==========================================
echo "\n3. آزمون انتخاب چند دسته‌بندی و حذف تکرار کالاهای همپوشان:\n";

// Add another product that belongs to category 10 only, and one to category 20 only
$prod2 = [
    'id' => 102,
    'name' => 'لوازم جانبی موبایل (فقط دسته ۱۰)',
    'type' => 'simple',
    'status' => 'publish',
    'regular_price' => '200000',
    'sale_price' => '',
    'categories' => [['id' => 10, 'name' => 'موبایل']],
];
$prod3 = [
    'id' => 103,
    'name' => 'تبلت دیجیتال (فقط دسته ۲۰)',
    'type' => 'simple',
    'status' => 'publish',
    'regular_price' => '8000000',
    'sale_price' => '',
    'categories' => [['id' => 20, 'name' => 'لوازم دیجیتال']],
];
$syncService->upsertLocalProduct($storeId, $prod2);
$syncService->upsertLocalProduct($storeId, $prod3);

// Query with multiple categories: [10, 20]
// Product 101 belongs to BOTH 10 and 20. It must appear EXACTLY ONCE!
$multiCatRes = $syncService->getLocalProducts($storeId, ['categories' => [10, 20]]);
$multiIds = array_column($multiCatRes['data'], 'id');
$counts = array_count_values($multiIds);

assertTest('انتخاب چند دسته‌بندی: کالاهای هر دو دسته بازیابی شدند',
    in_array(101, $multiIds, true) && in_array(102, $multiIds, true) && in_array(103, $multiIds, true),
    'برخی کالاها یافت نشدند'
);

assertTest('حذف تکرار (Deduplication): کالای عضو هر دو دسته فقط ۱ بار در نتایج قرار دارد',
    isset($counts[101]) && $counts[101] === 1,
    'کالای همپوشان چند بار تکرار شده است: ' . ($counts[101] ?? 0)
);

// ==========================================
// TEST 4: JSON Price Backup Creation & Safety
// ==========================================
echo "\n4. آزمون تهیه نسخه پشتیبان JSON پیش از تغییر قیمت:\n";

$targetProducts = [
    [
        'id' => 101,
        'parent_id' => 0,
        'type' => 'simple',
        'name' => 'گوشی سامسونگ',
        'regular_price' => '15000000',
        'sale_price' => '14200000',
    ],
    [
        'id' => 102,
        'parent_id' => 0,
        'type' => 'simple',
        'name' => 'لوازم جانبی',
        'regular_price' => '200000',
        'sale_price' => '', // empty sale price, not zero!
    ],
];

$storeRepo = new \App\Repositories\StoreRepository($pdo);
$storeModel = $storeRepo->findById($storeId);

$backupResult = $backupService->createBackup(
    $storeModel,
    $userId,
    1001,
    'increase_price_percent',
    $targetProducts
);

assertTest('تولید نسخه پشتیبان JSON موفقیت‌آمیز است',
    isset($backupResult['backup_uid'], $backupResult['backup_id']),
    'شناسه نسخه پشتیبان ایجاد نشد'
);

// Verify Backup Content & Structure
$backupDataWrap = $backupService->getBackupFileContent($storeId, $backupResult['backup_uid']);
$decoded = json_decode($backupDataWrap['content'] ?? '', true);

assertTest('ساختار نسخه پشتیبان دارای schema_version 1.0 است',
    ($decoded['schema_version'] ?? '') === '1.0',
    'نسخه اسکیما نامعتبر است'
);

assertTest('قیمت‌ها به شکل رشته با دقت کامل ذخیره شده و قیمت تخفیفی خالی حفظ شده است',
    $decoded['items'][0]['regular_price'] === '15000000' &&
    $decoded['items'][0]['sale_price'] === '14200000' &&
    $decoded['items'][1]['sale_price'] === '',
    'قیمت‌ها به شکل رشته ذخیره نشدند یا قیمت خالی صفر شده است'
);

assertTest('هش اعتبارسنجی (Checksum) در فایل ذخیره شده است',
    !empty($decoded['checksum']),
    'چک‌سام یافت نشد'
);

// ==========================================
// TEST 5: JSON Tamper Detection & Store Validation
// ==========================================
echo "\n5. آزمون اعتبارسنجی امنیتی فایل Backup و رد داده‌های نامعتبر:\n";

// Validate authentic backup
$validCheck = $backupService->validateBackupSchemaAndIntegrity($decoded);
assertTest('فایل سالم و معتبر توسط سیستم تأیید می‌شود', $validCheck['valid'] === true, $validCheck['error'] ?? '');

// Tamper with prices / checksum
$tampered = $decoded;
$tampered['checksum'] = 'fake_tampered_checksum_value_123';
$tamperCheck = $backupService->validateBackupSchemaAndIntegrity($tampered);
assertTest('تشخیص دستکاری: فایل با محتوای تغییریافته بلافاصله رد می‌شود',
    $tamperCheck['valid'] === false && str_contains($tamperCheck['error'], 'هش'),
    'فایل دستکاری شده باید رد شود'
);

// Wrong store ID rejection
$wrongStoreBackup = $decoded;
$wrongStoreBackup['store_id'] = 77777; // different store
$wrongRejected = false;
try {
    $backupService->previewRestore($storeModel, $wrongStoreBackup);
} catch (\Throwable $e) {
    if (str_contains($e->getMessage(), 'فروشگاه')) {
        $wrongRejected = true;
    }
}
assertTest('رد فایل متعلق به فروشگاه دیگر: بازیابی مسدود می‌شود',
    $wrongRejected === true,
    'فایل فروشگاه دیگر نباید پذیرفته شود'
);

// ==========================================
// TEST 6: Non-Compounding Target Price Calculation
// ==========================================
echo "\n6. آزمون عدم اِعمال چندباره درصد افزایش قیمت (Idempotency):\n";

$mutation = $productHandler->calculateProductMutation(
    $targetProducts[0],
    'increase_price_percent',
    ['value' => 10]
);

$calculatedReg = $mutation['payload']['regular_price'] ?? '';
assertTest('محاسبه قیمت هدف ۱۰٪: از ۱۵,۰۰۰,۰۰۰ به ۱۶,۵۰۰,۰۰۰ محاسبه شد',
    $calculatedReg === '16500000',
    'قیمت محاسبه شده: ' . $calculatedReg
);

// If executed again with precomputed target, it stays 16500000 and does NOT increase by 10% again!
$retryMutation = $productHandler->calculateProductMutation(
    $targetProducts[0],
    'increase_price_percent',
    [
        'value' => 10,
        'precomputed_targets' => [
            101 => ['regular_price' => '16500000', 'sale_price' => '']
        ]
    ]
);

$retryReg = $retryMutation['payload']['regular_price'] ?? '';
assertTest('عدم تکرار درصد در Retry: قیمت هدف از پیش تعیین‌شده حفظ می‌شود و دوباره ۱۰٪ افزایش نمی‌یابد',
    $retryReg === '16500000',
    'قیمت مجدداً محاسبه شد: ' . $retryReg
);

// Clean up test data
$pdo->exec("DELETE FROM wc_local_products WHERE store_id IN ({$storeId}, {$otherStoreId})");
$pdo->exec("DELETE FROM wc_local_sync_meta WHERE store_id IN ({$storeId}, {$otherStoreId})");
$pdo->exec("DELETE FROM price_backups WHERE store_id IN ({$storeId}, {$otherStoreId})");

echo "\n==========================================\n";
echo "نتیجه آزمون‌ها: {$passed} موفق | {$failed} ناموفق\n";
echo "==========================================\n";

if ($failed > 0) {
    exit(1);
}
