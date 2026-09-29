<?php

namespace App\Models;

use App\Support\Security;

class Store
{
    public ?int $id = null;
    public string $name = '';
    public ?string $icon = null;
    public string $url = '';
    public string $consumer_key_encrypted = '';
    public string $consumer_secret_encrypted = '';
    public ?string $webhook_secret_encrypted = null;
    public string $status = 'inactive';
    public bool $is_demo = false;
    public ?string $wp_version = null;
    public ?string $wc_version = null;
    public ?string $woocommerce_version = null;
    public ?string $wordpress_version = null;
    public bool $hpos_enabled = false;
    public string $currency = 'IRR';
    public string $timezone = 'Asia/Tehran';
    public ?array $capabilities = null;
    public ?string $last_sync_at = null;
    public ?string $last_connection_check = null;
    public ?string $last_error = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromArray(array $data): self
    {
        $store = new self();
        $store->id = isset($data['id']) ? (int)$data['id'] : null;
        $store->name = $data['name'] ?? '';
        $store->icon = $data['icon'] ?? null;
        $store->url = $data['url'] ?? '';
        $store->consumer_key_encrypted = $data['consumer_key_encrypted'] ?? '';
        $store->consumer_secret_encrypted = $data['consumer_secret_encrypted'] ?? '';
        $store->webhook_secret_encrypted = $data['webhook_secret_encrypted'] ?? null;
        $store->status = $data['status'] ?? 'inactive';
        $store->is_demo = (bool)($data['is_demo'] ?? false);
        $store->wp_version = $data['wp_version'] ?? ($data['wordpress_version'] ?? null);
        $store->wc_version = $data['wc_version'] ?? ($data['woocommerce_version'] ?? null);
        $store->wordpress_version = $store->wp_version;
        $store->woocommerce_version = $store->wc_version;
        $store->hpos_enabled = (bool)($data['hpos_enabled'] ?? false);
        $store->currency = $data['currency'] ?? 'IRR';
        $store->timezone = $data['timezone'] ?? 'Asia/Tehran';
        $store->capabilities = isset($data['capabilities']) && is_string($data['capabilities'])
            ? json_decode($data['capabilities'], true)
            : ($data['capabilities'] ?? null);
        $store->last_sync_at = $data['last_sync_at'] ?? null;
        $store->last_connection_check = $data['last_connection_check'] ?? null;
        $store->last_error = $data['last_error'] ?? null;
        $store->created_at = $data['created_at'] ?? null;
        $store->updated_at = $data['updated_at'] ?? null;
        return $store;
    }

    public function isDemo(): bool
    {
        return $this->is_demo || str_starts_with($this->url, 'demo://') || str_starts_with($this->url, 'mock://');
    }

    public function toArray(bool $includeMaskedSecrets = true): array
    {
        $decryptedKey = Security::decrypt($this->consumer_key_encrypted) ?? '';
        $decryptedSecret = Security::decrypt($this->consumer_secret_encrypted) ?? '';

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'url' => $this->url,
            'status' => $this->status,
            'is_demo' => $this->isDemo(),
            'woocommerce_version' => $this->woocommerce_version ?: $this->wc_version,
            'wordpress_version' => $this->wordpress_version ?: $this->wp_version,
            'wc_version' => $this->wc_version ?: $this->woocommerce_version,
            'wp_version' => $this->wp_version ?: $this->wordpress_version,
            'hpos_enabled' => (bool)$this->hpos_enabled,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'capabilities' => $this->capabilities,
            'last_sync_at' => $this->last_sync_at,
            'last_connection_check' => $this->last_connection_check,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'credentials_configured' => !empty($this->consumer_key_encrypted) && !empty($this->consumer_secret_encrypted),
        ];

        if ($includeMaskedSecrets) {
            $data['consumer_key_masked'] = !empty($decryptedKey) ? Security::maskConsumerKey($decryptedKey) : '••••••••••••••••';
            $data['consumer_secret_masked'] = !empty($decryptedSecret) ? Security::maskSecret($decryptedSecret) : '••••••••••••••••';
            $data['has_webhook_secret'] = !empty($this->webhook_secret_encrypted);
        }

        return $data;
    }

    public function getDecryptedCredentials(): array
    {
        return [
            'consumer_key' => Security::decrypt($this->consumer_key_encrypted) ?? '',
            'consumer_secret' => Security::decrypt($this->consumer_secret_encrypted) ?? '',
        ];
    }

    public function getDecryptedWebhookSecret(): string
    {
        if (!empty($this->webhook_secret_encrypted)) {
            $decrypted = Security::decrypt($this->webhook_secret_encrypted);
            if (!empty($decrypted)) {
                return $decrypted;
            }
        }

        $creds = $this->getDecryptedCredentials();
        return $creds['consumer_secret'] ?? '';
    }

    public function setWebhookSecret(string $secret): void
    {
        $this->webhook_secret_encrypted = Security::encrypt($secret);
    }

    public function getCapability(string $key, mixed $default = null): mixed
    {
        return $this->capabilities[$key] ?? $default;
    }
}
