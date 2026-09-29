<?php

namespace App\Integrations\WooCommerce;

class ProductNormalizer
{
    private static array $typeLabels = [
        'simple' => 'محصول ساده',
        'variable' => 'محصول متغیر',
        'grouped' => 'محصول گروهی',
        'external' => 'محصول خارجی / معرف',
    ];

    private static array $statusLabels = [
        'publish' => 'منتشر شده',
        'draft' => 'پیش‌نویس',
        'pending' => 'در انتظار بررسی',
        'private' => 'خصوصی',
        'trash' => 'زباله‌دان',
    ];

    private static array $stockStatusLabels = [
        'instock' => 'موجود در انبار',
        'outofstock' => 'ناموجود',
        'onbackorder' => 'در پیش‌خرید',
    ];

    /**
     * Normalize a single product from WooCommerce.
     */
    public static function normalize(array $raw, int $storeId): array
    {
        $id = (int)($raw['id'] ?? 0);
        $type = (string)($raw['type'] ?? 'simple');
        $status = (string)($raw['status'] ?? 'publish');
        $stockStatus = (string)($raw['stock_status'] ?? 'instock');

        $regPrice = isset($raw['regular_price']) && $raw['regular_price'] !== '' ? (float)$raw['regular_price'] : 0.0;
        $salePrice = isset($raw['sale_price']) && $raw['sale_price'] !== '' ? (float)$raw['sale_price'] : null;
        $price = isset($raw['price']) && $raw['price'] !== '' ? (float)$raw['price'] : ($salePrice ?? $regPrice);
        $onSale = (bool)($raw['on_sale'] ?? ($salePrice !== null && $salePrice < $regPrice));

        // Images
        $images = [];
        if (!empty($raw['images']) && is_array($raw['images'])) {
            foreach ($raw['images'] as $img) {
                if (is_array($img)) {
                    $images[] = [
                        'id' => (int)($img['id'] ?? 0),
                        'src' => (string)($img['src'] ?? ''),
                        'name' => (string)($img['name'] ?? ''),
                        'alt' => (string)($img['alt'] ?? ''),
                    ];
                }
            }
        }
        $primaryImage = !empty($images[0]['src']) ? $images[0]['src'] : '';

        // Categories
        $categories = [];
        if (!empty($raw['categories']) && is_array($raw['categories'])) {
            foreach ($raw['categories'] as $cat) {
                if (is_array($cat)) {
                    $categories[] = [
                        'id' => (int)($cat['id'] ?? 0),
                        'name' => (string)($cat['name'] ?? ''),
                        'slug' => (string)($cat['slug'] ?? ''),
                    ];
                }
            }
        }

        // Tags
        $tags = [];
        if (!empty($raw['tags']) && is_array($raw['tags'])) {
            foreach ($raw['tags'] as $tag) {
                if (is_array($tag)) {
                    $tags[] = [
                        'id' => (int)($tag['id'] ?? 0),
                        'name' => (string)($tag['name'] ?? ''),
                        'slug' => (string)($tag['slug'] ?? ''),
                    ];
                }
            }
        }

        // Attributes
        $attributes = [];
        if (!empty($raw['attributes']) && is_array($raw['attributes'])) {
            foreach ($raw['attributes'] as $attr) {
                if (is_array($attr)) {
                    $attributes[] = [
                        'id' => (int)($attr['id'] ?? 0),
                        'name' => (string)($attr['name'] ?? ''),
                        'position' => (int)($attr['position'] ?? 0),
                        'visible' => (bool)($attr['visible'] ?? true),
                        'variation' => (bool)($attr['variation'] ?? false),
                        'options' => is_array($attr['options'] ?? null) ? $attr['options'] : [],
                    ];
                }
            }
        }

        // Variations IDs list
        $variations = [];
        if (!empty($raw['variations']) && is_array($raw['variations'])) {
            foreach ($raw['variations'] as $v) {
                if (is_numeric($v)) {
                    $variations[] = (int)$v;
                } elseif (is_array($v) && isset($v['id'])) {
                    $variations[] = (int)$v['id'];
                }
            }
        }

        // Dimensions
        $dimensions = [
            'length' => (string)($raw['dimensions']['length'] ?? ''),
            'width' => (string)($raw['dimensions']['width'] ?? ''),
            'height' => (string)($raw['dimensions']['height'] ?? ''),
        ];

        // Safe metadata
        $meta = [];
        if (!empty($raw['meta_data']) && is_array($raw['meta_data'])) {
            foreach ($raw['meta_data'] as $m) {
                $k = (string)($m['key'] ?? '');
                $v = $m['value'] ?? null;
                // Exclude any internal sensitive keys
                $lowerK = strtolower($k);
                if (!str_contains($lowerK, 'secret') && !str_contains($lowerK, 'key') && !str_contains($lowerK, 'auth') && !str_contains($lowerK, 'token')) {
                    $meta[$k] = $v;
                }
            }
        }

        return [
            'id' => $id,
            'store_id' => $storeId,
            'name' => (string)($raw['name'] ?? ''),
            'slug' => (string)($raw['slug'] ?? ''),
            'permalink' => (string)($raw['permalink'] ?? ''),
            'type' => $type,
            'type_label' => self::$typeLabels[$type] ?? ucfirst($type),
            'status' => $status,
            'status_label' => self::$statusLabels[$status] ?? ucfirst($status),
            'featured' => (bool)($raw['featured'] ?? false),
            'catalog_visibility' => (string)($raw['catalog_visibility'] ?? 'visible'),
            'description' => (string)($raw['description'] ?? ''),
            'short_description' => (string)($raw['short_description'] ?? ''),
            'sku' => (string)($raw['sku'] ?? ''),
            'price' => $price,
            'regular_price' => $regPrice,
            'sale_price' => $salePrice,
            'on_sale' => $onSale,
            'date_on_sale_from' => $raw['date_on_sale_from'] ?? null,
            'date_on_sale_to' => $raw['date_on_sale_to'] ?? null,
            'manage_stock' => (bool)($raw['manage_stock'] ?? false),
            'stock_quantity' => isset($raw['stock_quantity']) ? (int)$raw['stock_quantity'] : null,
            'stock_status' => $stockStatus,
            'stock_status_label' => self::$stockStatusLabels[$stockStatus] ?? ucfirst($stockStatus),
            'backorders' => (string)($raw['backorders'] ?? 'no'),
            'low_stock_amount' => isset($raw['low_stock_amount']) ? (int)$raw['low_stock_amount'] : null,
            'sold_individually' => (bool)($raw['sold_individually'] ?? false),
            'weight' => (string)($raw['weight'] ?? ''),
            'dimensions' => $dimensions,
            'images' => $images,
            'primary_image' => $primaryImage,
            'categories' => $categories,
            'tags' => $tags,
            'attributes' => $attributes,
            'default_attributes' => is_array($raw['default_attributes'] ?? null) ? $raw['default_attributes'] : [],
            'variations' => $variations,
            'variations_count' => count($variations),
            'external_url' => (string)($raw['external_url'] ?? ''),
            'button_text' => (string)($raw['button_text'] ?? ''),
            'date_created' => (string)($raw['date_created'] ?? ''),
            'date_modified' => (string)($raw['date_modified'] ?? ''),
            'meta' => $meta,
        ];
    }

    /**
     * Normalize a list of products.
     */
    public static function normalizeCollection(array $rawList, int $storeId): array
    {
        return array_map(fn($item) => self::normalize($item, $storeId), $rawList);
    }

    /**
     * Normalize a single variation.
     */
    public static function normalizeVariation(array $raw, int $storeId, int $parentId = 0): array
    {
        $id = (int)($raw['id'] ?? 0);
        $productId = (int)($raw['parent_id'] ?? ($parentId ?: 0));
        $status = (string)($raw['status'] ?? 'publish');
        $stockStatus = (string)($raw['stock_status'] ?? 'instock');

        $regPrice = isset($raw['regular_price']) && $raw['regular_price'] !== '' ? (float)$raw['regular_price'] : 0.0;
        $salePrice = isset($raw['sale_price']) && $raw['sale_price'] !== '' ? (float)$raw['sale_price'] : null;
        $price = isset($raw['price']) && $raw['price'] !== '' ? (float)$raw['price'] : ($salePrice ?? $regPrice);
        $onSale = (bool)($raw['on_sale'] ?? ($salePrice !== null && $salePrice < $regPrice));

        // Attributes specific to this variation
        $attributes = [];
        if (!empty($raw['attributes']) && is_array($raw['attributes'])) {
            foreach ($raw['attributes'] as $attr) {
                if (is_array($attr)) {
                    $attributes[] = [
                        'id' => (int)($attr['id'] ?? 0),
                        'name' => (string)($attr['name'] ?? ''),
                        'option' => (string)($attr['option'] ?? ''),
                    ];
                }
            }
        }

        // Image
        $image = null;
        if (!empty($raw['image']) && is_array($raw['image'])) {
            $image = [
                'id' => (int)($raw['image']['id'] ?? 0),
                'src' => (string)($raw['image']['src'] ?? ''),
                'name' => (string)($raw['image']['name'] ?? ''),
                'alt' => (string)($raw['image']['alt'] ?? ''),
            ];
        }

        // Dimensions
        $dimensions = [
            'length' => (string)($raw['dimensions']['length'] ?? ''),
            'width' => (string)($raw['dimensions']['width'] ?? ''),
            'height' => (string)($raw['dimensions']['height'] ?? ''),
        ];

        // Safe metadata
        $meta = [];
        if (!empty($raw['meta_data']) && is_array($raw['meta_data'])) {
            foreach ($raw['meta_data'] as $m) {
                $k = (string)($m['key'] ?? '');
                $v = $m['value'] ?? null;
                $lowerK = strtolower($k);
                if (!str_contains($lowerK, 'secret') && !str_contains($lowerK, 'key') && !str_contains($lowerK, 'auth')) {
                    $meta[$k] = $v;
                }
            }
        }

        return [
            'id' => $id,
            'product_id' => $productId,
            'store_id' => $storeId,
            'sku' => (string)($raw['sku'] ?? ''),
            'description' => (string)($raw['description'] ?? ''),
            'permalink' => (string)($raw['permalink'] ?? ''),
            'status' => $status,
            'status_label' => self::$statusLabels[$status] ?? ucfirst($status),
            'price' => $price,
            'regular_price' => $regPrice,
            'sale_price' => $salePrice,
            'on_sale' => $onSale,
            'date_on_sale_from' => $raw['date_on_sale_from'] ?? null,
            'date_on_sale_to' => $raw['date_on_sale_to'] ?? null,
            'manage_stock' => (bool)($raw['manage_stock'] ?? false),
            'stock_quantity' => isset($raw['stock_quantity']) ? (int)$raw['stock_quantity'] : null,
            'stock_status' => $stockStatus,
            'stock_status_label' => self::$stockStatusLabels[$stockStatus] ?? ucfirst($stockStatus),
            'backorders' => (string)($raw['backorders'] ?? 'no'),
            'low_stock_amount' => isset($raw['low_stock_amount']) ? (int)$raw['low_stock_amount'] : null,
            'weight' => (string)($raw['weight'] ?? ''),
            'dimensions' => $dimensions,
            'image' => $image,
            'attributes' => $attributes,
            'date_created' => (string)($raw['date_created'] ?? ''),
            'date_modified' => (string)($raw['date_modified'] ?? ''),
            'meta' => $meta,
        ];
    }

    /**
     * Normalize a collection of variations.
     */
    public static function normalizeVariationCollection(array $rawList, int $storeId, int $parentId = 0): array
    {
        return array_map(fn($item) => self::normalizeVariation($item, $storeId, $parentId), $rawList);
    }

    /**
     * Normalize a product category.
     */
    public static function normalizeCategory(array $raw): array
    {
        return [
            'id' => (int)($raw['id'] ?? 0),
            'name' => (string)($raw['name'] ?? ''),
            'slug' => (string)($raw['slug'] ?? ''),
            'parent' => (int)($raw['parent'] ?? 0),
            'description' => (string)($raw['description'] ?? ''),
            'display' => (string)($raw['display'] ?? 'default'),
            'image' => isset($raw['image']['src']) ? (string)$raw['image']['src'] : null,
            'count' => (int)($raw['count'] ?? 0),
        ];
    }

    /**
     * Normalize a product tag.
     */
    public static function normalizeTag(array $raw): array
    {
        return [
            'id' => (int)($raw['id'] ?? 0),
            'name' => (string)($raw['name'] ?? ''),
            'slug' => (string)($raw['slug'] ?? ''),
            'description' => (string)($raw['description'] ?? ''),
            'count' => (int)($raw['count'] ?? 0),
        ];
    }

    /**
     * Normalize a product attribute taxonomy.
     */
    public static function normalizeAttribute(array $raw): array
    {
        return [
            'id' => (int)($raw['id'] ?? 0),
            'name' => (string)($raw['name'] ?? ''),
            'slug' => (string)($raw['slug'] ?? ''),
            'type' => (string)($raw['type'] ?? 'select'),
            'order_by' => (string)($raw['order_by'] ?? 'menu_order'),
            'has_archives' => (bool)($raw['has_archives'] ?? false),
        ];
    }
}
