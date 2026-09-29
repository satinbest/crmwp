<?php

namespace App\Automation\Actions;

use App\Automation\VariableResolver;
use App\Events\AutomationEvent;
use App\Services\ProductService;
use Exception;

class UpdateProductStatusAction implements ActionInterface
{
    private ProductService $productService;
    private VariableResolver $variableResolver;

    public function __construct(?ProductService $productService = null, ?VariableResolver $variableResolver = null)
    {
        $this->productService = $productService ?? new ProductService();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    public function getName(): string
    {
        return 'update_product_status';
    }

    public function getLabel(): string
    {
        return 'تغییر وضعیت انتشار محصول';
    }

    public function validate(array $config): array
    {
        $errors = [];
        if (empty($config['status'])) {
            $errors[] = 'وضعیت انتشار محصول (status) الزامی است.';
        } else {
            $status = strtolower(trim((string)$config['status']));
            if (!in_array($status, ['publish', 'draft', 'pending', 'private'], true)) {
                $errors[] = 'وضعیت انتخابی نامعتبر است (مجاز: publish, draft, pending, private).';
            }
        }
        return $errors;
    }

    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array
    {
        $storeId = $event->getStoreId();

        $productId = !empty($config['product_id'])
            ? (int)$this->variableResolver->resolveText((string)$config['product_id'], $resolvedVars)
            : (int)($resolvedVars['product.id'] ?? 0);

        if ($productId <= 0) {
            throw new Exception("شناسه محصول برای تغییر وضعیت انتشار یافت نشد.");
        }

        $newStatus = strtolower(trim((string)$config['status']));

        $payload = [
            'status' => $newStatus,
        ];

        $res = $this->productService->updateProduct(
            $storeId,
            $userId,
            $productId,
            $payload,
            '127.0.0.1',
            'AutomationEngine/1.0'
        );

        return [
            'action' => $this->getName(),
            'product_id' => $productId,
            'new_status' => $newStatus,
            'status' => 'success',
        ];
    }
}
