<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Config;

use Spiral\Core\InjectableConfig;

/**
 * Drafling system configuration
 */
final class DraflingConfig extends InjectableConfig implements DraflingConfigInterface
{
    public const CONFIG = 'drafling';

    protected array $config = [
        'enabled' => true,
        'templates_path' => '.templates',
        'projects_path' => '.projects',
        'storage_driver' => 'markdown',
        'default_entry_status' => 'draft',
        'env_config' => [],
    ];

    public function isEnabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    public function getTemplatesPath(): string
    {
        return (string) $this->config['templates_path'];
    }

    public function getProjectsPath(): string
    {
        return (string) $this->config['projects_path'];
    }

    public function getStorageDriver(): string
    {
        return (string) $this->config['storage_driver'];
    }

    public function getDefaultEntryStatus(): string
    {
        return (string) $this->config['default_entry_status'];
    }

    public function getEnvConfig(): array
    {
        return (array) $this->config['env_config'];
    }
}
