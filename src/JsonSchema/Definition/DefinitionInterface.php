<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Definition;

/**
 * Contract for JSON Schema definitions.
 *
 * Definitions are reusable schema components stored in the
 * #/definitions/ section of the schema and referenced via $ref.
 */
interface DefinitionInterface
{
    /**
     * Get the definition name (used in #/definitions/{name}).
     */
    public function getName(): string;

    /**
     * Convert to JSON Schema array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Get all $ref references used by this definition.
     *
     * Used for validation that all referenced definitions exist.
     *
     * @return array<string> Definition names referenced (without #/definitions/ prefix)
     */
    public function getReferences(): array;
}
