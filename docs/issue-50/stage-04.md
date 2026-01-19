# Stage 4: Registry & Validation

## Overview

Implement the central `SchemaDefinitionRegistry` that collects definitions from all modules and compiles them into a
complete JSON Schema. Includes reference validation to catch errors at build time.

## Files

**CREATE:**

- `src/JsonSchema/SchemaDefinitionRegistryInterface.php` - Public contract
- `src/JsonSchema/SchemaDefinitionRegistry.php` - Main implementation
- `src/JsonSchema/SchemaMetadata.php` - $schema, id, title, fileMatch configuration
- `tests/Unit/JsonSchema/SchemaDefinitionRegistryTest.php` - Registry tests

## Code References

- `src/Source/Registry/SourceRegistry.php` - Similar registry pattern
- `src/Modifier/SourceModifierRegistry.php` - Another registry example
- `json-schema.json:1-15` - Schema metadata structure
- `json-schema.json:580-1500` - Definitions section structure

## Implementation Details

### SchemaMetadata

Holds schema-level metadata:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema;

final class SchemaMetadata
{
    /**
     * @param array<string> $fileMatch Files this schema applies to
     */
    public function __construct(
        public readonly string $schema = 'http://json-schema.org/draft-07/schema#',
        public readonly string $id = 'https://ctxllm.com',
        public readonly string $title = 'ContextHub Configuration',
        public readonly string $description = 'Configuration schema for ContextHub document generator',
        public readonly array $fileMatch = ['context.json', 'context.yaml'],
    ) {}

    public function toArray(): array
    {
        return [
            '$schema' => $this->schema,
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'fileMatch' => $this->fileMatch,
        ];
    }
}
```

### SchemaDefinitionRegistryInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema;

use Butschster\ContextGenerator\JsonSchema\Definition\DefinitionInterface;
use Butschster\ContextGenerator\JsonSchema\Definition\ObjectDefinition;
use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

interface SchemaDefinitionRegistryInterface
{
    /**
     * Define a new object definition.
     * Returns the definition for fluent configuration.
     * 
     * @throws Exception\DuplicateDefinitionException If definition already exists
     */
    public function define(string $name): ObjectDefinition;

    /**
     * Register a pre-built definition.
     * 
     * @throws Exception\DuplicateDefinitionException If definition already exists
     */
    public function register(DefinitionInterface $definition): self;

    /**
     * Get an existing definition by name.
     */
    public function get(string $name): ?DefinitionInterface;

    /**
     * Check if a definition exists.
     */
    public function has(string $name): bool;

    /**
     * Add a property to the root schema.
     */
    public function addRootProperty(string $name, PropertyInterface $property): self;

    /**
     * Mark root properties as required.
     */
    public function addRootRequired(string ...$names): self;

    /**
     * Add a root-level oneOf constraint.
     */
    public function addRootOneOf(array $constraint): self;

    /**
     * Set schema metadata ($schema, id, title, etc.).
     */
    public function setMetadata(SchemaMetadata $metadata): self;

    /**
     * Build the complete JSON Schema array.
     * 
     * @throws Exception\SchemaValidationException If references are invalid
     * @return array<string, mixed>
     */
    public function build(): array;
}
```

### SchemaDefinitionRegistry

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema;

use Butschster\ContextGenerator\JsonSchema\Definition\DefinitionInterface;
use Butschster\ContextGenerator\JsonSchema\Definition\ObjectDefinition;
use Butschster\ContextGenerator\JsonSchema\Exception\DuplicateDefinitionException;
use Butschster\ContextGenerator\JsonSchema\Exception\SchemaValidationException;
use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

final class SchemaDefinitionRegistry implements SchemaDefinitionRegistryInterface
{
    /** @var array<string, DefinitionInterface> */
    private array $definitions = [];

    /** @var array<string, PropertyInterface> */
    private array $rootProperties = [];

    /** @var array<string> */
    private array $rootRequired = [];

    /** @var array<array> */
    private array $rootOneOf = [];

    private SchemaMetadata $metadata;

    public function __construct()
    {
        $this->metadata = new SchemaMetadata();
    }

    public function define(string $name): ObjectDefinition
    {
        if (isset($this->definitions[$name])) {
            throw new DuplicateDefinitionException($name);
        }

        $definition = ObjectDefinition::create($name);
        $this->definitions[$name] = $definition;

        return $definition;
    }

    public function register(DefinitionInterface $definition): self
    {
        $name = $definition->getName();
        
        if (isset($this->definitions[$name])) {
            throw new DuplicateDefinitionException($name);
        }

        $this->definitions[$name] = $definition;

        return $this;
    }

    public function get(string $name): ?DefinitionInterface
    {
        return $this->definitions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function addRootProperty(string $name, PropertyInterface $property): self
    {
        $this->rootProperties[$name] = $property;
        return $this;
    }

    public function addRootRequired(string ...$names): self
    {
        $this->rootRequired = [...$this->rootRequired, ...$names];
        return $this;
    }

    public function addRootOneOf(array $constraint): self
    {
        $this->rootOneOf[] = $constraint;
        return $this;
    }

    public function setMetadata(SchemaMetadata $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function build(): array
    {
        // Validate all references before building
        $this->validateReferences();

        // Build the schema
        $schema = $this->metadata->toArray();
        $schema['type'] = 'object';

        // Root oneOf constraints
        if ($this->rootOneOf !== []) {
            $schema['oneOf'] = $this->rootOneOf;
        }

        // Root properties
        if ($this->rootProperties !== []) {
            $schema['properties'] = [];
            foreach ($this->rootProperties as $name => $property) {
                $schema['properties'][$name] = $property->toArray();
            }
        }

        // Root required (if no oneOf, otherwise oneOf handles it)
        if ($this->rootRequired !== [] && $this->rootOneOf === []) {
            $schema['required'] = array_values(array_unique($this->rootRequired));
        }

        // Definitions
        if ($this->definitions !== []) {
            $schema['definitions'] = [];
            
            // Sort definitions alphabetically for consistent output
            $sortedNames = array_keys($this->definitions);
            sort($sortedNames);
            
            foreach ($sortedNames as $name) {
                $schema['definitions'][$name] = $this->definitions[$name]->toArray();
            }
        }

        return $schema;
    }

    /**
     * Validate that all referenced definitions exist.
     * 
     * @throws SchemaValidationException
     */
    private function validateReferences(): void
    {
        $allReferences = [];
        $errors = [];

        // Collect references from root properties
        foreach ($this->rootProperties as $name => $property) {
            foreach ($property->getReferences() as $ref) {
                $allReferences[$ref][] = "root.{$name}";
            }
        }

        // Collect references from definitions
        foreach ($this->definitions as $defName => $definition) {
            foreach ($definition->getReferences() as $ref) {
                $allReferences[$ref][] = "definitions.{$defName}";
            }
        }

        // Check each reference exists
        $missingRefs = [];
        foreach ($allReferences as $ref => $usedIn) {
            if (!isset($this->definitions[$ref])) {
                $missingRefs[$ref] = $usedIn;
                $errors[] = sprintf(
                    "Missing definition '%s' (referenced by: %s)",
                    $ref,
                    implode(', ', $usedIn),
                );
            }
        }

        if ($errors !== []) {
            throw new SchemaValidationException(
                message: "Schema validation failed:\n" . implode("\n", $errors),
                missingReferences: array_keys($missingRefs),
                errors: $errors,
            );
        }
    }

    /**
     * Get all registered definition names.
     * 
     * @return array<string>
     */
    public function getDefinitionNames(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Get count of registered definitions.
     */
    public function count(): int
    {
        return count($this->definitions);
    }
}
```

### Key Design Decisions

1. **Fluent `define()` returns ObjectDefinition**: Allows chaining without storing reference
   ```php
   $registry->define('fileSource')
       ->required('sourcePaths')
       ->property('sourcePaths', RefProperty::to('sourcePaths'));
   ```

2. **Reference validation**: `build()` validates ALL references before generating schema

3. **Sorted output**: Definitions are sorted alphabetically for deterministic output

4. **Root oneOf support**: For the `oneOf: [required: documents, required: prompts, ...]` pattern

5. **Separate `register()` method**: For pre-built definitions (like EnumDefinition)

## Usage Examples

```php
$registry = new SchemaDefinitionRegistry();

// Set metadata
$registry->setMetadata(new SchemaMetadata(
    title: 'My Schema',
    fileMatch: ['config.json'],
));

// Define reusable types
$registry->define('sourcePaths')
    ->property('paths', ArrayProperty::strings());

// Define source with reference
$registry->define('fileSource')
    ->required('sourcePaths')
    ->property('sourcePaths', RefProperty::to('sourcePaths'))
    ->property('pattern', StringProperty::new()->default('*.*'));

// Add root property
$registry->addRootProperty('sources', ArrayProperty::of(RefProperty::to('fileSource')));

// Add root oneOf constraint
$registry->addRootOneOf(['required' => ['sources']]);

// Build - will throw if 'sourcePaths' definition is missing
$schema = $registry->build();
```

## Definition of Done

- [ ] `SchemaMetadata` holds all schema-level fields
- [ ] `define()` creates and registers ObjectDefinition in one call
- [ ] `register()` accepts pre-built DefinitionInterface
- [ ] `DuplicateDefinitionException` thrown for duplicate names
- [ ] `build()` calls `validateReferences()` before compiling
- [ ] `validateReferences()` collects all refs from properties and definitions
- [ ] `SchemaValidationException` includes list of missing references and where they're used
- [ ] Definitions sorted alphabetically in output
- [ ] Root oneOf support for alternative required fields
- [ ] Unit tests for registry operations
- [ ] Unit tests for reference validation (missing refs throw)
- [ ] Unit tests for duplicate detection

## Dependencies

**Requires:** Stage 1-3 (All property types, definitions, combinators)
**Enables:** Stage 5 (Bootloader & Console)
