<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Drafling\Config\DraflingConfig;
use Butschster\ContextGenerator\Drafling\Config\DraflingConfigInterface;
use Butschster\ContextGenerator\Drafling\Console\ProjectInfoCommand;
use Butschster\ContextGenerator\Drafling\Console\ProjectListCommand;
use Butschster\ContextGenerator\Drafling\Console\TemplateListCommand;
use Butschster\ContextGenerator\Drafling\Service\DraflingService;
use Butschster\ContextGenerator\Drafling\Service\DraflingServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\EntryService;
use Butschster\ContextGenerator\Drafling\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\ProjectService;
use Butschster\ContextGenerator\Drafling\Service\ProjectServiceInterface;
use Butschster\ContextGenerator\Drafling\Service\TemplateService;
use Butschster\ContextGenerator\Drafling\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\Drafling\Storage\Config\FileStorageConfig;
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
            StorageBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Configuration
            DraflingConfigInterface::class => DraflingConfig::class,
            TemplateServiceInterface::class => TemplateService::class,
            DraflingServiceInterface::class => DraflingService::class,
            ProjectServiceInterface::class => ProjectService::class,
            EntryServiceInterface::class => EntryService::class,
        ];
    }

    public function init(ConsoleBootloader $console, EnvironmentInterface $env): void
    {
        $console->addCommand(
            ProjectListCommand::class,
            TemplateListCommand::class,
            ProjectInfoCommand::class,
        );

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

    public function boot(
        DraflingConfigInterface $config,
        StorageDriverInterface $storageDriver,
    ): void {
        // Verify Drafling system is enabled
        if (!$config->isEnabled()) {
            return;
        }

        // Initialize storage driver
        $storageDriver->initialize(new FileStorageConfig(
            basePath: $config->getProjectsPath(),
            templatesPath: $config->getTemplatesPath(),
            defaultEntryStatus: $config->getDefaultEntryStatus(),
        ));
    }
}
