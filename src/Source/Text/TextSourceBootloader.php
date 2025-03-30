<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Spiral\Boot\Bootloader\Bootloader;

final class TextSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            TextSourceFetcher::class => static fn(
                ContentBuilderFactory $builderFactory,
                VariableResolver $variables,
                HasPrefixLoggerInterface $logger,
            ): TextSourceFetcher => new TextSourceFetcher(
                builderFactory: $builderFactory,
                variableResolver: $variables,
                logger: $logger->withPrefix('text-source'),
            ),
        ];
    }

    public function init(SourceFetcherBootloader $registry): void
    {
        $registry->register(TextSourceFetcher::class);
    }
}
