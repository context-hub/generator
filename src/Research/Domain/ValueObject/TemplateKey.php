<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Domain\ValueObject;

/**
 * Template key value object
 */
final readonly class TemplateKey implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (empty(\trim($this->value))) {
            throw new \InvalidArgumentException('Template key cannot be empty');
        }
    }

    /**
     * Create from string
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Check equality with another TemplateKey
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
