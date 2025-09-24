<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Config;

use Spiral\Core\InjectableConfig;

final class ResearchConfig extends InjectableConfig implements ResearchConfigInterface
{
    public const string CONFIG = 'research';

    protected array $config = [
        'enabled' => true,
        'templates_path' => '.templates',
        'researches_path' => '.researches',
        'storage_driver' => 'markdown',
        'default_entry_status' => 'draft',
        'env_config' => [],
    ];

    public function getTemplatesPath(): string
    {
        return (string) $this->config['templates_path'];
    }

    public function getResearchesPath(): string
    {
        return (string) $this->config['researches_path'];
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
