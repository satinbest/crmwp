<?php

namespace App\Integrations\WooCommerce;

class CustomerNormalizer
{
    /**
     * Normalize a single WooCommerce customer array.
     */
    public static function normalize(array $raw, int $storeId): array
    {
        $id = (int)($raw['id'] ?? 0);
        $firstName = trim((string)($raw['first_name'] ?? ''));
        $lastName = trim((string)($raw['last_name'] ?? ''));
        $email = trim((string)($raw['email'] ?? ''));
        $username = trim((string)($raw['username'] ?? ''));

        // Derive full name with graceful fallbacks
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $username ?: ($email ? explode('@', $email)[0] : "مشتری #{$id}");
        }

        // Billing normalization
        $rawBilling = is_array($raw['billing'] ?? null) ? $raw['billing'] : [];
        $billing = [
            'first_name' => (string)($rawBilling['first_name'] ?? ''),
            'last_name' => (string)($rawBilling['last_name'] ?? ''),
            'company' => (string)($rawBilling['company'] ?? ''),
            'address_1' => (string)($rawBilling['address_1'] ?? ''),
            'address_2' => (string)($rawBilling['address_2'] ?? ''),
            'city' => (string)($rawBilling['city'] ?? ''),
            'state' => (string)($rawBilling['state'] ?? ''),
            'postcode' => (string)($rawBilling['postcode'] ?? ''),
            'country' => (string)($rawBilling['country'] ?? ''),
            'email' => (string)($rawBilling['email'] ?? $email),
            'phone' => (string)($rawBilling['phone'] ?? ''),
        ];

        // Shipping normalization
        $rawShipping = is_array($raw['shipping'] ?? null) ? $raw['shipping'] : [];
        $shipping = [
            'first_name' => (string)($rawShipping['first_name'] ?? ''),
            'last_name' => (string)($rawShipping['last_name'] ?? ''),
            'company' => (string)($rawShipping['company'] ?? ''),
            'address_1' => (string)($rawShipping['address_1'] ?? ''),
            'address_2' => (string)($rawShipping['address_2'] ?? ''),
            'city' => (string)($rawShipping['city'] ?? ''),
            'state' => (string)($rawShipping['state'] ?? ''),
            'postcode' => (string)($rawShipping['postcode'] ?? ''),
            'country' => (string)($rawShipping['country'] ?? ''),
            'phone' => (string)($rawShipping['phone'] ?? ''),
        ];

        // Phone fallback
        $phone = $billing['phone'] ?: ($shipping['phone'] ?? '');

        // Financial & Orders metrics
        $ordersCount = isset($raw['orders_count']) ? (int)$raw['orders_count'] : 0;
        $totalSpent = isset($raw['total_spent']) ? (float)$raw['total_spent'] : 0.0;
        $avgOrderValue = $ordersCount > 0 ? round($totalSpent / $ordersCount, 2) : 0.0;

        // Role & Customer Type
        $role = (string)($raw['role'] ?? 'customer');
        $customerType = $id === 0 ? 'guest' : ($role === 'customer' ? 'registered' : $role);

        // Dates
        $dateCreated = $raw['date_created'] ?? null;
        $dateModified = $raw['date_modified'] ?? null;
        $lastOrderDate = null;
        if (!empty($raw['last_order']['date'])) {
            $lastOrderDate = $raw['last_order']['date'];
        } elseif (!empty($raw['meta_data']) && is_array($raw['meta_data'])) {
            foreach ($raw['meta_data'] as $meta) {
                if (($meta['key'] ?? '') === '_last_order_date') {
                    $lastOrderDate = $meta['value'];
                    break;
                }
            }
        }

        // Controlled meta / extensions preservation
        $meta = [];
        if (!empty($raw['meta_data']) && is_array($raw['meta_data'])) {
            foreach ($raw['meta_data'] as $m) {
                if (isset($m['key'])) {
                    $meta[$m['key']] = $m['value'] ?? null;
                }
            }
        }

        return [
            'id' => $id,
            'store_id' => $storeId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'email' => $email,
            'username' => $username,
            'phone' => $phone,
            'role' => $role,
            'avatar_url' => (!empty($raw['avatar_url']) && !str_contains((string)$raw['avatar_url'], 'gravatar.com')) ? (string)$raw['avatar_url'] : '',
            'billing' => $billing,
            'shipping' => $shipping,
            'orders_count' => $ordersCount,
            'total_spent' => $totalSpent,
            'average_order_value' => $avgOrderValue,
            'last_order_date' => $lastOrderDate,
            'date_created' => $dateCreated,
            'date_modified' => $dateModified,
            'customer_type' => $customerType,
            'meta' => $meta,
        ];
    }

    /**
     * Normalize a collection of customers.
     */
    public static function normalizeCollection(array $rawList, int $storeId): array
    {
        return array_map(fn($item) => self::normalize($item, $storeId), $rawList);
    }
}
