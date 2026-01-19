<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

/**
 * Base class providing common functionality for all property types.
 *
 * Provides fluent builder pattern for common schema fields:
 * - description: Human-readable description of the property
 * - default: Default value if not provided
 */
abstract class AbstractProperty implements PropertyInterface
{
    protected ?string $description = null;
    protected mixed $default = null;
    protected bool $hasDefault = false;

    /**
     * Set the property description.
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set the default value for this property.
     */
    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    /**
     * Build the base array with common fields (description, default).
     *
     * @return array<string, mixed>
     */
    protected function buildBaseArray(): array
    {
        $schema = [];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->hasDefault) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }

    public function getReferences(): array
    {
        return []; // Override in RefProperty and combinators
    }
}
