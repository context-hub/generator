<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
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
    ): void {
        $configLoader->registerParserPlugin($parserPlugin);
        $configLoader->registerMerger($promptConfigMerger);
    }
}
