<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Fetcher;

use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Butschster\ContextGenerator\SourceParserInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * Registry for source fetchers
 */
#[Singleton]
final readonly class SourceFetcherProvider implements SourceParserInterface
{
    public function __construct(
        /** @var SourceFetcherInterface[] */
        private array $fetchers = [],
    ) {}

    /**
     * Find a fetcher that supports the given source
     */
    public function findFetcher(SourceInterface $source): SourceFetcherInterface
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->supports($source)) {
                return $fetcher;
            }
        }

        throw new \RuntimeException(
            \sprintf(
                'No fetcher found for source of type %s',
                $source::class,
            ),
        );
    }

    public function parse(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        return $this->findFetcher($source)->fetch($source, $modifiersApplier);
    }
}
