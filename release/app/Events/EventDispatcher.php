<?php

namespace App\Events;

use App\Support\Logger;
use Exception;

class EventDispatcher
{
    /** @var array<string, callable[]> */
    private static array $listeners = [];

    /** @var callable|null */
    private static $automationHandler = null;

    /**
     * Register a listener for an event type or '*' for all.
     */
    public static function listen(string $eventType, callable $listener): void
    {
        self::$listeners[$eventType][] = $listener;
    }

    /**
     * Register the central automation handler.
     */
    public static function setAutomationHandler(callable $handler): void
    {
        self::$automationHandler = $handler;
    }

    /**
     * Dispatch an event to all matching listeners and the automation engine.
     */
    public static function dispatch(AutomationEvent $event): array
    {
        $results = [];
        $type = $event->getType();

        // 1. Invoke specific listeners
        if (!empty(self::$listeners[$type])) {
            foreach (self::$listeners[$type] as $listener) {
                try {
                    $results[] = call_user_func($listener, $event);
                } catch (Exception $e) {
                    Logger::error("Event listener error for {$type}: " . $e->getMessage());
                }
            }
        }

        // 2. Invoke wildcard listeners
        if (!empty(self::$listeners['*'])) {
            foreach (self::$listeners['*'] as $listener) {
                try {
                    $results[] = call_user_func($listener, $event);
                } catch (Exception $e) {
                    Logger::error("Wildcard event listener error: " . $e->getMessage());
                }
            }
        }

        // 3. Central Automation Engine execution
        if (self::$automationHandler !== null) {
            try {
                $automationResult = call_user_func(self::$automationHandler, $event);
                $results['automation'] = $automationResult;
            } catch (Exception $e) {
                Logger::error("Automation handler error for event {$type}: " . $e->getMessage(), [
                    'event_id' => $event->getEventId(),
                    'store_id' => $event->getStoreId(),
                ]);
            }
        } else {
            // Lazy load default AutomationEngine if available
            if (class_exists(\App\Automation\AutomationEngine::class)) {
                try {
                    $engine = new \App\Automation\AutomationEngine();
                    $automationResult = $engine->handleEvent($event);
                    $results['automation'] = $automationResult;
                } catch (Exception $e) {
                    Logger::error("Default AutomationEngine error: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Clear listeners (useful for testing).
     */
    public static function reset(): void
    {
        self::$listeners = [];
        self::$automationHandler = null;
    }
}
