# Stage 3: Definitions & Combinators

## Overview

Implement definition builders (ObjectDefinition, EnumDefinition) and schema combinators (OneOf, AnyOf, AllOf,
Conditional). Definitions represent reusable schemas in `#/definitions/*`, while combinators enable complex type unions
and conditional schemas.

## Files

**CREATE:**

- `src/JsonSchema/Definition/ObjectDefinition.php` - Fluent object definition builder
- `src/JsonSchema/Definition/EnumDefinition.php` - Enum type definition
- `src/JsonSchema/Combinator/OneOf.php` - oneOf combinator
- `src/JsonSchema/Combinator/AnyOf.php` - anyOf combinator
- `src/JsonSchema/Combinator/AllOf.php` - allOf combinator
- `src/JsonSchema/Combinator/Conditional.php` - if/then/else combinator
- `tests/Unit/JsonSchema/Definition/` - Definition tests
- `tests/Unit/JsonSchema/Combinator/` - Combinator tests

## Code References

- `json-schema.json:650-750` - `fileSource` definition example
- `json-schema.json:180-220` - `source` with anyOf for different types
- `json-schema.json:250-350` - Conditional if/then/else pattern
- `json-schema.json:130-150` - oneOf usage for import types

## Implementation Details

### ObjectDefinition

Main builder for object schemas in definitions:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Definition;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

final class ObjectDefinition implements DefinitionInterface
{
    /** @var array<string, PropertyInterface> */
    private array $properties = [];
    
    /** @var array<string> */
    private array $required = [];
    
    private ?string $title = null;
    private ?string $description = null;
    private bool|PropertyInterface|null $additionalProperties = null;

    private function __construct(
        private readonly string $name,
    ) {}

    public static function create(string $name): self
    {
        return new self($name);
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

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function additionalProperties(bool|PropertyInterface $value): self
    {
        $this->additionalProperties = $value;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $schema = [];
        
        if ($this->title !== null) {
            $schema['title'] = $this->title;
        }
        
        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }
        
        // Only add type:object if we have properties
        if ($this->properties !== []) {
            $schema['type'] = 'object';
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

        return $schema;
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
        
        return array_values(array_unique($refs));
    }
}
```

### EnumDefinition

For string or integer enums:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Definition;

final class EnumDefinition implements DefinitionInterface
{
    private ?string $description = null;

    /**
     * @param array<string|int> $values
     */
    private function __construct(
        private readonly string $name,
        private readonly string $type, // 'string' or 'integer'
        private readonly array $values,
    ) {}

    public static function string(string $name, array $values): self
    {
        return new self($name, 'string', $values);
    }

    public static function integer(string $name, array $values): self
    {
        return new self($name, 'integer', $values);
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $schema = [
            'type' => $this->type,
            'enum' => $this->values,
        ];
        
        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }
        
        return $schema;
    }

    public function getReferences(): array
    {
        return [];
    }
}
```

### OneOf Combinator

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

final class OneOf implements CombinatorInterface
{
    /** @var array<PropertyInterface> */
    private array $options = [];

    public static function create(PropertyInterface ...$options): self
    {
        $instance = new self();
        $instance->options = $options;
        return $instance;
    }

    public function add(PropertyInterface $option): self
    {
        $this->options[] = $option;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'oneOf' => array_map(
                fn(PropertyInterface $opt) => $opt->toArray(),
                $this->options,
            ),
        ];
    }

    public function getReferences(): array
    {
        $refs = [];
        foreach ($this->options as $option) {
            $refs = [...$refs, ...$option->getReferences()];
        }
        return array_values(array_unique($refs));
    }
}
```

### AnyOf Combinator

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

final class AnyOf implements CombinatorInterface
{
    /** @var array<PropertyInterface> */
    private array $options = [];

    public static function create(PropertyInterface ...$options): self
    {
        $instance = new self();
        $instance->options = $options;
        return $instance;
    }

    public function toArray(): array
    {
        return [
            'anyOf' => array_map(
                fn(PropertyInterface $opt) => $opt->toArray(),
                $this->options,
            ),
        ];
    }

    public function getReferences(): array
    {
        $refs = [];
        foreach ($this->options as $option) {
            $refs = [...$refs, ...$option->getReferences()];
        }
        return array_values(array_unique($refs));
    }
}
```

### AllOf Combinator

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

final class AllOf implements CombinatorInterface
{
    /** @var array<PropertyInterface> */
    private array $schemas = [];

    public static function create(PropertyInterface ...$schemas): self
    {
        $instance = new self();
        $instance->schemas = $schemas;
        return $instance;
    }

    public function toArray(): array
    {
        return [
            'allOf' => array_map(
                fn(PropertyInterface $schema) => $schema->toArray(),
                $this->schemas,
            ),
        ];
    }

    public function getReferences(): array
    {
        $refs = [];
        foreach ($this->schemas as $schema) {
            $refs = [...$refs, ...$schema->getReferences()];
        }
        return array_values(array_unique($refs));
    }
}
```

### Conditional Combinator

For if/then/else patterns:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

final class Conditional implements CombinatorInterface
{
    private ?PropertyInterface $then = null;
    private ?PropertyInterface $else = null;

    private function __construct(
        private readonly array $if,
    ) {}

    /**
     * Create condition: if property equals value.
     */
    public static function when(string $property, mixed $equals): self
    {
        return new self([
            'properties' => [
                $property => ['const' => $equals],
            ],
        ]);
    }

    /**
     * Create condition with custom if schema.
     */
    public static function if(array $condition): self
    {
        return new self($condition);
    }

    public function then(PropertyInterface $schema): self
    {
        $this->then = $schema;
        return $this;
    }

    public function else(PropertyInterface $schema): self
    {
        $this->else = $schema;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['if' => $this->if];
        
        if ($this->then !== null) {
            $schema['then'] = $this->then->toArray();
        }
        
        if ($this->else !== null) {
            $schema['else'] = $this->else->toArray();
        }
        
        return $schema;
    }

    public function getReferences(): array
    {
        $refs = [];
        
        if ($this->then !== null) {
            $refs = [...$refs, ...$this->then->getReferences()];
        }
        
        if ($this->else !== null) {
            $refs = [...$refs, ...$this->else->getReferences()];
        }
        
        return array_values(array_unique($refs));
    }
}
```

## Usage Examples

```php
// Object definition with properties
$registry->define('fileSource')
    ->required('sourcePaths')
    ->property('sourcePaths', RefProperty::to('sourcePaths'))
    ->property('filePattern', RefProperty::to('filePattern')->default('*.*'))
    ->property('showTreeView', BooleanProperty::new()->default(true));

// OneOf for polymorphic types
$registry->addRootProperty('import', ArrayProperty::of(
    OneOf::create(
        StringProperty::new()->description('Path shorthand'),
        RefProperty::to('importConfig'),
    )
));

// Conditional based on type
$conditional = Conditional::when('type', 'file')
    ->then(RefProperty::to('fileSource'));

// AllOf to combine base + specific
$source = AllOf::create(
    RefProperty::to('baseSource'),
    Conditional::when('type', 'file')->then(RefProperty::to('fileSource')),
);
```

## Definition of Done

- [ ] `ObjectDefinition` supports all fluent methods (property, required, title, description, additionalProperties)
- [ ] `EnumDefinition` supports both string and integer enums
- [ ] All combinators (`OneOf`, `AnyOf`, `AllOf`) collect references from children
- [ ] `Conditional` supports `when(property, value)` shorthand
- [ ] `Conditional` supports custom `if()` conditions
- [ ] All combinators implement `CombinatorInterface` (extends `PropertyInterface`)
- [ ] Unit tests for each definition type
- [ ] Unit tests for each combinator
- [ ] Tests verify `getReferences()` collects nested refs correctly

## Dependencies

**Requires:** Stage 1 (Interfaces), Stage 2 (Property Types)
**Enables:** Stage 4 (Registry & Validation)
