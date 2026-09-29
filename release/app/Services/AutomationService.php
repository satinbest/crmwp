<?php

namespace App\Services;

use App\Automation\ActionExecutor;
use App\Automation\AutomationDryRunService;
use App\Automation\AutomationEngine;
use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Repositories\AutomationRepository;
use App\Repositories\AutomationRunRepository;
use App\Repositories\StoreRepository;
use Exception;

class AutomationService
{
    private AutomationRepository $automationRepository;
    private AutomationRunRepository $runRepository;
    private AutomationEngine $engine;
    private AutomationDryRunService $dryRunService;
    private ActionExecutor $actionExecutor;
    private AuditService $auditService;
    private StoreRepository $storeRepository;

    public function __construct(
        ?AutomationRepository $automationRepository = null,
        ?AutomationRunRepository $runRepository = null,
        ?AutomationEngine $engine = null,
        ?AutomationDryRunService $dryRunService = null,
        ?ActionExecutor $actionExecutor = null,
        ?AuditService $auditService = null,
        ?StoreRepository $storeRepository = null
    ) {
        $this->automationRepository = $automationRepository ?? new AutomationRepository();
        $this->runRepository = $runRepository ?? new AutomationRunRepository();
        $this->engine = $engine ?? new AutomationEngine();
        $this->dryRunService = $dryRunService ?? new AutomationDryRunService();
        $this->actionExecutor = $actionExecutor ?? new ActionExecutor();
        $this->auditService = $auditService ?? new AuditService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }

    public function listAutomations(?int $storeId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->automationRepository->listFiltered($storeId, $filters, $page, $perPage);
    }

    public function getAutomation(int $id, ?int $storeId = null): ?array
    {
        $automation = $this->automationRepository->findById($id);
        if (!$automation) {
            return null;
        }

        // Store isolation check
        if ($storeId !== null && !empty($automation['store_id']) && (int)$automation['store_id'] !== $storeId) {
            return null;
        }

        return $automation;
    }

    public function createAutomation(array $data, int $userId, ?int $storeId = null): array
    {
        $this->validateAutomationData($data);

        $effectiveStoreId = $storeId ?? (!empty($data['store_id']) ? (int)$data['store_id'] : null);
        if ($effectiveStoreId !== null) {
            $store = $this->storeRepository->findById($effectiveStoreId);
            if (!$store) {
                throw new Exception("فروشگاه انتخابی معتبر نمی‌باشد.", 404);
            }
        }

        $createData = [
            'store_id' => $effectiveStoreId,
            'name' => trim($data['name']),
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'status' => $data['status'] ?? 'active',
            'trigger_type' => trim($data['trigger_type']),
            'trigger_config' => $data['trigger_config'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'actions' => $data['actions'],
            'execution_mode' => $data['execution_mode'] ?? 'immediate',
            'max_runs' => !empty($data['max_runs']) ? (int)$data['max_runs'] : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        $automation = $this->automationRepository->create($createData);

        // Audit Log
        $this->auditService->log(
            $userId,
            $effectiveStoreId,
            'AUTOMATION_CREATED',
            'automation',
            (string)$automation['id'],
            null,
            ['name' => $automation['name'], 'trigger' => $automation['trigger_type']]
        );

        return $automation;
    }

    public function updateAutomation(int $id, array $data, int $userId, ?int $storeId = null): array
    {
        $existing = $this->getAutomation($id, $storeId);
        if (!$existing) {
            throw new Exception("اتوماسیون مورد نظر یافت نشد.", 404);
        }

        $this->validateAutomationData($data, false);

        $updateData = [];
        $fields = [
            'name', 'description', 'status', 'trigger_type',
            'trigger_config', 'conditions', 'actions', 'execution_mode', 'max_runs'
        ];

        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $updateData[$f] = $data[$f];
            }
        }
        $updateData['updated_by'] = $userId;

        $updated = $this->automationRepository->update($id, $updateData);

        // Audit Log
        $this->auditService->log(
            $userId,
            $existing['store_id'],
            'AUTOMATION_UPDATED',
            'automation',
            (string)$id,
            ['name' => $existing['name'], 'status' => $existing['status']],
            ['name' => $updated['name'], 'status' => $updated['status']]
        );

        return $updated;
    }

    public function deleteAutomation(int $id, int $userId, ?int $storeId = null): bool
    {
        $existing = $this->getAutomation($id, $storeId);
        if (!$existing) {
            throw new Exception("اتوماسیون مورد نظر یافت نشد.", 404);
        }

        $deleted = $this->automationRepository->delete($id);

        if ($deleted) {
            $this->auditService->log(
                $userId,
                $existing['store_id'],
                'AUTOMATION_DELETED',
                'automation',
                (string)$id,
                ['name' => $existing['name']],
                null
            );
        }

        return $deleted;
    }

    public function setStatus(int $id, string $status, int $userId, ?int $storeId = null): array
    {
        $existing = $this->getAutomation($id, $storeId);
        if (!$existing) {
            throw new Exception("اتوماسیون مورد نظر یافت نشد.", 404);
        }

        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            throw new Exception("وضعیت نامعتبر است.", 422);
        }

        $this->automationRepository->updateStatus($id, $status);
        $updated = $this->automationRepository->findById($id);

        $actionName = $status === 'active' ? 'AUTOMATION_ENABLED' : 'AUTOMATION_DISABLED';
        $this->auditService->log(
            $userId,
            $existing['store_id'],
            $actionName,
            'automation',
            (string)$id,
            ['status' => $existing['status']],
            ['status' => $status]
        );

        return $updated;
    }

    public function testDryRun(int $id, ?array $sampleData = null, ?int $storeId = null): array
    {
        $automation = $this->getAutomation($id, $storeId);
        if (!$automation) {
            throw new Exception("اتوماسیون مورد نظر یافت نشد.", 404);
        }

        $effectiveStoreId = $automation['store_id'] ?: ($storeId ?: 2);
        $triggerType = $automation['trigger_type'];

        // Build mock event for dry run test
        $mockData = $sampleData ?? $this->buildSampleEventData($triggerType);
        $resourceType = explode('.', $triggerType)[0] ?? 'custom';

        $event = new AutomationEvent(
            $effectiveStoreId,
            $triggerType,
            $resourceType,
            $mockData['id'] ?? 1001,
            $mockData,
            'test_dry_run'
        );

        return $this->dryRunService->dryRun($automation, $event);
    }

    public function runManual(int $id, int $userId, ?array $customData = null, ?int $storeId = null): array
    {
        $automation = $this->getAutomation($id, $storeId);
        if (!$automation) {
            throw new Exception("اتوماسیون مورد نظر یافت نشد.", 404);
        }

        $effectiveStoreId = $automation['store_id'] ?: ($storeId ?: 2);
        $triggerType = $automation['trigger_type'];

        $eventData = $customData ?? $this->buildSampleEventData($triggerType);
        $resourceType = explode('.', $triggerType)[0] ?? 'custom';

        $event = new AutomationEvent(
            $effectiveStoreId,
            $triggerType,
            $resourceType,
            $eventData['id'] ?? 1001,
            $eventData,
            'manual_user_' . $userId
        );

        $result = $this->engine->executeAutomation($automation, $event, $userId);

        $this->auditService->log(
            $userId,
            $effectiveStoreId,
            'AUTOMATION_MANUAL_RUN',
            'automation',
            (string)$id,
            null,
            ['run_result' => $result]
        );

        return $result;
    }

    public function listRuns(int $id, int $page = 1, int $perPage = 20, ?int $storeId = null): array
    {
        $automation = $this->getAutomation($id, $storeId);
        if (!$automation) {
            throw new Exception("اتوماسیون مورد نظر یافت نشد.", 404);
        }

        return $this->runRepository->listByAutomation($id, $page, $perPage);
    }

    public function getRun(int $runId, ?int $storeId = null): ?array
    {
        $run = $this->runRepository->findById($runId);
        if (!$run) {
            return null;
        }

        if ($storeId !== null && !empty($run['store_id']) && (int)$run['store_id'] !== $storeId) {
            return null;
        }

        return $run;
    }

    public function retryRun(int $runId, int $userId, ?int $storeId = null): array
    {
        $run = $this->getRun($runId, $storeId);
        if (!$run) {
            throw new Exception("لاگ اجرای مورد نظر یافت نشد.", 404);
        }

        $automation = $this->getAutomation((int)$run['automation_id'], $storeId);
        if (!$automation) {
            throw new Exception("اتوماسیون مربوطه یافت نشد.", 404);
        }

        $context = $run['context'] ?? [];
        $eventData = $context['event']['data'] ?? [];

        $event = new AutomationEvent(
            (int)($run['store_id'] ?? 2),
            $run['trigger_type'],
            $context['event']['resource_type'] ?? 'custom',
            $context['event']['resource_id'] ?? 0,
            $eventData,
            'retry_run_' . $runId
        );

        $retryResult = $this->engine->executeAutomation($automation, $event, $userId);

        $this->auditService->log(
            $userId,
            $run['store_id'],
            'AUTOMATION_RUN_RETRIED',
            'automation_run',
            (string)$runId,
            ['original_status' => $run['status']],
            ['retry_result' => $retryResult]
        );

        return $retryResult;
    }

    public function getSchema(): array
    {
        return [
            'triggers' => [
                'woocommerce' => [
                    ['type' => 'order.created', 'label' => 'ثبت سفارش جدید', 'resource' => 'order'],
                    ['type' => 'order.updated', 'label' => 'ویرایش سفارش', 'resource' => 'order'],
                    ['type' => 'order.status_changed', 'label' => 'تغییر وضعیت سفارش', 'resource' => 'order'],
                    ['type' => 'order.deleted', 'label' => 'حذف سفارش', 'resource' => 'order'],
                    ['type' => 'product.created', 'label' => 'تعریف محصول جدید', 'resource' => 'product'],
                    ['type' => 'product.updated', 'label' => 'بروزرسانی محصول', 'resource' => 'product'],
                    ['type' => 'product.deleted', 'label' => 'حذف محصول', 'resource' => 'product'],
                    ['type' => 'customer.created', 'label' => 'ثبت‌نام مشتری جدید', 'resource' => 'customer'],
                    ['type' => 'customer.updated', 'label' => 'ویرایش مشخصات مشتری', 'resource' => 'customer'],
                    ['type' => 'inventory.low_stock', 'label' => 'کمبود موجودی کالا', 'resource' => 'product'],
                    ['type' => 'inventory.out_of_stock', 'label' => 'اتمام موجودی کالا', 'resource' => 'product'],
                ],
                'crm' => [
                    ['type' => 'task.created', 'label' => 'ایجاد وظیفه جدید', 'resource' => 'task'],
                    ['type' => 'task.completed', 'label' => 'تکمیل وظیفه', 'resource' => 'task'],
                    ['type' => 'task.overdue', 'label' => 'فرارسیدن موعد سررسید وظیفه', 'resource' => 'task'],
                    ['type' => 'customer.tag_added', 'label' => 'انتساب برچسب به مشتری', 'resource' => 'customer'],
                    ['type' => 'customer.tag_removed', 'label' => 'حذف برچسب از مشتری', 'resource' => 'customer'],
                    ['type' => 'bulk_operation.completed', 'label' => 'اتمام عملیات گروهی', 'resource' => 'bulk_operation'],
                    ['type' => 'bulk_operation.failed', 'label' => 'خطا در عملیات گروهی', 'resource' => 'bulk_operation'],
                ],
                'system' => [
                    ['type' => 'scheduled', 'label' => 'زمان‌بندی‌شده (دوره‌ای/Cron)', 'resource' => 'system'],
                    ['type' => 'manual', 'label' => 'اجرای دستی توسط کاربر', 'resource' => 'system'],
                ],
            ],
            'fields' => [
                'order' => [
                    ['field' => 'order.total', 'label' => 'مبلغ کل سفارش', 'type' => 'number'],
                    ['field' => 'order.subtotal', 'label' => 'جمع اقلام سفارش', 'type' => 'number'],
                    ['field' => 'order.status', 'label' => 'وضعیت سفارش', 'type' => 'select', 'options' => ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed']],
                    ['field' => 'order.currency', 'label' => 'واحد پولی', 'type' => 'string'],
                    ['field' => 'order.item_count', 'label' => 'تعداد اقلام', 'type' => 'number'],
                    ['field' => 'order.billing_city', 'label' => 'شهر صورتحساب', 'type' => 'string'],
                ],
                'customer' => [
                    ['field' => 'customer.total_spent', 'label' => 'مجموع خرید مشتری', 'type' => 'number'],
                    ['field' => 'customer.order_count', 'label' => 'تعداد کل سفارش‌ها', 'type' => 'number'],
                    ['field' => 'customer.role', 'label' => 'نقش کاربری', 'type' => 'string'],
                    ['field' => 'customer.city', 'label' => 'شهر مشتری', 'type' => 'string'],
                ],
                'product' => [
                    ['field' => 'product.stock_quantity', 'label' => 'موجودی انبار', 'type' => 'number'],
                    ['field' => 'product.price', 'label' => 'قیمت کالا', 'type' => 'number'],
                    ['field' => 'product.stock_status', 'label' => 'وضعیت موجودی', 'type' => 'select', 'options' => ['instock', 'outofstock', 'onbackorder']],
                ],
                'task' => [
                    ['field' => 'task.status', 'label' => 'وضعیت وظیفه', 'type' => 'select', 'options' => ['pending', 'in_progress', 'completed', 'cancelled']],
                    ['field' => 'task.priority', 'label' => 'اولویت وظیفه', 'type' => 'select', 'options' => ['low', 'normal', 'high', 'urgent']],
                ],
            ],
            'operators' => [
                ['value' => 'equals', 'label' => 'برابر با (==)'],
                ['value' => 'not_equals', 'label' => 'نابرابر (!=)'],
                ['value' => 'greater_than', 'label' => 'بزرگتر از (>)'],
                ['value' => 'greater_or_equal', 'label' => 'بزرگتر یا مساوی (>=)'],
                ['value' => 'less_than', 'label' => 'کوچکتر از (<)'],
                ['value' => 'less_or_equal', 'label' => 'کوچکتر یا مساوی (<=)'],
                ['value' => 'contains', 'label' => 'شامل باشد'],
                ['value' => 'not_contains', 'label' => 'شامل نباشد'],
                ['value' => 'starts_with', 'label' => 'شروع شود با'],
                ['value' => 'ends_with', 'label' => 'پایان یابد با'],
                ['value' => 'is_empty', 'label' => 'خالی باشد'],
                ['value' => 'is_not_empty', 'label' => 'خالی نباشد'],
                ['value' => 'in', 'label' => 'عضو لیست باشد (In)'],
                ['value' => 'not_in', 'label' => 'عضو لیست نباشد (Not In)'],
                ['value' => 'between', 'label' => 'بین دو مقدار باشد (Between)'],
            ],
            'actions' => $this->actionExecutor->getAvailableActions(),
            'variables' => VariableResolver::getWhitelistedVariables(),
        ];
    }

    private function validateAutomationData(array $data, bool $isCreate = true): void
    {
        if ($isCreate || isset($data['name'])) {
            if (empty($data['name'])) {
                throw new Exception("نام اتوماسیون نمی‌تواند خالی باشد.", 422);
            }
        }

        if ($isCreate || isset($data['trigger_type'])) {
            if (empty($data['trigger_type'])) {
                throw new Exception("انتخاب نوع تریگر الزامی است.", 422);
            }
        }

        if ($isCreate || isset($data['actions'])) {
            $actions = $data['actions'] ?? [];
            if (is_string($actions)) {
                $actions = json_decode($actions, true) ?: [];
            }
            if (empty($actions) || !is_array($actions)) {
                throw new Exception("حداقل یک اقدام (Action) باید برای اتوماسیون تعریف شود.", 422);
            }

            foreach ($actions as $act) {
                $actType = $act['type'] ?? ($act['action'] ?? '');
                $actionObj = $this->actionExecutor->getAction($actType);
                if (!$actionObj) {
                    throw new Exception("اقدام «{$actType}» در سامانه پشتیبانی نمی‌شود.", 422);
                }
                $valErrors = $actionObj->validate($act['config'] ?? $act);
                if (!empty($valErrors)) {
                    throw new Exception("خطا در پیکربندی اقدام {$actType}: " . implode(', ', $valErrors), 422);
                }
            }
        }
    }

    private function buildSampleEventData(string $triggerType): array
    {
        if (str_starts_with($triggerType, 'order.')) {
            return [
                'id' => 1024,
                'number' => '1024',
                'status' => 'processing',
                'total' => 6500000,
                'subtotal' => 6000000,
                'currency' => 'IRR',
                'customer_id' => 301,
                'billing' => [
                    'first_name' => 'علیرضا',
                    'last_name' => 'شمس',
                    'email' => 'alireza@example.com',
                    'phone' => '09123456789',
                    'city' => 'تهران',
                ],
                'line_items' => [
                    ['id' => 1, 'name' => 'کفش چرم مردانه', 'quantity' => 1, 'total' => 6000000],
                ],
            ];
        }

        if (str_starts_with($triggerType, 'product.') || str_starts_with($triggerType, 'inventory.')) {
            return [
                'id' => 501,
                'name' => 'پیراهن آکسفورد کلاسیک',
                'sku' => 'SHIRT-OX-01',
                'price' => 1250000,
                'stock_quantity' => 3,
                'stock_status' => 'instock',
            ];
        }

        if (str_starts_with($triggerType, 'task.')) {
            return [
                'id' => 88,
                'title' => 'پیگیری واریز وجه سفارش',
                'status' => 'pending',
                'priority' => 'high',
                'customer_id' => 301,
            ];
        }

        return [
            'id' => 301,
            'first_name' => 'علیرضا',
            'last_name' => 'شمس',
            'email' => 'alireza@example.com',
            'orders_count' => 5,
            'total_spent' => 28000000,
            'role' => 'customer',
        ];
    }
}
