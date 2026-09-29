<?php

namespace App\Integrations\WooCommerce;

use App\Repositories\StoreRepository;
use App\Support\Logger;
use Exception;

class WebhookManager
{
    private StoreRepository $storeRepository;

    public function __construct(?StoreRepository $storeRepository = null)
    {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }

    private function getClient(int $storeId): WooCommerceClient
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه با شناسه {$storeId} یافت نشد.", 404);
        }

        $creds = $store->getDecryptedCredentials();
        return new WooCommerceClient(
            $store->url,
            $creds['consumer_key'],
            $creds['consumer_secret']
        );
    }

    /**
     * List all webhooks defined in WooCommerce store.
     */
    public function listWebhooks(int $storeId): array
    {
        $client = $this->getClient($storeId);
        try {
            $webhooks = $client->get('/webhooks', ['per_page' => 100]);
            return array_map([$this, 'sanitizeWebhookOutput'], $webhooks);
        } catch (Exception $e) {
            Logger::error("Failed to list webhooks from WooCommerce: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific webhook from WooCommerce.
     */
    public function getWebhook(int $storeId, int $webhookId): ?array
    {
        $client = $this->getClient($storeId);
        try {
            $webhook = $client->get("/webhooks/{$webhookId}");
            return $this->sanitizeWebhookOutput($webhook);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Create a webhook in WooCommerce.
     */
    public function createWebhook(int $storeId, array $data): array
    {
        $client = $this->getClient($storeId);
        $store = $this->storeRepository->findById($storeId);

        $secret = $store ? $store->getDecryptedWebhookSecret() : '';
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(16));
            if ($store) {
                $this->storeRepository->update($storeId, ['webhook_secret' => $secret]);
            }
        }

        $payload = [
            'name' => $data['name'] ?? ('CRMWP - ' . ($data['topic'] ?? 'Webhook')),
            'topic' => $data['topic'],
            'delivery_url' => $data['delivery_url'],
            'secret' => $secret,
            'status' => $data['status'] ?? 'active',
        ];

        $res = $client->post('/webhooks', $payload);
        return $this->sanitizeWebhookOutput($res);
    }

    /**
     * Update a webhook in WooCommerce.
     */
    public function updateWebhook(int $storeId, int $webhookId, array $data): array
    {
        $client = $this->getClient($storeId);
        $payload = [];

        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }
        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }
        if (isset($data['topic'])) {
            $payload['topic'] = $data['topic'];
        }
        if (isset($data['delivery_url'])) {
            $payload['delivery_url'] = $data['delivery_url'];
        }

        $res = $client->put("/webhooks/{$webhookId}", $payload);
        return $this->sanitizeWebhookOutput($res);
    }

    /**
     * Delete a webhook from WooCommerce.
     */
    public function deleteWebhook(int $storeId, int $webhookId): bool
    {
        $client = $this->getClient($storeId);
        try {
            $client->delete("/webhooks/{$webhookId}", ['force' => true]);
            return true;
        } catch (Exception $e) {
            Logger::error("Failed to delete webhook {$webhookId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Automatically register standard CRMWP webhooks on the store.
     */
    public function registerStandardWebhooks(int $storeId, string $receiverBaseUrl): array
    {
        $standardTopics = [
            'order.created' => 'CRMWP - سفارش‌های جدید',
            'order.updated' => 'CRMWP - بروزرسانی سفارش‌ها',
            'order.deleted' => 'CRMWP - حذف سفارش‌ها',
            'product.created' => 'CRMWP - محصولات جدید',
            'product.updated' => 'CRMWP - تغییرات محصولات و انبار',
            'product.deleted' => 'CRMWP - حذف محصولات',
            'customer.created' => 'CRMWP - مشتریان جدید',
            'customer.updated' => 'CRMWP - بروزرسانی مشتریان',
        ];

        $existing = $this->listWebhooks($storeId);
        $existingTopics = array_column($existing, 'topic');

        $deliveryUrl = rtrim($receiverBaseUrl, '/') . "/api/v1/webhooks/woocommerce/{$storeId}";
        $created = [];

        foreach ($standardTopics as $topic => $name) {
            if (in_array($topic, $existingTopics, true)) {
                continue;
            }

            try {
                $wh = $this->createWebhook($storeId, [
                    'name' => $name,
                    'topic' => $topic,
                    'delivery_url' => $deliveryUrl,
                    'status' => 'active',
                ]);
                $created[] = $wh;
            } catch (Exception $e) {
                Logger::warning("Could not auto-register webhook for {$topic}: " . $e->getMessage());
            }
        }

        return $created;
    }

    /**
     * Sanitize webhook output so secrets are NEVER exposed.
     */
    private function sanitizeWebhookOutput(array $webhook): array
    {
        unset($webhook['secret']);
        return [
            'id' => (int)($webhook['id'] ?? 0),
            'name' => $webhook['name'] ?? '',
            'status' => $webhook['status'] ?? 'active',
            'topic' => $webhook['topic'] ?? '',
            'resource' => $webhook['resource'] ?? '',
            'event' => $webhook['event'] ?? '',
            'delivery_url' => $webhook['delivery_url'] ?? '',
            'date_created' => $webhook['date_created'] ?? null,
            'date_modified' => $webhook['date_modified'] ?? null,
            'failure_count' => (int)($webhook['failure_count'] ?? 0),
        ];
    }
}
