<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\SourceInterface;

/**
 * Interface for source content fetchers
 * @template TSource Source type
 */
interface SourceFetcherInterface
{
    /**
     * Check if this fetcher supports the given source
     * @param TSource $source
     */
    public function supports(SourceInterface $source): bool;

    /**
     * Fetch content from the source
     * @param TSource $source
     */
    public function fetch(SourceInterface $source): string;
}
