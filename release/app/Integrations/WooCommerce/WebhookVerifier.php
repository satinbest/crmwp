<?php

namespace App\Integrations\WooCommerce;

use App\Models\Store;
use App\Support\Request;

class WebhookVerifier
{
    /**
     * Verify WooCommerce Webhook HMAC-SHA256 signature using timing-safe comparison.
     *
     * @param Request $request
     * @param Store $store
     * @return array [
     *     'valid' => bool,
     *     'error' => ?string,
     *     'signature' => ?string,
     *     'topic' => ?string,
     *     'resource' => ?string,
     *     'event' => ?string,
     *     'delivery_id' => ?string,
     *     'webhook_id' => ?string,
     * ]
     */
    public function verify(Request $request, Store $store): array
    {
        $signature = $request->getHeader('x-wc-webhook-signature')
            ?? $request->getHeader('x-wc-webhook-signature')
            ?? $request->header('x-wc-webhook-signature');

        $topic = $request->getHeader('x-wc-webhook-topic');
        $resource = $request->getHeader('x-wc-webhook-resource');
        $event = $request->getHeader('x-wc-webhook-event');
        $deliveryId = $request->getHeader('x-wc-webhook-delivery-id');
        $webhookId = $request->getHeader('x-wc-webhook-id');

        $result = [
            'valid' => false,
            'error' => null,
            'signature' => $signature,
            'topic' => $topic,
            'resource' => $resource,
            'event' => $event,
            'delivery_id' => $deliveryId,
            'webhook_id' => $webhookId,
        ];

        // 1. Signature header presence
        if (empty($signature)) {
            $result['error'] = 'MISSING_SIGNATURE: هدر X-WC-Webhook-Signature یافت نشد.';
            return $result;
        }

        // 2. Resolve webhook secret
        $secret = $store->getDecryptedWebhookSecret();
        if (empty($secret)) {
            $result['error'] = 'MISSING_STORE_SECRET: کلید وب‌هوک برای این فروشگاه پیکربندی نشده است.';
            return $result;
        }

        // 3. Raw Body validation
        $rawPayload = $request->getRawBody();

        // 4. Compute HMAC-SHA256 signature
        // WooCommerce sends base64_encode(hash_hmac('sha256', $rawPayload, $secret, true))
        $expectedSignature = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));

        // 5. Timing-safe comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            $result['error'] = 'INVALID_SIGNATURE: امضای اعتبارسنجی وب‌هوک نامعتبر است.';
            return $result;
        }

        $result['valid'] = true;
        return $result;
    }
}
