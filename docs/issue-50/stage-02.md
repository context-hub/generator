# Stage 2: Property Types

## Overview

Implement all concrete property types that map to JSON Schema primitives and structures. Each property type provides a
fluent builder API and generates the correct JSON Schema output. These are the building blocks for all schema
definitions.

## Files

**CREATE:**

- `src/JsonSchema/Property/StringProperty.php` - String with enum, pattern, format
- `src/JsonSchema/Property/IntegerProperty.php` - Integer with min/max
- `src/JsonSchema/Property/NumberProperty.php` - Number (float) with min/max
- `src/JsonSchema/Property/BooleanProperty.php` - Boolean type
- `src/JsonSchema/Property/ArrayProperty.php` - Array with items
- `src/JsonSchema/Property/ObjectProperty.php` - Inline object with properties
- `src/JsonSchema/Property/RefProperty.php` - $ref pointer
- `src/JsonSchema/Property/NullProperty.php` - Null type (for nullable unions)
- `tests/Unit/JsonSchema/Property/` - Unit tests for each property

## Code References

- `json-schema.json:150-180` - Example of string with enum (source type)
- `json-schema.json:200-250` - Example of array property (sourcePaths)
- `json-schema.json:400-450` - Example of $ref usage
- `vendor/spiral/json-schema-generator/src/Schema/Property.php` - Similar property concept

## Implementation Details

### StringProperty

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class StringProperty extends AbstractProperty
{
    /** @var array<string>|null */
    private ?array $enum = null;
    private ?string $pattern = null;
    private ?string $format = null;
    private ?int $minLength = null;
    private ?int $maxLength = null;

    public static function new(): self
    {
        return new self();
    }

    public function enum(array $values): self
    {
        $this->enum = $values;
        return $this;
    }

    public function pattern(string $regex): self
    {
        $this->pattern = $regex;
        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function minLength(int $length): self
    {
        $this->minLength = $length;
        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'string'];
        
        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }
        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }
        if ($this->format !== null) {
            $schema['format'] = $this->format;
        }
        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }
}
```

### IntegerProperty

```php
final class IntegerProperty extends AbstractProperty
{
    private ?int $minimum = null;
    private ?int $maximum = null;

    public static function new(): self
    {
        return new self();
    }

    public function minimum(int $value): self
    {
        $this->minimum = $value;
        return $this;
    }

    public function maximum(int $value): self
    {
        $this->maximum = $value;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'integer'];
        
        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }
}
```

### BooleanProperty

```php
final class BooleanProperty extends AbstractProperty
{
    public static function new(): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return ['type' => 'boolean', ...$this->buildBaseArray()];
    }
}
```

### ArrayProperty

```php
final class ArrayProperty extends AbstractProperty
{
    private ?PropertyInterface $items = null;
    private ?int $minItems = null;
    private ?int $maxItems = null;

    public static function new(): self
    {
        return new self();
    }

    /**
     * Shortcut for array of strings.
     */
    public static function strings(): self
    {
        return (new self())->items(StringProperty::new());
    }

    /**
     * Shortcut for array of specific type.
     */
    public static function of(PropertyInterface $itemType): self
    {
        return (new self())->items($itemType);
    }

    public function items(PropertyInterface $items): self
    {
        $this->items = $items;
        return $this;
    }

    public function minItems(int $count): self
    {
        $this->minItems = $count;
        return $this;
    }

    public function maxItems(int $count): self
    {
        $this->maxItems = $count;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'array'];
        
        if ($this->items !== null) {
            $schema['items'] = $this->items->toArray();
        }
        if ($this->minItems !== null) {
            $schema['minItems'] = $this->minItems;
        }
        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }

    public function getReferences(): array
    {
        return $this->items?->getReferences() ?? [];
    }
}
```

### ObjectProperty

For inline object definitions (not $ref to definitions):

```php
final class ObjectProperty extends AbstractProperty
{
    /** @var array<string, PropertyInterface> */
    private array $properties = [];
    /** @var array<string> */
    private array $required = [];
    private bool|PropertyInterface|null $additionalProperties = null;

    public static function new(): self
    {
        return new self();
    }

    public function property(string $name, PropertyInterface $property): self
    {
        $this->properties[$name] = $property;
        return $this;
    }

    public function required(string ...$names): self
    {
        $this->required = [...$this->required, ...$names];
        return $this;
    }

    public function additionalProperties(bool|PropertyInterface $value): self
    {
        $this->additionalProperties = $value;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'object'];
        
        if ($this->properties !== []) {
            $schema['properties'] = [];
            foreach ($this->properties as $name => $property) {
                $schema['properties'][$name] = $property->toArray();
            }
        }
        
        if ($this->required !== []) {
            $schema['required'] = array_values(array_unique($this->required));
        }
        
        if ($this->additionalProperties !== null) {
            $schema['additionalProperties'] = $this->additionalProperties instanceof PropertyInterface
                ? $this->additionalProperties->toArray()
                : $this->additionalProperties;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }

    public function getReferences(): array
    {
        $refs = [];
        foreach ($this->properties as $property) {
            $refs = [...$refs, ...$property->getReferences()];
        }
        if ($this->additionalProperties instanceof PropertyInterface) {
            $refs = [...$refs, ...$this->additionalProperties->getReferences()];
        }
        return $refs;
    }
}
```

### RefProperty

```php
final class RefProperty implements PropertyInterface
{
    private bool $nullable = false;

    private function __construct(
        private readonly string $reference,
    ) {}

    public static function to(string $definition): self
    {
        return new self($definition);
    }

    /**
     * Makes this reference nullable (oneOf with null type).
     */
    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function toArray(): array
    {
        $ref = ['$ref' => "#/definitions/{$this->reference}"];
        
        if ($this->nullable) {
            return [
                'oneOf' => [
                    $ref,
                    ['type' => 'null'],
                ],
            ];
        }
        
        return $ref;
    }

    public function getReferences(): array
    {
        return [$this->reference];
    }
}
```

### NullProperty

Simple null type for explicit null in unions:

```php
final class NullProperty implements PropertyInterface
{
    public static function new(): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return ['type' => 'null'];
    }

    public function getReferences(): array
    {
        return [];
    }
}
```

## Definition of Done

- [ ] All property classes implement `PropertyInterface`
- [ ] All properties have static `new()` factory method
- [ ] `ArrayProperty` has `strings()` and `of()` shortcuts
- [ ] `RefProperty` generates correct `$ref` path format
- [ ] `RefProperty::nullable()` generates `oneOf` with null
- [ ] `getReferences()` returns empty array for non-ref properties
- [ ] `getReferences()` collects refs from nested properties (ArrayProperty, ObjectProperty)
- [ ] All fluent methods return `self` or `static`
- [ ] Unit tests cover all property types
- [ ] Unit tests verify `toArray()` output matches expected JSON Schema

## Dependencies

**Requires:** Stage 1 (Interfaces & Base Classes)
**Enables:** Stage 3 (Definitions & Combinators), Stage 4 (Registry)
