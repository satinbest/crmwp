<?php

namespace App\Integrations\WooCommerce;

use App\Integrations\WooCommerce\Handlers\CouponEventHandler;
use App\Integrations\WooCommerce\Handlers\CustomerEventHandler;
use App\Integrations\WooCommerce\Handlers\OrderEventHandler;
use App\Integrations\WooCommerce\Handlers\ProductEventHandler;
use App\Support\Cache;
use App\Support\Logger;
use Exception;

class WebhookProcessor
{
    private OrderEventHandler $orderHandler;
    private ProductEventHandler $productHandler;
    private CustomerEventHandler $customerHandler;
    private CouponEventHandler $couponHandler;

    public function __construct(
        ?OrderEventHandler $orderHandler = null,
        ?ProductEventHandler $productHandler = null,
        ?CustomerEventHandler $customerHandler = null,
        ?CouponEventHandler $couponHandler = null
    ) {
        $this->orderHandler = $orderHandler ?? new OrderEventHandler();
        $this->productHandler = $productHandler ?? new ProductEventHandler();
        $this->customerHandler = $customerHandler ?? new CustomerEventHandler();
        $this->couponHandler = $couponHandler ?? new CouponEventHandler();
    }

    /**
     * Process normalized webhook event.
     */
    public function process(int $storeId, string $event, array $payload): array
    {
        $startTime = microtime(true);

        try {
            $details = [];

            // Automatic Store-Aware Cache Invalidation for incoming webhook events
            Cache::handleWebhookInvalidation($storeId, $event);

            if (str_starts_with($event, 'order.')) {
                $details = $this->orderHandler->handle($storeId, $event, $payload);
            } elseif (str_starts_with($event, 'product.')) {
                $details = $this->productHandler->handle($storeId, $event, $payload);
            } elseif (str_starts_with($event, 'customer.')) {
                $details = $this->customerHandler->handle($storeId, $event, $payload);
            } elseif (str_starts_with($event, 'coupon.')) {
                $details = $this->couponHandler->handle($storeId, $event, $payload);
            } elseif (str_starts_with($event, 'action.')) {
                // Generic action webhook: flush dashboard and related caches
                Cache::forgetStore($storeId);
                $details = ['action' => $event, 'cleared_dashboard_cache' => true];
            } else {
                // Unknown event: still acknowledge, invalidate store cache
                Cache::forgetStore($storeId);
                $details = ['unrecognized_event' => $event];
            }

            // Idempotent Local DB Synchronization
            try {
                $localSync = new \App\Services\LocalSyncService();
                if (str_starts_with($event, 'order.')) {
                    if ($event === 'order.deleted') {
                        $localSync->deleteLocalOrder($storeId, (int)($payload['id'] ?? 0));
                    } else {
                        $localSync->upsertLocalOrder($storeId, $payload);
                    }
                } elseif (str_starts_with($event, 'product.')) {
                    if ($event === 'product.deleted') {
                        $localSync->deleteLocalProduct($storeId, (int)($payload['id'] ?? 0));
                    } else {
                        $localSync->upsertLocalProduct($storeId, $payload);
                    }
                } elseif (str_starts_with($event, 'customer.')) {
                    if ($event === 'customer.deleted') {
                        $localSync->deleteLocalCustomer($storeId, (int)($payload['id'] ?? 0));
                    } else {
                        $localSync->upsertLocalCustomer($storeId, $payload);
                    }
                }
            } catch (\Throwable $localSyncEx) {
                Logger::warning("Could not sync webhook event to local cache: " . $localSyncEx->getMessage());
            }

            // Dispatch event to EventDispatcher / Automation Engine
            try {
                $resourceType = explode('.', $event)[0] ?? 'custom';
                $resourceId = (int)($payload['id'] ?? ($payload['order_id'] ?? ($payload['product_id'] ?? 0)));
                $automationEvent = new \App\Events\AutomationEvent(
                    $storeId,
                    $event,
                    $resourceType,
                    $resourceId,
                    $payload,
                    'woocommerce_webhook'
                );
                \App\Events\EventDispatcher::dispatch($automationEvent);

                // If product stock changed to low stock, also dispatch inventory.low_stock
                if (str_starts_with($event, 'product.') && isset($payload['stock_quantity'])) {
                    $stockQty = (int)$payload['stock_quantity'];
                    $lowStockThreshold = (int)($payload['low_stock_amount'] ?? 5);
                    if ($stockQty <= $lowStockThreshold) {
                        $lowStockEvent = new \App\Events\AutomationEvent(
                            $storeId,
                            'inventory.low_stock',
                            'product',
                            $resourceId,
                            $payload,
                            'woocommerce_webhook'
                        );
                        \App\Events\EventDispatcher::dispatch($lowStockEvent);
                    }
                }
            } catch (Exception $dispatchEx) {
                Logger::warning("Could not dispatch webhook event to EventDispatcher: " . $dispatchEx->getMessage());
            }

            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));

            return [
                'success' => true,
                'status' => 'processed',
                'event' => $event,
                'duration_ms' => $durationMs,
                'details' => $details,
            ];
        } catch (Exception $e) {
            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));
            Logger::error("Webhook processing failed: " . $e->getMessage(), [
                'store_id' => $storeId,
                'event' => $event,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
