<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class BooleanProperty extends AbstractProperty
{
    public static function new(): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return ['type' => 'boolean', ...$this->buildBaseArray()];
    }
}
