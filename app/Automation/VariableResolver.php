<?php

namespace App\Automation;

use App\Events\AutomationEvent;
use App\Repositories\StoreRepository;

class VariableResolver
{
    private StoreRepository $storeRepository;

    public function __construct(?StoreRepository $storeRepository = null)
    {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }

    /**
     * Whitelist of available variables and their human-readable labels.
     */
    public static function getWhitelistedVariables(): array
    {
        return [
            'order.id' => 'شناسه سفارش',
            'order.number' => 'شماره سفارش',
            'order.total' => 'مبلغ کل سفارش',
            'order.subtotal' => 'جمع جزء سفارش',
            'order.status' => 'وضعیت سفارش',
            'order.currency' => 'واحد پولی سفارش',
            'order.customer_id' => 'شناسه مشتری سفارش',
            'order.billing_name' => 'نام پرداخت‌کننده',
            'order.billing_email' => 'ایمیل پرداخت‌کننده',
            'order.billing_phone' => 'تلفن پرداخت‌کننده',
            'order.item_count' => 'تعداد اقلام سفارش',
            'customer.id' => 'شناسه مشتری',
            'customer.name' => 'نام کامل مشتری',
            'customer.email' => 'ایمیل مشتری',
            'customer.phone' => 'شماره تماس مشتری',
            'customer.role' => 'نقش مشتری',
            'customer.city' => 'شهر مشتری',
            'customer.country' => 'کشور مشتری',
            'customer.order_count' => 'تعداد کل سفارش‌های مشتری',
            'customer.total_spent' => 'مجموع خرید مشتری',
            'product.id' => 'شناسه محصول',
            'product.name' => 'نام محصول',
            'product.sku' => 'کد شناسه (SKU) محصول',
            'product.price' => 'قیمت محصول',
            'product.sale_price' => 'قیمت حراج محصول',
            'product.stock_quantity' => 'موجودی انبار محصول',
            'product.stock_status' => 'وضعیت موجودی انبار',
            'task.id' => 'شناسه وظیفه',
            'task.title' => 'عنوان وظیفه',
            'task.status' => 'وضعیت وظیفه',
            'task.priority' => 'اولویت وظیفه',
            'store.id' => 'شناسه فروشگاه',
            'store.name' => 'نام فروشگاه',
            'store.url' => 'آدرس وب‌سایت فروشگاه',
            'store.currency' => 'واحد پول فروشگاه',
        ];
    }

    /**
     * Extract flattened context dictionary from event and optional store lookup.
     */
    public function extractContextVariables(AutomationEvent $event): array
    {
        $vars = [];
        $data = $event->getData();
        $storeId = $event->getStoreId();

        // 1. Store variables
        $store = $this->storeRepository->findById($storeId);
        $vars['store.id'] = (string)$storeId;
        $vars['store.name'] = $store ? $store->name : "فروشگاه #{$storeId}";
        $vars['store.url'] = $store ? ($store->url ?? '') : '';
        $vars['store.currency'] = $store ? ($store->currency ?? 'IRR') : 'IRR';

        // 2. Resource-specific data extraction
        $resourceType = $event->getResourceType();
        $resourceId = $event->getResourceId();

        // Order variables
        $orderData = [];
        if ($resourceType === 'order') {
            $orderData = $data;
        } elseif (isset($data['order']) && is_array($data['order'])) {
            $orderData = $data['order'];
        }

        if (!empty($orderData) || $resourceType === 'order') {
            $vars['order.id'] = (string)($orderData['id'] ?? $resourceId);
            $vars['order.number'] = (string)($orderData['number'] ?? ($orderData['id'] ?? $resourceId));
            $vars['order.total'] = isset($orderData['total']) ? (string)$orderData['total'] : '0';
            $vars['order.subtotal'] = isset($orderData['subtotal']) ? (string)$orderData['subtotal'] : '0';
            $vars['order.status'] = (string)($orderData['status'] ?? '');
            $vars['order.currency'] = (string)($orderData['currency'] ?? $vars['store.currency']);
            $vars['order.customer_id'] = (string)($orderData['customer_id'] ?? '');

            $billing = $orderData['billing'] ?? [];
            $bFirst = $billing['first_name'] ?? '';
            $bLast = $billing['last_name'] ?? '';
            $billingName = trim("{$bFirst} {$bLast}");
            $vars['order.billing_name'] = $billingName ?: ($orderData['billing_name'] ?? '');
            $vars['order.billing_email'] = (string)($billing['email'] ?? ($orderData['billing_email'] ?? ''));
            $vars['order.billing_phone'] = (string)($billing['phone'] ?? ($orderData['billing_phone'] ?? ''));

            $lineItems = $orderData['line_items'] ?? [];
            $vars['order.item_count'] = (string)(is_array($lineItems) ? count($lineItems) : ($orderData['item_count'] ?? 1));
        }

        $custData = [];
        if ($resourceType === 'customer') {
            $custData = $data;
        } elseif (isset($data['customer']) && is_array($data['customer'])) {
            $custData = $data['customer'];
        } elseif (isset($data['orders_count']) || isset($data['total_spent']) || isset($data['first_name'])) {
            $custData = $data;
        }

        if (!empty($custData) || $resourceType === 'customer') {
            $vars['customer.id'] = (string)($custData['id'] ?? $resourceId);
            $cFirst = $custData['first_name'] ?? '';
            $cLast = $custData['last_name'] ?? '';
            $cName = trim("{$cFirst} {$cLast}");
            $vars['customer.name'] = $cName ?: ($custData['name'] ?? ($custData['username'] ?? 'مشتری'));
            $vars['customer.email'] = (string)($custData['email'] ?? '');
            $vars['customer.phone'] = (string)($custData['phone'] ?? ($custData['billing']['phone'] ?? ''));
            $vars['customer.role'] = (string)($custData['role'] ?? 'customer');
            $vars['customer.city'] = (string)($custData['billing']['city'] ?? ($custData['city'] ?? ''));
            $vars['customer.country'] = (string)($custData['billing']['country'] ?? ($custData['country'] ?? ''));
            $vars['customer.order_count'] = (string)($custData['orders_count'] ?? ($custData['order_count'] ?? 0));
            $vars['customer.total_spent'] = (string)($custData['total_spent'] ?? 0);
        }

        // If customer was referenced in an order, fallback for customer.name / email
        if (empty($vars['customer.name']) && !empty($vars['order.billing_name'])) {
            $vars['customer.name'] = $vars['order.billing_name'];
        }
        if (empty($vars['customer.email']) && !empty($vars['order.billing_email'])) {
            $vars['customer.email'] = $vars['order.billing_email'];
        }
        if (empty($vars['customer.id']) && !empty($vars['order.customer_id'])) {
            $vars['customer.id'] = $vars['order.customer_id'];
        }

        // Product variables
        $prodData = [];
        if ($resourceType === 'product') {
            $prodData = $data;
        } elseif (isset($data['product']) && is_array($data['product'])) {
            $prodData = $data['product'];
        }

        if (!empty($prodData) || $resourceType === 'product') {
            $vars['product.id'] = (string)($prodData['id'] ?? $resourceId);
            $vars['product.name'] = (string)($prodData['name'] ?? '');
            $vars['product.sku'] = (string)($prodData['sku'] ?? '');
            $vars['product.price'] = (string)($prodData['price'] ?? ($prodData['regular_price'] ?? '0'));
            $vars['product.sale_price'] = (string)($prodData['sale_price'] ?? '');
            $vars['product.stock_quantity'] = (string)($prodData['stock_quantity'] ?? 0);
            $vars['product.stock_status'] = (string)($prodData['stock_status'] ?? 'instock');
        }

        // Task variables
        $taskData = [];
        if ($resourceType === 'task') {
            $taskData = $data;
        } elseif (isset($data['task']) && is_array($data['task'])) {
            $taskData = $data['task'];
        }

        if (!empty($taskData) || $resourceType === 'task') {
            $vars['task.id'] = (string)($taskData['id'] ?? $resourceId);
            $vars['task.title'] = (string)($taskData['title'] ?? '');
            $vars['task.status'] = (string)($taskData['status'] ?? 'pending');
            $vars['task.priority'] = (string)($taskData['priority'] ?? 'normal');
        }

        return $vars;
    }

    /**
     * Replace all whitelisted {{variable.name}} placeholders in a text string.
     */
    public function resolveText(string $text, array $contextVars): string
    {
        if (!str_contains($text, '{{')) {
            return $text;
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($contextVars) {
            $varName = trim($matches[1]);
            if (array_key_exists($varName, $contextVars)) {
                return (string)$contextVars[$varName];
            }
            // Unknown variable: return empty string safely (no arbitrary code execution)
            return '';
        }, $text);
    }

    /**
     * Recursively resolve placeholders in array or string data.
     */
    public function resolveDeep(mixed $data, array $contextVars): mixed
    {
        if (is_string($data)) {
            return $this->resolveText($data, $contextVars);
        }

        if (is_array($data)) {
            $resolved = [];
            foreach ($data as $key => $val) {
                $resolved[$key] = $this->resolveDeep($val, $contextVars);
            }
            return $resolved;
        }

        return $data;
    }
}
