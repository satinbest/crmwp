<?php

namespace App\Services\Bulk;

use App\Integrations\WooCommerce\WooCommerceApiException;
use App\Models\Store;
use App\Support\Logger;
use Exception;

abstract class BaseBulkHandler implements BulkOperationHandlerInterface
{
    /**
     * Conservative transient retry executor with exponential backoff.
     * Retries: 429, 502, 503, 504, connection timeouts.
     * Never retries 400, 401, 403, 404, 422.
     */
    protected function executeWithRetry(callable $callback, int $maxRetries = 2, int $initialDelayMs = 500): mixed
    {
        $attempt = 0;
        $delay = $initialDelayMs;

        while (true) {
            $attempt++;
            try {
                return $callback();
            } catch (WooCommerceApiException $e) {
                $status = $e->getHttpStatus();
                $isTransient = in_array($status, [429, 502, 503, 504], true);

                if ($isTransient && $attempt <= $maxRetries) {
                    Logger::warning("Transient error during bulk operation (HTTP {$status}). Retrying attempt {$attempt}/{$maxRetries}...", [
                        'status' => $status,
                        'message' => $e->getMessage(),
                    ]);

                    // Respect 429 rate limit
                    $sleepMs = ($status === 429) ? max(1000, $delay * 2) : $delay;
                    usleep($sleepMs * 1000);
                    $delay *= 2;
                    continue;
                }

                throw $e;
            } catch (Exception $e) {
                if ($attempt <= $maxRetries && (str_contains(strtolower($e->getMessage()), 'timeout') || str_contains(strtolower($e->getMessage()), 'curl'))) {
                    Logger::warning("Network/cURL error during bulk operation. Retrying attempt {$attempt}/{$maxRetries}...", [
                        'message' => $e->getMessage(),
                    ]);
                    usleep($delay * 1000);
                    $delay *= 2;
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Check if selection mode is explicit IDs.
     */
    protected function isIdsMode(array $selection): bool
    {
        return ($selection['mode'] ?? '') === 'ids' || !empty($selection['ids']);
    }

    /**
     * Extract sanitized integer IDs from selection.
     */
    protected function extractIds(array $selection): array
    {
        $rawIds = $selection['ids'] ?? [];
        if (!is_array($rawIds)) {
            return [];
        }

        $clean = [];
        foreach ($rawIds as $id) {
            $val = (int)$id;
            if ($val > 0) {
                $clean[] = $val;
            }
        }

        return array_values(array_unique($clean));
    }
}
