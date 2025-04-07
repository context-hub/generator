<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Config;

/**
 * Represents a command argument that can be conditionally included.
 */
final readonly class ToolArg implements \Stringable
{
    /**
     * @param string $name The argument name or value
     * @param string|null $when Optional condition for including the argument
     */
    public function __construct(
        public string $name,
        public ?string $when = null,
    ) {}

    /**
     * Creates a ToolArg from a string or array configuration.
     *
     * @param string|array<string, mixed> $config The argument configuration
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    public static function fromMixed(mixed $config): self
    {
        // Simple string argument
        if (\is_string($config)) {
            return new self(name: $config);
        }

        // Associative array with name and when
        if (\is_array($config)) {
            if (!isset($config['name']) || !\is_string($config['name'])) {
                throw new \InvalidArgumentException('Argument must have a non-empty "name" property');
            }

            $when = null;
            if (isset($config['when'])) {
                if (!\is_string($config['when'])) {
                    throw new \InvalidArgumentException('Argument "when" condition must be a string');
                }
                $when = $config['when'];
            }

            return new self(
                name: $config['name'],
                when: $when,
            );
        }

        throw new \InvalidArgumentException('Argument must be a string or an array with "name" property');
    }

    /**
     * Converts to array representation for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return \array_filter([
            'name' => $this->name,
            'when' => $this->when,
        ], static fn($value) => $value !== null);
    }

    /**
     * Returns the argument name when cast to string.
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
