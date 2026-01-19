<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class StringProperty extends AbstractProperty
{
    /** @var array<string>|null */
    private ?array $enum = null;
    private ?string $pattern = null;
    private ?string $format = null;
    private ?int $minLength = null;
    private ?int $maxLength = null;

    public static function new(): self
    {
        return new self();
    }

    /**
     * @param array<string> $values
     */
    public function enum(array $values): self
    {
        $this->enum = $values;
        return $this;
    }

    public function pattern(string $regex): self
    {
        $this->pattern = $regex;
        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function minLength(int $length): self
    {
        $this->minLength = $length;
        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['type' => 'string'];

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }
        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }
        if ($this->format !== null) {
            $schema['format'] = $this->format;
        }
        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        return [...$schema, ...$this->buildBaseArray()];
    }
}
