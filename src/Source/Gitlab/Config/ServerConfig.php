<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab\Config;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;

/**
 * Configuration for a GitLab server
 */
final readonly class ServerConfig implements \JsonSerializable
{
    private function __construct(
        public string $url,
        public ?string $token,
        public array $headers,
    ) {}

    /**
     * Create a new GitLab server configuration
     *
     * @param string $url Server URL
     * @param string|null $token API token
     * @param array<string, string> $headers Custom HTTP headers
     */
    public static function create(
        string $url,
        ?string $token = null,
        array $headers = [],
    ): self {
        return new self(
            url: $url,
            token: $token,
            headers: $headers,
        );
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('GitLab server configuration must have a "url" property');
        }

        return self::create(
            url: (string) $config['url'],
            token: isset($config['token']) ? (string) $config['token'] : null,
            headers: (array) ($config['headers'] ?? []),
        );
    }

    public function withResolvedVariables(VariableResolver $resolver): self
    {
        $resolvedHeaders = \array_map(static fn($value) => $resolver->resolve($value), $this->headers);

        return new self(
            url: $resolver->resolve($this->url),
            token: $this->token !== null ? $resolver->resolve($this->token) : null,
            headers: $resolvedHeaders,
        );
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'url' => $this->url,
            'token' => $this->token,
            'headers' => $this->headers,
        ], static fn($value) => $value !== null && (!\is_array($value) || !empty($value)));
    }
}
