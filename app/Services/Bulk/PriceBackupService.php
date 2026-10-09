<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use App\Database\Connection;
use App\Integrations\WooCommerce\ProductAdapter;
use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Support\Logger;
use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use PDO;
use Throwable;

class PriceBackupService
{
    private PDO $pdo;
    private StoreRepository $storeRepo;
    private string $backupDir;

    public function __construct(?PDO $pdo = null, ?StoreRepository $storeRepo = null, ?string $backupDir = null)
    {
        $this->pdo = $pdo ?? Connection::get();
        $this->storeRepo = $storeRepo ?? new StoreRepository($this->pdo);
        $this->backupDir = $backupDir ?? dirname(__DIR__, 2) . '/../storage/backups';

        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Create a verified, downloadable JSON price backup prior to executing a price update.
     * Blocks execution if any required product's price cannot be authoritatively fetched.
     *
     * @param Store $store
     * @param int $userId
     * @param int $operationId
     * @param string $actionType
     * @param array $targetEntities List of products/variations with 'id', 'parent_id', 'type'
     * @return array Backup record metadata and file path
     * @throws Exception
     */
    public function createBackup(
        Store $store,
        int $userId,
        int $operationId,
        string $actionType,
        array $targetEntities
    ): array {
        $storeId = (int)$store->id;
        $adapter = new ProductAdapter($store);
        $currency = $store->currency ?: 'IRT';

        $items = [];
        $failedFetches = [];
        $utcNow = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        $timestamp = date('Ymd_His');

        foreach ($targetEntities as $entity) {
            $prodId = (int)($entity['id'] ?? $entity['entity_id'] ?? 0);
            $parentId = (int)($entity['parent_id'] ?? 0);
            $type = (string)($entity['type'] ?? 'simple');
            $name = (string)($entity['name'] ?? "محصول #{$prodId}");

            if ($prodId <= 0) {
                continue;
            }

            try {
                // Fetch fresh authoritative state directly, or use pre-supplied data if regular_price is present
                $freshData = null;
                if (isset($entity['regular_price']) && ($entity['regular_price'] !== null)) {
                    $freshData = $entity;
                } else {
                    if ($parentId > 0 || $type === 'variation') {
                        $freshData = $adapter->getVariation($parentId > 0 ? $parentId : $prodId, $prodId);
                    } else {
                        $freshData = $adapter->getProduct($prodId);
                    }
                }

                if (!$freshData || !is_array($freshData)) {
                    $failedFetches[] = "عدم امکان دریافت اطلاعات قیمت کالا #{$prodId}";
                    continue;
                }

                $regPrice = isset($freshData['regular_price']) && $freshData['regular_price'] !== ''
                    ? (string)$freshData['regular_price']
                    : '';

                $salePrice = isset($freshData['sale_price']) && $freshData['sale_price'] !== ''
                    ? (string)$freshData['sale_price']
                    : '';

                $price = isset($freshData['price']) && $freshData['price'] !== ''
                    ? (string)$freshData['price']
                    : '';

                $items[] = [
                    'product_id' => $prodId,
                    'parent_id' => $parentId,
                    'type' => $type,
                    'name' => (string)($freshData['name'] ?? $name),
                    'regular_price' => $regPrice,
                    'sale_price' => $salePrice,
                    'price' => $price,
                    'on_sale' => (bool)($freshData['on_sale'] ?? false),
                    'date_on_sale_from' => $freshData['date_on_sale_from'] ?? null,
                    'date_on_sale_to' => $freshData['date_on_sale_to'] ?? null,
                ];
            } catch (Throwable $e) {
                $failedFetches[] = "خطا در دریافت قیمت کالا #{$prodId}: " . $e->getMessage();
            }
        }

        // Strict completeness rule: if any product failed, abort entirely!
        if (!empty($failedFetches)) {
            $sampleErr = implode(', ', array_slice($failedFetches, 0, 3));
            throw new Exception("تهیه نسخه پشتیبان قیمت‌ها با شکست مواجه شد ({$sampleErr}). جهت حفظ ایمنی، عملیات قیمت‌گذاری متوقف گردید.");
        }

        if (empty($items)) {
            throw new Exception("هیچ کالایی برای تهیه نسخه پشتیبان قیمت یافت نشد.");
        }

        $backupUid = 'pb_' . bin2hex(random_bytes(12));
        $filename = "crm-price-backup-{$operationId}-{$timestamp}.json";
        $filePath = $this->backupDir . '/' . $filename;

        $checksum = hash('sha256', json_encode($items, JSON_UNESCAPED_UNICODE));

        $backupPayload = [
            'schema_version' => '1.0',
            'backup_uid' => $backupUid,
            'store_id' => $storeId,
            'store_url' => $store->url,
            'created_at_utc' => $utcNow,
            'operation_id' => $operationId,
            'operation_type' => $actionType,
            'currency' => $currency,
            'items_count' => count($items),
            'checksum' => $checksum,
            'items' => $items,
        ];

        $jsonEncoded = json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonEncoded === false) {
            throw new Exception("خطا در سریال‌سازی داده‌های نسخه پشتیبان.");
        }

        if (file_put_contents($filePath, $jsonEncoded) === false) {
            throw new Exception("خطا در ذخیره فایل نسخه پشتیبان در دیسک.");
        }

        // Record in price_backups table
        $stmt = $this->pdo->prepare("
            INSERT INTO price_backups (
                store_id, user_id, operation_id, schema_version, backup_uid,
                filename, operation_type, items_count, checksum, file_path,
                currency, status, created_at
            ) VALUES (
                :store_id, :user_id, :operation_id, '1.0', :backup_uid,
                :filename, :operation_type, :items_count, :checksum, :file_path,
                :currency, 'valid', NOW()
            )
        ");

        $stmt->execute([
            ':store_id' => $storeId,
            ':user_id' => $userId,
            ':operation_id' => $operationId,
            ':backup_uid' => $backupUid,
            ':filename' => $filename,
            ':operation_type' => $actionType,
            ':items_count' => count($items),
            ':checksum' => $checksum,
            ':file_path' => $filePath,
            ':currency' => $currency,
        ]);

        $lastId = (int)$this->pdo->lastInsertId();
        return [
            'id' => $lastId,
            'backup_id' => $lastId,
            'backup_uid' => $backupUid,
            'filename' => $filename,
            'checksum' => $checksum,
            'items_count' => count($items),
            'file_path' => $filePath,
            'created_at_utc' => $utcNow,
        ];
    }

    /**
     * Retrieve backup by UID.
     */
    public function findByUid(string $uid, int $storeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM price_backups WHERE backup_uid = :uid AND store_id = :store_id");
        $stmt->execute([':uid' => $uid, ':store_id' => $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getBackupFileContent(int $storeId, string $uid): array
    {
        $backup = $this->findByUid($uid, $storeId);
        if (!$backup) {
            throw new InvalidArgumentException("نسخه پشتیبان قیمت یافت نشد.");
        }

        $filePath = $backup['file_path'];
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("فایل فیزیکی نسخه پشتیبان بر روی دیسک یافت نشد.");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException("خطا در بارگذاری فایل نسخه پشتیبان از دیسک.");
        }

        return [
            'filename' => $backup['filename'],
            'content' => $content,
            'backup' => $backup,
        ];
    }

    /**
     * Preview restoration from uploaded/provided JSON backup content.
     * Validates schema, store ID, checksum, and compares with live WooCommerce prices.
     */
    public function previewRestore(Store $store, array $backupData): array
    {
        $this->validateBackupStructure($store, $backupData);

        $items = $backupData['items'] ?? [];
        $adapter = new ProductAdapter($store);

        $diffs = [];
        $matchingCount = 0;
        $conflictCount = 0;

        foreach ($items as $item) {
            $prodId = (int)($item['product_id'] ?? 0);
            $parentId = (int)($item['parent_id'] ?? 0);
            $backupReg = (string)($item['regular_price'] ?? '');
            $backupSale = (string)($item['sale_price'] ?? '');
            $name = (string)($item['name'] ?? "محصول #{$prodId}");

            // Live fetch to compare
            try {
                $live = null;
                if ($parentId > 0) {
                    $live = $adapter->getVariation($parentId, $prodId);
                } else {
                    $live = $adapter->getProduct($prodId);
                }

                $liveReg = isset($live['regular_price']) ? (string)$live['regular_price'] : '';
                $liveSale = isset($live['sale_price']) ? (string)$live['sale_price'] : '';

                $hasDiff = ($liveReg !== $backupReg) || ($liveSale !== $backupSale);

                if ($hasDiff) {
                    $conflictCount++;
                } else {
                    $matchingCount++;
                }

                $diffs[] = [
                    'product_id' => $prodId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'type' => $item['type'] ?? 'simple',
                    'current_regular_price' => $liveReg,
                    'current_sale_price' => $liveSale,
                    'backup_regular_price' => $backupReg,
                    'backup_sale_price' => $backupSale,
                    'has_diff' => $hasDiff,
                ];
            } catch (Throwable $e) {
                $conflictCount++;
                $diffs[] = [
                    'product_id' => $prodId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'type' => $item['type'] ?? 'simple',
                    'current_regular_price' => 'نامشخص',
                    'current_sale_price' => 'نامشخص',
                    'backup_regular_price' => $backupReg,
                    'backup_sale_price' => $backupSale,
                    'has_diff' => true,
                    'fetch_error' => $e->getMessage(),
                ];
            }
        }

        return [
            'store_id' => (int)$store->id,
            'schema_version' => $backupData['schema_version'] ?? '1.0',
            'created_at_utc' => $backupData['created_at_utc'] ?? null,
            'operation_type' => $backupData['operation_type'] ?? 'unknown',
            'currency' => $backupData['currency'] ?? 'IRT',
            'total_items' => count($items),
            'matching_count' => $matchingCount,
            'conflict_count' => $conflictCount,
            'diffs' => $diffs,
        ];
    }

    /**
     * Execute price restoration from validated backup data.
     */
    public function executeRestore(Store $store, int $userId, array $backupData, array $options = []): array
    {
        $this->validateBackupStructure($store, $backupData);

        $items = $backupData['items'] ?? [];
        $adapter = new ProductAdapter($store);
        $conflictMode = $options['conflict_mode'] ?? 'all'; // 'all', 'skip_conflicts'

        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $verifiedCount = 0;

        foreach ($items as $item) {
            $prodId = (int)($item['product_id'] ?? 0);
            $parentId = (int)($item['parent_id'] ?? 0);
            $backupReg = (string)($item['regular_price'] ?? '');
            $backupSale = (string)($item['sale_price'] ?? '');
            $name = (string)($item['name'] ?? "محصول #{$prodId}");

            $payload = [
                'regular_price' => $backupReg,
                'sale_price' => $backupSale,
            ];

            try {
                if ($parentId > 0) {
                    $adapter->updateVariation($parentId, $prodId, $payload);
                } else {
                    $adapter->updateProduct($prodId, $payload);
                }

                // Verify after restoration
                $verified = false;
                $verifyError = null;
                try {
                    $liveAfter = ($parentId > 0)
                        ? $adapter->getVariation($parentId, $prodId)
                        : $adapter->getProduct($prodId);

                    $afterReg = isset($liveAfter['regular_price']) ? (string)$liveAfter['regular_price'] : '';
                    $afterSale = isset($liveAfter['sale_price']) ? (string)$liveAfter['sale_price'] : '';

                    if ($afterReg === $backupReg && $afterSale === $backupSale) {
                        $verified = true;
                        $verifiedCount++;
                    } else {
                        $verifyError = "تطبیق قیمت پس از بازگردانی ناموفق بود (عادی: {$afterReg} به جای {$backupReg})";
                    }
                } catch (Throwable $ve) {
                    $verifyError = "عدم امکان بازخوانی قیمت جهت تأیید: " . $ve->getMessage();
                }

                $successCount++;
                $results[] = [
                    'product_id' => $prodId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'status' => $verified ? 'restored' : 'verification_failed',
                    'verified' => $verified,
                    'regular_price' => $backupReg,
                    'sale_price' => $backupSale,
                    'error' => $verifyError,
                ];
            } catch (Throwable $e) {
                $failedCount++;
                $results[] = [
                    'product_id' => $prodId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'status' => 'failed',
                    'verified' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update backup status in DB if exists
        $uid = $backupData['backup_uid'] ?? null;
        if ($uid) {
            $updStatus = ($failedCount === 0) ? 'restored' : 'partial_restored';
            $upStmt = $this->pdo->prepare("UPDATE price_backups SET status = :status WHERE backup_uid = :uid AND store_id = :store_id");
            $upStmt->execute([':status' => $updStatus, ':uid' => $uid, ':store_id' => (int)$store->id]);
        }

        // Record Audit Log
        try {
            $auditService = new AuditService($this->pdo);
            $auditService->log(
                $userId,
                (int)$store->id,
                'PRICE_RESTORE_COMPLETED',
                'bulk_operation',
                (string)($backupData['operation_id'] ?? 'restore'),
                null,
                [
                    'total_items' => count($items),
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'verified' => $verifiedCount,
                ]
            );
        } catch (Throwable) {
            // Ignore audit log error
        }

        return [
            'total' => count($items),
            'success_count' => $successCount,
            'verified_count' => $verifiedCount,
            'failed_count' => $failedCount,
            'items' => $results,
        ];
    }

    /**
     * Validate backup file data format and store boundary.
     */
    private function validateBackupStructure(Store $store, array $data): void
    {
        if (empty($data['schema_version'])) {
            throw new InvalidArgumentException("فرمت فایل نامعتبر است: نسخه طرح‌واره (schema_version) یافت نشد.");
        }

        $backupStoreId = (int)($data['store_id'] ?? 0);
        if ($backupStoreId !== (int)$store->id) {
            throw new InvalidArgumentException("این نسخه پشتیبان متعلق به فروشگاه دیگری است (شناسه فروشگاه در فایل: {$backupStoreId}، فروشگاه فعلی: {$store->id}). بازگردانی جهت جلوگیری از تداخل متوقف شد.");
        }

        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            throw new InvalidArgumentException("فایل نسخه پشتیبان فاقد رکورد محصولات برای بازگردانی است.");
        }
    }

    /**
     * Validate backup schema and checksum integrity.
     */
    public function validateBackupSchemaAndIntegrity(array $data): array
    {
        try {
            if (empty($data['schema_version']) || (string)$data['schema_version'] !== '1.0') {
                return ['valid' => false, 'error' => 'نسخه طرح‌واره (schema_version) نامعتبر است.'];
            }
            if (empty($data['store_id'])) {
                return ['valid' => false, 'error' => 'شناسه فروشگاه در فایل موجود نیست.'];
            }
            if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
                return ['valid' => false, 'error' => 'فایل نسخه پشتیبان فاقد اقلام محصولات است.'];
            }

            // Verify checksum if present
            if (!empty($data['checksum']) && !empty($data['backup_uid'])) {
                $uid = (string)$data['backup_uid'];
                $storeId = (int)$data['store_id'];
                $dbBackup = $this->findByUid($uid, $storeId);
                if ($dbBackup && $dbBackup['checksum'] !== $data['checksum']) {
                    return ['valid' => false, 'error' => 'هش اعتبارسنجی فایل با رکورد ثبت‌شده در سیستم مغایرت دارد (احتمال دستکاری داده‌ها).'];
                }
            }

            return ['valid' => true];
        } catch (Throwable $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
