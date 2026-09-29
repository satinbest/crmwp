<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Repositories\CustomerTaskRepository;
use Exception;

class UpdateTaskAction implements ActionInterface
{
    private CustomerTaskRepository $taskRepository;
    private VariableResolver $variableResolver;

    public function __construct(
        ?CustomerTaskRepository $taskRepository = null,
        ?VariableResolver $variableResolver = null
    ) {
        $this->taskRepository = $taskRepository ?? new CustomerTaskRepository();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'update_task';
    }

    public function getLabel(): string
    {
        return 'ویرایش وضعیت وظیفه';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['task_id']) && empty($config['use_event_task'])) {
            $errors[] = 'شناسه وظیفه (task_id) الزامی است.';
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();

        $taskId = !empty($config['task_id'])
            ? (int)$this->variableResolver->resolveText((string)$config['task_id'], $resolvedVars)
            : (int)($resolvedVars['task.id'] ?? 0);

        if ($taskId <= 0) {
            throw new Exception("شناسه وظیفه نامعتبر است.");
        }

        $existing = $this->taskRepository->findById($taskId);
        if (!$existing || (int)$existing['store_id'] !== $storeId) {
            throw new Exception("وظیفه مورد نظر در این فروشگاه یافت نشد.");
        }

        $updateData = [];
        if (!empty($config['status'])) {
            $updateData['status'] = trim($config['status']);
        }
        if (!empty($config['priority'])) {
            $updateData['priority'] = trim($config['priority']);
        }

        if (empty($updateData)) {
            return [
                'action' => $this->getName(),
                'task_id' => $taskId,
                'status' => 'skipped',
                'reason' => 'no_updates_provided',
            ];
        }

        $updated = $this->taskRepository->update($taskId, $updateData);

        return [
            'action' => $this->getName(),
            'task_id' => $taskId,
            'updated_fields' => array_keys($updateData),
            'status' => 'success',
        ];
    }
}
