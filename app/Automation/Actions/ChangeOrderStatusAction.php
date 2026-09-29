<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Services\OrderService;
use Exception;

class ChangeOrderStatusAction implements ActionInterface
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
        return 'change_order_status';
    }

    public function getLabel(): string
    {
        return 'تغییر وضعیت سفارش ووکامرس';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['new_status'])) {
            $errors[] = 'وضعیت جدید سفارش (new_status) الزامی است.';
        } else {
            $status = strtolower(trim((string)$config['new_status']));
            // Strict financial safety: refund actions are forbidden in v1 automations
            if (in_array($status, ['refunded', 'refund'], true)) {
                $errors[] = 'عملیات استرداد وجه (Refund) به دلایل امنیتی در موتور خودکار مجاز نمی‌باشد.';
            }
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
            throw new Exception("شناسه سفارش برای تغییر وضعیت یافت نشد.");
        }

        $newStatus = strtolower(trim((string)$config['new_status']));

        // Security check
        if (in_array($newStatus, ['refunded', 'refund'], true)) {
            throw new Exception("عملیات مالی استرداد در اتوماسیون ممنوع است.");
        }

        $res = $this->orderService->updateOrderStatus(
            $storeId,
            $orderId,
            $userId,
            $newStatus,
            '127.0.0.1',
            'AutomationEngine/1.0'
        );

        return [
            'action' => $this->getName(),
            'order_id' => $orderId,
            'new_status' => $newStatus,
            'status' => 'success',
        ];
    }
}
