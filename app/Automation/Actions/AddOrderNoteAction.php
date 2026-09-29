<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Services\OrderService;
use Exception;

class AddOrderNoteAction implements ActionInterface
{
    private OrderService $orderService;
    private VariableResolver $variableResolver;

    public function __construct(?OrderService $orderService = null, ?VariableResolver $variableResolver = null)
    {
        $this->orderService = $orderService ?? new OrderService();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'add_order_note';
    }

    public function getLabel(): string
    {
        return 'ثبت یادداشت روی سفارش';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['note'])) {
            $errors[] = 'متن یادداشت (note) الزامی است.';
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();

        $orderId = !empty($config['order_id'])
            ? (int)$this->variableResolver->resolveText((string)$config['order_id'], $resolvedVars)
            : (int)($resolvedVars['order.id'] ?? 0);

        if ($orderId <= 0) {
            throw new Exception("شناسه سفارش برای درج یادداشت یافت نشد.");
        }

        $note = $this->variableResolver->resolveText(trim($config['note']), $resolvedVars);
        $customerNote = !empty($config['customer_note']);

        $res = $this->orderService->createOrderNote(
            $storeId,
            $orderId,
            $userId,
            $note,
            $customerNote,
            '127.0.0.1',
            'AutomationEngine/1.0'
        );

        return [
            'action' => $this->getName(),
            'order_id' => $orderId,
            'note_id' => $res['id'] ?? null,
            'customer_note' => $customerNote,
            'status' => 'success',
        ];
    }
}
