<?php

namespace App\Services;

use App\Integrations\WooCommerce\WebhookAdapter;
use App\Integrations\WooCommerce\WebhookProcessor;
use App\Integrations\WooCommerce\WebhookVerifier;
use App\Repositories\StoreRepository;
use App\Repositories\WebhookLogRepository;
use App\Support\Logger;
use App\Support\Request;
use Exception;

class WooCommerceWebhookService
{
    private StoreRepository $storeRepository;
    private WebhookLogRepository $logRepository;
    private WebhookVerifier $verifier;
    private WebhookProcessor $processor;

    public function __construct(
        ?StoreRepository $storeRepository = null,
        ?WebhookLogRepository $logRepository = null,
        ?WebhookVerifier $verifier = null,
        ?WebhookProcessor $processor = null
    ) {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->logRepository = $logRepository ?? new WebhookLogRepository();
        $this->verifier = $verifier ?? new WebhookVerifier();
        $this->processor = $processor ?? new WebhookProcessor();
    }

    /**
     * Handle incoming WooCommerce webhook request with full verification, idempotency, and dispatching.
     */
    public function handleIncoming(Request $request, int|string $storeIdentifier): array
    {
        $startTime = microtime(true);
        $storeId = (int)$storeIdentifier;

        // 1. Identify Store
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            return [
                'http_status' => 404,
                'response' => [
                    'success' => false,
                    'error' => 'STORE_NOT_FOUND',
                    'message' => 'فروشگاه مورد نظر یافت نشد.',
                ],
            ];
        }

        // 2. Validate payload size (Max 5MB)
        $rawBody = $request->getRawBody();
        if (strlen($rawBody) > 5 * 1024 * 1024) {
            return [
                'http_status' => 413,
                'response' => [
                    'success' => false,
                    'error' => 'PAYLOAD_TOO_LARGE',
                    'message' => 'حجم داده ارسالی بیش از حد مجاز است.',
                ],
            ];
        }

        // 3. Signature Verification (HMAC-SHA256 timing-safe)
        $verification = $this->verifier->verify($request, $store);
        $topic = $verification['topic'] ?? 'action.created';
        $deliveryId = $verification['delivery_id'];
        $signature = $verification['signature'];
        $ip = $request->getIp();

        if (!$verification['valid']) {
            Logger::warning("WooCommerce Webhook verification failed: {$verification['error']}", [
                'store_id' => $storeId,
                'ip' => $ip,
                'topic' => $topic,
            ]);

            // Record failed attempt in logs if appropriate
            try {
                $this->logRepository->create([
                    'store_id' => $storeId,
                    'delivery_id' => $deliveryId,
                    'topic' => $topic ?: 'unknown',
                    'event' => 'verification.failed',
                    'signature' => $signature ? substr($signature, 0, 20) . '...' : null,
                    'payload' => ['error' => 'Invalid or missing signature'],
                    'status' => 'failed',
                    'error_message' => $verification['error'],
                    'ip_address' => $ip,
                ]);
            } catch (Exception $e) {
                // Silently ignore log insertion error on invalid signature
            }

            return [
                'http_status' => 401,
                'response' => [
                    'success' => false,
                    'error' => 'INVALID_SIGNATURE',
                    'message' => 'اعتبارسنجی امضای وب‌هوک ناموفق بود.',
                ],
            ];
        }

        // 4. JSON Decode
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->logRepository->create([
                'store_id' => $storeId,
                'delivery_id' => $deliveryId,
                'topic' => $topic,
                'event' => 'json.invalid',
                'signature' => $signature,
                'payload' => ['raw_preview' => substr($rawBody, 0, 200)],
                'status' => 'failed',
                'error_message' => 'INVALID_JSON: داده ارسالی دارای ساختار معتبر JSON نمی‌باشد.',
                'ip_address' => $ip,
            ]);

            return [
                'http_status' => 400,
                'response' => [
                    'success' => false,
                    'error' => 'INVALID_PAYLOAD',
                    'message' => 'فرمت داده نامعتبر است.',
                ],
            ];
        }

        // 5. Adapt event and extract resource
        $internalEvent = WebhookAdapter::resolveInternalEvent(
            $topic,
            $verification['resource'],
            $verification['event']
        );
        $resourceId = WebhookAdapter::extractResourceId($payload, $internalEvent);
        $redactedPayload = WebhookAdapter::redactSensitiveData($payload);

        // 6. Idempotency Check
        // Priority 1: WooCommerce Delivery ID check
        if (!empty($deliveryId)) {
            $existing = $this->logRepository->findByDeliveryId($storeId, $deliveryId);
            if ($existing) {
                Logger::info("Duplicate webhook delivery suppressed: {$deliveryId} for store {$storeId}");
                return [
                    'http_status' => 200,
                    'response' => [
                        'success' => true,
                        'status' => 'duplicate',
                        'message' => 'رویداد قبلاً پردازش شده است.',
                        'delivery_id' => $deliveryId,
                        'original_log_id' => $existing['id'],
                    ],
                ];
            }
        } elseif ($resourceId !== null) {
            // Priority 2: Fallback when delivery_id is not provided: check within short window (10s)
            $recentDuplicate = $this->logRepository->findRecentDuplicate($storeId, $internalEvent, $resourceId, 10);
            if ($recentDuplicate) {
                // Register as duplicate entry without re-triggering business events
                $logId = $this->logRepository->create([
                    'store_id' => $storeId,
                    'delivery_id' => $deliveryId,
                    'webhook_id' => $verification['webhook_id'],
                    'resource_id' => $resourceId,
                    'topic' => $topic,
                    'event' => $internalEvent,
                    'signature' => $signature,
                    'payload' => $redactedPayload,
                    'status' => 'duplicate',
                    'error_message' => 'Duplicate event suppressed within debounce window',
                    'ip_address' => $ip,
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);

                return [
                    'http_status' => 200,
                    'response' => [
                        'success' => true,
                        'status' => 'duplicate',
                        'message' => 'رویداد تکراری در بازه زمانی کوتاه نادیده گرفته شد.',
                        'log_id' => $logId,
                    ],
                ];
            }
        }

        // 7. Create log entry in 'processing' state
        $logId = $this->logRepository->create([
            'store_id' => $storeId,
            'delivery_id' => $deliveryId,
            'webhook_id' => $verification['webhook_id'],
            'resource_id' => $resourceId,
            'topic' => $topic,
            'event' => $internalEvent,
            'signature' => $signature,
            'payload' => $redactedPayload,
            'status' => 'processing',
            'ip_address' => $ip,
        ]);

        // 8. Process Event & Invalidate Caches / Activities
        try {
            $processResult = $this->processor->process($storeId, $internalEvent, $payload);
            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));

            $this->logRepository->updateStatus($logId, 'processed', null, $durationMs);

            return [
                'http_status' => 200,
                'response' => [
                    'success' => true,
                    'status' => 'processed',
                    'log_id' => $logId,
                    'event' => $internalEvent,
                    'resource_id' => $resourceId,
                    'duration_ms' => $durationMs,
                    'details' => $processResult['details'] ?? [],
                ],
            ];
        } catch (Exception $e) {
            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));
            $this->logRepository->updateStatus($logId, 'failed', $e->getMessage(), $durationMs);

            return [
                'http_status' => 500,
                'response' => [
                    'success' => false,
                    'status' => 'failed',
                    'log_id' => $logId,
                    'error' => 'PROCESSING_FAILED',
                    'message' => 'پردازش رویداد با خطا مواجه شد.',
                ],
            ];
        }
    }

    /**
     * Retry a failed or received webhook log.
     */
    public function retryWebhook(int $logId): array
    {
        $log = $this->logRepository->findById($logId);
        if (!$log) {
            throw new Exception("لاگ وب‌هوک یافت نشد.", 404);
        }

        $storeId = (int)$log['store_id'];
        $event = $log['event'] ?? $log['topic'];
        $payload = is_array($log['payload']) ? $log['payload'] : (json_decode($log['payload'] ?? '{}', true) ?: []);

        $this->logRepository->incrementAttempt($logId);
        $this->logRepository->updateStatus($logId, 'processing');

        $startTime = microtime(true);
        try {
            $result = $this->processor->process($storeId, $event, $payload);
            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));

            $this->logRepository->updateStatus($logId, 'processed', null, $durationMs);

            return [
                'success' => true,
                'status' => 'processed',
                'log_id' => $logId,
                'duration_ms' => $durationMs,
                'details' => $result['details'] ?? [],
            ];
        } catch (Exception $e) {
            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));
            $this->logRepository->updateStatus($logId, 'failed', $e->getMessage(), $durationMs);

            throw new Exception("تلاش مجدد ناموفق بود: " . $e->getMessage(), 500);
        }
    }

    /**
     * Mark a webhook log as ignored.
     */
    public function ignoreWebhook(int $logId): array
    {
        $log = $this->logRepository->findById($logId);
        if (!$log) {
            throw new Exception("لاگ وب‌هوک یافت نشد.", 404);
        }

        $this->logRepository->markIgnored($logId);

        return [
            'success' => true,
            'status' => 'ignored',
            'log_id' => $logId,
        ];
    }
}
