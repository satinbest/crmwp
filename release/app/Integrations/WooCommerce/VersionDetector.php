<?php

namespace App\Integrations\WooCommerce;

class VersionDetector
{
    private WooCommerceClient $client;

    public function __construct(WooCommerceClient $client)
    {
        $this->client = $client;
    }

    public function detect(): array
    {
        $wcVersion = null;
        $wpVersion = null;
        $phpVersion = null;

        try {
            $status = $this->client->get('/system_status');
            if (isset($status['environment'])) {
                $env = $status['environment'];
                $wcVersion = $env['version'] ?? null;
                $wpVersion = $env['wp_version'] ?? null;
                $phpVersion = $env['php_version'] ?? null;
            }
        } catch (\Exception $e) {
            // Fallback: try WordPress root index
            try {
                $root = $this->client->get('/wp-json/');
                // In some setups, namespaces or generator indicates version
            } catch (\Exception $e2) {
                // Keep nulls
            }
        }

        return [
            'woocommerce_version' => $wcVersion,
            'wordpress_version' => $wpVersion,
            'php_version' => $phpVersion,
        ];
    }
}
