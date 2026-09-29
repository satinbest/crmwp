<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Services\TaskService;
use Exception;

class CreateTaskAction implements ActionInterface
{
    private TaskService $taskService;
    private VariableResolver $variableResolver;

    public function __construct(?TaskService $taskService = null, ?VariableResolver $variableResolver = null)
    {
        $this->taskService = $taskService ?? new TaskService();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'create_task';
    }

    public function getLabel(): string
    {
        return 'ایجاد وظیفه جدید';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['title'])) {
            $errors[] = 'عنوان وظیفه (title) الزامی است.';
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();

        $rawTitle = $config['title'] ?? 'وظیفه خودکار اتوماسیون';
        $title = $this->variableResolver->resolveText($rawTitle, $resolvedVars);

        $rawDesc = $config['description'] ?? '';
        $description = $rawDesc ? $this->variableResolver->resolveText($rawDesc, $resolvedVars) : null;

        // Resolve customer and order IDs
        $customerId = !empty($config['customer_id'])
            ? (int)$this->variableResolver->resolveText((string)$config['customer_id'], $resolvedVars)
            : (int)($resolvedVars['customer.id'] ?? ($resolvedVars['order.customer_id'] ?? null));

        $orderId = !empty($config['order_id'])
            ? (int)$this->variableResolver->resolveText((string)$config['order_id'], $resolvedVars)
            : (int)($resolvedVars['order.id'] ?? null);

        // Due date calculation (e.g. +3 days)
        $dueDate = null;
        if (!empty($config['due_days'])) {
            $days = (int)$config['due_days'];
            $dueDate = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        } elseif (!empty($config['due_date'])) {
            $dueDate = $this->variableResolver->resolveText((string)$config['due_date'], $resolvedVars);
        }

        $assignedTo = !empty($config['assigned_to']) ? (int)$config['assigned_to'] : null;

        $taskData = [
            'title' => $title,
            'description' => $description,
            'priority' => $config['priority'] ?? 'normal',
            'status' => 'pending',
            'customer_id' => $customerId > 0 ? $customerId : null,
            'order_id' => $orderId > 0 ? $orderId : null,
            'assigned_to' => $assignedTo,
            'due_at' => $dueDate,
        ];

        $task = $this->taskService->createTask($storeId, $taskData, $userId);

        return [
            'action' => $this->getName(),
            'task_id' => $task['id'] ?? null,
            'title' => $title,
            'assigned_to' => $assignedTo,
            'status' => 'success',
        ];
    }
}
