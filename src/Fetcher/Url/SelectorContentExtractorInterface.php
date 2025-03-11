<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Url;

/**
 * Interface for extracting content from HTML using CSS selectors
 */
interface SelectorContentExtractorInterface
{
    /**
     * Extract content from HTML using a CSS selector
     */
    public function extract(string $html, string $selector): string;
}
