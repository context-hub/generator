<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * HTML content cleaner for URL sources
 */
final readonly class HtmlCleaner
{
    public function __construct(
        private HtmlConverter $htmlConverter = new HtmlConverter(),
    ) {
        $this->htmlConverter->getConfig()->setOption('strip_tags', true);
    }

    /**
     * Clean HTML content and extract meaningful text
     */
    public function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return $this->htmlConverter->convert($html);
    }

}
