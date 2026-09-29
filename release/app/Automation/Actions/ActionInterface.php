<?php

namespace App\Automation\Actions;

use App\Events\AutomationEvent;

interface ActionInterface
{
    /**
     * Unique machine identifier for this action (e.g. 'add_customer_tag').
     */
    public function getName(): string;

    /**
     * Persian human-readable label for UI.
     */
    public function getLabel(): string;

    /**
     * Validate action configuration parameters.
     * Returns empty array if valid, or array of validation error strings.
     */
    public function validate(array $config): array;

    /**
     * Execute action with resolved variables.
     * Returns associative array of execution results/details.
     */
    public function execute(array $config, AutomationEvent $event, array $resolvedVars, int $userId): array;
}
