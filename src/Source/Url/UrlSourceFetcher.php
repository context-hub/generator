<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Url;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Html\HtmlCleaner;
use Butschster\ContextGenerator\Lib\Html\HtmlCleanerInterface;
use Butschster\ContextGenerator\Lib\Html\SelectorContentExtractor;
use Butschster\ContextGenerator\Lib\Html\SelectorContentExtractorInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\SourceInterface;

/**
 * Fetcher for URL sources using Butschster HTTP client
 * @implements SourceFetcherInterface<UrlSource>
 */
final readonly class UrlSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param array<string, string> $defaultHeaders Default HTTP headers to use for all requests
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private array $defaultHeaders = [
            'User-Agent' => 'Context Generator Bot',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ],
        private HtmlCleanerInterface $cleaner = new HtmlCleaner(),
        private ?SelectorContentExtractorInterface $selectorExtractor = new SelectorContentExtractor(),
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
    ) {}

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof UrlSource;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof UrlSource) {
            throw new \InvalidArgumentException('Source must be an instance of UrlSource');
        }

        // Create builder
        $builder = $this->builderFactory->create();

        foreach ($source->urls as $url) {
            try {
                // Send the request
                $response = $this->httpClient->get($url, $this->defaultHeaders);
                $statusCode = $response->getStatusCode();

                if (!$response->isSuccess()) {
                    $builder
                        ->addComment("URL: {$url}")
                        ->addComment("Error: HTTP status code {$statusCode}")
                        ->addSeparator();
                    continue;
                }

                // Get the response body
                $html = $response->getBody();

                // Extract content from specific selector if defined
                if ($source->hasSelector() && $this->selectorExtractor !== null) {
                    $selector = $source->getSelector();
                    \assert(!empty($selector));
                    $contentFromSelector = $this->selectorExtractor->extract($html, $selector);
                    if (empty($html)) {
                        $builder
                            ->addComment("URL: {$url}")
                            ->addComment("Warning: Selector '{$source->getSelector()}' didn't match any content")
                            ->addSeparator();
                    } else {
                        $builder->addComment("URL: {$url} (selector: {$source->getSelector()})");
                        $html = $contentFromSelector;
                    }
                } else {
                    // Process the whole page
                    $builder->addComment("URL: {$url}");
                }
                $builder
                    ->addText($this->cleaner->clean($html))
                    ->addComment("END OF URL: {$url}")
                    ->addSeparator();
            } catch (\Throwable $e) {
                $builder
                    ->addComment("URL: {$url}")
                    ->addComment("Error: {$e->getMessage()}")
                    ->addSeparator();
            }
        }

        // Return built content
        return $builder->build();
    }
}
