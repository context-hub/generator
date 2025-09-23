<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Config;

/**
 * Configuration interface for Drafling system
 */
interface DraflingConfigInterface
{
    /**
     * Get templates directory path
     */
    public function getTemplatesPath(): string;

    /**
     * Get projects base directory path
     */
    public function getProjectsPath(): string;

    /**
     * Get storage driver name
     */
    public function getStorageDriver(): string;

    /**
     * Get default status for new entries
     */
    public function getDefaultEntryStatus(): string;

    /**
     * Check if Drafling system is enabled
     */
    public function isEnabled(): bool;

    /**
     * Get environment variable configuration
     */
    public function getEnvConfig(): array;
}
