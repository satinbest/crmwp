<?php

namespace App\Services\Bulk;

use App\Database\Connection;
use App\Integrations\WooCommerce\CustomerAdapter;
use App\Models\Store;
use App\Repositories\CustomerTagRepository;
use App\Repositories\CustomerTaskRepository;
use InvalidArgumentException;
use PDO;

class CustomerBulkHandler extends BaseBulkHandler
{
    private PDO $pdo;
    private CustomerTagRepository $tagRepo;
    private CustomerTaskRepository $taskRepo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
        $this->tagRepo = new CustomerTagRepository($this->pdo);
        $this->taskRepo = new CustomerTaskRepository($this->pdo);
    }

    public function getEntityType(): string
    {
        return 'customers';
    }

    public function getSupportedActions(): array
    {
        return [
            'add_tag' => [
                'name' => 'افزودن برچسب (CRM)',
                'description' => 'افزودن برچسب مدیریت مشتریان به تمام مشتریان انتخاب‌شده',
                'params' => [
                    'tag_name' => 'string|required',
                    'color' => 'string',
                ],
            ],
            'remove_tag' => [
                'name' => 'حذف برچسب (CRM)',
                'description' => 'حذف برچسب مدیریت مشتریان از تمام مشتریان انتخاب‌شده',
                'params' => [
                    'tag_id' => 'integer',
                    'tag_name' => 'string',
                ],
            ],
            'create_task' => [
                'name' => 'ایجاد وظیفه پیگیری (تسک)',
                'description' => 'ایجاد وظیفه جدید برای هر یک از مشتریان انتخاب‌شده',
                'params' => [
                    'title' => 'string|required',
                    'description' => 'string',
                    'priority' => 'enum:low,medium,high,urgent',
                    'due_date' => 'date',
                    'assigned_user_id' => 'integer',
                ],
            ],
        ];
    }

    public function validateAction(string $actionType, array $params, ?Store $store = null): array
    {
        $supported = $this->getSupportedActions();
        if (!isset($supported[$actionType])) {
            throw new InvalidArgumentException("عملیات گروهی '{$actionType}' برای مشتریان پشتیبانی نمی‌شود.");
        }

        $validated = [];

        if ($actionType === 'add_tag') {
            $tagName = trim((string)($params['tag_name'] ?? ''));
            if ($tagName === '') {
                throw new InvalidArgumentException("نام برچسب الزامی است.");
            }
            $validated['tag_name'] = $tagName;
            $validated['color'] = !empty($params['color']) ? trim((string)$params['color']) : '#4F46E5';
        } elseif ($actionType === 'remove_tag') {
            $tagId = (int)($params['tag_id'] ?? 0);
            $tagName = trim((string)($params['tag_name'] ?? ''));
            if ($tagId <= 0 && $tagName === '') {
                throw new InvalidArgumentException("تعیین شناسه یا نام برچسب برای حذف الزامی است.");
            }
            $validated['tag_id'] = $tagId;
            $validated['tag_name'] = $tagName;
        } elseif ($actionType === 'create_task') {
            $title = trim((string)($params['title'] ?? ''));
            if ($title === '') {
                throw new InvalidArgumentException("عنوان وظیفه الزامی است.");
            }
            $validated['title'] = $title;
            $validated['description'] = trim((string)($params['description'] ?? ''));
            $priority = strtolower(trim((string)($params['priority'] ?? 'medium')));
            $validated['priority'] = in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
            $validated['due_date'] = !empty($params['due_date']) ? $params['due_date'] : null;
            $validated['assigned_user_id'] = !empty($params['assigned_user_id']) ? (int)$params['assigned_user_id'] : null;
            $validated['created_by_user_id'] = !empty($params['created_by_user_id']) ? (int)$params['created_by_user_id'] : 1;
        }

        return $validated;
    }

    public function resolveCount(Store $store, array $selection, array $filter): int
    {
        if ($this->isIdsMode($selection)) {
            return count($this->extractIds($selection));
        }

        $adapter = new CustomerAdapter(
            new \App\Integrations\WooCommerce\WooCommerceClient(
                $store->url,
                $store->getDecryptedCredentials()['consumer_key'],
                $store->getDecryptedCredentials()['consumer_secret']
            ),
            (int)$store->id
        );
        $queryParams = array_merge($filter, ['page' => 1, 'per_page' => 1]);
        $res = $adapter->listCustomers($queryParams);
        return (int)($res['meta']['total'] ?? count($res['data'] ?? []));
    }

    public function resolveSample(Store $store, array $selection, array $filter, int $limit = 10): array
    {
        $adapter = new CustomerAdapter(
            new \App\Integrations\WooCommerce\WooCommerceClient(
                $store->url,
                $store->getDecryptedCredentials()['consumer_key'],
                $store->getDecryptedCredentials()['consumer_secret']
            ),
            (int)$store->id
        );

        if ($this->isIdsMode($selection)) {
            $ids = array_slice($this->extractIds($selection), 0, $limit);
            $items = [];
            foreach ($ids as $id) {
                try {
                    $c = $adapter->getCustomer($id);
                    if ($c) {
                        $items[] = $c;
                    }
                } catch (\Exception $e) {
                    // Skip
                }
            }
            return $items;
        }

        $queryParams = array_merge($filter, ['page' => 1, 'per_page' => $limit]);
        $res = $adapter->listCustomers($queryParams);
        return $res['data'] ?? [];
    }

    public function resolveEntitiesBatch(Store $store, array $selection, array $filter, int $page, int $perPage): array
    {
        $adapter = new CustomerAdapter(
            new \App\Integrations\WooCommerce\WooCommerceClient(
                $store->url,
                $store->getDecryptedCredentials()['consumer_key'],
                $store->getDecryptedCredentials()['consumer_secret']
            ),
            (int)$store->id
        );

        if ($this->isIdsMode($selection)) {
            $ids = $this->extractIds($selection);
            $offset = ($page - 1) * $perPage;
            $batchIds = array_slice($ids, $offset, $perPage);
            $items = [];
            foreach ($batchIds as $id) {
                try {
                    $c = $adapter->getCustomer($id);
                    if ($c) {
                        $items[] = $c;
                    }
                } catch (\Exception $e) {
                    // Skip
                }
            }
            return $items;
        }

        $queryParams = array_merge($filter, ['page' => $page, 'per_page' => $perPage]);
        $res = $adapter->listCustomers($queryParams);
        return $res['data'] ?? [];
    }

    public function preview(Store $store, array $selection, array $filter, string $actionType, array $params): array
    {
        $sampleCustomers = $this->resolveSample($store, $selection, $filter, 15);
        $sampleItems = [];
        $warnings = [];

        $storeId = (int)$store->id;
        $alreadyTaggedCount = 0;

        foreach ($sampleCustomers as $cust) {
            $id = (int)$cust['id'];
            $name = trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? '')) ?: ($cust['email'] ?? "مشتری #{$id}");

            $oldVal = null;
            $newVal = null;

            if ($actionType === 'add_tag') {
                $existingTags = $this->tagRepo->getTagsForCustomer($storeId, $id);
                $existingNames = array_column($existingTags, 'name');
                $oldVal = ['tags' => $existingNames];

                $targetTag = $params['tag_name'];
                if (in_array(mb_strtolower($targetTag), array_map('mb_strtolower', $existingNames), true)) {
                    $alreadyTaggedCount++;
                    $newVal = $oldVal;
                } else {
                    $newVal = ['tags' => array_values(array_unique(array_merge($existingNames, [$targetTag])))];
                }
            } elseif ($actionType === 'remove_tag') {
                $existingTags = $this->tagRepo->getTagsForCustomer($storeId, $id);
                $existingNames = array_column($existingTags, 'name');
                $oldVal = ['tags' => $existingNames];

                $targetTag = $params['tag_name'] ?? '';
                if ($targetTag !== '') {
                    $newVal = ['tags' => array_values(array_diff($existingNames, [$targetTag]))];
                } else {
                    $newVal = ['tags' => 'حذف برچسب انتخاب‌شده'];
                }
            } elseif ($actionType === 'create_task') {
                $oldVal = ['tasks_count' => count($this->taskRepo->listByCustomer($storeId, $id))];
                $newVal = [
                    'new_task' => $params['title'],
                    'priority' => $params['priority'] ?? 'medium',
                ];
            }

            $sampleItems[] = [
                'entity_id' => $id,
                'name' => $name,
                'old_value' => $oldVal,
                'new_value' => $newVal,
            ];
        }

        if ($alreadyTaggedCount > 0) {
            $warnings[] = "تعداد {$alreadyTaggedCount} مشتری در نمونه انتخابی، از قبل دارای این برچسب هستند و برای جلوگیری از تکرار رد خواهند شد (Skip).";
        }
        $warnings[] = "عملیات گروهی مشتریان روی پایگاه داده محلی CRM اعمال می‌شود و تغییری در ووکامرس ایجاد نمی‌کند.";

        return [
            'sample' => $sampleItems,
            'warnings' => $warnings,
        ];
    }

    public function executeBatch(Store $store, array $entities, string $actionType, array $params): array
    {
        $storeId = (int)$store->id;
        $results = [];

        foreach ($entities as $cust) {
            $id = (int)$cust['id'];

            if ($actionType === 'add_tag') {
                $tagName = $params['tag_name'];
                $color = $params['color'] ?? '#4F46E5';

                $existingTags = $this->tagRepo->getTagsForCustomer($storeId, $id);
                $existingNames = array_column($existingTags, 'name');

                if (in_array(mb_strtolower($tagName), array_map('mb_strtolower', $existingNames), true)) {
                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'skipped',
                        'old_value' => ['tags' => $existingNames],
                        'new_value' => ['tags' => $existingNames],
                        'error_code' => 'TAG_ALREADY_EXISTS',
                        'error_message' => 'برچسب از قبل به مشتری متصل است.',
                    ];
                } else {
                    try {
                        $this->pdo->beginTransaction();
                        $this->tagRepo->addTagToCustomer($storeId, $id, $tagName, $color);
                        $this->pdo->commit();

                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'completed',
                            'old_value' => ['tags' => $existingNames],
                            'new_value' => ['tags' => array_merge($existingNames, [$tagName])],
                            'error_code' => null,
                            'error_message' => null,
                        ];
                    } catch (\Exception $e) {
                        if ($this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'failed',
                            'old_value' => ['tags' => $existingNames],
                            'new_value' => null,
                            'error_code' => 'TAG_ADD_FAILED',
                            'error_message' => $e->getMessage(),
                        ];
                    }
                }
            } elseif ($actionType === 'remove_tag') {
                $existingTags = $this->tagRepo->getTagsForCustomer($storeId, $id);
                $tagId = $params['tag_id'] ?? 0;
                $tagName = $params['tag_name'] ?? '';

                // Find matching tag for customer
                $foundTagId = 0;
                foreach ($existingTags as $t) {
                    if ($tagId > 0 && (int)$t['id'] === $tagId) {
                        $foundTagId = (int)$t['id'];
                        break;
                    }
                    if ($tagName !== '' && mb_strtolower($t['name']) === mb_strtolower($tagName)) {
                        $foundTagId = (int)$t['id'];
                        break;
                    }
                }

                $existingNames = array_column($existingTags, 'name');

                if ($foundTagId === 0) {
                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'skipped',
                        'old_value' => ['tags' => $existingNames],
                        'new_value' => ['tags' => $existingNames],
                        'error_code' => 'TAG_NOT_FOUND',
                        'error_message' => 'برچسب مورد نظر در لیست برچسب‌های مشتری یافت نشد.',
                    ];
                } else {
                    try {
                        $this->pdo->beginTransaction();
                        $this->tagRepo->removeTagFromCustomer($storeId, $id, $foundTagId);
                        $this->pdo->commit();

                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'completed',
                            'old_value' => ['tags' => $existingNames],
                            'new_value' => ['tags' => array_values(array_filter($existingNames, fn($n) => mb_strtolower($n) !== mb_strtolower($tagName)))],
                            'error_code' => null,
                            'error_message' => null,
                        ];
                    } catch (\Exception $e) {
                        if ($this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        $results[] = [
                            'entity_id' => $id,
                            'status' => 'failed',
                            'old_value' => ['tags' => $existingNames],
                            'new_value' => null,
                            'error_code' => 'TAG_REMOVE_FAILED',
                            'error_message' => $e->getMessage(),
                        ];
                    }
                }
            } elseif ($actionType === 'create_task') {
                try {
                    $this->pdo->beginTransaction();
                    $taskData = [
                        'store_id' => $storeId,
                        'customer_id' => $id,
                        'title' => $params['title'],
                        'description' => $params['description'] ?? '',
                        'priority' => $params['priority'] ?? 'medium',
                        'due_date' => $params['due_date'] ?? null,
                        'assigned_user_id' => $params['assigned_user_id'] ?? null,
                        'created_by_user_id' => $params['created_by_user_id'] ?? 1,
                        'status' => 'pending',
                    ];
                    $createdTask = $this->taskRepo->create($taskData);
                    $this->pdo->commit();

                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'completed',
                        'old_value' => null,
                        'new_value' => [
                            'task_id' => $createdTask['id'] ?? null,
                            'title' => $params['title'],
                        ],
                        'error_code' => null,
                        'error_message' => null,
                    ];
                } catch (\Exception $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    $results[] = [
                        'entity_id' => $id,
                        'status' => 'failed',
                        'old_value' => null,
                        'new_value' => null,
                        'error_code' => 'TASK_CREATE_FAILED',
                        'error_message' => $e->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }
}
