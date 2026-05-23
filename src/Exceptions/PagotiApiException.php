<?php

namespace Metrique\Pagoti\Exceptions;

class PagotiApiException extends PagotiException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
