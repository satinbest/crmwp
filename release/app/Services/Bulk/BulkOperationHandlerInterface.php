<?php

namespace App\Services\Bulk;

use App\Models\Store;

interface BulkOperationHandlerInterface
{
    /**
     * Return entity type slug (e.g. 'products', 'orders', 'customers').
     */
    public function getEntityType(): string;

    /**
     * Return list of supported action definitions and their metadata.
     */
    public function getSupportedActions(): array;

    /**
     * Validate action type and action parameters.
     * Returns normalized/validated parameters or throws InvalidArgumentException.
     */
    public function validateAction(string $actionType, array $params, ?Store $store = null): array;

    /**
     * Count total matching entities based on selection (ids or filter).
     */
    public function resolveCount(Store $store, array $selection, array $filter): int;

    /**
     * Retrieve a limited sample of entities for preview.
     */
    public function resolveSample(Store $store, array $selection, array $filter, int $limit = 10): array;

    /**
     * Retrieve a batch/page of entities for execution.
     */
    public function resolveEntitiesBatch(Store $store, array $selection, array $filter, int $page, int $perPage): array;

    /**
     * Generate preview data (sample before/after transformations and factual warnings).
     */
    public function preview(Store $store, array $selection, array $filter, string $actionType, array $params): array;

    /**
     * Execute action on a batch of entities.
     * Returns array of results per entity:
     * [
     *   [
     *     'entity_id' => int,
     *     'status' => 'completed' | 'skipped' | 'failed',
     *     'old_value' => mixed,
     *     'new_value' => mixed,
     *     'error_code' => ?string,
     *     'error_message' => ?string,
     *   ],
     *   ...
     * ]
     */
    public function executeBatch(Store $store, array $entities, string $actionType, array $params): array;
}
