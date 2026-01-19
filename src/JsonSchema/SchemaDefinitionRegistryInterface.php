<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema;

use Butschster\ContextGenerator\JsonSchema\Definition\DefinitionInterface;
use Butschster\ContextGenerator\JsonSchema\Definition\ObjectDefinition;
use Butschster\ContextGenerator\JsonSchema\Exception\DuplicateDefinitionException;
use Butschster\ContextGenerator\JsonSchema\Exception\SchemaValidationException;
use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

interface SchemaDefinitionRegistryInterface
{
    /**
     * Define a new object definition.
     * Returns the definition for fluent configuration.
     *
     * @throws DuplicateDefinitionException If definition already exists
     */
    public function define(string $name): ObjectDefinition;

    /**
     * Register a pre-built definition.
     *
     * @throws DuplicateDefinitionException If definition already exists
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
     *
     * @param array<string, mixed> $constraint
     */
    public function addRootOneOf(array $constraint): self;

    /**
     * Set schema metadata ($schema, id, title, etc.).
     */
    public function setMetadata(SchemaMetadata $metadata): self;

    /**
     * Get all registered definition names.
     *
     * @return array<string>
     */
    public function getDefinitionNames(): array;

    /**
     * Get count of registered definitions.
     */
    public function count(): int;

    /**
     * Build the complete JSON Schema array.
     *
     * @throws SchemaValidationException If references are invalid
     * @return array<string, mixed>
     */
    public function build(): array;
}
