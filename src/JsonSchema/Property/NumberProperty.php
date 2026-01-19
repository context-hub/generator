<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class NumberProperty extends AbstractProperty
{
    private ?float $minimum = null;
    private ?float $maximum = null;

    public static function new(): self
    {
        return new self();
    }

    public function minimum(float $value): self
    {
        $this->minimum = $value;
        return $this;
    }

    public function maximum(float $value): self
    {
        $this->maximum = $value;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'number'];

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }
}
