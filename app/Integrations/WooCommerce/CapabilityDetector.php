<?php

namespace App\Integrations\WooCommerce;

use App\Support\Logger;

class CapabilityDetector
{
    private WooCommerceClient $client;

    public function __construct(WooCommerceClient $client)
    {
        $this->client = $client;
    }

    public function detect(): array
    {
        $capabilities = [
            'rest_api_available' => false,
            'api_version' => 'wc/v3',
            'woocommerce_version' => null,
            'wordpress_version' => null,
            'hpos_enabled' => false,
            'currency' => 'IRR',
            'currency_symbol' => '﷼',
            'timezone' => 'Asia/Tehran',
            'order_refunds_supported' => true,
            'webhooks_supported' => false,
            'batch_processing_supported' => true,
            'custom_order_statuses' => [],
            'detected_at' => date('Y-m-d H:i:s'),
        ];

        // 1. Check system_status for environment, versions, HPOS, currency, timezone
        try {
            $systemStatus = $this->client->get('/system_status');
            $capabilities['rest_api_available'] = true;

            if (isset($systemStatus['environment'])) {
                $env = $systemStatus['environment'];
                $capabilities['woocommerce_version'] = $env['version'] ?? null;
                $capabilities['wordpress_version'] = $env['wp_version'] ?? null;
            }

            if (isset($systemStatus['settings'])) {
                $settings = $systemStatus['settings'];
                $capabilities['currency'] = $settings['currency'] ?? 'IRR';
                $capabilities['currency_symbol'] = $settings['currency_symbol'] ?? '﷼';
                $capabilities['timezone'] = $settings['timezone'] ?? 'Asia/Tehran';
            }

            // HPOS detection:
            // 1. database.hpos_enabled
            // 2. features.custom_order_tables.enabled
            // 3. settings.order_storage == 'cot' or 'custom'
            $hpos = false;
            if (isset($systemStatus['database']['hpos_enabled'])) {
                $hpos = (bool)$systemStatus['database']['hpos_enabled'];
            } elseif (isset($systemStatus['features']['custom_order_tables']['enabled'])) {
                $hpos = (bool)$systemStatus['features']['custom_order_tables']['enabled'];
            } elseif (isset($systemStatus['settings']['order_storage'])) {
                $storage = strtolower((string)$systemStatus['settings']['order_storage']);
                $hpos = str_contains($storage, 'cot') || str_contains($storage, 'custom');
            }
            $capabilities['hpos_enabled'] = $hpos;

        } catch (WooCommerceApiException $e) {
            // Rethrow critical auth, permission, rate-limit, and server errors
            if (in_array($e->getHttpStatus(), [401, 403, 429, 500, 502, 503, 504], true)) {
                throw $e;
            }

            Logger::warning("Could not read /system_status, probing fallback endpoints", [
                'error' => $e->getMessage(),
            ]);

            // Fallback: check basic API accessibility via orders index
            try {
                $this->client->get('/orders', ['per_page' => 1]);
                $capabilities['rest_api_available'] = true;
            } catch (WooCommerceApiException $e2) {
                if (in_array($e2->getHttpStatus(), [401, 403, 429], true)) {
                    throw $e2;
                }
                $capabilities['rest_api_available'] = false;
            } catch (\Exception $e2) {
                $capabilities['rest_api_available'] = false;
            }
        } catch (\Exception $e) {
            throw $e;
        }

        // 2. Webhooks capability probe
        try {
            $this->client->get('/webhooks', ['per_page' => 1]);
            $capabilities['webhooks_supported'] = true;
        } catch (\Exception $e) {
            $capabilities['webhooks_supported'] = false;
        }

        // 3. Custom Order Statuses probe
        try {
            // Probe WooCommerce data/reports or query recent orders to dynamically harvest statuses
            $statuses = $this->client->get('/reports/orders/totals');
            if (is_array($statuses)) {
                $customStatuses = [];
                foreach ($statuses as $item) {
                    if (isset($item['slug'])) {
                        $customStatuses[] = [
                            'slug' => $item['slug'],
                            'name' => $item['name'] ?? $item['slug'],
                            'total' => $item['total'] ?? 0,
                        ];
                    }
                }
                $capabilities['custom_order_statuses'] = $customStatuses;
            }
        } catch (\Exception $e) {
            // Dynamic statuses fallback to standard if reports endpoint unavailable
            $capabilities['custom_order_statuses'] = [
                ['slug' => 'pending', 'name' => 'در انتظار پرداخت'],
                ['slug' => 'processing', 'name' => 'در حال انجام'],
                ['slug' => 'on-hold', 'name' => 'در انتظار بررسی'],
                ['slug' => 'completed', 'name' => 'تکمیل شده'],
                ['slug' => 'cancelled', 'name' => 'لغو شده'],
                ['slug' => 'refunded', 'name' => 'مسترد شده'],
                ['slug' => 'failed', 'name' => 'ناموفق'],
            ];
        }

        return $capabilities;
    }
}
