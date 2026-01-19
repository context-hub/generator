# Stage 1: Interfaces & Base Classes

## Overview

Establish the foundational contracts and abstract base classes for the JSON Schema builder. This stage creates the type
system that all subsequent property types, definitions, and combinators will implement. No functional schema generation
yet - just the contracts.

## Files

**CREATE:**

- `src/JsonSchema/Property/PropertyInterface.php` - Contract for all property types
- `src/JsonSchema/Property/AbstractProperty.php` - Base class with description/default
- `src/JsonSchema/Definition/DefinitionInterface.php` - Contract for definitions
- `src/JsonSchema/Combinator/CombinatorInterface.php` - Contract for oneOf/anyOf/allOf
- `src/JsonSchema/Exception/SchemaException.php` - Base exception
- `src/JsonSchema/Exception/SchemaValidationException.php` - Validation errors
- `src/JsonSchema/Exception/DuplicateDefinitionException.php` - Duplicate registration

## Code References

- `src/Source/Registry/SourceRegistryInterface.php:8-14` - Simple interface pattern to follow
- `src/Modifier/SourceModifierInterface.php` - Another interface example
- `src/Source/SourceInterface.php` - Interface with multiple methods pattern

## Implementation Details

### PropertyInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

interface PropertyInterface
{
    /**
     * Convert to JSON Schema array representation.
     * @return array<string, mixed>
     */
    public function toArray(): array;
    
    /**
     * Get all $ref references used by this property.
     * Used for validation that all references exist.
     * @return array<string> Definition names referenced (without #/definitions/ prefix)
     */
    public function getReferences(): array;
}
```

### AbstractProperty

Base class providing common functionality:

- `description(?string)` - Sets description
- `default(mixed)` - Sets default value
- Protected method to build base array with these common fields
- Implements `PropertyInterface`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

abstract class AbstractProperty implements PropertyInterface
{
    protected ?string $description = null;
    protected mixed $default = null;
    protected bool $hasDefault = false;

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

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
```

### DefinitionInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Definition;

interface DefinitionInterface
{
    /**
     * Get the definition name (used in #/definitions/{name}).
     */
    public function getName(): string;
    
    /**
     * Convert to JSON Schema array representation.
     * @return array<string, mixed>
     */
    public function toArray(): array;
    
    /**
     * Get all $ref references used by this definition.
     * @return array<string> Definition names referenced
     */
    public function getReferences(): array;
}
```

### CombinatorInterface

Extends PropertyInterface since combinators can be used anywhere a property can:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

interface CombinatorInterface extends PropertyInterface
{
    // Inherits toArray() and getReferences() from PropertyInterface
    // Combinators ARE properties (can be used in same places)
}
```

### Exceptions

```php
// SchemaException - base
class SchemaException extends \RuntimeException {}

// SchemaValidationException - with details about what's wrong
class SchemaValidationException extends SchemaException
{
    public function __construct(
        string $message,
        public readonly array $missingReferences = [],
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}

// DuplicateDefinitionException
class DuplicateDefinitionException extends SchemaException
{
    public function __construct(
        public readonly string $definitionName,
    ) {
        parent::__construct("Definition '{$definitionName}' is already registered");
    }
}
```

### Naming Conventions

- All classes in `Butschster\ContextGenerator\JsonSchema` namespace
- Interfaces suffixed with `Interface`
- Exceptions in `Exception` sub-namespace
- Use `static` return type for fluent methods (allows extension)

## Definition of Done

- [ ] All interface files created with proper PHPDoc
- [ ] `AbstractProperty` implements fluent builder pattern
- [ ] All exceptions extend `SchemaException`
- [ ] `SchemaValidationException` holds structured error data
- [ ] All files use `declare(strict_types=1)`
- [ ] No syntax errors (files parse correctly)
- [ ] Namespace matches directory structure

## Dependencies

**Requires:** None (foundation stage)
**Enables:** Stage 2 (Property Types), Stage 3 (Definitions & Combinators)
