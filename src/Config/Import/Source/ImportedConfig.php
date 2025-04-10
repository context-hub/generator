<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;

/**
 * @implements \ArrayAccess<non-empty-string, mixed>
 */
final readonly class ImportedConfig implements \ArrayAccess
{
    public function __construct(
        public SourceConfigInterface $sourceConfig,
        public array $config,
        public string $path,
        public bool $isLocal,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->config);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->config[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('Cannot set value in imported config');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('Cannot unset value in imported config');
    }
}
