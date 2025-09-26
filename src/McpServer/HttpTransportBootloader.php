<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Mcp\Server\Transports\Middleware\CorsMiddleware;
use Mcp\Server\Transports\Middleware\ProxyAwareMiddleware;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\McpServer\MiddlewareRegistryInterface;

final class HttpTransportBootloader extends Bootloader
{
    public function boot(MiddlewareRegistryInterface $registry, EnvironmentInterface $env): void
    {
        $convertValues = static fn(
            string|null|bool $values,
        ) => \is_string($values) ? \array_map(\trim(...), \explode(',', $values)) : [];

        // Get allowed origins from env or use wildcard
        $allowedOrigins = $convertValues($env->get('MCP_CORS_ALLOWED_ORIGINS', '*'));
        $allowedMethods = $convertValues($env->get('MCP_CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS'));
        $allowedHeaders = $convertValues($env->get('MCP_CORS_ALLOWED_HEADERS', 'Content-Type,Authorization'));

        $registry->register(
            new CorsMiddleware(
                allowedOrigins: $allowedOrigins,
                allowedMethods: $allowedMethods,
                allowedHeaders: $allowedHeaders,
            ),
        );

        $registry->register(
            new ProxyAwareMiddleware(
                trustProxy: (bool) $env->get('MCP_TRUST_PROXY', true),
            ),
        );
    }
}
