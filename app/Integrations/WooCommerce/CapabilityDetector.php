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

            // Reliable multi-layer HPOS detection
            $capabilities['hpos_enabled'] = $this->resolveHposStatus($systemStatus);
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

    /**
     * Reliable, multi-layer HPOS (High-Performance Order Storage) detection.
     * Uses official WooCommerce system_status database inspection, settings probe,
     * and fallback to settings API without guessing or hardcoding.
     *
     * @return bool|null True if enabled, False if disabled, Null if undetectable
     */
    public function resolveHposStatus(array $systemStatus): ?bool
    {
        // 1. Direct system_status checks
        // Check database.order_storage
        if (isset($systemStatus['database']['order_storage'])) {
            $storage = strtolower((string)$systemStatus['database']['order_storage']);
            if (str_contains($storage, 'hpos') || str_contains($storage, 'custom') || str_contains($storage, 'cot')) {
                return true;
            }
            if (str_contains($storage, 'post') || str_contains($storage, 'legacy')) {
                return false;
            }
        }

        // Check database.hpos_enabled
        if (isset($systemStatus['database']['hpos_enabled'])) {
            return (bool)$systemStatus['database']['hpos_enabled'];
        }

        // Check environment.hpos_enabled
        if (isset($systemStatus['environment']['hpos_enabled'])) {
            return (bool)$systemStatus['environment']['hpos_enabled'];
        }

        // Check features.custom_order_tables.enabled
        if (isset($systemStatus['features']['custom_order_tables']['enabled'])) {
            return (bool)$systemStatus['features']['custom_order_tables']['enabled'];
        }

        // Check settings.order_storage
        if (isset($systemStatus['settings']['order_storage'])) {
            $storage = strtolower((string)$systemStatus['settings']['order_storage']);
            if (str_contains($storage, 'cot') || str_contains($storage, 'custom') || str_contains($storage, 'hpos')) {
                return true;
            }
            if (str_contains($storage, 'post') || str_contains($storage, 'legacy')) {
                return false;
            }
        }

        // 2. Query WooCommerce Settings API for woocommerce_custom_orders_table_enabled
        try {
            $hposSetting = $this->client->get('/settings/advanced/woocommerce_custom_orders_table_enabled');
            if (is_array($hposSetting) && isset($hposSetting['value'])) {
                return $hposSetting['value'] === 'yes' || $hposSetting['value'] === true || $hposSetting['value'] === '1';
            }
        } catch (\Throwable $e) {
            // Ignore and try fallback
        }

        // Try /settings/features/custom_order_tables
        try {
            $featSetting = $this->client->get('/settings/features/custom_order_tables');
            if (is_array($featSetting) && isset($featSetting['value'])) {
                return $featSetting['value'] === 'yes' || $featSetting['value'] === true || $featSetting['value'] === '1';
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        // 3. Check WooCommerce database tables list in system_status
        if (isset($systemStatus['database']['database_tables']['woocommerce'])) {
            $wcTables = $systemStatus['database']['database_tables']['woocommerce'];
            if (is_array($wcTables)) {
                $hasWcOrdersTable = false;
                foreach ($wcTables as $tblName => $tblInfo) {
                    $tName = is_string($tblName) ? $tblName : (is_array($tblInfo) ? ($tblInfo['name'] ?? '') : '');
                    if (str_contains($tName, 'wc_orders') && !str_contains($tName, 'wc_orders_meta')) {
                        $hasWcOrdersTable = true;
                        break;
                    }
                }
                if ($hasWcOrdersTable) {
                    $wcVer = $systemStatus['environment']['version'] ?? '0.0.0';
                    if (version_compare($wcVer, '8.2.0', '>=')) {
                        return true;
                    }
                }
            }
        }

        // 4. Undetectable
        return null;
    }
}
