<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\MCP\Config\McpServerParserPlugin;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Butschster\ContextGenerator\Source\MCP\Config\ServerRegistry;
use Butschster\ContextGenerator\Source\MCP\Operations\OperationFactory;
use Butschster\ContextGenerator\Source\MCP\Connection\ConnectionManager;
use Spiral\Boot\Bootloader\Bootloader;

final class McpSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            McpSourceFetcher::class => McpSourceFetcher::class,
            ServerRegistry::class => ServerRegistry::class,
            OperationFactory::class => OperationFactory::class,
            ConnectionManager::class => ConnectionManager::class,
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        McpSourceFactory $factory,
    ): void {
        $registry->register(McpSourceFetcher::class);
        $sourceRegistry->register($factory);
    }

    public function boot(
        ConfigLoaderBootloader $parserRegistry,
        McpServerParserPlugin $plugin,
    ): void {
        $parserRegistry->registerParserPlugin($plugin);
    }
}
