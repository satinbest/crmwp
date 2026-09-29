<?php

namespace App\Services\Bulk;

use App\Integrations\WooCommerce\ProductAdapter;
use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Models\Store;
use App\Support\Logger;
use InvalidArgumentException;

class ProductBulkHandler extends BaseBulkHandler
{
    public function getEntityType(): string
    {
        return 'products';
    }

    public function getSupportedActions(): array
    {
        return [
            // Price Actions
            'increase_price_percent' => [
                'name' => 'افزایش درصد قیمت',
                'category' => 'price',
                'description' => 'افزایش قیمت عادی بر اساس درصد مشخص (مثلاً +10%)',
                'params' => ['value' => 'numeric|positive'],
            ],
            'decrease_price_percent' => [
                'name' => 'کاهش درصد قیمت',
                'category' => 'price',
                'description' => 'کاهش قیمت عادی بر اساس درصد مشخص (مثلاً -10%)',
                'params' => ['value' => 'numeric|positive|max:100'],
            ],
            'increase_price_amount' => [
                'name' => 'افزایش مبلغ ثابت قیمت',
                'category' => 'price',
                'description' => 'افزایش مبلغ ثابت به قیمت عادی (مثلاً +100,000 ریال)',
                'params' => ['value' => 'numeric|positive'],
            ],
            'decrease_price_amount' => [
                'name' => 'کاهش مبلغ ثابت قیمت',
                'category' => 'price',
                'description' => 'کاهش مبلغ ثابت از قیمت عادی (مثلاً -100,000 ریال)',
                'params' => ['value' => 'numeric|positive'],
            ],
            'set_regular_price' => [
                'name' => 'تنظیم قیمت عادی',
                'category' => 'price',
                'description' => 'تعیین قیمت عادی مشخص برای تمام محصولات انتخاب‌شده',
                'params' => ['value' => 'numeric|non_negative'],
            ],
            'set_sale_price' => [
                'name' => 'تنظیم قیمت ویژه (فروش فوق‌العاده)',
                'category' => 'price',
                'description' => 'تنظیم قیمت تخفیف‌خورده برای محصولات',
                'params' => ['value' => 'numeric|non_negative'],
            ],
            'clear_sale_price' => [
                'name' => 'حذف قیمت ویژه',
                'category' => 'price',
                'description' => 'حذف تخفیف و بازگرداندن قیمت به حالت عادی',
                'params' => [],
            ],

            // Inventory Actions
            'set_stock' => [
                'name' => 'تنظیم موجودی انبار',
                'category' => 'inventory',
                'description' => 'تنظیم تعداد مشخص موجودی در انبار (مدیریت انبار فعال می‌شود)',
                'params' => ['value' => 'numeric|non_negative'],
            ],
            'increase_stock' => [
                'name' => 'افزایش موجودی انبار',
                'category' => 'inventory',
                'description' => 'افزایش تعداد مشخص به موجودی فعلی انبار',
                'params' => ['value' => 'numeric|positive'],
            ],
            'decrease_stock' => [
                'name' => 'کاهش موجودی انبار',
                'category' => 'inventory',
                'description' => 'کاهش تعداد مشخص از موجودی فعلی انبار (حداقل 0)',
                'params' => ['value' => 'numeric|positive'],
            ],
            'set_stock_status' => [
                'name' => 'تنظیم وضعیت انبار',
                'category' => 'inventory',
                'description' => 'تنظیم وضعیت موجودی (موجود، ناموجود، در پیش‌خرید)',
                'params' => ['status' => 'enum:instock,outofstock,onbackorder'],
            ],

            // Taxonomy Actions
            'add_category' => [
                'name' => 'افزودن دسته‌بندی',
                'category' => 'taxonomy',
                'description' => 'افزودن دسته‌بندی به محصولات بدون حذف دسته‌های موجود',
                'params' => ['category_id' => 'integer|positive'],
            ],
            'remove_category' => [
                'name' => 'حذف دسته‌بندی',
                'category' => 'taxonomy',
                'description' => 'حذف یک دسته‌بندی مشخص از محصولات',
                'params' => ['category_id' => 'integer|positive'],
            ],
            'add_tag' => [
                'name' => 'افزودن برچسب',
                'category' => 'taxonomy',
                'description' => 'افزودن برچسب به محصولات بدون حذف برچسب‌های موجود',
                'params' => ['tag_id' => 'integer|positive'],
            ],
            'remove_tag' => [
                'name' => 'حذف برچسب',
                'category' => 'taxonomy',
                'description' => 'حذف یک برچسب مشخص از محصولات',
                'params' => ['tag_id' => 'integer|positive'],
            ],

            // Status Actions
            'set_status' => [
                'name' => 'تغییر وضعیت انتشار محصول',
                'category' => 'status',
                'description' => 'تغییر وضعیت به منتشر شده، پیش‌نویس، خصوصی یا در انتظار بررسی',
                'params' => ['status' => 'string'],
            ],

            // General & Variation Specific Actions
            'set_manage_stock' => [
                'name' => 'تنظیم قابلیت مدیریت انبار',
                'category' => 'inventory',
                'description' => 'فعال یا غیرفعال کردن مدیریت موجودی در سطح انبار',
                'params' => ['manage_stock' => 'boolean'],
            ],
            'set_weight' => [
                'name' => 'تنظیم وزن کالا (کیلوگرم)',
                'category' => 'general',
                'description' => 'تنظیم وزن فیزیکی کالا برای محاسبه هزینه حمل‌ونقل',
                'params' => ['value' => 'numeric|non_negative'],
            ],
        ];
    }

    public function validateAction(string $actionType, array $params, ?Store $store = null): array
    {
        $supported = $this->getSupportedActions();
        if (!isset($supported[$actionType])) {
            throw new InvalidArgumentException("عملیات گروهی '{$actionType}' برای محصولات پشتیبانی نمی‌شود.");
        }

        $validated = [];

        // Target Scope: 'parent' (default), 'variations', 'both'
        $target = $params['target'] ?? ($params['target_scope'] ?? 'parent');
        if (!in_array($target, ['parent', 'variations', 'both'], true)) {
            $target = 'parent';
        }
        $validated['target'] = $target;

        // Taxonomy actions only apply to parents in WooCommerce
        if ($target === 'variations' && in_array($actionType, ['add_category', 'remove_category', 'add_tag', 'remove_tag'], true)) {
            throw new InvalidArgumentException("دسته‌بندی‌ها و برچسب‌ها در ووکامرس فقط در سطح محصول والد قابل تعریف هستند و روی متغیرها اعمال نمی‌شوند.");
        }

        switch ($actionType) {
            case 'increase_price_percent':
            case 'decrease_price_percent':
                $val = (float)($params['value'] ?? 0);
                if ($val <= 0) {
                    throw new InvalidArgumentException("درصد تغییر قیمت باید بزرگتر از صفر باشد.");
                }
                if ($actionType === 'decrease_price_percent' && $val > 100) {
                    throw new InvalidArgumentException("درصد کاهش قیمت نمی‌تواند بیشتر از 100٪ باشد.");
                }
                $validated['value'] = $val;
                break;

            case 'increase_price_amount':
            case 'decrease_price_amount':
                $val = (float)($params['value'] ?? 0);
                if ($val <= 0) {
                    throw new InvalidArgumentException("مبلغ تغییر قیمت باید یک مقدار مثبت باشد.");
                }
                $validated['value'] = $val;
                break;

            case 'set_regular_price':
            case 'set_sale_price':
                $val = trim((string)($params['value'] ?? ''));
                if ($val === '' || !is_numeric($val) || (float)$val < 0) {
                    throw new InvalidArgumentException("قیمت باید یک مقدار عددی نامنفی باشد.");
                }
                $validated['value'] = (string)$val;
                break;

            case 'clear_sale_price':
                // No parameters needed
                break;

            case 'set_stock':
                $val = (int)($params['value'] ?? -1);
                if ($val < 0) {
                    throw new InvalidArgumentException("موجودی کالا باید بزرگتر یا مساوی صفر باشد.");
                }
                $validated['value'] = $val;
                break;

            case 'increase_stock':
            case 'decrease_stock':
                $val = (int)($params['value'] ?? 0);
                if ($val <= 0) {
                    throw new InvalidArgumentException("میزان تغییر موجودی باید عددی مثبت باشد.");
                }
                $validated['value'] = $val;
                break;

            case 'set_stock_status':
                $status = trim((string)($params['status'] ?? ''));
                $allowed = ['instock', 'outofstock', 'onbackorder'];
                if (!in_array($status, $allowed, true)) {
                    throw new InvalidArgumentException("وضعیت انبار نامعتبر است. مقادیر مجاز: " . implode(', ', $allowed));
                }
                $validated['status'] = $status;
                break;

            case 'add_category':
            case 'remove_category':
                $catId = (int)($params['category_id'] ?? 0);
                if ($catId <= 0) {
                    throw new InvalidArgumentException("شناسه دسته‌بندی نامعتبر است.");
                }
                $validated['category_id'] = $catId;
                break;

            case 'add_tag':
            case 'remove_tag':
                $tagId = (int)($params['tag_id'] ?? 0);
                if ($tagId <= 0) {
                    throw new InvalidArgumentException("شناسه برچسب نامعتبر است.");
                }
                $validated['tag_id'] = $tagId;
                break;

            case 'set_status':
                $status = trim((string)($params['status'] ?? ''));
                $validStatuses = ['publish', 'draft', 'pending', 'private'];
                if (!in_array($status, $validStatuses, true)) {
                    throw new InvalidArgumentException("وضعیت انتخاب‌شده برای محصول نامعتبر است.");
                }
                $validated['status'] = $status;
                break;

            case 'set_manage_stock':
                $validated['manage_stock'] = filter_var($params['manage_stock'] ?? true, FILTER_VALIDATE_BOOLEAN);
                break;

            case 'set_weight':
                $val = (float)($params['value'] ?? 0);
                if ($val < 0) {
                    throw new InvalidArgumentException("وزن کالا نمی‌تواند منفی باشد.");
                }
                $validated['value'] = (string)$val;
                break;
        }

        return $validated;
    }

    public function resolveCount(Store $store, array $selection, array $filter): int
    {
        if ($this->isIdsMode($selection)) {
            $ids = $this->extractIds($selection);
            return count($ids);
        }

        $adapter = new ProductAdapter($store);
        $queryParams = array_merge($filter, ['page' => 1, 'per_page' => 1]);
        $res = $adapter->listProducts($queryParams);
        return (int)($res['meta']['total'] ?? count($res['data'] ?? []));
    }

    public function resolveSample(Store $store, array $selection, array $filter, int $limit = 10): array
    {
        $adapter = new ProductAdapter($store);

        if ($this->isIdsMode($selection)) {
            $ids = array_slice($this->extractIds($selection), 0, $limit);
            $items = [];
            foreach ($ids as $id) {
                try {
                    $prod = $adapter->getProduct((int)$id);
                    $items[] = $prod;
                } catch (\Throwable $e) {
                    continue;
                }
            }
            return $items;
        }

        $queryParams = array_merge($filter, ['page' => 1, 'per_page' => $limit]);
        $res = $adapter->listProducts($queryParams);
        return $res['data'] ?? [];
    }

    public function resolveEntitiesBatch(Store $store, array $selection, array $filter, int $page, int $perPage): array
    {
        $adapter = new ProductAdapter($store);

        if ($this->isIdsMode($selection)) {
            $ids = $this->extractIds($selection);
            $slice = array_slice($ids, ($page - 1) * $perPage, $perPage);
            $items = [];
            foreach ($slice as $id) {
                try {
                    $prod = $adapter->getProduct((int)$id);
                    $items[] = $prod;
                } catch (\Throwable $e) {
                    continue;
                }
            }
            return $items;
        }

        $queryParams = array_merge($filter, ['page' => $page, 'per_page' => $perPage]);
        $res = $adapter->listProducts($queryParams);
        return $res['data'] ?? [];
    }

    public function preview(Store $store, array $selection, array $filter, string $actionType, array $params): array
    {
        $target = $params['target'] ?? 'parent';
        $adapter = new ProductAdapter($store);
        $sampleEntities = $this->resolveSample($store, $selection, $filter, 15);
        $sampleItems = [];
        $warnings = [];

        $missingPricesCount = 0;
        $unmanagedStockCount = 0;
        $zeroPriceCount = 0;
        $variableCount = 0;
        $sampleVariationCount = 0;

        foreach ($sampleEntities as $prod) {
            $isVariable = ($prod['type'] ?? '') === 'variable' || !empty($prod['variations']);
            if ($isVariable) {
                $variableCount++;
            }

            // 1. Parent preview (if target is parent or both)
            if ($target === 'parent' || $target === 'both') {
                $mutation = $this->calculateProductMutation($prod, $actionType, $params);
                $sampleItems[] = [
                    'entity_id' => (int)$prod['id'],
                    'name' => (string)($prod['name'] ?? "محصول #{$prod['id']}"),
                    'is_variation' => false,
                    'old_value' => $mutation['old_value'],
                    'new_value' => $mutation['new_value'],
                ];
            }

            // 2. Variations preview (if target is variations or both, and product is variable)
            if (($target === 'variations' || $target === 'both') && $isVariable) {
                try {
                    $vars = $adapter->listVariations((int)$prod['id']);
                    foreach ($vars as $v) {
                        $sampleVariationCount++;
                        $vMutation = $this->calculateProductMutation($v, $actionType, $params);
                        $attrSummary = implode(' / ', array_filter(array_map(fn($a) => ($a['name'] ?? '') . ': ' . ($a['option'] ?? ''), $v['attributes'] ?? [])));
                        $sampleItems[] = [
                            'entity_id' => (int)$v['id'],
                            'name' => ($prod['name'] ?? "محصول #{$prod['id']}") . ($attrSummary ? " (متغیر: {$attrSummary})" : " (متغیر #{$v['id']})"),
                            'is_variation' => true,
                            'parent_id' => (int)$prod['id'],
                            'old_value' => $vMutation['old_value'],
                            'new_value' => $vMutation['new_value'],
                        ];
                    }
                } catch (\Exception $e) {
                    Logger::warning("Could not fetch variations for preview of product #{$prod['id']}: " . $e->getMessage());
                }
            }
        }

        // Calculate authoritative affected counts
        $parentCount = $this->resolveCount($store, $selection, $filter);
        $estimatedVariationsCount = $sampleVariationCount;
        if ($parentCount > count($sampleEntities) && count($sampleEntities) > 0) {
            $estimatedVariationsCount = (int)round(($sampleVariationCount / count($sampleEntities)) * $parentCount);
        }

        $finalAffectedCount = match ($target) {
            'variations' => max($sampleVariationCount, $estimatedVariationsCount),
            'both' => $parentCount + max($sampleVariationCount, $estimatedVariationsCount),
            default => $parentCount,
        };

        // Factual Warnings
        if ($target === 'parent' && $variableCount > 0 && in_array($actionType, ['increase_price_percent', 'decrease_price_percent', 'increase_price_amount', 'decrease_price_amount', 'set_regular_price', 'set_sale_price', 'set_stock', 'increase_stock', 'decrease_stock'], true)) {
            $warnings[] = "تعداد {$variableCount} محصول متغیر در انتخاب وجود دارد. قیمت و موجودی محصولات متغیر در ووکامرس در سطح متغیرها تعیین می‌شود نه محصول والد. برای اعمال تغییرات روی آنها، دامنه هدف را روی «متغیرهای کالاها» یا «هر دو» قرار دهید.";
        }
        if ($target === 'variations' && in_array($actionType, ['add_category', 'remove_category', 'add_tag', 'remove_tag'], true)) {
            $warnings[] = "دسته‌بندی‌ها و برچسب‌ها در ووکامرس فقط در سطح محصول والد تعریف می‌شوند و روی متغیرها اعمال نخواهند شد.";
        }
        if ($missingPricesCount > 0) {
            $warnings[] = "تعداد {$missingPricesCount} محصول در نمونه قیمت اولیه ندارند یا قیمت آنها صفر است و تغییر درصدی روی آنها تاثیری نخواهد داشت.";
        }
        if ($zeroPriceCount > 0) {
            $warnings[] = "کاهش اعمال شده باعث صفر شدن قیمت در تعدادی از محصولات خواهد شد.";
        }
        if ($unmanagedStockCount > 0) {
            $warnings[] = "تعداد {$unmanagedStockCount} محصول در نمونه، مدیریت انبارشان غیرفعال است. این عملیات مدیریت موجودی را برای آنها فعال خواهد کرد.";
        }
        $warnings[] = "این عملیات به صورت مستقیم و دائمی در ووکامرس ثبت می‌شود و قابلیت بازگردانی خودکار (Undo) ندارد.";

        return [
            'affected_count' => $finalAffectedCount,
            'parent_count' => $parentCount,
            'variation_count' => max($sampleVariationCount, $estimatedVariationsCount),
            'target' => $target,
            'sample' => $sampleItems,
            'warnings' => $warnings,
        ];
    }

    public function executeBatch(Store $store, array $entities, string $actionType, array $params): array
    {
        $target = $params['target'] ?? 'parent';
        $adapter = new ProductAdapter($store);
        $batchSupported = (bool)$store->getCapability('batch_processing_supported', true);
        $results = [];

        $parentToExecute = [];
        $variationBatches = []; // Map of [productId => [variationsToExecute]]

        foreach ($entities as $prod) {
            $id = (int)$prod['id'];
            $isVariable = ($prod['type'] ?? '') === 'variable' || !empty($prod['variations']);

            if ($isVariable) {
                // If targeting parent or both
                if ($target === 'parent' || $target === 'both') {
                    if (in_array($actionType, ['add_category', 'remove_category', 'add_tag', 'remove_tag', 'set_status'], true)) {
                        $mutation = $this->calculateProductMutation($prod, $actionType, $params);
                        $parentToExecute[$id] = [
                            'entity' => $prod,
                            'payload' => $mutation['payload'],
                            'old_value' => $mutation['old_value'],
                            'new_value' => $mutation['new_value'],
                            'skip' => $mutation['skip'] ?? false,
                            'skip_reason' => $mutation['skip_reason'] ?? null,
                        ];
                    } elseif ($target === 'parent') {
                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'skipped',
                            'old_value' => null,
                            'new_value' => null,
                            'error_code' => 'VARIABLE_PARENT_SKIPPED',
                            'error_message' => 'در ووکامرس، قیمت و موجودی محصولات متغیر در سطح متغیرها مدیریت می‌شود. جهت ویرایش، دامنه هدف را روی «متغیرهای کالاها» تنظیم فرمایید.',
                        ];
                    }
                }

                // If targeting variations or both
                if ($target === 'variations' || $target === 'both') {
                    if (!in_array($actionType, ['add_category', 'remove_category', 'add_tag', 'remove_tag'], true)) {
                        try {
                            $vars = $adapter->listVariations($id);
                            if (!empty($vars)) {
                                $variationBatches[$id] = [
                                    'parent' => $prod,
                                    'variations' => $vars,
                                ];
                            }
                        } catch (\Exception $e) {
                            Logger::error("Failed fetching variations for product #{$id}: " . $e->getMessage());
                            $results[] = [
                                'entity_id' => $id,
                                'status' => 'failed',
                                'old_value' => null,
                                'new_value' => null,
                                'error_code' => 'FETCH_VARIATIONS_FAILED',
                                'error_message' => "خطا در دریافت متغیرهای محصول: " . $e->getMessage(),
                            ];
                        }
                    }
                }
            } else {
                // Simple product
                if ($target === 'variations') {
                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'skipped',
                        'old_value' => null,
                        'new_value' => null,
                        'error_code' => 'SIMPLE_PRODUCT_NO_VARIATIONS',
                        'error_message' => 'این کالا محصول ساده است و فاقد متغیر می‌باشد.',
                    ];
                } else {
                    $mutation = $this->calculateProductMutation($prod, $actionType, $params);
                    $parentToExecute[$id] = [
                        'entity' => $prod,
                        'payload' => $mutation['payload'],
                        'old_value' => $mutation['old_value'],
                        'new_value' => $mutation['new_value'],
                        'skip' => $mutation['skip'] ?? false,
                        'skip_reason' => $mutation['skip_reason'] ?? null,
                    ];
                }
            }
        }

        // Execute Parent Products (if any)
        if (!empty($parentToExecute)) {
            $parentResults = $this->executeParentUpdates($adapter, $parentToExecute, $batchSupported);
            $results = array_merge($results, $parentResults);
        }

        // Execute Variations (if any)
        if (!empty($variationBatches)) {
            $varResults = $this->executeVariationBatches($adapter, $variationBatches, $actionType, $params);
            $results = array_merge($results, $varResults);
        }

        return $results;
    }

    private function executeParentUpdates(ProductAdapter $adapter, array $toExecuteMap, bool $batchSupported): array
    {
        $results = [];
        $toRun = [];

        foreach ($toExecuteMap as $id => $info) {
            if ($info['skip']) {
                $results[] = [
                    'entity_id' => $id,
                    'status' => 'skipped',
                    'old_value' => $info['old_value'],
                    'new_value' => $info['new_value'],
                    'error_code' => 'SKIPPED',
                    'error_message' => $info['skip_reason'] ?? 'بدون نیاز به تغییر',
                ];
            } else {
                $toRun[$id] = $info;
            }
        }

        if (empty($toRun)) {
            return $results;
        }

        $batchSuccess = false;
        if ($batchSupported) {
            try {
                $batchPayload = ['update' => []];
                foreach ($toRun as $id => $info) {
                    $batchPayload['update'][] = array_merge(['id' => $id], $info['payload']);
                }

                $batchResponse = $this->executeWithRetry(function () use ($adapter, $batchPayload) {
                    return $adapter->batch($batchPayload);
                });

                $updatedItems = $batchResponse['update'] ?? (is_array($batchResponse) ? $batchResponse : []);
                $updatedMap = [];
                foreach ($updatedItems as $u) {
                    if (isset($u['id'])) {
                        $updatedMap[(int)$u['id']] = $u;
                    }
                }

                foreach ($toRun as $id => $info) {
                    $resp = $updatedMap[$id] ?? null;
                    if ($resp && !isset($resp['error'])) {
                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'completed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => null,
                            'error_message' => null,
                        ];
                    } else {
                        $errMessage = $resp['error']['message'] ?? 'خطا در اعمال تغییرات در ووکامرس';
                        $errCode = $resp['error']['code'] ?? 'BATCH_ITEM_ERROR';
                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'failed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => $errCode,
                            'error_message' => $errMessage,
                        ];
                    }
                }
                $batchSuccess = true;
            } catch (\Exception $e) {
                Logger::warning("Batch product update failed: " . $e->getMessage() . ". Falling back to individual updates.");
                $batchSuccess = false;
            }
        }

        if (!$batchSuccess) {
            foreach ($toRun as $id => $info) {
                try {
                    $this->executeWithRetry(function () use ($adapter, $id, $info) {
                        return $adapter->updateProduct($id, $info['payload']);
                    });

                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'completed',
                        'old_value' => $info['old_value'],
                        'new_value' => $info['new_value'],
                        'error_code' => null,
                        'error_message' => null,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'failed',
                        'old_value' => $info['old_value'],
                        'new_value' => $info['new_value'],
                        'error_code' => 'MUTATION_FAILED',
                        'error_message' => $e->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    private function executeVariationBatches(ProductAdapter $adapter, array $variationBatches, string $actionType, array $params): array
    {
        $results = [];

        foreach ($variationBatches as $parentId => $data) {
            $vars = $data['variations'];
            $toMutate = [];

            foreach ($vars as $v) {
                $vId = (int)$v['id'];
                $mutation = $this->calculateProductMutation($v, $actionType, $params);

                if ($mutation['skip']) {
                    $results[] = [
                        'entity_id' => $vId,
                        'status' => 'skipped',
                        'old_value' => $mutation['old_value'],
                        'new_value' => $mutation['new_value'],
                        'error_code' => 'SKIPPED',
                        'error_message' => $mutation['skip_reason'] ?? 'بدون نیاز به تغییر',
                    ];
                } else {
                    $toMutate[$vId] = [
                        'variation' => $v,
                        'payload' => $mutation['payload'],
                        'old_value' => $mutation['old_value'],
                        'new_value' => $mutation['new_value'],
                    ];
                }
            }

            if (empty($toMutate)) {
                continue;
            }

            // Execute batch update for variations of this parent
            $batchPayload = ['update' => []];
            foreach ($toMutate as $vId => $info) {
                $batchPayload['update'][] = array_merge(['id' => $vId], $info['payload']);
            }

            $batchDone = false;
            try {
                $batchResp = $this->executeWithRetry(function () use ($adapter, $parentId, $batchPayload) {
                    return $adapter->batchVariations($parentId, $batchPayload);
                });

                $updatedList = $batchResp['update'] ?? (is_array($batchResp) ? $batchResp : []);
                $updatedMap = [];
                foreach ($updatedList as $u) {
                    if (isset($u['id'])) {
                        $updatedMap[(int)$u['id']] = $u;
                    }
                }

                foreach ($toMutate as $vId => $info) {
                    $resp = $updatedMap[$vId] ?? null;
                    if ($resp && !isset($resp['error'])) {
                        $results[] = [
                            'entity_id' => $vId,
                            'status' => 'completed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => null,
                            'error_message' => null,
                        ];
                    } else {
                        $results[] = [
                            'entity_id' => $vId,
                            'status' => 'failed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => $resp['error']['code'] ?? 'VARIATION_BATCH_ERROR',
                            'error_message' => $resp['error']['message'] ?? 'خطا در ثبت تغییرات متغیر در ووکامرس',
                        ];
                    }
                }
                $batchDone = true;
            } catch (\Exception $e) {
                Logger::warning("Batch variation update failed for parent #{$parentId}: " . $e->getMessage() . ". Falling back to single updates.");
            }

            // Fallback: Individual variation update
            if (!$batchDone) {
                foreach ($toMutate as $vId => $info) {
                    try {
                        $this->executeWithRetry(function () use ($adapter, $parentId, $vId, $info) {
                            return $adapter->updateVariation($parentId, $vId, $info['payload']);
                        });

                        $results[] = [
                            'entity_id' => $vId,
                            'status' => 'completed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => null,
                            'error_message' => null,
                        ];
                    } catch (\Exception $ex) {
                        $results[] = [
                            'entity_id' => $vId,
                            'status' => 'failed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => 'VARIATION_UPDATE_FAILED',
                            'error_message' => $ex->getMessage(),
                        ];
                    }
                }
            }
        }

        return $results;
    }

    private function calculateProductMutation(array $prod, string $actionType, array $params): array
    {
        $payload = [];
        $oldVal = null;
        $newVal = null;
        $skip = false;
        $skipReason = null;

        switch ($actionType) {
            case 'increase_price_percent':
            case 'decrease_price_percent':
            case 'increase_price_amount':
            case 'decrease_price_amount':
            case 'set_regular_price':
            case 'set_sale_price':
            case 'clear_sale_price':
                $oldReg = (float)($prod['regular_price'] ?? 0);
                $oldVal = [
                    'regular_price' => $prod['regular_price'] ?? '',
                    'sale_price' => $prod['sale_price'] ?? '',
                    'price' => $prod['price'] ?? '',
                ];

                $newReg = $oldReg;
                $newSale = $prod['sale_price'] ?? '';

                if ($actionType === 'increase_price_percent') {
                    $newReg = round($oldReg * (1 + ($params['value'] / 100)));
                    $payload['regular_price'] = (string)$newReg;
                } elseif ($actionType === 'decrease_price_percent') {
                    $newReg = max(0, round($oldReg * (1 - ($params['value'] / 100))));
                    $payload['regular_price'] = (string)$newReg;
                } elseif ($actionType === 'increase_price_amount') {
                    $newReg = $oldReg + $params['value'];
                    $payload['regular_price'] = (string)$newReg;
                } elseif ($actionType === 'decrease_price_amount') {
                    $newReg = max(0, $oldReg - $params['value']);
                    $payload['regular_price'] = (string)$newReg;
                } elseif ($actionType === 'set_regular_price') {
                    $newReg = (float)$params['value'];
                    $payload['regular_price'] = (string)$newReg;
                } elseif ($actionType === 'set_sale_price') {
                    $newSale = (string)$params['value'];
                    $payload['sale_price'] = $newSale;
                } elseif ($actionType === 'clear_sale_price') {
                    $newSale = '';
                    $payload['sale_price'] = '';
                }

                $newVal = [
                    'regular_price' => (string)$newReg,
                    'sale_price' => $newSale,
                    'price' => ($newSale !== '' && (float)$newSale < $newReg) ? $newSale : (string)$newReg,
                ];
                break;

            case 'set_stock':
            case 'increase_stock':
            case 'decrease_stock':
            case 'set_stock_status':
                $oldVal = [
                    'stock_quantity' => $prod['stock_quantity'] ?? null,
                    'stock_status' => $prod['stock_status'] ?? 'instock',
                    'manage_stock' => $prod['manage_stock'] ?? false,
                ];

                $curQty = (int)($prod['stock_quantity'] ?? 0);
                $newQty = $curQty;
                $newStatus = $prod['stock_status'] ?? 'instock';

                if ($actionType === 'set_stock') {
                    $newQty = (int)$params['value'];
                    $payload['manage_stock'] = true;
                    $payload['stock_quantity'] = $newQty;
                } elseif ($actionType === 'increase_stock') {
                    $newQty = $curQty + (int)$params['value'];
                    $payload['manage_stock'] = true;
                    $payload['stock_quantity'] = $newQty;
                } elseif ($actionType === 'decrease_stock') {
                    $newQty = max(0, $curQty - (int)$params['value']);
                    $payload['manage_stock'] = true;
                    $payload['stock_quantity'] = $newQty;
                } elseif ($actionType === 'set_stock_status') {
                    $newStatus = $params['status'];
                    $payload['stock_status'] = $newStatus;
                }

                if ($actionType !== 'set_stock_status') {
                    $payload['stock_status'] = ($newQty > 0) ? 'instock' : 'outofstock';
                    $newStatus = $payload['stock_status'];
                }

                $newVal = [
                    'stock_quantity' => $newQty,
                    'stock_status' => $newStatus,
                    'manage_stock' => true,
                ];
                break;

            case 'set_manage_stock':
                $oldVal = ['manage_stock' => (bool)($prod['manage_stock'] ?? false)];
                $newManage = (bool)($params['manage_stock'] ?? true);
                $payload['manage_stock'] = $newManage;
                $newVal = ['manage_stock' => $newManage];
                break;

            case 'set_weight':
                $oldVal = ['weight' => (string)($prod['weight'] ?? '')];
                $newWeight = (string)($params['value'] ?? '0');
                $payload['weight'] = $newWeight;
                $newVal = ['weight' => $newWeight];
                break;

            case 'add_category':
                $targetCatId = (int)$params['category_id'];
                $existing = $prod['categories'] ?? [];
                $existingIds = array_column($existing, 'id');
                $oldVal = ['category_ids' => $existingIds];

                if (in_array($targetCatId, $existingIds, true)) {
                    $skip = true;
                    $skipReason = 'محصول از قبل در این دسته‌بندی قرار دارد.';
                    $newVal = $oldVal;
                } else {
                    $mergedIds = array_unique(array_merge($existingIds, [$targetCatId]));
                    $payload['categories'] = array_map(fn($cid) => ['id' => $cid], $mergedIds);
                    $newVal = ['category_ids' => $mergedIds];
                }
                break;

            case 'remove_category':
                $targetCatId = (int)$params['category_id'];
                $existing = $prod['categories'] ?? [];
                $existingIds = array_column($existing, 'id');
                $oldVal = ['category_ids' => $existingIds];

                if (!in_array($targetCatId, $existingIds, true)) {
                    $skip = true;
                    $skipReason = 'محصول در این دسته‌بندی قرار ندارد.';
                    $newVal = $oldVal;
                } else {
                    $remainingIds = array_values(array_diff($existingIds, [$targetCatId]));
                    $payload['categories'] = array_map(fn($cid) => ['id' => $cid], $remainingIds);
                    $newVal = ['category_ids' => $remainingIds];
                }
                break;

            case 'add_tag':
                $targetTagId = (int)$params['tag_id'];
                $existing = $prod['tags'] ?? [];
                $existingIds = array_column($existing, 'id');
                $oldVal = ['tag_ids' => $existingIds];

                if (in_array($targetTagId, $existingIds, true)) {
                    $skip = true;
                    $skipReason = 'برچسب از قبل به محصول اختصاص داده شده است.';
                    $newVal = $oldVal;
                } else {
                    $mergedIds = array_unique(array_merge($existingIds, [$targetTagId]));
                    $payload['tags'] = array_map(fn($tid) => ['id' => $tid], $mergedIds);
                    $newVal = ['tag_ids' => $mergedIds];
                }
                break;

            case 'remove_tag':
                $targetTagId = (int)$params['tag_id'];
                $existing = $prod['tags'] ?? [];
                $existingIds = array_column($existing, 'id');
                $oldVal = ['tag_ids' => $existingIds];

                if (!in_array($targetTagId, $existingIds, true)) {
                    $skip = true;
                    $skipReason = 'این برچسب به محصول اختصاص داده نشده است.';
                    $newVal = $oldVal;
                } else {
                    $remainingIds = array_values(array_diff($existingIds, [$targetTagId]));
                    $payload['tags'] = array_map(fn($tid) => ['id' => $tid], $remainingIds);
                    $newVal = ['tag_ids' => $remainingIds];
                }
                break;

            case 'set_status':
                $oldStatus = $prod['status'] ?? 'publish';
                $oldVal = ['status' => $oldStatus];
                $newStatus = $params['status'];

                if ($oldStatus === $newStatus) {
                    $skip = true;
                    $skipReason = 'محصول هم‌اکنون در این وضعیت قرار دارد.';
                    $newVal = $oldVal;
                } else {
                    $payload['status'] = $newStatus;
                    $newVal = ['status' => $newStatus];
                }
                break;
        }

        return [
            'payload' => $payload,
            'old_value' => $oldVal,
            'new_value' => $newVal,
            'skip' => $skip,
            'skip_reason' => $skipReason,
        ];
    }
}
