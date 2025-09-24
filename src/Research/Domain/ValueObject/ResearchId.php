<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Domain\ValueObject;

final readonly class ResearchId implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (empty(\trim($this->value))) {
            throw new \InvalidArgumentException('Research ID cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(\uniqid('research_', true));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
