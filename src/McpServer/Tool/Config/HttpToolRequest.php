<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Config;

/**
 * Represents an HTTP request to be executed by a tool.
 */
final readonly class HttpToolRequest implements \JsonSerializable
{
    /**
     * @param string $url The URL to send the request to
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param array<string, string> $headers HTTP request headers
     * @param array<string, mixed> $query Query parameters
     * @param string|array<string, mixed>|null $body Request body (for POST, PUT, etc.)
     */
    public function __construct(
        public string $url,
        public string $method = 'GET',
        public array $headers = [],
        public array $query = [],
        public string|array|null $body = null,
    ) {}

    /**
     * Creates an HttpToolRequest from a configuration array.
     *
     * @param array<string, mixed> $config The request configuration
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    public static function fromArray(array $config, array $data): self
    {
        if (!isset($config['url']) || !\is_string($config['url'])) {
            throw new \InvalidArgumentException('HTTP request must have a non-empty "url" property');
        }

        $method = 'GET';
        if (isset($config['method'])) {
            if (!\is_string($config['method'])) {
                throw new \InvalidArgumentException('HTTP request "method" must be a string');
            }
            $method = \strtoupper($config['method']);

            // Validate method
            if (!\in_array($method, ['GET', 'POST'], true)) {
                throw new \InvalidArgumentException("Invalid HTTP method: {$method}");
            }
        }

        $headers = [];
        if (isset($config['headers']) && \is_array($config['headers'])) {
            foreach ($config['headers'] as $key => $value) {
                if (!\is_string($key) || !\is_string($value)) {
                    throw new \InvalidArgumentException('HTTP headers must be string key-value pairs');
                }
                $headers[$key] = $value;
            }
        }

        $query = [];
        $body = null;
        if ($method === 'GET') {
            $query = $data;
        } else {
            $body = $data;
        }

        return new self(
            url: $config['url'],
            method: $method,
            headers: $headers,
            query: $query,
            body: $body,
        );
    }

    /**
     * Returns the final URL with query parameters included.
     */
    public function getFullUrl(): string
    {
        if (empty($this->query)) {
            return $this->url;
        }

        $url = $this->url;
        $queryString = \http_build_query($this->query);

        // Check if URL already has query parameters
        if (\str_contains($url, '?')) {
            return $url . '&' . $queryString;
        }

        return $url . '?' . $queryString;
    }

    /**
     * Converts body to string format suitable for sending in a request.
     */
    public function getBodyAsString(): ?string
    {
        if ($this->body === null) {
            return null;
        }

        if (\is_string($this->body)) {
            return $this->body;
        }

        // Convert array to JSON
        return \json_encode($this->body) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'query' => $this->query,
            'body' => $this->body,
        ], static fn($value) => $value !== null && $value !== []);
    }
}
