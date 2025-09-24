<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Domain\ValueObject;

/**
 * Entry identifier value object
 */
final readonly class EntryId implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (empty(\trim($this->value))) {
            throw new \InvalidArgumentException('Entry ID cannot be empty');
        }
    }

    /**
     * Generate new UUID-based entry ID
     */
    public static function generate(): self
    {
        return new self(\uniqid('entry_', true));
    }

    /**
     * Create from string
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Check equality with another EntryId
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
