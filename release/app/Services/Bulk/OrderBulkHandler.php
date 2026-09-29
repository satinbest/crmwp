<?php

namespace App\Services\Bulk;

use App\Integrations\WooCommerce\OrderAdapter;
use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Models\Store;
use App\Support\Logger;
use InvalidArgumentException;

class OrderBulkHandler extends BaseBulkHandler
{
    public function getEntityType(): string
    {
        return 'orders';
    }

    public function getSupportedActions(): array
    {
        return [
            'change_status' => [
                'name' => 'تغییر وضعیت سفارش',
                'description' => 'تغییر وضعیت سفارشات بر اساس وضعیت‌های معتبر فروشگاه',
                'params' => ['status' => 'string|required'],
            ],
            'add_note' => [
                'name' => 'افزودن یادداشت به سفارش',
                'description' => 'ثبت یک یادداشت خصوصی یا عمومی برای تمام سفارشات انتخاب‌شده',
                'params' => [
                    'note' => 'string|required',
                    'customer_note' => 'boolean',
                ],
            ],
        ];
    }

    public function getValidStatusesForStore(Store $store): array
    {
        $custom = $store->getCapability('custom_order_statuses', []);
        $slugs = [];

        if (is_array($custom) && !empty($custom)) {
            foreach ($custom as $item) {
                if (isset($item['slug'])) {
                    $slugs[] = strtolower(trim((string)$item['slug']));
                }
            }
        }

        // Standard WooCommerce fallback statuses
        $defaultSlugs = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'failed'];

        return array_values(array_unique(array_merge($defaultSlugs, $slugs)));
    }

    public function validateAction(string $actionType, array $params, ?Store $store = null): array
    {
        $supported = $this->getSupportedActions();
        if (!isset($supported[$actionType])) {
            throw new InvalidArgumentException("عملیات گروهی '{$actionType}' برای سفارشات پشتیبانی نمی‌شود.");
        }

        $validated = [];

        if ($actionType === 'change_status') {
            $status = strtolower(trim((string)($params['status'] ?? '')));
            if ($status === '') {
                throw new InvalidArgumentException("وضعیت جدید سفارش الزامی است.");
            }

            // High risk protection: Never allow bulk refunds!
            if ($status === 'refunded' || str_contains($status, 'refund')) {
                throw new InvalidArgumentException("استرداد وجه سفارش (Refund) به دلیل حساسیت‌های مالی بالا در عملیات گروهی مجاز نیست.");
            }

            if ($store) {
                $allowed = $this->getValidStatusesForStore($store);
                if (!in_array($status, $allowed, true)) {
                    throw new InvalidArgumentException("وضعیت '{$status}' در لیست وضعیت‌های معتبر فروشگاه یافت نشد.");
                }
            }

            $validated['status'] = $status;
        } elseif ($actionType === 'add_note') {
            $note = trim((string)($params['note'] ?? ''));
            if ($note === '') {
                throw new InvalidArgumentException("متن یادداشت نمی‌تواند خالی باشد.");
            }
            $validated['note'] = $note;
            $validated['customer_note'] = !empty($params['customer_note']);
        }

        return $validated;
    }

    public function resolveCount(Store $store, array $selection, array $filter): int
    {
        if ($this->isIdsMode($selection)) {
            return count($this->extractIds($selection));
        }

        $adapter = new OrderAdapter($store);
        $queryParams = array_merge($filter, ['page' => 1, 'per_page' => 1]);
        $res = $adapter->listOrders($queryParams);
        return (int)($res['meta']['total'] ?? count($res['data'] ?? []));
    }

    public function resolveSample(Store $store, array $selection, array $filter, int $limit = 10): array
    {
        $adapter = new OrderAdapter($store);

        if ($this->isIdsMode($selection)) {
            $ids = array_slice($this->extractIds($selection), 0, $limit);
            $items = [];
            foreach ($ids as $id) {
                try {
                    $order = $adapter->getOrder($id);
                    if ($order) {
                        $items[] = $order;
                    }
                } catch (\Exception $e) {
                    // Skip
                }
            }
            return $items;
        }

        $queryParams = array_merge($filter, ['page' => 1, 'per_page' => $limit]);
        $res = $adapter->listOrders($queryParams);
        return $res['data'] ?? [];
    }

    public function resolveEntitiesBatch(Store $store, array $selection, array $filter, int $page, int $perPage): array
    {
        $adapter = new OrderAdapter($store);

        if ($this->isIdsMode($selection)) {
            $ids = $this->extractIds($selection);
            $offset = ($page - 1) * $perPage;
            $batchIds = array_slice($ids, $offset, $perPage);
            $items = [];
            foreach ($batchIds as $id) {
                try {
                    $order = $adapter->getOrder($id);
                    if ($order) {
                        $items[] = $order;
                    }
                } catch (\Exception $e) {
                    // Skip
                }
            }
            return $items;
        }

        $queryParams = array_merge($filter, ['page' => $page, 'per_page' => $perPage]);
        $res = $adapter->listOrders($queryParams);
        return $res['data'] ?? [];
    }

    public function preview(Store $store, array $selection, array $filter, string $actionType, array $params): array
    {
        $sampleOrders = $this->resolveSample($store, $selection, $filter, 15);
        $sampleItems = [];
        $warnings = [];

        $alreadyInStatusCount = 0;

        foreach ($sampleOrders as $ord) {
            $id = (int)$ord['id'];
            $orderNumber = $ord['number'] ?? (string)$id;
            $name = "سفارش #{$orderNumber}";

            $oldVal = null;
            $newVal = null;

            if ($actionType === 'change_status') {
                $curStatus = $ord['status'] ?? 'pending';
                $oldVal = ['status' => $curStatus];
                $targetStatus = $params['status'];
                $newVal = ['status' => $targetStatus];

                if ($curStatus === $targetStatus) {
                    $alreadyInStatusCount++;
                }
            } elseif ($actionType === 'add_note') {
                $oldVal = ['notes_count' => 'موجود'];
                $newVal = [
                    'added_note' => $params['note'],
                    'customer_note' => !empty($params['customer_note']),
                ];
            }

            $sampleItems[] = [
                'entity_id' => $id,
                'name' => $name,
                'old_value' => $oldVal,
                'new_value' => $newVal,
            ];
        }

        if ($alreadyInStatusCount > 0) {
            $warnings[] = "تعداد {$alreadyInStatusCount} سفارش در نمونه انتخابی، در حال حاضر دارای همین وضعیت هستند و بدون تغییر عبور (Skip) خواهند شد.";
        }
        if ($actionType === 'change_status') {
            $warnings[] = "تغییر وضعیت ممکن است باعث ارسال ایمیل‌های اطلاع‌رسانی خودکار ووکامرس به مشتریان گردد.";
        }
        $warnings[] = "این عملیات بلافاصله روی فروشگاه ووکامرس اعمال می‌شود و قابل بازگردانی خودکار نیست.";

        return [
            'sample' => $sampleItems,
            'warnings' => $warnings,
        ];
    }

    public function executeBatch(Store $store, array $entities, string $actionType, array $params): array
    {
        $adapter = new OrderAdapter($store);
        $batchSupported = (bool)$store->getCapability('batch_processing_supported', true);
        $results = [];

        if ($actionType === 'change_status') {
            $targetStatus = $params['status'];
            $toUpdate = [];

            foreach ($entities as $ord) {
                $id = (int)$ord['id'];
                $curStatus = $ord['status'] ?? 'pending';
                $oldVal = ['status' => $curStatus];
                $newVal = ['status' => $targetStatus];

                if ($curStatus === $targetStatus) {
                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'skipped',
                        'old_value' => $oldVal,
                        'new_value' => $newVal,
                        'error_code' => 'ALREADY_IN_STATUS',
                        'error_message' => 'سفارش از قبل در این وضعیت قرار دارد.',
                    ];
                } else {
                    $toUpdate[$id] = [
                        'old_value' => $oldVal,
                        'new_value' => $newVal,
                    ];
                }
            }

            if (empty($toUpdate)) {
                return $results;
            }

            // Try Batch API first
            $batchSuccess = false;
            if ($batchSupported) {
                try {
                    $batchPayload = ['update' => []];
                    foreach (array_keys($toUpdate) as $id) {
                        $batchPayload['update'][] = ['id' => $id, 'status' => $targetStatus];
                    }

                    $batchResponse = $this->executeWithRetry(function () use ($adapter, $batchPayload) {
                        return $adapter->batch($batchPayload);
                    });

                    $updated = $batchResponse['update'] ?? (is_array($batchResponse) ? $batchResponse : []);
                    $updatedMap = [];
                    foreach ($updated as $u) {
                        if (isset($u['id'])) {
                            $updatedMap[(int)$u['id']] = $u;
                        }
                    }

                    foreach ($toUpdate as $id => $info) {
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
                            $errMessage = $resp['error']['message'] ?? 'خطا در ثبت وضعیت سفارش در ووکامرس';
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
                    Logger::warning("Order batch endpoint failed: " . $e->getMessage() . ". Falling back to individual updates.");
                    $batchSuccess = false;
                }
            }

            // Fallback: Individual updates
            if (!$batchSuccess) {
                foreach ($toUpdate as $id => $info) {
                    try {
                        $this->executeWithRetry(function () use ($adapter, $id, $targetStatus) {
                            return $adapter->updateStatus($id, $targetStatus);
                        });

                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'completed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => null,
                            'error_message' => null,
                        ];
                    } catch (WooCommerceApiException $e) {
                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'failed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => $e->getErrorCode(),
                            'error_message' => $e->getMessage(),
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'failed',
                            'old_value' => $info['old_value'],
                            'new_value' => $info['new_value'],
                            'error_code' => 'UPDATE_FAILED',
                            'error_message' => $e->getMessage(),
                        ];
                    }
                }
            }
        } elseif ($actionType === 'add_note') {
            $note = $params['note'];
            $custNote = !empty($params['customer_note']);

            foreach ($entities as $ord) {
                $id = (int)$ord['id'];
                $oldVal = ['note' => null];
                $newVal = ['note' => $note, 'customer_note' => $custNote];

                try {
                    $this->executeWithRetry(function () use ($adapter, $id, $note, $custNote) {
                        return $adapter->createOrderNote($id, $note, $custNote);
                    });

                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'completed',
                        'old_value' => $oldVal,
                        'new_value' => $newVal,
                        'error_code' => null,
                        'error_message' => null,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'failed',
                        'old_value' => $oldVal,
                        'new_value' => $newVal,
                        'error_code' => 'NOTE_CREATE_FAILED',
                        'error_message' => $e->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }
}
