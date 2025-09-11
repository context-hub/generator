<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient;

use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;

final readonly class HttpResponse
{
    public function __construct(
        private int $statusCode,
        private string $body,
        private array $headers = [],
    ) {}

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $normalizedName = \strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (\strtolower((string) $key) === $normalizedName) {
                return $value;
            }
        }

        return null;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirect(): bool
    {
        return $this->statusCode === 301 || $this->statusCode === 302 || $this->statusCode === 307 || $this->statusCode === 308;
    }

    /**
     * Parse the response body as JSON and return the decoded data
     *
     * @param bool $assoc When true, returns the data as associative arrays instead of objects
     * @param int $depth Maximum nesting depth of the JSON structure
     * @param int $options Bitmask of JSON decode options
     *
     * @return mixed The decoded JSON data
     *
     * @throws HttpException If the response body contains invalid JSON
     */
    public function getJson(bool $assoc = true, int $depth = 512, int $options = 0): mixed
    {
        $data = \json_decode($this->body, $assoc, $depth, $options);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(
                \sprintf(
                    'Failed to parse JSON response: %s',
                    \json_last_error_msg(),
                ),
            );
        }

        return $data;
    }

    /**
     * Get a specific value from the JSON response using a key or path
     *
     * @param string $key The key or dot-notation path to retrieve
     * @param mixed $default The default value to return if the key doesn't exist
     *
     * @return mixed The value at the specified key/path or the default value
     *
     * @throws HttpException If the response body contains invalid JSON
     */
    public function getJsonValue(string $key, mixed $default = null): mixed
    {
        $data = $this->getJson(true);

        if (!\str_contains($key, '.')) {
            return $data[$key] ?? $default;
        }

        // Handle dot notation for nested values
        $segments = \explode('.', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Check if the JSON response contains a specific key or path
     *
     * @param string $key The key or dot-notation path to check
     *
     * @return bool True if the key/path exists, false otherwise
     *
     * @throws HttpException If the response body contains invalid JSON
     */
    public function hasJsonKey(string $key): bool
    {
        $data = $this->getJson(true);

        if (!\str_contains($key, '.')) {
            return \array_key_exists($key, $data);
        }

        // Handle dot notation for nested values
        $segments = \explode('.', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }
}
