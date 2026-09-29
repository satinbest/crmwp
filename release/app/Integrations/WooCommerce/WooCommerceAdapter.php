<?php

namespace App\Integrations\WooCommerce;

use App\Support\Logger;

class WooCommerceAdapter
{
    private WooCommerceClient $client;
    private CapabilityDetector $capabilityDetector;
    private VersionDetector $versionDetector;

    public function __construct(WooCommerceClient $client)
    {
        $this->client = $client;
        $this->capabilityDetector = new CapabilityDetector($client);
        $this->versionDetector = new VersionDetector($client);
    }

    public function getClient(): WooCommerceClient
    {
        return $this->client;
    }

    public function testConnection(): array
    {
        Logger::info("Testing WooCommerce connection", [
            'store_url' => $this->client->getStoreUrl(),
        ]);

        $capabilities = $this->capabilityDetector->detect();

        if (!$capabilities['rest_api_available']) {
            throw new WooCommerceApiException(
                "اتصال به فروشگاه برقرار نشد یا REST API ووکامرس فعال نیست.",
                'API_UNAVAILABLE',
                503
            );
        }

        Logger::info("WooCommerce connection test successful", [
            'store_url' => $this->client->getStoreUrl(),
            'wc_version' => $capabilities['woocommerce_version'],
            'wp_version' => $capabilities['wordpress_version'],
            'hpos_enabled' => $capabilities['hpos_enabled'],
        ]);

        return [
            'connected' => true,
            'message' => 'اتصال به فروشگاه ووکامرس با موفقیت برقرار شد.',
            'woocommerce_version' => $capabilities['woocommerce_version'] ?? 'نامشخص',
            'wordpress_version' => $capabilities['wordpress_version'] ?? 'نامشخص',
            'hpos_enabled' => (bool)$capabilities['hpos_enabled'],
            'currency' => $capabilities['currency'] ?? 'IRR',
            'currency_symbol' => $capabilities['currency_symbol'] ?? '﷼',
            'timezone' => $capabilities['timezone'] ?? 'Asia/Tehran',
            'capabilities' => $capabilities,
            'tested_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function detectCapabilities(): array
    {
        return $this->capabilityDetector->detect();
    }
}
