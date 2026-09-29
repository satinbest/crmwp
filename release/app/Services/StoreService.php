<?php

namespace App\Services;

use App\Integrations\WooCommerce\DemoWooCommerceAdapter;
use App\Integrations\WooCommerce\WooCommerceAdapter;
use App\Integrations\WooCommerce\WooCommerceAdapterFactory;
use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\Store;
use App\Repositories\StoreRepository;
use App\Repositories\SyncLogRepository;
use App\Repositories\WebhookLogRepository;
use App\Validators\StoreValidator;
use App\Support\Logger;
use Exception;

class StoreService
{
    private StoreRepository $repository;
    private AuditService $auditService;
    private RbacService $rbacService;
    private ?WebhookLogRepository $webhookLogRepo = null;
    private ?SyncLogRepository $syncLogRepo = null;

    public function __construct(
        ?StoreRepository $repository = null,
        ?AuditService $auditService = null,
        ?RbacService $rbacService = null
    ) {
        $this->repository = $repository ?? new StoreRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->rbacService = $rbacService ?? new RbacService();
    }

    private function getWebhookLogRepo(): WebhookLogRepository
    {
        if ($this->webhookLogRepo === null) {
            $this->webhookLogRepo = new WebhookLogRepository();
        }
        return $this->webhookLogRepo;
    }

    private function getSyncLogRepo(): SyncLogRepository
    {
        if ($this->syncLogRepo === null) {
            $this->syncLogRepo = new SyncLogRepository();
        }
        return $this->syncLogRepo;
    }

    public function listStores(): array
    {
        return array_map(fn($s) => $s->toArray(true), $this->repository->all());
    }

    public function listStoresForUser(int $userId): array
    {
        $isAdmin = $this->rbacService->userHasRole($userId, 'admin') || $this->rbacService->userHasRole($userId, 'Admin');
        $stores = $this->repository->listForUser($userId, $isAdmin);
        return array_map(fn($s) => $s->toArray(true), $stores);
    }

    public function getStore(int $id): ?array
    {
        $store = $this->repository->findById($id);
        return $store ? $store->toArray(true) : null;
    }

    public function createStore(array $data, int $userId): array
    {
        $isDemo = !empty($data['is_demo']) || str_starts_with($data['url'] ?? '', 'demo://') || str_starts_with($data['url'] ?? '', 'mock://');

        $validated = StoreValidator::validate($data, false);
        $url = $validated['normalized_url'] ?? $validated['url'];

        if ($isDemo) {
            $capabilities = [
                'rest_api_available' => true,
                'woocommerce_version' => '9.2.0',
                'wordpress_version' => '6.6.1',
                'hpos_enabled' => true,
                'currency' => $data['currency'] ?? 'IRR',
                'timezone' => $data['timezone'] ?? 'Asia/Tehran',
                'refunds' => true,
                'coupons' => true,
                'variations' => true,
            ];
            $testResult = [
                'woocommerce_version' => '9.2.0',
                'wordpress_version' => '6.6.1',
                'hpos_enabled' => true,
                'currency' => $data['currency'] ?? 'IRR',
                'timezone' => $data['timezone'] ?? 'Asia/Tehran',
            ];
            $status = $data['status'] ?? 'active';
        } else {
            // Test connection before saving real store
            $client = new WooCommerceClient($url, $validated['consumer_key'], $validated['consumer_secret']);
            $adapter = new WooCommerceAdapter($client);

            try {
                $testResult = $adapter->testConnection();
                $capabilities = $testResult['capabilities'] ?? [];
                $status = 'active';
            } catch (WooCommerceApiException $e) {
                $capabilities = [];
                $status = 'error';
                throw new Exception("امکان اتصال به فروشگاه وجود ندارد: " . $e->getMessage(), 422);
            }
        }

        $store = $this->repository->create([
            'name' => $validated['name'],
            'icon' => $data['icon'] ?? null,
            'url' => $url,
            'consumer_key' => $validated['consumer_key'],
            'consumer_secret' => $validated['consumer_secret'],
            'status' => $status,
            'is_demo' => $isDemo ? 1 : 0,
            'woocommerce_version' => $testResult['woocommerce_version'] ?? null,
            'wordpress_version' => $testResult['wordpress_version'] ?? null,
            'hpos_enabled' => $testResult['hpos_enabled'] ?? false,
            'currency' => $testResult['currency'] ?? ($data['currency'] ?? 'IRR'),
            'timezone' => $testResult['timezone'] ?? ($data['timezone'] ?? 'Asia/Tehran'),
            'capabilities' => $capabilities,
            'last_connection_check' => date('Y-m-d H:i:s'),
        ]);

        // Automatically assign creator to this store
        $this->repository->assignUserToStore((int)$store->id, $userId);

        $this->auditService->log(
            $userId,
            $store->id,
            'STORE_CREATED',
            'store',
            (string)$store->id,
            null,
            ['name' => $store->name, 'url' => $store->url]
        );

        return $store->toArray(true);
    }

    public function updateStore(int $id, array $data, int $userId): array
    {
        $store = $this->repository->findById($id);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        $validated = StoreValidator::validate($data, true);
        if (isset($validated['normalized_url'])) {
            $validated['url'] = $validated['normalized_url'];
        }

        if (array_key_exists('icon', $data)) {
            $validated['icon'] = $data['icon'];
        }
        if (array_key_exists('currency', $data)) {
            $validated['currency'] = $data['currency'];
        }
        if (array_key_exists('timezone', $data)) {
            $validated['timezone'] = $data['timezone'];
        }

        $oldValues = [
            'name' => $store->name,
            'url' => $store->url,
            'status' => $store->status,
        ];

        // If credentials or URL changed on a non-demo store, re-test connection
        if (!$store->isDemo() && (!empty($validated['consumer_key']) || !empty($validated['consumer_secret']) || isset($validated['url']))) {
            $url = $validated['url'] ?? $store->url;
            $creds = $store->getDecryptedCredentials();
            $key = !empty($validated['consumer_key']) ? $validated['consumer_key'] : $creds['consumer_key'];
            $secret = !empty($validated['consumer_secret']) ? $validated['consumer_secret'] : $creds['consumer_secret'];

            $client = new WooCommerceClient($url, $key, $secret);
            $adapter = new WooCommerceAdapter($client);
            try {
                $test = $adapter->testConnection();
                $validated['status'] = 'active';
                $this->repository->updateConnectionCheck(
                    $id,
                    'active',
                    $test['woocommerce_version'],
                    $test['wordpress_version'],
                    $test['hpos_enabled'],
                    $test['capabilities']
                );
            } catch (\Exception $e) {
                $validated['status'] = 'connection_error';
                $this->repository->updateConnectionCheck($id, 'connection_error', null, null, false, null, $e->getMessage());
            }
        }

        $updated = $this->repository->update($id, $validated);

        $this->auditService->log(
            $userId,
            $id,
            'STORE_UPDATED',
            'store',
            (string)$id,
            $oldValues,
            ['name' => $updated->name, 'url' => $updated->url, 'status' => $updated->status]
        );

        return $updated->toArray(true);
    }

    public function toggleStoreStatus(int $id, string $status, int $userId): array
    {
        $store = $this->repository->findById($id);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        $allowed = ['active', 'inactive', 'disabled'];
        if (!in_array($status, $allowed, true)) {
            throw new Exception("وضعیت ارسالی نامعتبر است.", 422);
        }

        // Section 28: Prevent disabling the last active store
        if ($status !== 'active' && $store->status === 'active') {
            $activeCount = $this->repository->countActiveStores();
            if ($activeCount <= 1) {
                throw new Exception("امکان غیرفعال کردن تنها فروشگاه فعال سامانه وجود ندارد. حداقل یک فروشگاه باید فعال باقی بماند.", 422);
            }
        }

        $updated = $this->repository->update($id, ['status' => $status]);

        $this->auditService->log(
            $userId,
            $id,
            'STORE_STATUS_CHANGED',
            'store',
            (string)$id,
            ['status' => $store->status],
            ['status' => $status]
        );

        return $updated->toArray(true);
    }

    public function deleteStore(int $id, int $userId): bool
    {
        $store = $this->repository->findById($id);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        // Section 28: Prevent deleting the last active store
        $activeCount = $this->repository->countActiveStores();
        if ($store->status === 'active' && $activeCount <= 1) {
            throw new Exception("امکان حذف آخرین فروشگاه فعال سامانه وجود ندارد. ابتدا فروشگاه دیگری را فعال نمایید.", 422);
        }

        // Section 27: Get CRM data count for auditing
        $crmCounts = $this->repository->getStoreCrmDataCounts($id);

        $this->auditService->log(
            $userId,
            $id,
            'STORE_DELETED',
            'store',
            (string)$id,
            [
                'name' => $store->name,
                'url' => $store->url,
                'crm_records_count' => $crmCounts['total_records'],
            ],
            null
        );

        return $this->repository->delete($id);
    }

    public function getStoreCrmCounts(int $storeId): array
    {
        $store = $this->repository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        return $this->repository->getStoreCrmDataCounts($storeId);
    }

    public function testUnsavedConnection(string $url, string $consumerKey, string $consumerSecret): array
    {
        if (str_starts_with($url, 'demo://') || str_starts_with($url, 'mock://')) {
            return [
                'connected' => true,
                'message' => 'اتصال دمو با موفقیت شبیه‌سازی شد.',
                'woocommerce_version' => '9.2.0',
                'wordpress_version' => '6.6.1',
                'hpos_enabled' => true,
                'currency' => 'IRR',
                'currency_symbol' => '﷼',
                'timezone' => 'Asia/Tehran',
                'is_demo' => true,
            ];
        }

        $normalizedUrl = StoreValidator::normalizeUrl($url);
        $client = new WooCommerceClient($normalizedUrl, $consumerKey, $consumerSecret);
        $adapter = new WooCommerceAdapter($client);

        return $adapter->testConnection();
    }

    public function testSavedConnection(int $storeId): array
    {
        $store = $this->repository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        $adapter = WooCommerceAdapterFactory::create($store);

        try {
            $result = $adapter->testConnection();
            $this->repository->updateConnectionCheck(
                $storeId,
                'active',
                $result['woocommerce_version'] ?? '9.2.0',
                $result['wordpress_version'] ?? '6.6.1',
                $result['hpos_enabled'] ?? true,
                $result['capabilities'] ?? []
            );
            return $result;
        } catch (WooCommerceApiException $e) {
            $this->repository->updateConnectionCheck(
                $storeId,
                'connection_error',
                $store->woocommerce_version,
                $store->wordpress_version,
                $store->hpos_enabled,
                $store->capabilities,
                $e->getMessage()
            );
            throw $e;
        }
    }

    public function getStoreCapabilities(int $storeId): array
    {
        $store = $this->repository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        if (!empty($store->capabilities)) {
            return $store->capabilities;
        }

        $adapter = WooCommerceAdapterFactory::create($store);
        $caps = $adapter->detectCapabilities();

        $this->repository->updateConnectionCheck(
            $storeId,
            ($caps['rest_api_available'] ?? true) ? 'active' : 'connection_error',
            $caps['woocommerce_version'] ?? null,
            $caps['wordpress_version'] ?? null,
            (bool)($caps['hpos_enabled'] ?? false),
            $caps
        );

        return $caps;
    }

    /**
     * Store Health summary (Section 26 & 42)
     */
    public function getStoreHealth(int $storeId): array
    {
        $store = $this->repository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        $wlRepo = new \App\Repositories\WebhookLogRepository();
        $slRepo = new \App\Repositories\SyncLogRepository();

        $whStats = $wlRepo->getHealthStats($storeId);
        $syncStats = $slRepo->getHealthStats($storeId);

        $apiHealthy = in_array($store->status, ['active'], true);
        $webhooksHealthy = ($whStats['health_status'] ?? 'healthy') !== 'failing';
        $syncHealthy = ($syncStats['failed'] ?? 0) === 0;

        return [
            'store_id' => $storeId,
            'store_name' => $store->name,
            'status' => $store->status,
            'is_demo' => $store->isDemo(),
            'connection' => [
                'status' => $store->status,
                'api_available' => $apiHealthy,
                'is_connected' => $apiHealthy,
                'last_check' => $store->last_connection_check,
                'last_error' => $store->last_error,
            ],
            'api' => [
                'status' => $apiHealthy ? 'connected' : 'error',
                'woocommerce_version' => $store->woocommerce_version ?: $store->wc_version,
                'wordpress_version' => $store->wordpress_version ?: $store->wp_version,
                'hpos_enabled' => (bool)$store->hpos_enabled,
                'currency' => $store->currency,
                'timezone' => $store->timezone,
            ],
            'webhooks' => [
                'status' => $whStats['health_status'] ?? 'healthy',
                'healthy' => $webhooksHealthy,
                'total' => $whStats['total_webhooks'] ?? 0,
                'failed_last_24h' => $whStats['failed_24h'] ?? 0,
                'last_delivery' => $whStats['last_webhook_at'] ?? null,
            ],
            'sync' => [
                'status' => $syncHealthy ? 'healthy' : 'failed',
                'healthy' => $syncHealthy,
                'total_syncs' => $syncStats['total_syncs'] ?? 0,
                'last_sync' => $syncStats['last_completed_at'] ?? null,
                'failed_last_24h' => $syncStats['failed'] ?? 0,
            ],
            'health_overall' => ($apiHealthy && $webhooksHealthy && $syncHealthy) ? 'healthy' : 'needs_attention',
        ];
    }

    // ==========================================
    // Store User Access Management
    // ==========================================

    public function listStoreUsers(int $storeId): array
    {
        $store = $this->repository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        return $this->repository->getStoreUsers($storeId);
    }

    public function addUserToStore(int $storeId, int $userId, int $actorId): bool
    {
        $store = $this->repository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        $assigned = $this->repository->assignUserToStore($storeId, $userId);

        $this->auditService->log(
            $actorId,
            $storeId,
            'STORE_USER_ASSIGNED',
            'user_store',
            "{$storeId}:{$userId}",
            null,
            ['store_id' => $storeId, 'user_id' => $userId]
        );

        return $assigned;
    }

    public function removeUserFromStore(int $storeId, int $userId, int $actorId): bool
    {
        $store = $this->repository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        $removed = $this->repository->removeUserFromStore($storeId, $userId);

        $this->auditService->log(
            $actorId,
            $storeId,
            'STORE_USER_REMOVED',
            'user_store',
            "{$storeId}:{$userId}",
            ['store_id' => $storeId, 'user_id' => $userId],
            null
        );

        return $removed;
    }
}
