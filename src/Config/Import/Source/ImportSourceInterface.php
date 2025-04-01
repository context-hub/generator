<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
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
     * Check if this source supports the given source configuration
     */
    public function supports(SourceConfigInterface $config): bool;

    /**
     * Load configuration from this source
     *
     * @param SourceConfigInterface $config The source configuration
     * @return array<mixed> The loaded configuration data
     * @throws ImportSourceException If loading fails
     */
    public function load(SourceConfigInterface $config): array;

    /**
     * Get the list of sections that this source can import
     *
     * @return array<non-empty-string> List of section names (Empty array means all sections are allowed)
     */
    public function allowedSections(): array;
}
