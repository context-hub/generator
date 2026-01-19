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

    /** @var array<array<string, mixed>> */
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

    public function getDefinitionNames(): array
    {
        return \array_keys($this->definitions);
    }

    public function count(): int
    {
        return \count($this->definitions);
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
            $schema['required'] = \array_values(\array_unique($this->rootRequired));
        }

        // Definitions
        if ($this->definitions !== []) {
            $schema['definitions'] = [];

            // Sort definitions alphabetically for consistent output
            $sortedNames = \array_keys($this->definitions);
            \sort($sortedNames);

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
                $errors[] = \sprintf(
                    "Missing definition '%s' (referenced by: %s)",
                    $ref,
                    \implode(', ', $usedIn),
                );
            }
        }

        if ($errors !== []) {
            throw new SchemaValidationException(
                message: "Schema validation failed:\n" . \implode("\n", $errors),
                missingReferences: \array_keys($missingRefs),
                errors: $errors,
            );
        }
    }
}
