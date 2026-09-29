<?php

namespace App\Automation;

use App\Events\AutomationEvent;

class AutomationDryRunService
{
    private RuleMatcher $ruleMatcher;
    private ConditionEvaluator $conditionEvaluator;
    private VariableResolver $variableResolver;
    private ActionExecutor $actionExecutor;

    public function __construct(
        ?RuleMatcher $ruleMatcher = null,
        ?ConditionEvaluator $conditionEvaluator = null,
        ?VariableResolver $variableResolver = null,
        ?ActionExecutor $actionExecutor = null
    ) {
        $this->ruleMatcher = $ruleMatcher ?? new RuleMatcher();
        $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
        $this->actionExecutor = $actionExecutor ?? new ActionExecutor();
    }

    /**
     * Perform a dry-run test of an automation definition against an event.
     * Guaranteed zero side-effects.
     */
    public function dryRun(array $automation, AutomationEvent $event): array
    {
        $contextVars = $this->variableResolver->extractContextVariables($event);

        // 1. Trigger Match Check
        $triggerMatches = $this->ruleMatcher->matches($automation, $event);

        // 2. Condition Evaluation
        $conditionsConfig = $automation['conditions'] ?? [];
        $condResult = $this->conditionEvaluator->evaluate($conditionsConfig, $event, $contextVars);
        $conditionsMatched = $condResult['matched'];

        // 3. Planned Actions Simulation
        $actionsConfig = $automation['actions'] ?? [];
        if (is_string($actionsConfig)) {
            $actionsConfig = json_decode($actionsConfig, true) ?: [];
        }

        $plannedActions = [];
        $validationErrors = [];

        foreach ($actionsConfig as $idx => $actDef) {
            $actionType = $actDef['type'] ?? ($actDef['action'] ?? '');
            $config = $actDef['config'] ?? $actDef;

            $action = $this->actionExecutor->getAction($actionType);
            if (!$action) {
                $err = "اکشن ناشناخته: «{$actionType}»";
                $validationErrors[] = $err;
                $plannedActions[] = [
                    'index' => $idx,
                    'type' => $actionType,
                    'status' => 'invalid',
                    'error' => $err,
                ];
                continue;
            }

            $valErrors = $action->validate($config);
            if (!empty($valErrors)) {
                $validationErrors = array_merge($validationErrors, $valErrors);
                $plannedActions[] = [
                    'index' => $idx,
                    'type' => $actionType,
                    'status' => 'invalid',
                    'errors' => $valErrors,
                ];
                continue;
            }

            // Simulate resolved config without calling execute
            $simulatedConfig = $this->variableResolver->resolveDeep($config, $contextVars);

            $plannedActions[] = [
                'index' => $idx,
                'type' => $actionType,
                'label' => $action->getLabel(),
                'status' => $conditionsMatched && $triggerMatches ? 'would_execute' : 'would_skip',
                'simulated_config' => $simulatedConfig,
            ];
        }

        $wouldRun = $triggerMatches && $conditionsMatched && empty($validationErrors);

        return [
            'success' => true,
            'would_run' => $wouldRun,
            'trigger_matched' => $triggerMatches,
            'conditions_matched' => $conditionsMatched,
            'condition_details' => $condResult['details'] ?? [],
            'resolved_variables' => $contextVars,
            'planned_actions' => $plannedActions,
            'validation_errors' => $validationErrors,
            'summary' => $wouldRun
                ? 'تمام شروط محقق شده و تمام اقدامات آماده اجرای واقعی می‌باشند.'
                : (!$triggerMatches ? 'تریگر رویداد با شرط تریگر تطابق ندارد.' : 'شروط تعریف‌شده محقق نگردید و اقدامات نادیده گرفته می‌شوند.')
        ];
    }
}
