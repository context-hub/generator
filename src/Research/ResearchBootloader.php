<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Research\Config\ResearchConfig;
use Butschster\ContextGenerator\Research\Config\ResearchConfigInterface;
use Butschster\ContextGenerator\Research\Console\ResearchInfoCommand;
use Butschster\ContextGenerator\Research\Console\ResearchListCommand;
use Butschster\ContextGenerator\Research\Console\TemplateListCommand;
use Butschster\ContextGenerator\Research\Service\EntryService;
use Butschster\ContextGenerator\Research\Service\EntryServiceInterface;
use Butschster\ContextGenerator\Research\Service\ResearchService;
use Butschster\ContextGenerator\Research\Service\ResearchServiceInterface;
use Butschster\ContextGenerator\Research\Service\TemplateService;
use Butschster\ContextGenerator\Research\Service\TemplateServiceInterface;
use Butschster\ContextGenerator\Research\Storage\FileStorageBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;

final class ResearchBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    #[\Override]
    public function defineDependencies(): array
    {
        return [
            FileStorageBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Configuration
            ResearchConfigInterface::class => ResearchConfig::class,
            TemplateServiceInterface::class => TemplateService::class,
            ResearchServiceInterface::class => ResearchService::class,
            EntryServiceInterface::class => EntryService::class,
        ];
    }

    public function init(ConsoleBootloader $console, EnvironmentInterface $env): void
    {
        $console->addCommand(
            ResearchListCommand::class,
            TemplateListCommand::class,
            ResearchInfoCommand::class,
        );

        // Initialize configuration from environment variables
        $this->config->setDefaults(
            ResearchConfig::CONFIG,
            [
                'templates_path' => $env->get('RESEARCH_TEMPLATES_PATH', '.templates'),
                'researches_path' => $env->get('RESEARCH_RESEARCHES_PATH', '.researches'),
                'storage_driver' => $env->get('RESEARCH_STORAGE_DRIVER', 'markdown'),
                'default_entry_status' => $env->get('RESEARCH_DEFAULT_STATUS', 'draft'),
                'env_config' => [],
            ],
        );
    }
}
