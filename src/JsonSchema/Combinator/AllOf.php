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
            'allOf' => \array_map(
                static fn(PropertyInterface $schema) => $schema->toArray(),
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
        return \array_values(\array_unique($refs));
    }
}
