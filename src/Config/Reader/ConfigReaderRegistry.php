<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

final readonly class ConfigReaderRegistry
{
    public function __construct(
        /** @var array<ReaderInterface> */
        private array $readers,
    ) {}

    public function has(string $ext): bool
    {
        foreach ($this->readers as $reader) {
            if (\in_array($ext, $reader->getSupportedExtensions(), true)) {
                return true;
            }
        }

        return false;
    }

    public function get(string $ext): ReaderInterface
    {
        foreach ($this->readers as $reader) {
            if (\in_array($ext, $reader->getSupportedExtensions(), true)) {
                return $reader;
            }
        }

        throw new \RuntimeException(\sprintf('No reader found for extension: %s', $ext));
    }
}
