<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Definition;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

/**
 * Definition that produces a oneOf schema at the root level.
 * Used for definitions like sourcePaths: oneOf [string, string[]].
 */
final class OneOfDefinition implements DefinitionInterface
{
    /** @var array<PropertyInterface> */
    private array $options;

    private function __construct(
        private readonly string $name,
        PropertyInterface ...$options,
    ) {
        $this->options = $options;
    }

    public static function create(string $name, PropertyInterface ...$options): self
    {
        return new self($name, ...$options);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'oneOf' => \array_map(
                static fn(PropertyInterface $o) => $o->toArray(),
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
        return \array_values(\array_unique($refs));
    }
}
