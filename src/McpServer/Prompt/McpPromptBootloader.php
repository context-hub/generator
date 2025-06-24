<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Console\ListPromptsCommand;
use Butschster\ContextGenerator\McpServer\Prompt\Console\ShowPromptCommand;
use Butschster\ContextGenerator\McpServer\Prompt\Content\FileMessageContentLoader;
use Butschster\ContextGenerator\McpServer\Prompt\Content\TextMessageContentLoader;
use Psr\Log\LoggerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Files\FilesInterface;

final class McpPromptBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            PromptRegistryInterface::class => PromptRegistry::class,
            PromptProviderInterface::class => PromptRegistry::class,
            PromptRegistry::class => PromptRegistry::class,
            PromptConfigFactory::class => static fn(
                TextMessageContentLoader $textMessageLoader,
                FileMessageContentLoader $fileMessageLoader,
            ) => new PromptConfigFactory(
                contentLoaders: [
                    $textMessageLoader,
                    $fileMessageLoader,
                ],
            ),
            FileMessageContentLoader::class => static fn(
                FilesInterface $files,
                LoggerInterface $logger,
                HttpClientInterface $httpClient,
                DirectoriesInterface $dirs,
            ) => new FileMessageContentLoader(
                logger: $logger,
                providers: [
                    new Content\LocalFileContentProvider(
                        files: $files,
                        rootPath: $dirs->getRootPath(),
                    ),
                    new Content\UrlFileContentProvider(
                        httpClient: $httpClient,
                    ),
                ],
            ),
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
        $console->addCommand(ShowPromptCommand::class);
    }
}
