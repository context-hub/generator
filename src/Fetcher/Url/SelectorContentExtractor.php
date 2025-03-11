<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Url;

/**
 * Extracts content from HTML using CSS selectors
 */
class SelectorContentExtractor implements SelectorContentExtractorInterface
{
    /**
     * Extract content from HTML using a CSS selector
     */
    public function extract(string $html, string $selector): string
    {
        if (empty($html)) {
            return $html;
        }

        $dom = new \DOMDocument();
        \libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        \libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Convert CSS selector to XPath
        $xpathSelector = $this->cssToXPath($selector);

        $elements = $xpath->query($xpathSelector);
        if ($elements === false || $elements->length === 0) {
            return '';
        }

        $result = '';
        foreach ($elements as $element) {
            $result .= $dom->saveHTML($element) . "\n";
        }

        return $result;
    }

    /**
     * Very basic CSS to XPath converter (only handles simple selectors)
     * In a real implementation, use a proper library for this
     */
    private function cssToXPath(string $selector): string
    {
        // Handle ID selector (#id)
        if (\str_starts_with($selector, '#')) {
            return "//*[@id='" . \substr($selector, 1) . "']";
        }

        // Handle class selector (.class)
        if (\str_starts_with($selector, '.')) {
            return "//*[contains(@class, '" . \substr($selector, 1) . "')]";
        }

        // Handle element selector (div, p, etc.)
        return "//{$selector}";
    }
}
