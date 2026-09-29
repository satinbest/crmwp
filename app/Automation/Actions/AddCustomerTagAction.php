<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Services\TagService;
use Exception;

class AddCustomerTagAction implements ActionInterface
{
    private TagService $tagService;
    private VariableResolver $variableResolver;

    public function __construct(?TagService $tagService = null, ?VariableResolver $variableResolver = null)
    {
        $this->tagService = $tagService ?? new TagService();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'add_customer_tag';
    }

    public function getLabel(): string
    {
        return 'افزودن برچسب به مشتری';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['tag_name']) && empty($config['tag_id'])) {
            $errors[] = 'نام برچسب (tag_name) الزامی است.';
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();

        // 1. Resolve Customer ID
        $customerId = null;
        if (!empty($config['customer_id'])) {
            $resolvedCust = $this->variableResolver->resolveText((string)$config['customer_id'], $resolvedVars);
            $customerId = (int)$resolvedCust;
        }

        if (empty($customerId)) {
            $customerId = (int)($resolvedVars['customer.id'] ?? ($resolvedVars['order.customer_id'] ?? 0));
        }

        if ($customerId <= 0) {
            throw new Exception("شناسه مشتری برای انتساب برچسب یافت نشد.");
        }

        // 2. Resolve Tag Name and Color
        $tagName = !empty($config['tag_name'])
            ? $this->variableResolver->resolveText(trim($config['tag_name']), $resolvedVars)
            : 'VIP';
        $tagColor = !empty($config['tag_color']) ? trim($config['tag_color']) : '#4F46E5';

        // 3. Add Tag
        $tag = $this->tagService->addTagToCustomer($storeId, $customerId, $tagName, $tagColor, $userId);

        return [
            'action' => $this->getName(),
            'customer_id' => $customerId,
            'tag_name' => $tagName,
            'tag_id' => $tag['id'] ?? null,
            'status' => 'success',
        ];
    }
}
