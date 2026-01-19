<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Exception;

/**
 * Exception thrown when attempting to register a definition
 * with a name that is already in use.
 */
class DuplicateDefinitionException extends SchemaException
{
    public function __construct(
        public readonly string $definitionName,
    ) {
        parent::__construct("Definition '{$definitionName}' is already registered");
    }
}
