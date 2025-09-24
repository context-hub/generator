<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Config;

interface ResearchConfigInterface
{
    /**
     * Get templates directory path
     */
    public function getTemplatesPath(): string;

    /**
     * Get researches base directory path
     */
    public function getResearchesPath(): string;

    /**
     * Get storage driver name
     */
    public function getStorageDriver(): string;

    /**
     * Get default status for new entries
     */
    public function getDefaultEntryStatus(): string;

    /**
     * Get environment variable configuration
     */
    public function getEnvConfig(): array;
}
