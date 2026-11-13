<?php

declare(strict_types=1);

namespace App\Exception;

final class ValidationException extends ApiException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 422, 'validation_failed', $context);
    }
}
