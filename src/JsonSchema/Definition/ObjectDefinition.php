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
            $schema['required'] = \array_values(\array_unique($this->required));
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

        return \array_values(\array_unique($refs));
    }
}
