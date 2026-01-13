<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Butschster\ContextGenerator\McpServer\Interceptor\InterceptorPipeline;
use Butschster\ContextGenerator\McpServer\Interceptor\McpServerInterceptorBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;

/**
 * Bootloader for multi-project support in MCP tools.
 *
 * Registers:
 * - ProjectWhitelistRegistry for storing whitelisted projects
 * - ProjectsParserPlugin for parsing `projects` section in context.yaml
 * - ProjectInterceptor for handling project context switching
 */
#[Singleton]
final class ProjectBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [
            ConfigLoaderBootloader::class,
            McpServerInterceptorBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ProjectWhitelistRegistryInterface::class => ProjectWhitelistRegistry::class,
            ProjectWhitelistRegistry::class => ProjectWhitelistRegistry::class,
            ProjectsParserPlugin::class => ProjectsParserPlugin::class,
            ProjectInterceptor::class => ProjectInterceptor::class,
        ];
    }

    public function boot(
        ConfigLoaderBootloader $configLoader,
        ProjectsParserPlugin $parserPlugin,
        InterceptorPipeline $pipeline,
        ProjectInterceptor $interceptor,
    ): void {
        // Register the parser plugin
        $configLoader->registerParserPlugin($parserPlugin);

        // Register the interceptor in the pipeline
        $pipeline->addInterceptor($interceptor);
    }
}
