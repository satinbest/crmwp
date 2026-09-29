<?php

namespace App\Automation;

use App\Events\AutomationEvent;

class RuleMatcher
{
    /**
     * Check if an automation definition matches the given event.
     */
    public function matches(array $automation, AutomationEvent $event): bool
    {
        // 1. Status check: must be active
        if (($automation['status'] ?? '') !== 'active') {
            return false;
        }

        // 2. Trigger Type check
        $triggerType = $automation['trigger_type'] ?? '';
        if ($triggerType !== $event->getType()) {
            return false;
        }

        // 3. Store Scope check: strictly enforce store isolation
        $autoStoreId = isset($automation['store_id']) ? (int)$automation['store_id'] : null;
        if ($autoStoreId !== null && $autoStoreId > 0) {
            if ($autoStoreId !== $event->getStoreId()) {
                return false;
            }
        }

        // 4. Trigger Configuration evaluation
        $triggerConfig = $automation['trigger_config'] ?? null;
        if (is_string($triggerConfig)) {
            $triggerConfig = json_decode($triggerConfig, true);
        }

        if (is_array($triggerConfig) && !empty($triggerConfig)) {
            $eventData = $event->getData();

            // Check to_status filter for status_changed triggers
            if (!empty($triggerConfig['to_status'])) {
                $currentStatus = strtolower(trim((string)($eventData['status'] ?? '')));
                $expectedStatus = strtolower(trim((string)$triggerConfig['to_status']));
                if ($currentStatus !== $expectedStatus) {
                    return false;
                }
            }

            // Check from_status filter if provided
            if (!empty($triggerConfig['from_status'])) {
                $oldStatus = strtolower(trim((string)($eventData['old_status'] ?? '')));
                $expectedOld = strtolower(trim((string)$triggerConfig['from_status']));
                if ($oldStatus !== $expectedOld) {
                    return false;
                }
            }

            // Check specific product/category filter if configured
            if (!empty($triggerConfig['resource_id'])) {
                if ((string)$event->getResourceId() !== (string)$triggerConfig['resource_id']) {
                    return false;
                }
            }
        }

        return true;
    }
}
