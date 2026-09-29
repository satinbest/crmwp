<?php

namespace App\Services;

use App\Database\Connection;
use App\Integrations\WooCommerce\CustomerAdapter;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Repositories\CustomerActivityRepository;
use App\Repositories\CustomerNoteRepository;
use App\Repositories\CustomerTagRepository;
use App\Repositories\CustomerTaskRepository;
use App\Repositories\SegmentRepository;
use App\Repositories\StoreRepository;
use App\Support\Cache;
use App\Support\Logger;
use Exception;
use Throwable;
use PDO;

class CrmService
{
    private StoreRepository $storeRepository;
    private SegmentRepository $segmentRepository;
    private CustomerTagRepository $tagRepository;
    private CustomerTaskRepository $taskRepository;
    private CustomerActivityRepository $activityRepository;
    private CustomerNoteRepository $noteRepository;
    private AuditService $auditService;
    private PDO $pdo;

    public function __construct(
        ?StoreRepository $storeRepository = null,
        ?SegmentRepository $segmentRepository = null,
        ?CustomerTagRepository $tagRepository = null,
        ?CustomerTaskRepository $taskRepository = null,
        ?CustomerActivityRepository $activityRepository = null,
        ?CustomerNoteRepository $noteRepository = null,
        ?AuditService $auditService = null
    ) {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->segmentRepository = $segmentRepository ?? new SegmentRepository();
        $this->tagRepository = $tagRepository ?? new CustomerTagRepository();
        $this->taskRepository = $taskRepository ?? new CustomerTaskRepository();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->noteRepository = $noteRepository ?? new CustomerNoteRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->pdo = Connection::get();
    }

    private function getCustomerAdapter(int $storeId): CustomerAdapter
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        $creds = $store->getDecryptedCredentials();
        $client = new WooCommerceClient(
            $store->url,
            $creds['consumer_key'],
            $creds['consumer_secret']
        );

        return new CustomerAdapter($client, $store->id);
    }

    /**
     * Get CRM Dashboard Summary & KPIs
     */
    public function getSummary(int $storeId, ?int $currentUserId = null): array
    {
        $cacheKey = "crm_summary_{$storeId}_user_" . ($currentUserId ?? 0);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 1. Task metrics
        $taskMetrics = $this->taskRepository->countMetrics($storeId, $currentUserId);

        // 2. Segment & Tag counts
        $segmentsCount = $this->segmentRepository->countByStore($storeId);
        $tagsCount = $this->tagRepository->countStoreTags($storeId);

        // 3. Tasks needing attention (overdue or high/urgent priority)
        $tasksNeedingAttention = $this->taskRepository->listStoreTasks($storeId, [
            'limit' => 5,
            'view' => 'overdue',
        ], $currentUserId);

        if (empty($tasksNeedingAttention)) {
            $tasksNeedingAttention = $this->taskRepository->listStoreTasks($storeId, [
                'limit' => 5,
                'priority' => 'urgent',
            ], $currentUserId);
        }

        // 4. Upcoming tasks
        $upcomingTasks = $this->taskRepository->listStoreTasks($storeId, [
            'limit' => 5,
            'view' => 'upcoming',
        ], $currentUserId);

        // 5. Recent activities
        $recentActivities = $this->activityRepository->listStoreActivities($storeId, [
            'limit' => 8,
        ]);

        // 6. Recent segments
        $recentSegments = $this->segmentRepository->listByStore($storeId);
        $recentSegments = array_slice($recentSegments, 0, 5);

        // 7. Customers requiring follow-up (customers with open overdue tasks or tasks due today)
        $followUpCustomerIds = $this->getCustomersRequiringFollowUpIds($storeId);
        $customersRequiringFollowUp = [];
        if (!empty($followUpCustomerIds)) {
            try {
                $adapter = $this->getCustomerAdapter($storeId);
                foreach (array_slice($followUpCustomerIds, 0, 5) as $cid) {
                    $c = $adapter->getCustomer($cid);
                    if ($c) {
                        $customersRequiringFollowUp[] = [
                            'id' => $c['id'],
                            'name' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['username'] ?? 'مشتری #' . $c['id']),
                            'email' => $c['email'] ?? '',
                            'orders_count' => $c['orders_count'] ?? 0,
                            'total_spent' => $c['total_spent'] ?? 0,
                        ];
                    }
                }
            } catch (Throwable $e) {
                Logger::warning("Could not fetch WooCommerce follow-up customer details: " . $e->getMessage());
            }
        }

        $summary = [
            'kpis' => [
                'open_tasks' => $taskMetrics['open_tasks'],
                'overdue_tasks' => $taskMetrics['overdue_tasks'],
                'due_today' => $taskMetrics['due_today'],
                'upcoming_tasks' => $taskMetrics['upcoming_tasks'],
                'completed_tasks' => $taskMetrics['completed_tasks'],
                'my_open_tasks' => $taskMetrics['my_open_tasks'],
                'segments_count' => $segmentsCount,
                'tags_count' => $tagsCount,
            ],
            'tasks_needing_attention' => $tasksNeedingAttention,
            'upcoming_tasks' => $upcomingTasks,
            'recent_activities' => $recentActivities,
            'recent_segments' => $recentSegments,
            'customers_requiring_follow_up' => $customersRequiringFollowUp,
        ];

        Cache::set($cacheKey, $summary, 60); // 1 minute TTL
        return $summary;
    }

    private function getCustomersRequiringFollowUpIds(int $storeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT wc_customer_id
            FROM tasks
            WHERE store_id = :store_id
              AND wc_customer_id IS NOT NULL
              AND status NOT IN ('completed', 'cancelled')
              AND (due_date < NOW() OR DATE(due_date) = CURDATE() OR priority IN ('high', 'urgent'))
            ORDER BY id DESC
            LIMIT 10
        ");
        $stmt->execute(['store_id' => $storeId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Preview Segment Rule Matches
     */
    public function previewSegment(int $storeId, array $rules): array
    {
        $adapter = $this->getCustomerAdapter($storeId);

        // 1. Fetch customers batch (up to 100) from WooCommerce for evaluation
        $wcResponse = $adapter->listCustomers(['per_page' => 100]);
        $customers = $wcResponse['data'] ?? [];

        // 2. Load CRM auxiliary data in bulk to avoid N+1 queries
        $customerIds = array_map(fn($c) => (int)$c['id'], $customers);
        $crmData = $this->batchLoadCustomerCrmData($storeId, $customerIds);

        // 3. Evaluate rules
        $matching = [];
        foreach ($customers as $cust) {
            $cid = (int)$cust['id'];
            $custCrm = $crmData[$cid] ?? [
                'tags' => [],
                'tasks_count' => 0,
                'open_tasks_count' => 0,
                'overdue_tasks_count' => 0,
                'notes_count' => 0,
                'last_activity' => null,
            ];

            if ($this->evaluateRules($cust, $custCrm, $rules)) {
                $matching[] = [
                    'id' => $cid,
                    'name' => trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? '')) ?: ($cust['username'] ?? 'مشتری #' . $cid),
                    'email' => $cust['email'] ?? '',
                    'orders_count' => (int)($cust['orders_count'] ?? 0),
                    'total_spent' => (float)($cust['total_spent'] ?? 0),
                    'city' => $cust['billing']['city'] ?? '',
                    'tags' => $custCrm['tags'] ?? [],
                    'open_tasks_count' => $custCrm['open_tasks_count'] ?? 0,
                ];
            }
        }

        $totalEstimated = count($matching);

        return [
            'total_matching' => $totalEstimated,
            'sample_customers' => array_slice($matching, 0, 10),
            'rules' => $rules,
        ];
    }

    /**
     * Evaluate rules tree (supports AND/OR and nested groups)
     */
    public function evaluateRules(array $wcCustomer, array $crmData, array $ruleGroup): bool
    {
        $combinator = strtoupper($ruleGroup['combinator'] ?? 'AND');
        $rules = $ruleGroup['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            // Nested rule group
            if (isset($rule['rules'])) {
                $result = $this->evaluateRules($wcCustomer, $crmData, $rule);
            } else {
                $result = $this->evaluateSingleRule($wcCustomer, $crmData, $rule);
            }

            if ($combinator === 'AND' && !$result) {
                return false;
            }
            if ($combinator === 'OR' && $result) {
                return true;
            }
        }

        return $combinator === 'AND';
    }

    private function evaluateSingleRule(array $wc, array $crm, array $rule): bool
    {
        $field = $rule['field'] ?? '';
        $op = $rule['operator'] ?? 'equals';
        $targetValue = $rule['value'] ?? null;

        // Resolve field value
        $actualValue = match ($field) {
            'name' => trim(($wc['first_name'] ?? '') . ' ' . ($wc['last_name'] ?? '')),
            'email' => $wc['email'] ?? '',
            'country' => $wc['billing']['country'] ?? '',
            'state' => $wc['billing']['state'] ?? '',
            'city' => $wc['billing']['city'] ?? '',
            'role' => $wc['role'] ?? '',
            'order_count', 'orders_count' => (int)($wc['orders_count'] ?? 0),
            'total_spent' => (float)($wc['total_spent'] ?? 0),
            'aov' => !empty($wc['orders_count']) ? (float)$wc['total_spent'] / (int)$wc['orders_count'] : 0,
            'last_order_date' => $wc['last_order_date'] ?? null,
            'date_created', 'registration_date' => $wc['date_created'] ?? null,
            // CRM fields
            'tag' => array_column($crm['tags'] ?? [], 'name'),
            'tasks_count' => (int)($crm['tasks_count'] ?? 0),
            'open_tasks' => (int)($crm['open_tasks_count'] ?? 0),
            'overdue_tasks' => (int)($crm['overdue_tasks_count'] ?? 0),
            'has_notes' => ($crm['notes_count'] ?? 0) > 0,
            'last_activity' => $crm['last_activity'] ?? null,
            default => $wc[$field] ?? null,
        };

        return $this->compareValues($actualValue, $op, $targetValue);
    }

    private function compareValues(mixed $actual, string $op, mixed $target): bool
    {
        // Special operators for empty / not empty
        if ($op === 'is_empty') {
            return empty($actual);
        }
        if ($op === 'is_not_empty') {
            return !empty($actual);
        }

        // Array comparison (e.g. tag in tags array)
        if (is_array($actual)) {
            if ($op === 'contains' || $op === 'equals') {
                return in_array($target, $actual, false);
            }
            if ($op === 'not_equals') {
                return !in_array($target, $actual, false);
            }
            return false;
        }

        // Numeric comparison
        if (is_numeric($actual) && is_numeric($target)) {
            $numActual = (float)$actual;
            $numTarget = (float)$target;
            return match ($op) {
                'equals' => $numActual == $numTarget,
                'not_equals' => $numActual != $numTarget,
                'greater_than' => $numActual > $numTarget,
                'less_than' => $numActual < $numTarget,
                'greater_equal' => $numActual >= $numTarget,
                'less_equal' => $numActual <= $numTarget,
                default => false,
            };
        }

        // Date comparison
        if (in_array($op, ['before', 'after', 'between'])) {
            $actTime = strtotime((string)$actual);
            if (!$actTime) return false;

            if ($op === 'before') {
                return $actTime < strtotime((string)$target);
            }
            if ($op === 'after') {
                return $actTime > strtotime((string)$target);
            }
            if ($op === 'between' && is_array($target)) {
                $start = strtotime((string)($target[0] ?? ''));
                $end = strtotime((string)($target[1] ?? ''));
                return $actTime >= $start && $actTime <= $end;
            }
        }

        // String comparison
        $strActual = mb_strtolower(trim((string)$actual));
        $strTarget = mb_strtolower(trim((string)$target));

        return match ($op) {
            'equals' => $strActual === $strTarget,
            'not_equals' => $strActual !== $strTarget,
            'contains' => str_contains($strActual, $strTarget),
            'starts_with' => str_starts_with($strActual, $strTarget),
            default => false,
        };
    }

    private function batchLoadCustomerCrmData(int $storeId, array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $idList = implode(',', array_map('intval', $customerIds));
        $crm = [];
        foreach ($customerIds as $cid) {
            $crm[$cid] = [
                'tags' => [],
                'tasks_count' => 0,
                'open_tasks_count' => 0,
                'overdue_tasks_count' => 0,
                'notes_count' => 0,
                'last_activity' => null,
            ];
        }

        // 1. Tags
        $stmtTags = $this->pdo->query("
            SELECT ct.wc_customer_id, t.id, t.name, t.color
            FROM customer_tags ct
            JOIN tags t ON ct.tag_id = t.id
            WHERE ct.store_id = $storeId AND ct.wc_customer_id IN ($idList)
        ");
        while ($row = $stmtTags->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int)$row['wc_customer_id'];
            $crm[$cid]['tags'][] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'color' => $row['color'],
            ];
        }

        // 2. Task counts
        $stmtTasks = $this->pdo->query("
            SELECT wc_customer_id,
                   COUNT(*) AS total_tasks,
                   SUM(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS open_tasks,
                   SUM(CASE WHEN status NOT IN ('completed', 'cancelled') AND due_date < NOW() THEN 1 ELSE 0 END) AS overdue_tasks
            FROM tasks
            WHERE store_id = $storeId AND wc_customer_id IN ($idList)
            GROUP BY wc_customer_id
        ");
        while ($row = $stmtTasks->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int)$row['wc_customer_id'];
            $crm[$cid]['tasks_count'] = (int)$row['total_tasks'];
            $crm[$cid]['open_tasks_count'] = (int)$row['open_tasks'];
            $crm[$cid]['overdue_tasks_count'] = (int)$row['overdue_tasks'];
        }

        // 3. Notes counts
        $stmtNotes = $this->pdo->query("
            SELECT wc_customer_id, COUNT(*) AS total_notes
            FROM customer_notes
            WHERE store_id = $storeId AND wc_customer_id IN ($idList)
            GROUP BY wc_customer_id
        ");
        while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int)$row['wc_customer_id'];
            $crm[$cid]['notes_count'] = (int)$row['total_notes'];
        }

        // 4. Last activity
        $stmtActs = $this->pdo->query("
            SELECT entity_id, MAX(created_at) AS last_act
            FROM activities
            WHERE store_id = $storeId AND entity_type = 'customer' AND entity_id IN ($idList)
            GROUP BY entity_id
        ");
        while ($row = $stmtActs->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int)$row['entity_id'];
            $crm[$cid]['last_activity'] = $row['last_act'];
        }

        return $crm;
    }
}
