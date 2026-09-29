<?php

namespace App\Services;

use App\Repositories\CustomerActivityRepository;
use App\Repositories\CustomerTagRepository;
use App\Support\Cache;
use Exception;

class TagService
{
    private CustomerTagRepository $tagRepository;
    private CustomerActivityRepository $activityRepository;
    private AuditService $auditService;

    public function __construct(
        ?CustomerTagRepository $tagRepository = null,
        ?CustomerActivityRepository $activityRepository = null,
        ?AuditService $auditService = null
    ) {
        $this->tagRepository = $tagRepository ?? new CustomerTagRepository();
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->auditService = $auditService ?? new AuditService();
    }

    public function listTags(int $storeId): array
    {
        return $this->tagRepository->listStoreTags($storeId);
    }

    public function getTag(int $tagId, int $storeId): ?array
    {
        $tag = $this->tagRepository->findTagById($tagId);
        if (!$tag || (int)$tag['store_id'] !== $storeId) {
            return null;
        }
        return $tag;
    }

    public function createTag(int $storeId, string $name, string $color, int $userId): array
    {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            throw new Exception("نام برچسب نمی‌تواند خالی باشد.", 422);
        }

        $tag = $this->tagRepository->createTag($storeId, $cleanName, $color ?: '#4F46E5');

        $this->auditService->log(
            $userId,
            $storeId,
            'tag.created',
            'tag',
            (string)$tag['id'],
            [],
            $tag
        );

        Cache::forget("crm_summary_{$storeId}_user_{$userId}");
        return $tag;
    }

    public function updateTag(int $tagId, int $storeId, array $data, int $userId): ?array
    {
        $existing = $this->getTag($tagId, $storeId);
        if (!$existing) {
            return null;
        }

        $updated = $this->tagRepository->updateTag($tagId, $storeId, $data);

        $this->auditService->log(
            $userId,
            $storeId,
            'tag.updated',
            'tag',
            (string)$tagId,
            $existing,
            $updated
        );

        return $updated;
    }

    public function deleteTag(int $tagId, int $storeId, int $userId): bool
    {
        $existing = $this->getTag($tagId, $storeId);
        if (!$existing) {
            return false;
        }

        $deleted = $this->tagRepository->deleteTag($tagId, $storeId);

        if ($deleted) {
            $this->auditService->log(
                $userId,
                $storeId,
                'tag.deleted',
                'tag',
                (string)$tagId,
                $existing,
                []
            );
            Cache::forget("crm_summary_{$storeId}_user_{$userId}");
        }

        return $deleted;
    }

    public function addTagToCustomer(int $storeId, int $customerId, string $name, string $color, int $userId): array
    {
        $tag = $this->tagRepository->addTagToCustomer($storeId, $customerId, $name, $color);

        $this->activityRepository->record(
            $storeId,
            $userId,
            'tag_added',
            $customerId,
            ['tag_id' => $tag['id'], 'tag_name' => $tag['name']]
        );

        $this->auditService->log(
            $userId,
            $storeId,
            'customer_tag.added',
            'customer',
            (string)$customerId,
            [],
            ['tag_id' => $tag['id'], 'name' => $tag['name']]
        );

        return $tag;
    }

    public function removeTagFromCustomer(int $storeId, int $customerId, int $tagId, int $userId): bool
    {
        $tag = $this->tagRepository->findTagById($tagId);
        $removed = $this->tagRepository->removeTagFromCustomer($storeId, $customerId, $tagId);

        if ($removed && $tag) {
            $this->activityRepository->record(
                $storeId,
                $userId,
                'tag_removed',
                $customerId,
                ['tag_id' => $tagId, 'tag_name' => $tag['name']]
            );

            $this->auditService->log(
                $userId,
                $storeId,
                'customer_tag.removed',
                'customer',
                (string)$customerId,
                ['tag_id' => $tagId, 'name' => $tag['name']],
                []
            );
        }

        return $removed;
    }
}
