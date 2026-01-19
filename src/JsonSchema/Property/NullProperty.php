<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class NullProperty implements PropertyInterface
{
    public static function new(): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return ['type' => 'null'];
    }

    public function getReferences(): array
    {
        return [];
    }
}
