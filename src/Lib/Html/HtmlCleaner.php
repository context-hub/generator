<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Html;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * HTML content cleaner for URL sources
 */
final readonly class HtmlCleaner implements HtmlCleanerInterface
{
    public function __construct(
        private HtmlConverter $htmlConverter = new HtmlConverter(),
    ) {
        $this->htmlConverter->getConfig()->setOption(key: 'strip_tags', value: true);
    }

    public function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return $this->htmlConverter->convert(html: $html);
    }
}
