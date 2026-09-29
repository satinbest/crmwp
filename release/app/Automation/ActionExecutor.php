<?php

namespace App\Automation;

use App\Automation\Actions\ActionInterface;
use App\Automation\Actions\AddCustomerTagAction;
use App\Automation\Actions\AddOrderNoteAction;
use App\Automation\Actions\ChangeOrderStatusAction;
use App\Automation\Actions\CreateActivityAction;
use App\Automation\Actions\CreateNotificationAction;
use App\Automation\Actions\CreateTaskAction;
use App\Automation\Actions\RemoveCustomerTagAction;
use App\Automation\Actions\UpdateProductStatusAction;
use App\Automation\Actions\UpdateProductStockAction;
use App\Automation\Actions\UpdateTaskAction;
use App\Events\AutomationEvent;
use App\Support\Logger;
use Exception;

class ActionExecutor
{
    /** @var array<string, ActionInterface> */
    private array $actions = [];

    public function __construct()
    {
        $this->registerDefaultActions();
    }

    private function registerDefaultActions(): void
    {
        $this->register(new AddCustomerTagAction());
        $this->register(new RemoveCustomerTagAction());
        $this->register(new CreateTaskAction());
        $this->register(new UpdateTaskAction());
        $this->register(new CreateActivityAction());
        $this->register(new CreateNotificationAction());
        $this->register(new ChangeOrderStatusAction());
        $this->register(new AddOrderNoteAction());
        $this->register(new UpdateProductStockAction());
        $this->register(new UpdateProductStatusAction());
    }

    public function register(ActionInterface $action): void
    {
        $this->actions[$action->getName()] = $action;
    }

    public function getAction(string $name): ?ActionInterface
    {
        return $this->actions[$name] ?? null;
    }

    /**
     * Return list of available actions with labels and parameter definitions for UI builder.
     */
    public function getAvailableActions(): array
    {
        $list = [];
        foreach ($this->actions as $name => $action) {
            $list[] = [
                'name' => $name,
                'label' => $action->getLabel(),
            ];
        }
        return $list;
    }

    /**
     * Execute array of configured actions.
     */
    public function executeAll(
        array|string $actionsConfig,
        AutomationEvent $event,
        array $resolvedVars,
        int $userId = 0,
        bool $stopOnError = false
    ): array {
        if (is_string($actionsConfig)) {
            $decoded = json_decode($actionsConfig, true);
            $actionsConfig = is_array($decoded) ? $decoded : [];
        }

        $results = [];
        $hasErrors = false;

        foreach ($actionsConfig as $idx => $actionDef) {
            $actionType = $actionDef['type'] ?? ($actionDef['action'] ?? '');
            $config = $actionDef['config'] ?? $actionDef;

            $action = $this->getAction($actionType);
            if (!$action) {
                $err = "اکشن ناشناخته یا غیرمجاز: «{$actionType}»";
                Logger::warning($err);
                $results[] = [
                    'index' => $idx,
                    'type' => $actionType,
                    'status' => 'failed',
                    'error' => $err,
                ];
                $hasErrors = true;
                if ($stopOnError) {
                    break;
                }
                continue;
            }

            // Validate config
            $valErrors = $action->validate($config);
            if (!empty($valErrors)) {
                $err = implode(' | ', $valErrors);
                $results[] = [
                    'index' => $idx,
                    'type' => $actionType,
                    'status' => 'failed',
                    'error' => $err,
                ];
                $hasErrors = true;
                if ($stopOnError) {
                    break;
                }
                continue;
            }

            // Execute action
            try {
                $execRes = $action->execute($config, $event, $resolvedVars, $userId);
                $results[] = [
                    'index' => $idx,
                    'type' => $actionType,
                    'status' => 'success',
                    'result' => $execRes,
                ];
            } catch (Exception $e) {
                Logger::error("Action {$actionType} failed: " . $e->getMessage());
                $results[] = [
                    'index' => $idx,
                    'type' => $actionType,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $hasErrors = true;
                if ($stopOnError) {
                    break;
                }
            }
        }

        $allSuccess = !$hasErrors && count($results) > 0;
        $anySuccess = false;
        foreach ($results as $r) {
            if ($r['status'] === 'success') {
                $anySuccess = true;
                break;
            }
        }

        $finalStatus = 'completed';
        if (!$anySuccess && $hasErrors) {
            $finalStatus = 'failed';
        } elseif ($anySuccess && $hasErrors) {
            $finalStatus = 'partial';
        }

        return [
            'status' => $finalStatus,
            'has_errors' => $hasErrors,
            'actions_count' => count($actionsConfig),
            'executed_count' => count($results),
            'results' => $results,
        ];
    }
}
