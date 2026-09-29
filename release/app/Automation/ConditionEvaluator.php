<?php

namespace App\Automation;

use App\Events\AutomationEvent;

class ConditionEvaluator
{
    private VariableResolver $variableResolver;

    public function __construct(?VariableResolver $variableResolver = null)
    {
        $this->variableResolver = $variableResolver ?? new VariableResolver();
    }

    /**
     * Evaluates a condition structure against an AutomationEvent.
     * Supported structure:
     * [
     *   "operator" => "AND" | "OR",
     *   "conditions" => [
     *     ["field" => "order.total", "operator" => "greater_than", "value" => 5000000],
     *     ["field" => "order.status", "operator" => "equals", "value" => "completed"],
     *     ["operator" => "OR", "conditions" => [...]] // nested group
     *   ]
     * ]
     *
     * Returns ['matched' => bool, 'details' => array]
     */
    public function evaluate(array|string|null $conditionsConfig, AutomationEvent $event, ?array $contextVars = null): array
    {
        if (empty($conditionsConfig)) {
            return ['matched' => true, 'details' => ['rule' => 'no_conditions_defined']];
        }

        if (is_string($conditionsConfig)) {
            $decoded = json_decode($conditionsConfig, true);
            $conditionsConfig = is_array($decoded) ? $decoded : [];
        }

        if (empty($conditionsConfig['conditions']) && !isset($conditionsConfig['field'])) {
            return ['matched' => true, 'details' => ['rule' => 'empty_conditions']];
        }

        if ($contextVars === null) {
            $contextVars = $this->variableResolver->extractContextVariables($event);
        }

        // Handle single condition directly
        if (isset($conditionsConfig['field'])) {
            $singleRes = $this->evaluateSingleCondition($conditionsConfig, $contextVars, $event);
            return [
                'matched' => $singleRes['matched'],
                'details' => ['single' => $singleRes],
            ];
        }

        return $this->evaluateGroup($conditionsConfig, $contextVars, $event);
    }

    /**
     * Evaluate a logical group (AND / OR).
     */
    private function evaluateGroup(array $group, array $contextVars, AutomationEvent $event): array
    {
        $operator = strtoupper(trim((string)($group['operator'] ?? 'AND')));
        $conditions = $group['conditions'] ?? [];

        if (empty($conditions)) {
            return ['matched' => true, 'details' => []];
        }

        $results = [];
        $isAnd = ($operator === 'AND');
        $overallMatch = $isAnd ? true : false;

        foreach ($conditions as $item) {
            if (!is_array($item)) {
                continue;
            }

            // If nested group
            if (isset($item['conditions']) && is_array($item['conditions'])) {
                $subResult = $this->evaluateGroup($item, $contextVars, $event);
                $matched = $subResult['matched'];
                $results[] = [
                    'group_operator' => $item['operator'] ?? 'AND',
                    'matched' => $matched,
                    'details' => $subResult['details'],
                ];
            } else {
                // Single condition
                $singleRes = $this->evaluateSingleCondition($item, $contextVars, $event);
                $matched = $singleRes['matched'];
                $results[] = $singleRes;
            }

            if ($isAnd && !$matched) {
                $overallMatch = false;
                // In strict evaluation we can continue recording details for transparency
            } elseif (!$isAnd && $matched) {
                $overallMatch = true;
            }
        }

        if ($isAnd) {
            // Check that all matched
            $allPassed = true;
            foreach ($results as $r) {
                if (!$r['matched']) {
                    $allPassed = false;
                    break;
                }
            }
            $overallMatch = $allPassed;
        }

        return [
            'matched' => $overallMatch,
            'details' => [
                'operator' => $operator,
                'overall' => $overallMatch,
                'items' => $results,
            ],
        ];
    }

    /**
     * Evaluate a single field comparison condition.
     */
    private function evaluateSingleCondition(array $cond, array $contextVars, AutomationEvent $event): array
    {
        $field = trim((string)($cond['field'] ?? ''));
        $operator = strtolower(trim((string)($cond['operator'] ?? 'equals')));
        $expectedValue = $cond['value'] ?? null;

        // Resolve field actual value from context variables or event data
        $actualValue = $this->resolveFieldValue($field, $contextVars, $event);

        $matched = $this->compareValues($actualValue, $operator, $expectedValue);

        return [
            'field' => $field,
            'operator' => $operator,
            'actual' => is_scalar($actualValue) ? $actualValue : (is_array($actualValue) ? json_encode($actualValue) : null),
            'expected' => is_scalar($expectedValue) ? $expectedValue : (is_array($expectedValue) ? json_encode($expectedValue) : null),
            'matched' => $matched,
        ];
    }

    /**
     * Extract actual value of field.
     */
    private function resolveFieldValue(string $field, array $contextVars, AutomationEvent $event): mixed
    {
        // 1. Direct variable match
        if (array_key_exists($field, $contextVars)) {
            return $contextVars[$field];
        }

        // 2. Direct event data match (e.g. data.total, data.status)
        $data = $event->getData();
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }

        // 3. Dot-nested path match (e.g. order.billing.city, customer.tags)
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $curr = $data;
            foreach ($parts as $part) {
                if (is_array($curr) && array_key_exists($part, $curr)) {
                    $curr = $curr[$part];
                } else {
                    $curr = null;
                    break;
                }
            }
            if ($curr !== null) {
                return $curr;
            }
        }

        return null;
    }

    /**
     * Compare values using supported operators.
     */
    public function compareValues(mixed $actual, string $operator, mixed $expected): bool
    {
        // Normalize numeric values if numeric
        $isNumeric = is_numeric($actual) && is_numeric($expected);
        $actNum = $isNumeric ? (float)$actual : null;
        $expNum = $isNumeric ? (float)$expected : null;

        $actStr = is_scalar($actual) ? (string)$actual : '';
        $expStr = is_scalar($expected) ? (string)$expected : '';

        switch ($operator) {
            case 'equals':
            case 'eq':
            case '==':
                if ($isNumeric) {
                    return abs($actNum - $expNum) < 0.0001;
                }
                return strcasecmp(trim($actStr), trim($expStr)) === 0;

            case 'not_equals':
            case 'neq':
            case '!=':
                if ($isNumeric) {
                    return abs($actNum - $expNum) >= 0.0001;
                }
                return strcasecmp(trim($actStr), trim($expStr)) !== 0;

            case 'contains':
                if (is_array($actual)) {
                    return in_array($expected, $actual, false);
                }
                return stripos($actStr, $expStr) !== false;

            case 'not_contains':
                if (is_array($actual)) {
                    return !in_array($expected, $actual, false);
                }
                return stripos($actStr, $expStr) === false;

            case 'starts_with':
                return str_starts_with(mb_strtolower($actStr), mb_strtolower($expStr));

            case 'ends_with':
                return str_ends_with(mb_strtolower($actStr), mb_strtolower($expStr));

            case 'greater_than':
            case 'gt':
            case '>':
                return (float)$actual > (float)$expected;

            case 'greater_or_equal':
            case 'gte':
            case '>=':
                return (float)$actual >= (float)$expected;

            case 'less_than':
            case 'lt':
            case '<':
                return (float)$actual < (float)$expected;

            case 'less_or_equal':
            case 'lte':
            case '<=':
                return (float)$actual <= (float)$expected;

            case 'is_empty':
                return $actual === null || $actual === '' || (is_array($actual) && empty($actual));

            case 'is_not_empty':
                return $actual !== null && $actual !== '' && (!is_array($actual) || !empty($actual));

            case 'in':
                $haystack = is_array($expected) ? $expected : array_map('trim', explode(',', (string)$expected));
                foreach ($haystack as $item) {
                    if (strcasecmp(trim($actStr), trim((string)$item)) === 0) {
                        return true;
                    }
                }
                return false;

            case 'not_in':
                $haystack = is_array($expected) ? $expected : array_map('trim', explode(',', (string)$expected));
                foreach ($haystack as $item) {
                    if (strcasecmp(trim($actStr), trim((string)$item)) === 0) {
                        return false;
                    }
                }
                return true;

            case 'contains_any':
                $needles = is_array($expected) ? $expected : array_map('trim', explode(',', (string)$expected));
                $haystack = is_array($actual) ? $actual : array_map('trim', explode(',', (string)$actual));
                foreach ($needles as $n) {
                    if (in_array($n, $haystack, false)) {
                        return true;
                    }
                }
                return false;

            case 'contains_all':
                $needles = is_array($expected) ? $expected : array_map('trim', explode(',', (string)$expected));
                $haystack = is_array($actual) ? $actual : array_map('trim', explode(',', (string)$actual));
                foreach ($needles as $n) {
                    if (!in_array($n, $haystack, false)) {
                        return false;
                    }
                }
                return true;

            case 'between':
                $range = is_array($expected) ? $expected : array_map('trim', explode(',', (string)$expected));
                if (count($range) >= 2) {
                    $min = (float)$range[0];
                    $max = (float)$range[1];
                    $val = (float)$actual;
                    return $val >= $min && $val <= $max;
                }
                return false;

            case 'not_between':
                $range = is_array($expected) ? $expected : array_map('trim', explode(',', (string)$expected));
                if (count($range) >= 2) {
                    $min = (float)$range[0];
                    $max = (float)$range[1];
                    $val = (float)$actual;
                    return $val < $min || $val > $max;
                }
                return true;

            default:
                return false;
        }
    }
}
