<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Automation\ActionExecutor;
use App\Automation\AutomationDryRunService;
use App\Automation\AutomationEngine;
use App\Automation\ConditionEvaluator;
use App\Automation\RuleMatcher;
use App\Automation\VariableResolver;
use App\Database\Connection;
use App\Events\AutomationEvent;
use App\Events\EventDispatcher;
use App\Repositories\AutomationRepository;
use App\Repositories\AutomationRunRepository;
use App\Services\AutomationService;
use App\Support\Env;

Env::load(__DIR__ . '/../.env');

$pdo = Connection::get();
$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$testName}\n";
        $passed++;
    } else {
        echo " [FAIL] {$testName}\n";
        $failed++;
    }
}

echo "==============================================\n";
echo " Starting Phase 15 Test Suite (Workflow Engine)\n";
echo "==============================================\n\n";

// 1. ConditionEvaluator Operators & Logic
$evaluator = new ConditionEvaluator();
$resolver = new VariableResolver();

// Numeric comparisons
assertTest($evaluator->compareValues(6000000, 'greater_than', 5000000) === true, "ConditionEvaluator: 6M > 5M");
assertTest($evaluator->compareValues(4000000, 'greater_than', 5000000) === false, "ConditionEvaluator: 4M > 5M (false)");
assertTest($evaluator->compareValues(5000000, 'greater_or_equal', 5000000) === true, "ConditionEvaluator: 5M >= 5M");
assertTest($evaluator->compareValues(3, 'less_or_equal', 5) === true, "ConditionEvaluator: 3 <= 5");
assertTest($evaluator->compareValues(6, 'less_or_equal', 5) === false, "ConditionEvaluator: 6 <= 5 (false)");

// String & Array operators
assertTest($evaluator->compareValues('processing', 'equals', 'processing') === true, "ConditionEvaluator: equals status");
assertTest($evaluator->compareValues('VIP Customer', 'contains', 'VIP') === true, "ConditionEvaluator: contains");
assertTest($evaluator->compareValues('Special Offer', 'starts_with', 'Special') === true, "ConditionEvaluator: starts_with");
assertTest($evaluator->compareValues('shirt-oxford-01', 'ends_with', '-01') === true, "ConditionEvaluator: ends_with");
assertTest($evaluator->compareValues('processing', 'in', ['pending', 'processing', 'completed']) === true, "ConditionEvaluator: in array");
assertTest($evaluator->compareValues('refunded', 'not_in', ['pending', 'processing', 'completed']) === true, "ConditionEvaluator: not_in array");
assertTest($evaluator->compareValues(25, 'between', [10, 50]) === true, "ConditionEvaluator: between [10, 50]");

// Complex Group Evaluation (AND / OR)
$testEvent = new AutomationEvent(2, 'order.created', 'order', 1024, [
    'total' => 7500000,
    'status' => 'processing',
    'customer_id' => 301,
    'billing' => ['city' => 'تهران', 'first_name' => 'علی', 'last_name' => 'اکبری'],
]);

$andCondition = [
    'operator' => 'AND',
    'conditions' => [
        ['field' => 'order.total', 'operator' => 'greater_than', 'value' => 5000000],
        ['field' => 'order.status', 'operator' => 'equals', 'value' => 'processing'],
    ]
];
$res = $evaluator->evaluate($andCondition, $testEvent);
assertTest($res['matched'] === true, "ConditionEvaluator: AND group matched");

$andConditionFail = [
    'operator' => 'AND',
    'conditions' => [
        ['field' => 'order.total', 'operator' => 'greater_than', 'value' => 10000000],
        ['field' => 'order.status', 'operator' => 'equals', 'value' => 'processing'],
    ]
];
$resFail = $evaluator->evaluate($andConditionFail, $testEvent);
assertTest($resFail['matched'] === false, "ConditionEvaluator: AND group failed when one false");

// Nested OR within AND
$nestedCond = [
    'operator' => 'AND',
    'conditions' => [
        ['field' => 'order.status', 'operator' => 'equals', 'value' => 'processing'],
        [
            'operator' => 'OR',
            'conditions' => [
                ['field' => 'order.total', 'operator' => 'greater_than', 'value' => 10000000],
                ['field' => 'order.total', 'operator' => 'greater_than', 'value' => 5000000],
            ]
        ]
    ]
];
$resNested = $evaluator->evaluate($nestedCond, $testEvent);
assertTest($resNested['matched'] === true, "ConditionEvaluator: Nested (AND with OR child) evaluation");

// 2. VariableResolver Whitelisting & Template Injection Protection
$vars = $resolver->extractContextVariables($testEvent);
assertTest(!empty($vars['order.id']) && $vars['order.id'] === '1024', "VariableResolver: order.id extracted");
assertTest(!empty($vars['order.total']) && $vars['order.total'] === '7500000', "VariableResolver: order.total extracted");
assertTest($vars['order.billing_name'] === 'علی اکبری', "VariableResolver: order.billing_name resolved");

$template = "سفارش #{{order.number}} با مبلغ {{order.total}} برای {{order.billing_name}} ثبت شد.";
$resolvedText = $resolver->resolveText($template, $vars);
assertTest($resolvedText === "سفارش #1024 با مبلغ 7500000 برای علی اکبری ثبت شد.", "VariableResolver: text placeholders replaced");

// Unknown variable replaced by empty (no PHP code execution)
$maliciousTemplate = "تست امنیتی: {{php_system_call}} {{malicious.eval}}";
$sanitizedText = $resolver->resolveText($maliciousTemplate, $vars);
assertTest($sanitizedText === "تست امنیتی:  ", "VariableResolver: arbitrary/unknown tags safely stripped");

// 3. RuleMatcher & Store Isolation
$matcher = new RuleMatcher();
$autoStoreA = ['id' => 1, 'status' => 'active', 'trigger_type' => 'order.created', 'store_id' => 2];
$autoStoreB = ['id' => 2, 'status' => 'active', 'trigger_type' => 'order.created', 'store_id' => 9];

assertTest($matcher->matches($autoStoreA, $testEvent) === true, "RuleMatcher: matched event for Store A (store_id=2)");
assertTest($matcher->matches($autoStoreB, $testEvent) === false, "RuleMatcher: Store isolation blocked Store B automation on Store A event");

// 4. Financial Safety: Prevent Dangerous Actions
$actionExecutor = new ActionExecutor();
$statusAction = $actionExecutor->getAction('change_order_status');
$refundValErrors = $statusAction->validate(['new_status' => 'refunded']);
assertTest(!empty($refundValErrors), "Financial Safety: ActionExecutor blocks automatic refund action");

// 5. AutomationDryRunService: Zero Side Effects
$dryRunService = new AutomationDryRunService();
$dryRunRes = $dryRunService->dryRun($autoStoreA, $testEvent);
assertTest($dryRunRes['success'] === true && $dryRunRes['would_run'] === true, "AutomationDryRunService: simulated execution without mutations");

// 6. AutomationEngine End-to-End Execution
$autoRepo = new AutomationRepository($pdo);
$runRepo = new AutomationRunRepository($pdo);
$engine = new AutomationEngine($autoRepo, $runRepo);

// Fetch seeded automation 1 (VIP customer on order.created > 5M)
$automations = $autoRepo->findActiveByTrigger('order.created', 2);
assertTest(count($automations) >= 1, "Database: Found seeded active automations for order.created");

$firstAuto = $automations[0];
$execResult = $engine->executeAutomation($firstAuto, $testEvent, 1);
assertTest(in_array($execResult['status'], ['completed', 'partial']), "AutomationEngine: Executed automation successfully (Status: {$execResult['status']})");
assertTest(!empty($execResult['run_id']), "AutomationEngine: Run record created (#{$execResult['run_id']})");

// 7. Idempotency Check: Second run with same event must be suppressed
$secondRunResult = $engine->executeAutomation($firstAuto, $testEvent, 1);
assertTest($secondRunResult['status'] === 'duplicate_suppressed', "Idempotency: Duplicate event suppressed without duplicate execution");

// 8. Loop & Recursion Protection Check
$deepEvent = new AutomationEvent(2, 'order.created', 'order', 1024, ['total' => 6000000], 'test', null, 5);
$recursionResult = $engine->handleEvent($deepEvent);
assertTest($recursionResult['status'] === 'skipped' && $recursionResult['reason'] === 'recursion_depth_exceeded', "Loop Protection: Exceeded recursion depth (5) was blocked");

// 9. Low Stock Trigger Execution
$lowStockEvent = new AutomationEvent(2, 'inventory.low_stock', 'product', 501, [
    'id' => 501,
    'name' => 'پیراهن آکسفورد کلاسیک',
    'stock_quantity' => 2,
    'low_stock_amount' => 5,
]);
$lowStockAutomations = $autoRepo->findActiveByTrigger('inventory.low_stock', 2);
if (!empty($lowStockAutomations)) {
    $lsRes = $engine->executeAutomation($lowStockAutomations[0], $lowStockEvent, 1);
    assertTest(in_array($lsRes['status'], ['completed', 'partial']), "AutomationEngine: Low stock automation executed successfully");
} else {
    assertTest(false, "AutomationEngine: Low stock automation not found");
}

// 10. Scheduled Batch Execution
$scheduledRes = $engine->runScheduled(10);
assertTest($scheduledRes['scheduled_automations_count'] >= 1, "AutomationEngine: Scheduled batch processed automations ({$scheduledRes['processed']} executed)");

// 11. AutomationService & Audit Integration
$autoService = new AutomationService($autoRepo, $runRepo, $engine);
$schema = $autoService->getSchema();
assertTest(!empty($schema['triggers']['woocommerce']), "AutomationService: Schema contains WooCommerce triggers");
assertTest(!empty($schema['actions']), "AutomationService: Schema contains available actions");
assertTest(!empty($schema['operators']), "AutomationService: Schema contains condition operators");

// 12. Run Details & History Fetching
$runsList = $runRepo->listByAutomation((int)$firstAuto['id'], 1, 10);
assertTest($runsList['meta']['total'] >= 1, "AutomationRunRepository: Run history recorded and retrievable");

echo "\n==============================================\n";
echo " Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "==============================================\n";

if ($failed > 0) {
    exit(1);
}
