<?php

namespace App\Automation;

use App\Events\AutomationEvent;
use App\Repositories\AutomationRepository;
use App\Repositories\AutomationRunRepository;
use App\Services\NotificationService;
use App\Support\Logger;
use Exception;

class AutomationEngine
{
    private AutomationRepository $automationRepository;
    private AutomationRunRepository $runRepository;
    private RuleMatcher $ruleMatcher;
    private ConditionEvaluator $conditionEvaluator;
    private VariableResolver $variableResolver;
    private ActionExecutor $actionExecutor;
    private NotificationService $notificationService;

    public const MAX_RECURSION_DEPTH = 5;

    public function __construct(
        ?AutomationRepository $automationRepository = null,
        ?AutomationRunRepository $runRepository = null,
        ?RuleMatcher $ruleMatcher = null,
        ?ConditionEvaluator $conditionEvaluator = null,
        ?VariableResolver $variableResolver = null,
        ?ActionExecutor $actionExecutor = null,
        ?NotificationService $notificationService = null
    ) {
        $this->automationRepository = $automationRepository ?? new AutomationRepository();
        $this->runRepository = $runRepository ?? new AutomationRunRepository();
        $this->ruleMatcher = $ruleMatcher ?? new RuleMatcher();
        $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator();
        $this->variableResolver = $variableResolver ?? new VariableResolver();
        $this->actionExecutor = $actionExecutor ?? new ActionExecutor();
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Process an incoming AutomationEvent through all eligible automations.
     */
    public function handleEvent(AutomationEvent $event): array
    {
        $startTime = microtime(true);
        $storeId = $event->getStoreId();
        $eventType = $event->getType();

        // 1. Loop and Recursion Protection
        if ($event->getDepth() >= self::MAX_RECURSION_DEPTH) {
            Logger::warning("Automation recursion depth limit reached ({$event->getDepth()}) for event: {$eventType}");
            return [
                'success' => false,
                'status' => 'skipped',
                'reason' => 'recursion_depth_exceeded',
                'depth' => $event->getDepth(),
            ];
        }

        // 2. Fetch candidate active automations for this trigger & store
        $automations = $this->automationRepository->findActiveByTrigger($eventType, $storeId);
        if (empty($automations)) {
            return [
                'success' => true,
                'matched_count' => 0,
                'executed_count' => 0,
                'automations' => [],
            ];
        }

        $results = [];

        foreach ($automations as $automation) {
            $autoId = (int)$automation['id'];

            // Loop check: check if this automation was already in the trigger chain of this event
            if (in_array($autoId, $event->getTriggerChain(), true)) {
                Logger::warning("Automation circular loop detected for automation #{$autoId}. Skipping execution.");
                $this->runRepository->create([
                    'automation_id' => $autoId,
                    'store_id' => $storeId,
                    'event_id' => $event->getEventId(),
                    'trigger_type' => $eventType,
                    'status' => 'skipped',
                    'error' => 'recursion_detected: Automation already in active event execution chain',
                    'context' => $event->toArray(),
                ]);
                continue;
            }

            // Check if automation matches event rule
            if (!$this->ruleMatcher->matches($automation, $event)) {
                continue;
            }

            // Check max_runs limit if configured
            if (!empty($automation['max_runs']) && (int)$automation['run_count'] >= (int)$automation['max_runs']) {
                continue;
            }

            // Execute this individual automation with idempotency
            $runResult = $this->executeAutomation($automation, $event);
            $results[] = $runResult;
        }

        return [
            'success' => true,
            'event_id' => $event->getEventId(),
            'event_type' => $eventType,
            'matched_count' => count($automations),
            'executed_count' => count($results),
            'runs' => $results,
        ];
    }

    /**
     * Execute a single automation against an event with idempotency, condition evaluation and action execution.
     */
    public function executeAutomation(array $automation, AutomationEvent $event, int $userId = 0): array
    {
        $autoId = (int)$automation['id'];
        $storeId = $event->getStoreId();
        $eventType = $event->getType();
        $startTime = microtime(true);

        // 1. Idempotency Check
        // Key: automation_id + store_id + event_type + resource_id + payload_hash
        $payloadHash = md5(json_encode($event->getData()));
        $idempotencyKey = "auto_{$autoId}_evt_{$event->getEventId()}_{$payloadHash}";

        $existingRun = $this->runRepository->findByIdempotencyKey($idempotencyKey);
        if ($existingRun && in_array($existingRun['status'], ['completed', 'running'], true)) {
            Logger::info("Idempotent duplicate automation execution suppressed: #{$autoId} for key {$idempotencyKey}");
            return [
                'automation_id' => $autoId,
                'status' => 'duplicate_suppressed',
                'run_id' => $existingRun['id'],
            ];
        }

        // 2. Create Initial Run Record
        $run = $this->runRepository->create([
            'automation_id' => $autoId,
            'store_id' => $storeId,
            'event_id' => $event->getEventId(),
            'trigger_type' => $eventType,
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
            'idempotency_key' => $idempotencyKey,
            'context' => [
                'event' => $event->toArray(),
                'automation_name' => $automation['name'],
            ],
        ]);
        $runId = (int)$run['id'];

        try {
            // 3. Extract Context Variables
            $contextVars = $this->variableResolver->extractContextVariables($event);

            // 4. Evaluate Conditions
            $conditionsConfig = $automation['conditions'] ?? [];
            $condResult = $this->conditionEvaluator->evaluate($conditionsConfig, $event, $contextVars);

            if (!$condResult['matched']) {
                $durationMs = (int)(round((microtime(true) - $startTime) * 1000));
                $this->runRepository->update($runId, [
                    'status' => 'skipped',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'duration_ms' => $durationMs,
                    'result' => [
                        'reason' => 'conditions_not_met',
                        'details' => $condResult['details'],
                    ],
                ]);

                return [
                    'automation_id' => $autoId,
                    'run_id' => $runId,
                    'status' => 'skipped',
                    'reason' => 'conditions_not_met',
                ];
            }

            // 5. Execute Actions
            $actionsConfig = $automation['actions'] ?? [];
            $actionsResult = $this->actionExecutor->executeAll(
                $actionsConfig,
                $event,
                $contextVars,
                $userId
            );

            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));
            $finalStatus = $actionsResult['status']; // 'completed' | 'partial' | 'failed'

            // Update run record
            $this->runRepository->update($runId, [
                'status' => $finalStatus,
                'completed_at' => date('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
                'error' => $actionsResult['has_errors'] ? 'One or more actions encountered an error' : null,
                'result' => [
                    'condition_eval' => $condResult,
                    'actions' => $actionsResult,
                ],
            ]);

            // Update automation stats
            $this->automationRepository->incrementRunCount($autoId);

            // If automation failed, notify store administrators
            if ($finalStatus === 'failed') {
                $this->notifyAutomationFailure($automation, $runId, $storeId);
            }

            return [
                'automation_id' => $autoId,
                'run_id' => $runId,
                'status' => $finalStatus,
                'duration_ms' => $durationMs,
                'actions_result' => $actionsResult,
            ];
        } catch (Exception $e) {
            $durationMs = (int)(round((microtime(true) - $startTime) * 1000));
            Logger::error("Automation #{$autoId} execution crashed: " . $e->getMessage());

            $this->runRepository->update($runId, [
                'status' => 'failed',
                'completed_at' => date('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
                'result' => ['trace' => substr($e->getTraceAsString(), 0, 500)],
            ]);

            $this->notifyAutomationFailure($automation, $runId, $storeId, $e->getMessage());

            return [
                'automation_id' => $autoId,
                'run_id' => $runId,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send in-app notification when an automation fails.
     */
    private function notifyAutomationFailure(array $automation, int $runId, int $storeId, ?string $errorMsg = null): void
    {
        try {
            $title = "خطا در اجرای اتوماسیون «{$automation['name']}»";
            $message = $errorMsg ?: "اجرای شماره #{$runId} با خطا متوقف شد. برای بررسی لاگ‌ها به تاریخچه اجرا مراجعه کنید.";

            // User ID 1 is default admin
            $this->notificationService->createForUser(
                1,
                'automation_failed',
                $title,
                $message,
                [
                    'automation_id' => $automation['id'],
                    'run_id' => $runId,
                ],
                $storeId,
                "/automations/{$automation['id']}/runs",
                'high'
            );
        } catch (Exception $e) {
            // Silently ignore notification failure
        }
    }

    /**
     * Process batch of scheduled automations.
     */
    public function runScheduled(int $limit = 50): array
    {
        $automations = $this->automationRepository->findActiveByTrigger('scheduled');
        $executed = 0;
        $results = [];

        foreach ($automations as $auto) {
            if ($executed >= $limit) {
                break;
            }

            $storeId = (int)($auto['store_id'] ?? 2);
            $event = new AutomationEvent(
                $storeId,
                'scheduled',
                'system',
                'cron',
                ['scheduled_at' => date('c')],
                'scheduled_cron'
            );

            $res = $this->executeAutomation($auto, $event);
            $results[] = $res;
            $executed++;
        }

        return [
            'scheduled_automations_count' => count($automations),
            'processed' => $executed,
            'results' => $results,
        ];
    }
}
