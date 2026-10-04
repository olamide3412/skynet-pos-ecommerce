<?php

namespace App\Services\PosIntegration;

use Exception;

class PosIntegrationException extends Exception
{
    protected string $customerMessage;
    protected array $contextData;

    public function __construct(string $message, string $customerMessage = '', int $code = 0, array $contextData = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->customerMessage = $customerMessage ?: 'A temporary inventory service issue occurred. Please try again or contact support.';
        $this->contextData = $contextData;
    }

    public function getCustomerMessage(): string
    {
        return $this->customerMessage;
    }

    public function getContextData(): array
    {
        return $this->contextData;
    }
}
