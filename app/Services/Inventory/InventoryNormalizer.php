<?php

namespace App\Services\Inventory;

class InventoryNormalizer
{
    private static array $stockStatusLabels = [
        'instock' => 'موجود در انبار',
        'outofstock' => 'ناموجود',
        'onbackorder' => 'در پیش‌خرید',
    ];

    private static array $backordersLabels = [
        'no' => 'مجاز نیست',
        'notify' => 'مجاز با اطلاع به مشتری',
        'yes' => 'مجاز',
    ];

    private static array $typeLabels = [
        'simple' => 'محصول ساده',
        'variable' => 'محصول متغیر',
        'grouped' => 'محصول گروهی',
        'external' => 'محصول خارجی / معرف',
    ];

    /**
     * Normalize a product into a standard inventory structure.
     */
    public static function normalizeProduct(array $product, int $storeId): array
    {
        $productId = (int)($product['id'] ?? 0);
        $type = (string)($product['type'] ?? 'simple');
        $manageStock = (bool)($product['manage_stock'] ?? false);
        $stockQuantity = isset($product['stock_quantity']) && $product['stock_quantity'] !== null
            ? (float)$product['stock_quantity']
            : null;
        $stockStatus = (string)($product['stock_status'] ?? 'instock');
        $backorders = (string)($product['backorders'] ?? 'no');
        $lowStockAmount = isset($product['low_stock_amount']) && $product['low_stock_amount'] !== null && $product['low_stock_amount'] !== ''
            ? (int)$product['low_stock_amount']
            : null;
        $soldIndividually = (bool)($product['sold_individually'] ?? false);

        // Low stock determination: only if managing stock and quantity <= low_stock_amount (if set)
        $isLowStock = false;
        if ($manageStock && $stockQuantity !== null) {
            if ($lowStockAmount !== null) {
                $isLowStock = ($stockQuantity <= $lowStockAmount && $stockQuantity > 0);
            }
        }

        $image = !empty($product['primary_image'])
            ? $product['primary_image']
            : (!empty($product['images'][0]['src']) ? $product['images'][0]['src'] : '');

        return [
            'product_id' => $productId,
            'variation_id' => null,
            'is_variation' => false,
            'parent_id' => null,
            'product_name' => (string)($product['name'] ?? ''),
            'variation_name' => '',
            'display_name' => (string)($product['name'] ?? ''),
            'sku' => (string)($product['sku'] ?? ''),
            'type' => $type,
            'type_label' => self::$typeLabels[$type] ?? ucfirst($type),
            'manage_stock' => $manageStock,
            'stock_quantity' => $stockQuantity,
            'stock_status' => $stockStatus,
            'stock_status_label' => self::$stockStatusLabels[$stockStatus] ?? ucfirst($stockStatus),
            'backorders' => $backorders,
            'backorders_label' => self::$backordersLabels[$backorders] ?? ucfirst($backorders),
            'low_stock_amount' => $lowStockAmount,
            'is_low_stock' => $isLowStock,
            'sold_individually' => $soldIndividually,
            'status' => (string)($product['status'] ?? 'publish'),
            'permalink' => (string)($product['permalink'] ?? ''),
            'image' => $image,
            'attributes' => $product['attributes'] ?? [],
            'categories' => $product['categories'] ?? [],
            'has_variations' => !empty($product['variations']) || $type === 'variable',
            'variations_count' => (int)($product['variations_count'] ?? count($product['variations'] ?? [])),
            'price' => $product['price'] ?? 0,
            'date_modified' => (string)($product['date_modified'] ?? ''),
            'store_id' => $storeId,
        ];
    }

    /**
     * Normalize a product variation into a standard inventory structure.
     */
    public static function normalizeVariation(array $variation, array $parentProduct, int $storeId): array
    {
        $productId = (int)($parentProduct['id'] ?? ($variation['product_id'] ?? 0));
        $variationId = (int)($variation['id'] ?? 0);
        $manageStock = (bool)($variation['manage_stock'] ?? false);
        $stockQuantity = isset($variation['stock_quantity']) && $variation['stock_quantity'] !== null
            ? (float)$variation['stock_quantity']
            : null;
        $stockStatus = (string)($variation['stock_status'] ?? 'instock');
        $backorders = (string)($variation['backorders'] ?? 'no');
        $lowStockAmount = isset($variation['low_stock_amount']) && $variation['low_stock_amount'] !== null && $variation['low_stock_amount'] !== ''
            ? (int)$variation['low_stock_amount']
            : null;

        $isLowStock = false;
        if ($manageStock && $stockQuantity !== null) {
            if ($lowStockAmount !== null) {
                $isLowStock = ($stockQuantity <= $lowStockAmount && $stockQuantity > 0);
            }
        }

        // Format variation attribute name (e.g. "رنگ: قرمز، سایز: XL")
        $attrParts = [];
        foreach ($variation['attributes'] ?? [] as $attr) {
            $name = $attr['name'] ?? '';
            $opt = $attr['option'] ?? '';
            if ($name && $opt) {
                $attrParts[] = "{$name}: {$opt}";
            } elseif ($opt) {
                $attrParts[] = $opt;
            }
        }
        $variationName = !empty($attrParts) ? implode(' / ', $attrParts) : "#{$variationId}";
        $displayName = ($parentProduct['name'] ?? 'محصول متغیر') . ' (' . $variationName . ')';

        $image = !empty($variation['image']['src'])
            ? $variation['image']['src']
            : (!empty($parentProduct['primary_image']) ? $parentProduct['primary_image'] : '');

        return [
            'product_id' => $productId,
            'variation_id' => $variationId,
            'is_variation' => true,
            'parent_id' => $productId,
            'product_name' => (string)($parentProduct['name'] ?? ''),
            'variation_name' => $variationName,
            'display_name' => $displayName,
            'sku' => (string)($variation['sku'] ?? ''),
            'type' => 'variation',
            'type_label' => 'تنوع محصول',
            'manage_stock' => $manageStock,
            'stock_quantity' => $stockQuantity,
            'stock_status' => $stockStatus,
            'stock_status_label' => self::$stockStatusLabels[$stockStatus] ?? ucfirst($stockStatus),
            'backorders' => $backorders,
            'backorders_label' => self::$backordersLabels[$backorders] ?? ucfirst($backorders),
            'low_stock_amount' => $lowStockAmount,
            'is_low_stock' => $isLowStock,
            'sold_individually' => (bool)($parentProduct['sold_individually'] ?? false),
            'status' => (string)($variation['status'] ?? 'publish'),
            'permalink' => (string)($variation['permalink'] ?? ($parentProduct['permalink'] ?? '')),
            'image' => $image,
            'attributes' => $variation['attributes'] ?? [],
            'categories' => $parentProduct['categories'] ?? [],
            'has_variations' => false,
            'variations_count' => 0,
            'price' => $variation['price'] ?? 0,
            'date_modified' => (string)($variation['date_modified'] ?? ''),
            'store_id' => $storeId,
        ];
    }
}
