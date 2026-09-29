<?php

namespace App\Integrations\WooCommerce;

class WebhookAdapter
{
    /**
     * Map WooCommerce topic / headers to standard internal event name.
     */
    public static function resolveInternalEvent(?string $topic, ?string $resource = null, ?string $event = null): string
    {
        $rawTopic = trim(strtolower($topic ?? ''));

        if (!empty($rawTopic)) {
            // Standard WooCommerce topics are already format "resource.event", e.g. "order.created"
            return match ($rawTopic) {
                'order.created' => 'order.created',
                'order.updated' => 'order.updated',
                'order.deleted' => 'order.deleted',
                'order.restored' => 'order.updated',

                'product.created' => 'product.created',
                'product.updated' => 'product.updated',
                'product.deleted' => 'product.deleted',
                'product.restored' => 'product.updated',

                'customer.created' => 'customer.created',
                'customer.updated' => 'customer.updated',
                'customer.deleted' => 'customer.deleted',

                'coupon.created' => 'coupon.created',
                'coupon.updated' => 'coupon.updated',
                'coupon.deleted' => 'coupon.deleted',

                'action.woocommerce_order_status_changed' => 'order.updated',
                default => $rawTopic,
            };
        }

        if (!empty($resource) && !empty($event)) {
            return strtolower(trim($resource)) . '.' . strtolower(trim($event));
        }

        return 'action.created';
    }

    /**
     * Extract resource ID from webhook payload or headers.
     */
    public static function extractResourceId(array $payload, string $event): ?string
    {
        if (isset($payload['id']) && (is_numeric($payload['id']) || is_string($payload['id']))) {
            return (string)$payload['id'];
        }

        if (isset($payload['order_id'])) {
            return (string)$payload['order_id'];
        }

        if (isset($payload['product_id'])) {
            return (string)$payload['product_id'];
        }

        if (isset($payload['customer_id'])) {
            return (string)$payload['customer_id'];
        }

        return null;
    }

    /**
     * Redact sensitive personal or security fields from payload before storing to database.
     */
    public static function redactSensitiveData(array $payload): array
    {
        $sensitiveKeys = [
            'password', 'consumer_secret', 'consumer_key', 'secret', 'token', 'access_token',
            'api_key', 'authorization', 'credit_card', 'card_number', 'cvv', 'cvc',
            'card_security_code', 'pin', 'ssn',
        ];

        return self::recursiveRedact($payload, $sensitiveKeys);
    }

    private static function recursiveRedact(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $data[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $data[$key] = self::recursiveRedact($value, $sensitiveKeys);
            }
        }
        return $data;
    }
}
