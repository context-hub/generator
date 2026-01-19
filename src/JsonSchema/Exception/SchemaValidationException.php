<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Exception;

/**
 * Exception thrown when schema validation fails.
 *
 * Contains structured information about validation errors,
 * including missing references and other validation issues.
 */
class SchemaValidationException extends SchemaException
{
    /**
     * @param string $message Human-readable error message
     * @param array<string> $missingReferences List of undefined definition names that are referenced
     * @param array<string> $errors Additional validation error messages
     */
    public function __construct(
        string $message,
        public readonly array $missingReferences = [],
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
