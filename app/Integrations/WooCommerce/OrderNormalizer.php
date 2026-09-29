<?php

namespace App\Integrations\WooCommerce;

class OrderNormalizer
{
    /**
     * Normalize a single WooCommerce order array for list and detail views.
     */
    public static function normalize(array $raw, int $storeId): array
    {
        $id = (int)($raw['id'] ?? 0);
        $orderNumber = (string)($raw['number'] ?? (string)$id);
        $status = (string)($raw['status'] ?? 'pending');
        $statusLabel = OrderStatusResolver::getLabel($status, $storeId);

        $dateCreated = $raw['date_created'] ?? null;
        $dateModified = $raw['date_modified'] ?? null;
        $dateCompleted = $raw['date_completed'] ?? null;
        $datePaid = $raw['date_paid'] ?? null;

        $currency = (string)($raw['currency'] ?? 'IRR');
        $currencySymbol = (string)($raw['currency_symbol'] ?? ($currency === 'IRR' ? '﷼' : ($currency === 'IRT' ? 'تومان' : $currency)));

        $total = (float)($raw['total'] ?? 0.0);
        $subtotal = 0.0;
        $discountTotal = (float)($raw['discount_total'] ?? 0.0);
        $shippingTotal = (float)($raw['shipping_total'] ?? 0.0);
        $totalTax = (float)($raw['total_tax'] ?? 0.0);
        $cartTax = (float)($raw['cart_tax'] ?? 0.0);

        // Customer details & guest detection
        $customerId = (int)($raw['customer_id'] ?? 0);
        $rawBilling = is_array($raw['billing'] ?? null) ? $raw['billing'] : [];
        $rawShipping = is_array($raw['shipping'] ?? null) ? $raw['shipping'] : [];

        $billingFirstName = trim((string)($rawBilling['first_name'] ?? ''));
        $billingLastName = trim((string)($rawBilling['last_name'] ?? ''));
        $billingFullName = trim($billingFirstName . ' ' . $billingLastName);
        $billingEmail = trim((string)($rawBilling['email'] ?? ''));
        $billingPhone = trim((string)($rawBilling['phone'] ?? ''));

        $isGuest = ($customerId === 0);
        $customerDisplayName = $billingFullName ?: ($billingEmail ?: ($isGuest ? 'کاربر مهمان' : "مشتری #{$customerId}"));

        $customer = [
            'id' => $customerId,
            'is_guest' => $isGuest,
            'name' => $customerDisplayName,
            'email' => $billingEmail,
            'phone' => $billingPhone,
            'username' => (string)($raw['customer_user_agent'] ?? ''),
        ];

        // Billing normalization
        $billing = [
            'first_name' => $billingFirstName,
            'last_name' => $billingLastName,
            'full_name' => $billingFullName,
            'company' => (string)($rawBilling['company'] ?? ''),
            'address_1' => (string)($rawBilling['address_1'] ?? ''),
            'address_2' => (string)($rawBilling['address_2'] ?? ''),
            'city' => (string)($rawBilling['city'] ?? ''),
            'state' => (string)($rawBilling['state'] ?? ''),
            'postcode' => (string)($rawBilling['postcode'] ?? ''),
            'country' => (string)($rawBilling['country'] ?? ''),
            'email' => $billingEmail,
            'phone' => $billingPhone,
        ];

        // Shipping normalization
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

        // Line items normalization
        $items = [];
        $itemsCount = 0;
        if (!empty($raw['line_items']) && is_array($raw['line_items'])) {
            foreach ($raw['line_items'] as $item) {
                $qty = (int)($item['quantity'] ?? 1);
                $itemsCount += $qty;
                $itemSubtotal = (float)($item['subtotal'] ?? 0.0);
                $subtotal += $itemSubtotal;
                $itemTotal = (float)($item['total'] ?? 0.0);
                $price = (float)($item['price'] ?? ($qty > 0 ? $itemTotal / $qty : 0.0));

                // Variations / meta attributes
                $metaData = [];
                if (!empty($item['meta_data']) && is_array($item['meta_data'])) {
                    foreach ($item['meta_data'] as $m) {
                        $key = (string)($m['display_key'] ?? $m['key'] ?? '');
                        $val = (string)($m['display_value'] ?? $m['value'] ?? '');
                        if ($key !== '' && !str_starts_with($key, '_')) {
                            $metaData[] = [
                                'key' => $key,
                                'value' => $val,
                            ];
                        }
                    }
                }

                $items[] = [
                    'id' => (int)($item['id'] ?? 0),
                    'name' => (string)($item['name'] ?? 'محصول نامشخص'),
                    'product_id' => (int)($item['product_id'] ?? 0),
                    'variation_id' => (int)($item['variation_id'] ?? 0),
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $itemSubtotal,
                    'total' => $itemTotal,
                    'sku' => (string)($item['sku'] ?? ''),
                    'tax' => (float)($item['total_tax'] ?? 0.0),
                    'meta_data' => $metaData,
                    'variation_attributes' => $metaData,
                ];
            }
        }

        // Payment & Shipping methods
        $paymentMethod = (string)($raw['payment_method'] ?? '');
        $paymentMethodTitle = (string)($raw['payment_method_title'] ?? ($paymentMethod ?: 'نامشخص'));
        $transactionId = (string)($raw['transaction_id'] ?? '');

        $shippingMethod = '';
        if (!empty($raw['shipping_lines']) && is_array($raw['shipping_lines'])) {
            $shippingMethod = (string)($raw['shipping_lines'][0]['method_title'] ?? '');
        }

        // Refunds normalization
        $refunds = [];
        $refundedTotal = 0.0;
        if (!empty($raw['refunds']) && is_array($raw['refunds'])) {
            foreach ($raw['refunds'] as $ref) {
                $refTotal = abs((float)($ref['total'] ?? 0.0));
                $refundedTotal += $refTotal;
                $refunds[] = [
                    'id' => (int)($ref['id'] ?? 0),
                    'reason' => (string)($ref['reason'] ?? ''),
                    'total' => $refTotal,
                ];
            }
        }

        // Safe additional metadata without credentials or secrets
        $meta = [];
        if (!empty($raw['meta_data']) && is_array($raw['meta_data'])) {
            foreach ($raw['meta_data'] as $m) {
                $k = (string)($m['key'] ?? '');
                $v = $m['value'] ?? null;
                // Exclude any internal sensitive keys
                if (!str_contains(strtolower($k), 'secret') && !str_contains(strtolower($k), 'key') && !str_contains(strtolower($k), 'auth')) {
                    $meta[$k] = $v;
                }
            }
        }

        return [
            'id' => $id,
            'store_id' => $storeId,
            'number' => $orderNumber,
            'status' => $status,
            'status_label' => $statusLabel,
            'date_created' => $dateCreated,
            'date_modified' => $dateModified,
            'date_completed' => $dateCompleted,
            'date_paid' => $datePaid,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
            'customer_id' => $customerId,
            'customer' => $customer,
            'billing' => $billing,
            'shipping' => $shipping,
            'payment_method' => $paymentMethod,
            'payment_method_title' => $paymentMethodTitle,
            'transaction_id' => $transactionId,
            'shipping_method' => $shippingMethod,
            'items' => $items,
            'items_count' => $itemsCount,
            'subtotal' => $subtotal > 0 ? $subtotal : $total,
            'discount_total' => $discountTotal,
            'shipping_total' => $shippingTotal,
            'cart_tax' => $cartTax,
            'total_tax' => $totalTax,
            'total' => $total,
            'refunded_total' => $refundedTotal,
            'total_refunded' => $refundedTotal,
            'refunds' => $refunds,
            'meta' => $meta,
        ];
    }

    /**
     * Normalize a list of orders.
     */
    public static function normalizeCollection(array $rawList, int $storeId): array
    {
        return array_map(fn($item) => self::normalize($item, $storeId), $rawList);
    }
}
