<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class ArrayProperty extends AbstractProperty
{
    private ?PropertyInterface $items = null;
    private ?int $minItems = null;
    private ?int $maxItems = null;

    public static function new(): self
    {
        return new self();
    }

    /**
     * Shortcut for array of strings.
     */
    public static function strings(): self
    {
        return (new self())->items(StringProperty::new());
    }

    /**
     * Shortcut for array of specific type.
     */
    public static function of(PropertyInterface $itemType): self
    {
        return (new self())->items($itemType);
    }

    public function items(PropertyInterface $items): self
    {
        $this->items = $items;
        return $this;
    }

    public function minItems(int $count): self
    {
        $this->minItems = $count;
        return $this;
    }

    public function maxItems(int $count): self
    {
        $this->maxItems = $count;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'array'];

        if ($this->items !== null) {
            $schema['items'] = $this->items->toArray();
        }
        if ($this->minItems !== null) {
            $schema['minItems'] = $this->minItems;
        }
        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }

    public function getReferences(): array
    {
        return $this->items?->getReferences() ?? [];
    }
}
