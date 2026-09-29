<?php

namespace App\Events;

class AutomationEvent
{
    private string $eventId;
    private int $storeId;
    private string $type;
    private string $resourceType;
    private int|string $resourceId;
    private string $occurredAt;
    private string $source;
    private array $data;
    private int $depth;
    private array $triggerChain;

    public function __construct(
        int $storeId,
        string $type,
        string $resourceType,
        int|string $resourceId,
        array $data = [],
        string $source = 'internal',
        ?string $eventId = null,
        int $depth = 0,
        array $triggerChain = []
    ) {
        $this->storeId = $storeId;
        $this->type = $type;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->data = $this->sanitizeData($data);
        $this->source = $source;
        $this->eventId = $eventId ?? ('evt_' . bin2hex(random_bytes(8)) . '_' . time());
        $this->occurredAt = date('c');
        $this->depth = $depth;
        $this->triggerChain = $triggerChain;
    }

    /**
     * Redact sensitive credentials or keys from event data.
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'consumer_key', 'consumer_secret', 'password', 'user_pass',
            'token', 'secret', 'auth_token', 'api_key', 'card_number'
        ];

        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $data[$key] = $this->sanitizeData($val);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): int|string
    {
        return $this->resourceId;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getTriggerChain(): array
    {
        return $this->triggerChain;
    }

    /**
     * Create child event with incremented depth and tracked chain for loop detection.
     */
    public function spawnChild(int $automationId, string $type, string $resourceType, int|string $resourceId, array $data = []): self
    {
        $newChain = $this->triggerChain;
        $newChain[] = $automationId;

        return new self(
            $this->storeId,
            $type,
            $resourceType,
            $resourceId,
            $data,
            'automation_' . $automationId,
            null,
            $this->depth + 1,
            $newChain
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'store_id' => $this->storeId,
            'type' => $this->type,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'occurred_at' => $this->occurredAt,
            'source' => $this->source,
            'depth' => $this->depth,
            'trigger_chain' => $this->triggerChain,
            'data' => $this->data,
        ];
    }
}
