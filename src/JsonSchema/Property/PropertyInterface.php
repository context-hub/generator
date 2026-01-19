<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

/**
 * Contract for all JSON Schema property types.
 *
 * Properties represent schema type definitions (string, integer, array, object, etc.)
 * and can be nested within other properties or definitions.
 */
interface PropertyInterface
{
    /**
     * Convert to JSON Schema array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Get all $ref references used by this property.
     *
     * Used for validation that all referenced definitions exist.
     *
     * @return array<string> Definition names referenced (without #/definitions/ prefix)
     */
    public function getReferences(): array;
}
