<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\ValueObject;

/**
 * Project identifier value object
 */
final readonly class ProjectId implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (empty($this->value)) {
            throw new \InvalidArgumentException('Project ID cannot be empty');
        }
    }

    /**
     * Generate new UUID-based project ID
     */
    public static function generate(): self
    {
        return new self(\uniqid('project_', true));
    }

    /**
     * Create from string
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
