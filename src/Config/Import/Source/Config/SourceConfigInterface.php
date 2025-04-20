<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Config;

/**
 * Interface for all import source configurations
 */
interface SourceConfigInterface extends \JsonSerializable
{
    /**
     * Get the source path
     */
    public function getPath(): string;

    /**
     * Get the source type identifier
     */
    public function getType(): string;

    /**
     * Get the filter configuration, if any
     */
    public function getFilter(): ?FilterConfig;
}
