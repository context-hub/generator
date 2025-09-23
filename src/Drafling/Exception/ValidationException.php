<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Exception;

/**
 * Exception thrown when validation fails
 */
final class ValidationException extends DraflingException
{
    /**
     * @param string[] $errors Array of validation error messages
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Validation failed',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $errorMessage = $message;
        if (!empty($this->errors)) {
            $errorMessage .= ': ' . implode(', ', $this->errors);
        }
        
        parent::__construct($errorMessage, $code, $previous);
    }

    /**
     * Create from array of errors
     * 
     * @param string[] $errors
     */
    public static function fromErrors(array $errors, string $message = 'Validation failed'): self
    {
        return new self($errors, $message);
    }
}
