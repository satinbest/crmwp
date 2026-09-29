<?php

namespace App\Integrations\WooCommerce;

use Exception;

class WooCommerceApiException extends Exception
{
    private string $errorCode;
    private array $details;
    private int $httpStatus;

    public function __construct(
        string $message,
        string $errorCode = 'WOOCOMMERCE_API_ERROR',
        int $httpStatus = 500,
        array $details = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
        $this->httpStatus = $httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
