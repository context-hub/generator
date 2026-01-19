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
            'oneOf' => \array_map(
                static fn(PropertyInterface $opt) => $opt->toArray(),
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
