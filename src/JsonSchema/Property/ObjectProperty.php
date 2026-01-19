<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

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
            $schema['required'] = \array_values(\array_unique($this->required));
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
