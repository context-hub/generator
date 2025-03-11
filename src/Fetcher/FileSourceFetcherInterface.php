<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\SourceInterface;

/**
 * Interface for file source fetchers
 * @template-extends SourceFetcherInterface<FileSource>
 */
interface FileSourceFetcherInterface extends SourceFetcherInterface
{
    /**
     * Check if this fetcher supports the given source
     */
    public function supports(SourceInterface $source): bool;

    /**
     * Fetch content from the given source
     * 
     * @throws \InvalidArgumentException If the source is not supported
     */
    public function fetch(SourceInterface $source): string;
}
