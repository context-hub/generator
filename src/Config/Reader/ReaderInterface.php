<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Config\Exception\ReaderException;

/**
 * Interface for configuration readers
 */
interface ReaderInterface
{
    /**
     * Read configuration from the given path
     *
     * @param string $path Path to configuration source
     * @return array<mixed> The parsed configuration data
     * @throws ReaderException If reading or parsing fails
     */
    public function read(string $path): array;

    /**
     * Check if this reader supports the given path
     *
     * @param string $path Path to configuration source
     * @return bool True if the reader can handle this path
     */
    public function supports(string $path): bool;

    public function getSupportedExtensions(): array;
}
