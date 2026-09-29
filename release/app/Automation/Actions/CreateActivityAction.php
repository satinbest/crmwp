<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Repositories\CustomerActivityRepository;

class CreateActivityAction implements ActionInterface
{
    private CustomerActivityRepository $activityRepository;
    private VariableResolver $variableResolver;

    public function __construct(
        ?CustomerActivityRepository $activityRepository = null,
        ?VariableResolver $variableResolver = null
    ) {
        $this->activityRepository = $activityRepository ?? new CustomerActivityRepository();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'create_activity';
    }

    public function getLabel(): string
    {
        return 'ثبت فعالیت CRM';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['action_type']) && empty($config['description'])) {
            $errors[] = 'نوع یا شرح فعالیت الزامی است.';
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();
        $actionType = !empty($config['action_type']) ? trim($config['action_type']) : 'automation_executed';

        $description = !empty($config['description'])
            ? $this->variableResolver->resolveText(trim($config['description']), $resolvedVars)
            : 'عملیات خودکار توسط موتور گردش‌کار اجرا شد.';

        $customerId = (int)($resolvedVars['customer.id'] ?? ($resolvedVars['order.customer_id'] ?? 0));
        $orderId = (int)($resolvedVars['order.id'] ?? 0);

        $details = [
            'description' => $description,
            'event_type' => $event->getType(),
            'event_id' => $event->getEventId(),
            'source' => 'automation_engine',
        ];

        if ($customerId > 0) {
            $act = $this->activityRepository->record($storeId, $userId ?: null, $actionType, $customerId, $details);
        } elseif ($orderId > 0) {
            $act = $this->activityRepository->recordForOrder($storeId, $userId ?: null, $actionType, $orderId, $details);
        } else {
            $act = $this->activityRepository->recordGeneral($storeId, $userId ?: null, $actionType, $details);
        }

        return [
            'action' => $this->getName(),
            'activity_id' => $act['id'] ?? null,
            'action_type' => $actionType,
            'status' => 'success',
        ];
    }
}
