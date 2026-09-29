<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Repositories\CustomerTagRepository;
use App\Services\TagService;
use Exception;

class RemoveCustomerTagAction implements ActionInterface
{
    private TagService $tagService;
    private CustomerTagRepository $tagRepository;
    private VariableResolver $variableResolver;

    public function __construct(
        ?TagService $tagService = null,
        ?CustomerTagRepository $tagRepository = null,
        ?VariableResolver $variableResolver = null
    ) {
        $this->tagService = $tagService ?? new TagService();
        $this->tagRepository = $tagRepository ?? new CustomerTagRepository();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'remove_customer_tag';
    }

    public function getLabel(): string
    {
        return 'حذف برچسب از مشتری';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['tag_name']) && empty($config['tag_id'])) {
            $errors[] = 'نام یا شناسه برچسب الزامی است.';
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();

        $customerId = null;
        if (!empty($config['customer_id'])) {
            $resolvedCust = $this->variableResolver->resolveText((string)$config['customer_id'], $resolvedVars);
            $customerId = (int)$resolvedCust;
        }

        if (empty($customerId)) {
            $customerId = (int)($resolvedVars['customer.id'] ?? ($resolvedVars['order.customer_id'] ?? 0));
        }

        if ($customerId <= 0) {
            throw new Exception("شناسه مشتری برای حذف برچسب یافت نشد.");
        }

        $tagId = null;
        if (!empty($config['tag_id'])) {
            $tagId = (int)$config['tag_id'];
        } elseif (!empty($config['tag_name'])) {
            $tagName = $this->variableResolver->resolveText(trim($config['tag_name']), $resolvedVars);
            // Look up tag by name in store
            $allTags = $this->tagRepository->listStoreTags($storeId);
            foreach ($allTags as $t) {
                if (strcasecmp($t['name'], $tagName) === 0) {
                    $tagId = (int)$t['id'];
                    break;
                }
            }
        }

        if (!$tagId) {
            return [
                'action' => $this->getName(),
                'customer_id' => $customerId,
                'status' => 'skipped',
                'reason' => 'tag_not_found',
            ];
        }

        $removed = $this->tagService->removeTagFromCustomer($storeId, $customerId, $tagId, $userId);

        return [
            'action' => $this->getName(),
            'customer_id' => $customerId,
            'tag_id' => $tagId,
            'removed' => $removed,
            'status' => 'success',
        ];
    }
}
