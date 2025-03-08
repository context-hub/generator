<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Parser;

use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\SourceInterface;
use Butschster\ContextGenerator\SourceParserInterface;

/**
 * Default implementation of source parser
 */
final readonly class DefaultSourceParser implements SourceParserInterface
{
    public function __construct(
        private SourceFetcherRegistry $fetcherRegistry,
    ) {}

    public function parse(SourceInterface $source): string
    {
        return $this->fetcherRegistry->findFetcher($source)->fetch($source);
    }
}
