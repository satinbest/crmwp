<?php

namespace App\Services;

use App\Integrations\WooCommerce\CustomerAdapter;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Repositories\CustomerActivityRepository;
use App\Repositories\CustomerNoteRepository;
use App\Repositories\CustomerTagRepository;
use App\Repositories\CustomerTaskRepository;
use App\Repositories\StoreRepository;
use App\Support\Logger;
use Exception;

class CustomerService
{
    private StoreRepository $storeRepository;
    private CustomerNoteRepository $noteRepository;
    private CustomerTagRepository $tagRepository;
    private CustomerTaskRepository $taskRepository;
    private CustomerActivityRepository $activityRepository;
    private AuditService $auditService;

    public function __construct(
        ?StoreRepository $storeRepository = null,
        ?CustomerNoteRepository $noteRepository = null,
        ?CustomerTagRepository $tagRepository = null,
        ?CustomerTaskRepository $taskRepository = null,
        ?CustomerActivityRepository $activityRepository = null,
        ?AuditService $auditService = null
    ) {
        $this->storeRepository = $storeRepository ?? new StoreRepository();
        $this->noteRepository = $noteRepository ?? new CustomerNoteRepository();
        $this->tagRepository = $tagRepository ?? new CustomerTagRepository();
        $this->taskRepository = $taskRepository ?? new CustomerTaskRepository();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Resolve store adapter with secure credential handling.
     */
    public function getCustomerAdapter(int $storeId): CustomerAdapter
    {
        $store = $this->storeRepository->findById($storeId);
        if (!$store) {
            throw new Exception("فروشگاه مورد نظر یافت نشد.", 404);
        }

        return new CustomerAdapter($store);
    }

    /**
     * List customers from WooCommerce with pagination, search, and sorting.
     */
    public function listCustomers(int $storeId, array $params = []): array
    {
        $bypass = !empty($params['bypass_cache']) || !empty($params['fresh']);
        if (!$bypass) {
            $localSync = new LocalSyncService();
            $meta = $localSync->getSyncState($storeId, 'customers');
            $localCount = $localSync->getLocalTableCount($storeId, 'customers');

            if ($localCount > 0 || ($meta['status'] === 'completed' && $meta['last_successful_sync'] !== null)) {
                $result = $localSync->getLocalCustomers($storeId, $params);
                foreach ($result['data'] as &$cust) {
                    $cust['tags'] = $this->tagRepository->getTagsForCustomer($storeId, (int)($cust['id'] ?? 0));
                }
                unset($cust);
                return $result;
            }
        }

        $adapter = $this->getCustomerAdapter($storeId);
        $result = $adapter->listCustomers($params);

        // Enhance customer records with local CRM tags
        $allTags = $this->tagRepository->listStoreTags($storeId);
        $tagMap = [];
        foreach ($allTags as $tag) {
            $tagMap[$tag['id']] = $tag;
        }

        // Attach tags for each customer
        foreach ($result['data'] as &$cust) {
            $cust['tags'] = $this->tagRepository->getTagsForCustomer($storeId, (int)$cust['id']);
        }

        return $result;
    }

    /**
     * Retrieve single customer details with CRM metadata.
     */
    public function getCustomer(int $storeId, int $customerId): ?array
    {
        $adapter = $this->getCustomerAdapter($storeId);
        $customer = $adapter->getCustomer($customerId);

        if (!$customer) {
            return null;
        }

        // Attach CRM entities
        $customer['tags'] = $this->tagRepository->getTagsForCustomer($storeId, $customerId);
        $customer['notes_count'] = count($this->noteRepository->listByCustomer($storeId, $customerId));
        $customer['tasks_count'] = count($this->taskRepository->listByCustomer($storeId, $customerId));

        return $customer;
    }

    /**
     * Retrieve orders for a customer directly from WooCommerce.
     */
    public function getCustomerOrders(int $storeId, int $customerId, array $params = []): array
    {
        $adapter = $this->getCustomerAdapter($storeId);
        return $adapter->getCustomerOrders($customerId, $params);
    }

    // ==========================================
    // Customer Notes (CRM Local Data)
    // ==========================================

    public function listNotes(int $storeId, int $customerId): array
    {
        return $this->noteRepository->listByCustomer($storeId, $customerId);
    }

    public function createNote(int $storeId, int $customerId, int $userId, string $content, ?string $ip = null, ?string $userAgent = null): array
    {
        $content = trim($content);
        if ($content === '') {
            throw new Exception("متن یادداشت نمی‌تواند خالی باشد.", 422);
        }

        $note = $this->noteRepository->create($storeId, $customerId, $userId, $content);

        // Activity log
        $this->activityRepository->record($storeId, $userId, 'note_created', $customerId, [
            'note_id' => $note['id'],
            'excerpt' => mb_substr($content, 0, 80) . (mb_strlen($content) > 80 ? '...' : ''),
        ]);

        // Audit log
        $this->auditService->log(
            $userId,
            $storeId,
            'CUSTOMER_NOTE_CREATED',
            'customer_note',
            (string)$note['id'],
            null,
            ['customer_id' => $customerId, 'content' => $content],
            $ip,
            $userAgent
        );

        return $note;
    }

    public function updateNote(int $storeId, int $customerId, int $noteId, int $userId, string $content, ?string $ip = null, ?string $userAgent = null): array
    {
        $content = trim($content);
        if ($content === '') {
            throw new Exception("متن یادداشت نمی‌تواند خالی باشد.", 422);
        }

        $existing = $this->noteRepository->findById($noteId);
        if (!$existing || (int)$existing['store_id'] !== $storeId || (int)$existing['wc_customer_id'] !== $customerId) {
            throw new Exception("یادداشت مورد نظر یافت نشد.", 404);
        }

        $oldContent = $existing['content'];
        $updated = $this->noteRepository->update($noteId, $content);

        // Activity log
        $this->activityRepository->record($storeId, $userId, 'note_edited', $customerId, [
            'note_id' => $noteId,
            'excerpt' => mb_substr($content, 0, 80) . (mb_strlen($content) > 80 ? '...' : ''),
        ]);

        // Audit log
        $this->auditService->log(
            $userId,
            $storeId,
            'CUSTOMER_NOTE_EDITED',
            'customer_note',
            (string)$noteId,
            ['content' => $oldContent],
            ['content' => $content],
            $ip,
            $userAgent
        );

        return $updated;
    }

    public function deleteNote(int $storeId, int $customerId, int $noteId, int $userId, ?string $ip = null, ?string $userAgent = null): bool
    {
        $existing = $this->noteRepository->findById($noteId);
        if (!$existing || (int)$existing['store_id'] !== $storeId || (int)$existing['wc_customer_id'] !== $customerId) {
            throw new Exception("یادداشت مورد نظر یافت نشد.", 404);
        }

        $this->noteRepository->delete($noteId);

        // Activity log
        $this->activityRepository->record($storeId, $userId, 'note_deleted', $customerId, [
            'note_id' => $noteId,
        ]);

        // Audit log
        $this->auditService->log(
            $userId,
            $storeId,
            'CUSTOMER_NOTE_DELETED',
            'customer_note',
            (string)$noteId,
            ['customer_id' => $customerId, 'content' => $existing['content']],
            null,
            $ip,
            $userAgent
        );

        return true;
    }

    // ==========================================
    // Customer Tags (CRM Local Data)
    // ==========================================

    public function listTags(int $storeId, int $customerId): array
    {
        return $this->tagRepository->getTagsForCustomer($storeId, $customerId);
    }

    public function addTag(int $storeId, int $customerId, int $userId, string $name, string $color = '#4F46E5', ?string $ip = null, ?string $userAgent = null): array
    {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new Exception("نام برچسب نمی‌تواند خالی باشد.", 422);
        }

        $tag = $this->tagRepository->addTagToCustomer($storeId, $customerId, $cleanName, $color);

        // Activity
        $this->activityRepository->record($storeId, $userId, 'tag_added', $customerId, [
            'tag_id' => $tag['id'],
            'tag_name' => $tag['name'],
        ]);

        // Audit
        $this->auditService->log(
            $userId,
            $storeId,
            'CUSTOMER_TAG_ADDED',
            'customer_tag',
            (string)$tag['id'],
            null,
            ['customer_id' => $customerId, 'tag_name' => $tag['name']],
            $ip,
            $userAgent
        );

        return $tag;
    }

    public function removeTag(int $storeId, int $customerId, int $tagId, int $userId, ?string $ip = null, ?string $userAgent = null): bool
    {
        $tag = $this->tagRepository->findTagById($tagId);
        $tagName = $tag['name'] ?? "Tag #{$tagId}";

        $removed = $this->tagRepository->removeTagFromCustomer($storeId, $customerId, $tagId);

        if ($removed) {
            $this->activityRepository->record($storeId, $userId, 'tag_removed', $customerId, [
                'tag_id' => $tagId,
                'tag_name' => $tagName,
            ]);

            $this->auditService->log(
                $userId,
                $storeId,
                'CUSTOMER_TAG_REMOVED',
                'customer_tag',
                (string)$tagId,
                ['customer_id' => $customerId, 'tag_name' => $tagName],
                null,
                $ip,
                $userAgent
            );
        }

        return $removed;
    }

    // ==========================================
    // Customer Tasks (CRM Local Data)
    // ==========================================

    public function listTasks(int $storeId, int $customerId): array
    {
        return $this->taskRepository->listByCustomer($storeId, $customerId);
    }

    public function createTask(int $storeId, int $customerId, int $userId, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new Exception("عنوان وظیفه الزامی است.", 422);
        }

        $task = $this->taskRepository->create([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'created_by_user_id' => $userId,
            'assigned_user_id' => !empty($data['assigned_user_id']) ? (int)$data['assigned_user_id'] : null,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'priority' => in_array($data['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $data['priority'] : 'medium',
            'status' => in_array($data['status'] ?? '', ['pending', 'in_progress', 'completed', 'cancelled'], true) ? $data['status'] : 'pending',
            'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
        ]);

        $this->activityRepository->record($storeId, $userId, 'task_created', $customerId, [
            'task_id' => $task['id'],
            'title' => $task['title'],
            'priority' => $task['priority'],
        ]);

        $this->auditService->log(
            $userId,
            $storeId,
            'CUSTOMER_TASK_CREATED',
            'task',
            (string)$task['id'],
            null,
            ['customer_id' => $customerId, 'title' => $task['title']],
            $ip,
            $userAgent
        );

        return $task;
    }

    public function updateTask(int $storeId, int $customerId, int $taskId, int $userId, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $existing = $this->taskRepository->findById($taskId);
        if (!$existing || (int)$existing['store_id'] !== $storeId || (int)$existing['wc_customer_id'] !== $customerId) {
            throw new Exception("وظیفه مورد نظر یافت نشد.", 404);
        }

        $updated = $this->taskRepository->update($taskId, $data);

        $action = 'task_updated';
        if (isset($data['status']) && $data['status'] === 'completed' && $existing['status'] !== 'completed') {
            $action = 'task_completed';
        }

        $this->activityRepository->record($storeId, $userId, $action, $customerId, [
            'task_id' => $taskId,
            'title' => $updated['title'],
            'status' => $updated['status'],
        ]);

        $this->auditService->log(
            $userId,
            $storeId,
            'CUSTOMER_TASK_UPDATED',
            'task',
            (string)$taskId,
            ['status' => $existing['status'], 'title' => $existing['title']],
            $data,
            $ip,
            $userAgent
        );

        return $updated;
    }

    // ==========================================
    // Customer Activities (CRM Local Timeline)
    // ==========================================

    public function listActivities(int $storeId, int $customerId, int $limit = 50): array
    {
        return $this->activityRepository->listByCustomer($storeId, $customerId, $limit);
    }
}
