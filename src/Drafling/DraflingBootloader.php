<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling;

use Butschster\ContextGenerator\Application\Bootloader\ConfigurationBootloader;
use Butschster\ContextGenerator\Drafling\Config\DraflingConfig;
use Butschster\ContextGenerator\Drafling\Config\DraflingConfigInterface;
use Butschster\ContextGenerator\Drafling\Storage\StorageBootloader;
use Butschster\ContextGenerator\Drafling\Storage\StorageDriverInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;

/**
 * Main bootloader for the Drafling file-based project management system
 */
final class DraflingBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    #[\Override]
    public function defineDependencies(): array
    {
        return [
            ConfigurationBootloader::class,
            StorageBootloader::class,
        ];
    }

    public function init(EnvironmentInterface $env): void
    {
        // Initialize configuration from environment variables
        $this->config->setDefaults(
            DraflingConfig::CONFIG,
            [
                'enabled' => (bool) $env->get('DRAFLING_ENABLED', true),
                'templates_path' => $env->get('DRAFLING_TEMPLATES_PATH', '.templates'),
                'projects_path' => $env->get('DRAFLING_PROJECTS_PATH', '.projects'),
                'storage_driver' => $env->get('DRAFLING_STORAGE_DRIVER', 'markdown'),
                'default_entry_status' => $env->get('DRAFLING_DEFAULT_STATUS', 'draft'),
                'env_config' => [],
            ],
        );
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Configuration
            DraflingConfigInterface::class => DraflingConfig::class,
        ];
    }

    public function boot(
        DraflingConfigInterface $config,
        StorageDriverInterface $storageDriver,
    ): void {
        // Verify Drafling system is enabled
        if (!$config->isEnabled()) {
            return;
        }

        // Initialize storage driver
        $storageDriver->initialize([
            'base_path' => $config->getProjectsPath(),
            'templates_path' => $config->getTemplatesPath(),
            'storage_driver' => $config->getStorageDriver(),
            'default_entry_status' => $config->getDefaultEntryStatus(),
        ]);
    }
}
