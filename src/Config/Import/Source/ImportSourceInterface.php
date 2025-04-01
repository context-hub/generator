<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\ImportConfig;
use Butschster\ContextGenerator\Config\Import\Source\Exception\ImportSourceException;

/**
 * Interface for all import sources that can load configuration from different locations
 */
interface ImportSourceInterface
{
    /**
     * Get the name/type of this import source
     */
    public function getName(): string;

    /**
     * Check if this source supports the given import configuration
     */
    public function supports(ImportConfig $config): bool;

    /**
     * Load configuration from this source
     *
     * @param ImportConfig $config The import configuration
     * @return array<mixed> The loaded configuration data
     * @throws ImportSourceException If loading fails
     */
    public function load(ImportConfig $config): array;
}
