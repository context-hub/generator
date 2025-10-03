<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Html;

/**
 * Extracts content from HTML using CSS selectors
 */
final class SelectorContentExtractor implements SelectorContentExtractorInterface
{
    public function extract(string $html, string $selector): string
    {
        if (empty($html)) {
            return $html;
        }

        $dom = new \DOMDocument();
        \libxml_use_internal_errors(use_errors: true);
        $dom->loadHTML(source: $html, options: LIBXML_NOWARNING | LIBXML_NOERROR);
        \libxml_clear_errors();

        $xpath = new \DOMXPath(document: $dom);

        // Convert CSS selector to XPath
        $xpathSelector = $this->cssToXPath(selector: $selector);

        $elements = $xpath->query(expression: $xpathSelector);
        if ($elements === false || $elements->length === 0) {
            return '';
        }

        $result = '';
        foreach ($elements as $element) {
            $result .= $dom->saveHTML(node: $element) . "\n";
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
        if (\str_starts_with(haystack: $selector, needle: '#')) {
            return "//*[@id='" . \substr(string: $selector, offset: 1) . "']";
        }

        // Handle class selector (.class)
        if (\str_starts_with(haystack: $selector, needle: '.')) {
            return "//*[contains(@class, '" . \substr(string: $selector, offset: 1) . "')]";
        }

        // Handle element selector (div, p, etc.)
        return "//{$selector}";
    }
}
