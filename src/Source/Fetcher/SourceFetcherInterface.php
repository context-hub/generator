<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Fetcher;

use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\SourceInterface;

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
    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string;
}
