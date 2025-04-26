<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Docs;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for Context7 documentation sources
 * @implements SourceFetcherInterface<DocsSource>
 */
#[LoggerPrefix(prefix: 'docs-source')]
final readonly class DocsSourceFetcher implements SourceFetcherInterface
{
    private const string CONTEXT7_BASE_URL = 'https://context7.com';

    /**
     * @param array<string, string> $defaultHeaders Default HTTP headers to use for all requests
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private VariableResolver $variableResolver,
        private ContentBuilderFactory $builderFactory,
        private LoggerInterface $logger,
        private array $defaultHeaders = [
            'User-Agent' => 'CTX Bot',
            'Accept' => 'text/plain',
            'Accept-Language' => 'en-US,en;q=0.9',
        ],
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof DocsSource;
        $this->logger->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof DocsSource) {
            $errorMessage = 'Source must be an instance of DocsSource';
            $this->logger->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger->info('Fetching documentation from Context7', [
            'library' => $source->library,
            'topic' => $source->topic,
            'tokens' => $source->tokens,
        ]);

        // Create builder
        $builder = $this->builderFactory
            ->create()
            ->addDescription($this->variableResolver->resolve($source->getDescription()));

        try {
            $library = $this->variableResolver->resolve($source->library);
            $topic = $this->variableResolver->resolve($source->topic);
            $tokens = $source->tokens;

            // Build the URL for Context7 API
            $url = \sprintf(
                '%s/%s/llms.txt?topic=%s&tokens=%d',
                self::CONTEXT7_BASE_URL,
                $library,
                \rawurlencode($topic),
                $tokens,
            );

            $this->logger->debug('Sending HTTP request to Context7', [
                'url' => $url,
                'headers' => $this->defaultHeaders,
            ]);

            // Send the request
            $requestHeaders = $this->variableResolver->resolve($this->defaultHeaders);
            $response = $this->httpClient->get($url, $requestHeaders);
            $statusCode = $response->getStatusCode();

            if (!$response->isSuccess()) {
                $this->logger->warning('Context7 request failed', [
                    'url' => $url,
                    'statusCode' => $statusCode,
                ]);

                $builder
                    ->addComment("Library: {$library}")
                    ->addComment("Topic: {$topic}")
                    ->addComment("Error: HTTP status code {$statusCode}")
                    ->addSeparator();
                return $builder->build();
            }

            $this->logger->debug('Context7 request successful', [
                'url' => $url,
                'statusCode' => $statusCode,
            ]);

            // Get the response body
            $content = $response->getBody();
            $contentLength = \strlen($content);

            $this->logger->debug('Received documentation content', [
                'library' => $library,
                'topic' => $topic,
                'contentLength' => $contentLength,
            ]);

            // Add metadata to the builder
            $builder
                ->addComment("Library: {$library}")
                ->addComment("Topic: {$topic}")
                ->addComment("Tokens: {$tokens}")
                ->addSeparator();

            // Apply modifiers to the content
            $processedContent = $modifiersApplier->apply($content, $url);

            // Add the processed content to the builder
            $builder->addText($processedContent);
        } catch (\Throwable $e) {
            $this->logger->error('Error retrieving documentation from Context7', [
                'library' => $source->library ?? 'unknown',
                'topic' => $source->topic ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $builder
                ->addComment("Library: {$source->library}")
                ->addComment("Topic: {$source->topic}")
                ->addComment("Error: {$e->getMessage()}")
                ->addSeparator();
        }

        $content = $builder->build();
        $this->logger->info('Documentation content fetched successfully', [
            'contentLength' => \strlen($content),
        ]);

        // Return built content
        return $content;
    }
}
