<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\ImportConfig;
use Butschster\ContextGenerator\Config\Reader\StringJsonReader;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Import source for remote URL configurations
 */
final class UrlImportSource extends AbstractImportSource
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'url';
    }

    public function supports(ImportConfig $config): bool
    {
        // Support 'url' type or URLs that start with http/https
        $type = $config->type ?? 'local';
        if ($type === 'url') {
            return true;
        }

        return \str_starts_with($config->path, 'http://') || \str_starts_with($config->path, 'https://');
    }

    public function load(ImportConfig $config): array
    {
        if (!$this->supports($config)) {
            throw Exception\ImportSourceException::sourceNotSupported(
                $config->path,
                $config->type ?? 'unknown',
            );
        }

        try {
            $url = $config->path;
            $headers = $this->prepareHeaders($config->headers ?? []);

            $this->logger->debug('Loading URL import', [
                'url' => $url,
                'headers' => \array_keys($headers),
            ]);

            // Fetch the content from the URL
            $response = $this->httpClient->getWithRedirects($url, $headers);

            if (!$response->isSuccess()) {
                throw new Exception\ImportSourceException(
                    \sprintf('Failed to fetch URL: %s (status code: %d)', $url, $response->getStatusCode()),
                );
            }

            $content = $response->getBody();

            // Determine content type and parse accordingly
            $contentType = $this->getContentType($response->getHeader('Content-Type') ?? '');
            $extension = $this->getExtensionFromUrl($url);

            // Parse content based on content type or URL extension
            $importedConfig = $this->parseContent($content, $contentType, $extension);

            // Process selective imports if specified
            return $this->processSelectiveImports($importedConfig, $config);
        } catch (\Throwable $e) {
            $this->logger->error('URL import failed', [
                'url' => $config->path,
                'error' => $e->getMessage(),
            ]);

            throw Exception\ImportSourceException::networkError(
                $config->path,
                $e->getMessage(),
            );
        }
    }

    /**
     * Prepare request headers, resolving any environment variables
     *
     * @param array<string, string> $configHeaders
     * @return array<string, string>
     */
    private function prepareHeaders(array $configHeaders): array
    {
        $headers = [];

        foreach ($configHeaders as $name => $value) {
            // Resolve environment variables in header values
            if (\preg_match('/^\${([^}]+)}$/', $value, $matches)) {
                $envVar = $matches[1];
                $value = \getenv($envVar) ?: '';
            }

            $headers[$name] = $value;
        }

        return $headers;
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
     * Get file extension from URL
     */
    private function getExtensionFromUrl(string $url): string
    {
        // Parse URL and extract path
        $parsedUrl = \parse_url($url);
        if (!isset($parsedUrl['path'])) {
            return '';
        }

        // Get extension from path
        $extension = \pathinfo($parsedUrl['path'], PATHINFO_EXTENSION);
        return \strtolower($extension);
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
