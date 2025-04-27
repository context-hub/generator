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
                $config->getPath(),
                $config->getType(),
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
            $url = $this->container->get(VariableResolver::class)->resolve($config->url);
            $headers = $this->container->get(VariableResolver::class)->resolve($config->headers);

            $this->logger->debug('Loading URL import', [
                'url' => $url,
                'headers' => \array_keys($headers),
            ]);

            // Fetch the content from the URL
            $response = $this->httpClient->getWithRedirects($url, $headers);
            $this->lastFetchTime = \time();

            if (!$response->isSuccess()) {
                throw new Exception\ImportSourceException(
                    \sprintf('Failed to fetch URL: %s (status code: %d)', $url, $response->getStatusCode()),
                );
            }

            $content = $response->getBody();

            // Determine content type and parse accordingly
            $contentType = $this->getContentType($response->getHeader('Content-Type') ?? '');
            $extension = $config->getExtension();

            // Parse content based on content type or URL extension
            $importedConfig = $this->parseContent($content, $contentType, $extension);

            // Process selective imports if specified
            return $this->processSelectiveImports($importedConfig, $config);
        } catch (\Throwable $e) {
            $this->logger->error('URL import failed', [
                'url' => $config->url,
                'error' => $e->getMessage(),
            ]);

            throw Exception\ImportSourceException::networkError(
                $config->url,
                $e->getMessage(),
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
        if (\preg_match('/^([^;]+)/', $contentTypeHeader, $matches)) {
            return \strtolower(\trim($matches[1]));
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
            return (new StringJsonReader($content))->read('');
        }

        if ($contentType === 'application/yaml' || $contentType === 'application/x-yaml') {
            return Yaml::parse($content);
        }

        // If content type not determined, try by extension
        return match ($extension) {
            'json' => (new StringJsonReader($content))->read(''),
            'yaml', 'yml' => Yaml::parse($content),
            default => throw new Exception\ImportSourceException('Unsupported content type for URL import'),
        };
    }
}
