<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\ValueObject;

/**
 * Template key value object
 */
final readonly class TemplateKey implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (empty($this->value)) {
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

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
