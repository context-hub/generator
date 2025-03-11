<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Fetcher\Url\HtmlCleaner;
use Butschster\ContextGenerator\Fetcher\Url\HtmlCleanerInterface;
use Butschster\ContextGenerator\Fetcher\Url\SelectorContentExtractor;
use Butschster\ContextGenerator\Fetcher\Url\SelectorContentExtractorInterface;
use Butschster\ContextGenerator\Source\UrlSource;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Fetcher for URL sources using PSR-compatible HTTP client
 * @implements SourceFetcherInterface<UrlSource>
 */
final readonly class UrlSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param array<string, string> $defaultHeaders Default HTTP headers to use for all requests
     */
    public function __construct(
        private ?ClientInterface $httpClient = null,
        private ?RequestFactoryInterface $requestFactory = null,
        private ?UriFactoryInterface $uriFactory = null,
        private array $defaultHeaders = [
            'User-Agent' => 'Context Generator Bot',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ],
        private HtmlCleanerInterface $cleaner = new HtmlCleaner(),
        private ?SelectorContentExtractorInterface $selectorExtractor = new SelectorContentExtractor(),
    ) {
        if ($this->httpClient === null || $this->requestFactory === null || $this->uriFactory === null) {
            throw new \RuntimeException('To use Url source you need to install PSR-18 HTTP client');
        }
    }

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof UrlSource;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof UrlSource) {
            throw new \InvalidArgumentException('Source must be an instance of UrlSource');
        }

        $content = '';
        foreach ($source->urls as $url) {
            try {
                // Create and send the request
                $request = $this->requestFactory->createRequest('GET', $this->uriFactory->createUri($url));
                // Add headers
                foreach ($this->defaultHeaders as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }
                // Send the request
                $response = $this->httpClient->sendRequest($request);
                $statusCode = $response->getStatusCode();
                if ($statusCode < 200 || $statusCode >= 300) {
                    $content .= "// URL: {$url}" . PHP_EOL;
                    $content .= "// Error: HTTP status code {$statusCode}" . PHP_EOL;
                    $content .= '----------------------------------------------------------' . PHP_EOL;
                    continue;
                }
                // Get the response body
                $html = (string) $response->getBody();
                // Extract content from specific selector if defined
                if ($source->hasSelector() && $this->selectorExtractor !== null) {
                    $selector = $source->getSelector();
                    \assert(!empty($selector));
                    $contentFromSelector = $this->selectorExtractor->extract($html, $selector);
                    if (empty($html)) {
                        $content .= "// URL: {$url}" . PHP_EOL;
                        $content .= "// Warning: Selector '{$source->getSelector()}' didn't match any content" . PHP_EOL;
                    } else {
                        $content .= "// URL: {$url} (selector: {$source->getSelector()})" . PHP_EOL;
                        $html = $contentFromSelector;
                    }
                } else {
                    // Process the whole page
                    $content .= "// URL: {$url}" . PHP_EOL;
                }
                $content .= $this->cleaner->clean($html) . PHP_EOL . PHP_EOL;
                $content .= "// END OF URL: {$url}" . PHP_EOL;
                $content .= '----------------------------------------------------------' . PHP_EOL;
            } catch (\Throwable $e) {
                $content .= "// URL: {$url}" . PHP_EOL;
                $content .= "// Error: {$e->getMessage()}" . PHP_EOL;
                $content .= '----------------------------------------------------------' . PHP_EOL;
            }
        }
        return $content;
    }
}