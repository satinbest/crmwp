<?php

namespace App\Services;

use App\Repositories\CustomerActivityRepository;
use App\Repositories\CustomerTaskRepository;
use App\Repositories\StoreRepository;
use App\Support\Cache;
use App\Support\Logger;
use Exception;

class TaskService
{
    private CustomerTaskRepository $taskRepository;
    private CustomerActivityRepository $activityRepository;
    private AuditService $auditService;
    private StoreRepository $storeRepository;

    public function __construct(
        ?CustomerTaskRepository $taskRepository = null,
        ?CustomerActivityRepository $activityRepository = null,
        ?AuditService $auditService = null,
        ?StoreRepository $storeRepository = null
    ) {
        $this->taskRepository = $taskRepository ?? new CustomerTaskRepository();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->auditService = $auditService ?? new AuditService();
        $this->storeRepository = $storeRepository ?? new StoreRepository();
    }

    public function listTasks(int $storeId, array $filters = [], ?int $currentUserId = null): array
    {
        return $this->taskRepository->listStoreTasks($storeId, $filters, $currentUserId);
    }

    public function getTask(int $taskId, int $storeId): ?array
    {
        $task = $this->taskRepository->findById($taskId);
        if (!$task || (int)$task['store_id'] !== $storeId) {
            return null;
        }
        return $task;
    }

    public function createTask(int $storeId, array $data, int $creatorUserId): array
    {
        if (empty($data['title'])) {
            throw new Exception("عنوان وظیفه الزامی است.", 422);
        }

        $taskData = [
            'store_id' => $storeId,
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => $data['status'] ?? 'pending',
            'due_date' => !empty($data['due_at']) ? $data['due_at'] : ($data['due_date'] ?? null),
            'customer_id' => !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
            'order_id' => !empty($data['order_id']) ? (int)$data['order_id'] : null,
            'assigned_user_id' => !empty($data['assigned_to']) ? (int)$data['assigned_to'] : ($data['assigned_user_id'] ?? null),
            'created_by_user_id' => $creatorUserId,
        ];

        $task = $this->taskRepository->create($taskData);

        // Audit & Activity
        $this->auditService->log(
            $creatorUserId,
            $storeId,
            'task.created',
            'task',
            (string)$task['id'],
            [],
            $task
        );

        $this->activityRepository->recordGeneral(
            $storeId,
            $creatorUserId,
            'task_created',
            'task',
            $task['id'],
            ['title' => $task['title'], 'priority' => $task['priority'], 'customer_id' => $task['wc_customer_id']]
        );

        if (!empty($task['wc_customer_id'])) {
            $this->activityRepository->record(
                $storeId,
                $creatorUserId,
                'task_created',
                (int)$task['wc_customer_id'],
                ['task_id' => $task['id'], 'title' => $task['title']]
            );
        }

        Cache::forget("crm_summary_{$storeId}_user_{$creatorUserId}");
        return $task;
    }

    public function updateTask(int $taskId, int $storeId, array $data, int $userId): ?array
    {
        $existing = $this->getTask($taskId, $storeId);
        if (!$existing) {
            return null;
        }

        $updateData = [];
        if (array_key_exists('title', $data)) $updateData['title'] = trim($data['title']);
        if (array_key_exists('description', $data)) $updateData['description'] = $data['description'];
        if (array_key_exists('priority', $data)) $updateData['priority'] = $data['priority'];
        if (array_key_exists('status', $data)) $updateData['status'] = $data['status'];
        if (array_key_exists('due_at', $data)) $updateData['due_date'] = $data['due_at'];
        if (array_key_exists('due_date', $data)) $updateData['due_date'] = $data['due_date'];
        if (array_key_exists('assigned_to', $data)) $updateData['assigned_user_id'] = $data['assigned_to'];
        if (array_key_exists('assigned_user_id', $data)) $updateData['assigned_user_id'] = $data['assigned_user_id'];

        $updated = $this->taskRepository->update($taskId, $updateData);

        // Audit
        $this->auditService->log(
            $userId,
            $storeId,
            'task.updated',
            'task',
            (string)$taskId,
            $existing,
            $updated
        );

        // Activity if status changed
        if (isset($updateData['status']) && $updateData['status'] !== $existing['status']) {
            $this->activityRepository->recordGeneral(
                $storeId,
                $userId,
                'task_status_changed',
                'task',
                $taskId,
                ['old_status' => $existing['status'], 'new_status' => $updateData['status'], 'title' => $updated['title']]
            );
        }

        Cache::forget("crm_summary_{$storeId}_user_{$userId}");
        return $updated;
    }

    public function completeTask(int $taskId, int $storeId, int $userId): ?array
    {
        $existing = $this->getTask($taskId, $storeId);
        if (!$existing) {
            return null;
        }

        $completed = $this->taskRepository->complete($taskId);

        $this->auditService->log(
            $userId,
            $storeId,
            'task.completed',
            'task',
            (string)$taskId,
            ['status' => $existing['status']],
            ['status' => 'completed']
        );

        $this->activityRepository->recordGeneral(
            $storeId,
            $userId,
            'task_completed',
            'task',
            $taskId,
            ['title' => $completed['title']]
        );

        Cache::forget("crm_summary_{$storeId}_user_{$userId}");
        return $completed;
    }

    public function reopenTask(int $taskId, int $storeId, int $userId): ?array
    {
        $existing = $this->getTask($taskId, $storeId);
        if (!$existing) {
            return null;
        }

        $reopened = $this->taskRepository->reopen($taskId);

        $this->auditService->log(
            $userId,
            $storeId,
            'task.reopened',
            'task',
            (string)$taskId,
            ['status' => $existing['status']],
            ['status' => 'in_progress']
        );

        Cache::forget("crm_summary_{$storeId}_user_{$userId}");
        return $reopened;
    }

    public function deleteTask(int $taskId, int $storeId, int $userId): bool
    {
        $existing = $this->getTask($taskId, $storeId);
        if (!$existing) {
            return false;
        }

        $deleted = $this->taskRepository->delete($taskId);

        if ($deleted) {
            $this->auditService->log(
                $userId,
                $storeId,
                'task.deleted',
                'task',
                (string)$taskId,
                $existing,
                []
            );
            Cache::forget("crm_summary_{$storeId}_user_{$userId}");
        }

        return $deleted;
    }
}
