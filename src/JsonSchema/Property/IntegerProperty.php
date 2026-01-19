<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class IntegerProperty extends AbstractProperty
{
    private ?int $minimum = null;
    private ?int $maximum = null;

    public static function new(): self
    {
        return new self();
    }

    public function minimum(int $value): self
    {
        $this->minimum = $value;
        return $this;
    }

    public function maximum(int $value): self
    {
        $this->maximum = $value;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'integer'];

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }
}
