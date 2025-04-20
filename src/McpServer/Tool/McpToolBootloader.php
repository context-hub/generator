<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Butschster\ContextGenerator\McpServer\Tool\Console\ToolListCommand;
use Butschster\ContextGenerator\McpServer\Tool\Console\ToolRunCommand;
use Butschster\ContextGenerator\McpServer\Tool\Types\RunToolHandler;
use Butschster\ContextGenerator\McpServer\Tool\Types\ToolHandlerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\Config\Proxy;
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
            CommandExecutorInterface::class => new Proxy(
                interface: CommandExecutorInterface::class,
            ),
            ToolHandlerInterface::class => static fn(
                FactoryInterface $factory,
                EnvironmentInterface $env,
            ) => $factory->make(RunToolHandler::class, [
                'executionEnabled' => (bool) ($env->get('MCP_TOOL_COMMAND_EXECUTION') ?? true),
            ]),
        ];
    }

    public function init(
        ConfigLoaderBootloader $configLoader,
        ToolParserPlugin $parserPlugin,
        ToolConfigMerger $toolConfigMerger,
        ConsoleBootloader $console,
    ): void {
        $configLoader->registerParserPlugin($parserPlugin);
        $configLoader->registerMerger($toolConfigMerger);

        $console->addCommand(ToolListCommand::class);
        $console->addCommand(ToolRunCommand::class);
    }
}
