<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Url;

use Butschster\ContextGenerator\Config\Import\Source\Config\FilterConfig;
use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;

/**
 * Configuration for URL imports
 */
final readonly class UrlSourceConfig implements SourceConfigInterface
{
    /**
     * Creates a new URL source configuration
     */
    public function __construct(
        public string $url,
        public int $ttl = 300,
        public array $headers = [],
        private ?FilterConfig $filter = null,
    ) {}

    /**
     * Create from an array configuration
     *
     * @param array $config Import configuration array
     * @param string $basePath Base path is ignored for URL sources
     */
    public static function fromArray(array $config, string $basePath): self
    {
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException("Source configuration must have a 'url' property");
        }

        $url = $config['url'];
        $headers = $config['headers'] ?? [];
        $filter = FilterConfig::fromArray($config['filter'] ?? null);

        // Ensure headers is an array
        if (!\is_array($headers)) {
            $headers = [];
        }

        return new self(
            url: $url,
            ttl: (int) ($config['ttl'] ?? 300),
            headers: $headers,
            filter: $filter,
        );
    }

    public function getType(): string
    {
        return 'url';
    }

    /**
     * Get the file extension from the URL
     */
    public function getExtension(): string
    {
        $parsedUrl = \parse_url($this->url);
        if (!isset($parsedUrl['path'])) {
            return '';
        }

        return \strtolower(\pathinfo($parsedUrl['path'], PATHINFO_EXTENSION));
    }

    public function getPath(): string
    {
        return $this->url;
    }

    public function getFilter(): ?FilterConfig
    {
        return $this->filter;
    }

    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->getType(),
            'url' => $this->url,
            'ttl' => $this->ttl,
            'headers' => $this->headers,
        ];

        if ($this->filter !== null && !$this->filter->isEmpty()) {
            $result['filter'] = $this->filter;
        }

        return $result;
    }
}
