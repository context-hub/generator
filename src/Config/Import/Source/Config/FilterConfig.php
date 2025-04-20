<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Config;

/**
 * Configuration for filtering imported prompts.
 */
final readonly class FilterConfig implements \JsonSerializable
{
    /**
     * @param array<string, mixed>|null $config Raw filter configuration
     */
    public function __construct(
        private ?array $config = null,
    ) {}

    /**
     * Creates a FilterConfig from an array.
     *
     * @param array<string, mixed>|null $config The filter configuration
     */
    public static function fromArray(?array $config): self
    {
        if (empty($config)) {
            return new self();
        }

        return new self($config);
    }

    /**
     * Gets the raw filter configuration.
     *
     * @return array<string, mixed>|null The filter configuration
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * Checks if the filter configuration is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->config);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->config;
    }
}
