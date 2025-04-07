<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutor;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Butschster\ContextGenerator\McpServer\Tool\Types\RunToolHandler;
use Butschster\ContextGenerator\McpServer\Tool\Types\ToolHandlerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\FactoryInterface;

final class McpToolBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [
            ConfigLoaderBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ToolRegistryInterface::class => ToolRegistry::class,
            ToolProviderInterface::class => ToolRegistry::class,
            ToolRegistry::class => ToolRegistry::class,
            CommandExecutorInterface::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
                EnvironmentInterface $env,
            ) => $factory->make(CommandExecutor::class, [
                'projectRoot' => (string) $dirs->getRootPath(),
                'timeout' => (int) ($env->get('MCP_TOOL_MAX_RUNTIME') ?? 30),
            ]),
            ToolHandlerInterface::class => static fn(
                FactoryInterface $factory,
                EnvironmentInterface $env,
            ) => $factory->make(RunToolHandler::class, [
                'executionEnabled' => (bool) ($env->get('MCP_TOOL_COMMAND_EXECUTION') ?? true),
            ]),
        ];
    }

    public function init(ConfigLoaderBootloader $configLoader, ToolParserPlugin $parserPlugin): void
    {
        $configLoader->registerParserPlugin($parserPlugin);
    }
}
