<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\McpServer\Prompt\Console\ListPromptsCommand;
use Spiral\Boot\Bootloader\Bootloader;

final class McpPromptBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            PromptRegistryInterface::class => PromptRegistry::class,
            PromptProviderInterface::class => PromptRegistry::class,
            PromptRegistry::class => PromptRegistry::class,
        ];
    }

    public function init(
        ConfigLoaderBootloader $configLoader,
        PromptParserPlugin $parserPlugin,
        PromptConfigMerger $promptConfigMerger,
        ConsoleBootloader $console,
    ): void {
        $configLoader->registerParserPlugin($parserPlugin);
        $configLoader->registerMerger($promptConfigMerger);

        $console->addCommand(ListPromptsCommand::class);
    }
}
