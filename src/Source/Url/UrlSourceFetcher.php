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
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for URL sources using Butschster HTTP client
 * @implements SourceFetcherInterface<UrlSource>
 */
final readonly class UrlSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param array<string, string> $defaultHeaders Default HTTP headers to use for all requests
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private array $defaultHeaders = [
            'User-Agent' => 'Context Generator Bot',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ],
        private VariableResolver $variableResolver = new VariableResolver(),
        private HtmlCleanerInterface $cleaner = new HtmlCleaner(),
        private ?SelectorContentExtractorInterface $selectorExtractor = new SelectorContentExtractor(),
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof UrlSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof UrlSource) {
            $errorMessage = 'Source must be an instance of UrlSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->info('Fetching URL source content', [
            'urlCount' => \count($source->urls),
            'hasSelector' => $source->hasSelector(),
        ]);

        // Create builder
        $builder = $this->builderFactory
            ->create()
            ->addDescription($this->variableResolver->resolve($source->getDescription()));

        foreach ($source->urls as $index => $url) {
            $this->logger?->debug('Processing URL', [
                'url' => $url,
                'index' => $index + 1,
                'total' => \count($source->urls),
            ]);

            try {
                $requestHeaders = $this->variableResolver->resolve(
                    \array_merge($this->defaultHeaders, $source->headers),
                );

                // Send the request
                $this->logger?->debug('Sending HTTP request', [
                    'url' => $url,
                    'headers' => $requestHeaders,
                ]);

                $response = $this->httpClient->get($url, $requestHeaders);
                $statusCode = $response->getStatusCode();

                if (!$response->isSuccess()) {
                    $this->logger?->warning('HTTP request failed', [
                        'url' => $url,
                        'statusCode' => $statusCode,
                    ]);

                    $builder
                        ->addComment("URL: {$url}")
                        ->addComment("Error: HTTP status code {$statusCode}")
                        ->addSeparator();
                    continue;
                }

                $this->logger?->debug('HTTP request successful', [
                    'url' => $url,
                    'statusCode' => $statusCode,
                ]);

                // Get the response body
                $html = $response->getBody();
                $htmlLength = \strlen($html);
                $this->logger?->debug('Received HTML content', [
                    'url' => $url,
                    'contentLength' => $htmlLength,
                ]);

                // Extract content from specific selector if defined
                if ($source->hasSelector() && $this->selectorExtractor !== null) {
                    $selector = $source->getSelector();
                    \assert(!empty($selector));

                    $this->logger?->debug('Extracting content using selector', [
                        'url' => $url,
                        'selector' => $selector,
                    ]);

                    $contentFromSelector = $this->selectorExtractor->extract($html, $selector);
                    $extractedLength = \strlen($contentFromSelector);

                    if (empty($contentFromSelector)) {
                        $this->logger?->warning('Selector did not match any content', [
                            'url' => $url,
                            'selector' => $selector,
                        ]);

                        $builder
                            ->addComment("URL: {$url}")
                            ->addComment("Warning: Selector '{$source->getSelector()}' didn't match any content")
                            ->addSeparator();
                    } else {
                        $this->logger?->debug('Content extracted using selector', [
                            'url' => $url,
                            'selector' => $selector,
                            'extractedLength' => $extractedLength,
                            'originalLength' => $htmlLength,
                        ]);

                        $builder->addComment("URL: {$url} (selector: {$source->getSelector()})");
                        $html = $contentFromSelector;
                    }
                } else {
                    // Process the whole page
                    $this->logger?->debug('Processing entire HTML page', ['url' => $url]);
                    $builder->addComment("URL: {$url}");
                }

                $this->logger?->debug('Cleaning HTML content', ['url' => $url]);
                $cleanedHtml = $modifiersApplier->apply($this->cleaner->clean($html), $url);
                $this->logger?->debug('HTML content cleaned', [
                    'url' => $url,
                    'originalLength' => \strlen($html),
                    'cleanedLength' => \strlen($cleanedHtml),
                ]);

                $builder
                    ->addText($cleanedHtml)
                    ->addComment("END OF URL: {$url}")
                    ->addSeparator();

                $this->logger?->debug('URL processed successfully', ['url' => $url]);
            } catch (\Throwable $e) {
                $this->logger?->error('Error processing URL', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $builder
                    ->addComment("URL: {$url}")
                    ->addComment("Error: {$e->getMessage()}")
                    ->addSeparator();
            }
        }

        $content = $builder->build();
        $this->logger?->info('URL source content fetched successfully', [
            'contentLength' => \strlen($content),
            'urlCount' => \count($source->urls),
        ]);

        // Return built content
        return $content;
    }
}
