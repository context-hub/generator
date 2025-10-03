<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Url;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Source\AbstractImportSource;
use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Import\Source\Exception;
use Butschster\ContextGenerator\Config\Reader\StringJsonReader;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Psr\Log\LoggerInterface;
use Spiral\Core\Container;
use Symfony\Component\Yaml\Yaml;

/**
 * Import source for remote URL configurations
 */
#[LoggerPrefix(prefix: 'import-source-url')]
final class UrlImportSource extends AbstractImportSource
{
    private int $lastFetchTime = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Container $container,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'url';
    }

    public function supports(SourceConfigInterface $config): bool
    {
        return $config instanceof UrlSourceConfig;
    }

    public function load(SourceConfigInterface $config): array
    {
        if (!$config instanceof UrlSourceConfig) {
            throw Exception\ImportSourceException::sourceNotSupported(
                path: $config->getPath(),
                type: $config->getType(),
            );
        }

        // Check if the URL is still valid based on TTL
        if ($this->lastFetchTime > 0 && \time() - $this->lastFetchTime < $config->ttl) {
            $this->logger->debug('Using cached URL import', [
                'url' => $config->url,
                'ttl' => $config->ttl,
            ]);

            return [];
        }

        try {
            $url = $this->container->get(id: VariableResolver::class)->resolve($config->url);
            $headers = $this->container->get(id: VariableResolver::class)->resolve($config->headers);

            $this->logger->debug('Loading URL import', [
                'url' => $url,
                'headers' => \array_keys(array: $headers),
            ]);

            // Fetch the content from the URL
            $response = $this->httpClient->getWithRedirects($url, $headers);
            $this->lastFetchTime = \time();

            if (!$response->isSuccess()) {
                throw new Exception\ImportSourceException(
                    message: \sprintf('Failed to fetch URL: %s (status code: %d)', $url, $response->getStatusCode()),
                );
            }

            $content = $response->getBody();

            // Determine content type and parse accordingly
            $contentType = $this->getContentType(contentTypeHeader: $response->getHeader(name: 'Content-Type') ?? '');
            $extension = $config->getExtension();

            // Parse content based on content type or URL extension
            $importedConfig = $this->parseContent(content: $content, contentType: $contentType, extension: $extension);

            // Process selective imports if specified
            return $this->processSelectiveImports(config: $importedConfig, sourceConfig: $config);
        } catch (\Throwable $e) {
            $this->logger->error('URL import failed', [
                'url' => $config->url,
                'error' => $e->getMessage(),
            ]);

            throw Exception\ImportSourceException::networkError(
                url: $config->url,
                message: $e->getMessage(),
            );
        }
    }

    public function allowedSections(): array
    {
        return ['prompts'];
    }

    /**
     * Extract content type from Content-Type header
     */
    private function getContentType(string $contentTypeHeader): string
    {
        // Extract main content type, e.g. 'application/json; charset=utf-8' -> 'application/json'
        if (\preg_match(pattern: '/^([^;]+)/', subject: $contentTypeHeader, matches: $matches)) {
            return \strtolower(string: \trim(string: $matches[1]));
        }

        return '';
    }

    /**
     * Parse content based on content type or file extension
     */
    private function parseContent(string $content, string $contentType, string $extension): array
    {
        // Try to determine format from content type
        if ($contentType === 'application/json') {
            return (new StringJsonReader(jsonContent: $content))->read(path: '');
        }

        if ($contentType === 'application/yaml' || $contentType === 'application/x-yaml') {
            return Yaml::parse(input: $content);
        }

        // If content type not determined, try by extension
        return match ($extension) {
            'json' => (new StringJsonReader(jsonContent: $content))->read(path: ''),
            'yaml', 'yml' => Yaml::parse(input: $content),
            default => throw new Exception\ImportSourceException(message: 'Unsupported content type for URL import'),
        };
    }
}
