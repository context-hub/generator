<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use GuzzleHttp\Client;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Mcp\Server\Authentication\Contract\OAuthRegisteredClientsStoreInterface;
use Mcp\Server\Authentication\Contract\OAuthServerProviderInterface;
use Mcp\Server\Authentication\Contract\OAuthTokenVerifierInterface;
use Mcp\Server\Authentication\Dto\OAuthClientInformation;
use Mcp\Server\Authentication\Dto\OAuthMetadata;
use Mcp\Server\Authentication\Middleware\ClientAuthMiddleware;
use Mcp\Server\Authentication\Provider\ProxyEndpoints;
use Mcp\Server\Authentication\Provider\ProxyProvider;
use Mcp\Server\Authentication\Router\AuthRouterOptions;
use Mcp\Server\Authentication\Router\McpAuthRouter;
use Mcp\Server\Authentication\Storage\InMemoryClientRepository;
use Mcp\Server\Authentication\Storage\JwtTokenValidator;
use Mcp\Server\Transports\Middleware\CorsMiddleware;
use Mcp\Server\Transports\Middleware\ProxyAwareMiddleware;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\McpServer\MiddlewareRegistryInterface;

final class HttpTransportBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            // OAuth Token Validator
            OAuthTokenVerifierInterface::class => fn() => new JwtTokenValidator(
                publicKey: $this->getPublicKey(),
                algorithm: 'RS256',
            ),

            OAuthRegisteredClientsStoreInterface::class => static function (
                EnvironmentInterface $env,
            ) {
                $store = new InMemoryClientRepository();
                $store->registerClient(new OAuthClientInformation(
                    clientId: $env->get('OAUTH_CLIENT_ID'),
                    clientSecret: $env->get('OAUTH_CLIENT_SECRET'),
                    clientIdIssuedAt: \time(),
                    clientSecretExpiresAt: null, // Never expires
                ));

                return $store;
            },

            // OAuth Server Provider - using proxy to external OAuth server
            OAuthServerProviderInterface::class => static fn(
                EnvironmentInterface $env,
                OAuthTokenVerifierInterface $tokenVerifier,
                OAuthRegisteredClientsStoreInterface $clientStore,
            ) => new ProxyProvider(
                endpoints: new ProxyEndpoints(
                    authorizationUrl: $env->get('OAUTH_AUTHORIZATION_URL', 'https://github.com/login/oauth/authorize'),
                    tokenUrl: $env->get('OAUTH_TOKEN_URL', 'https://github.com/login/oauth/access_token'),
                    revocationUrl: $env->get('OAUTH_REVOCATION_URL'),
                    registrationUrl: $env->get('OAUTH_REGISTRATION_URL'),
                ),
                verifyAccessToken: static fn(string $token) => $tokenVerifier->verifyAccessToken($token),
                getClient: static fn(string $clientId) => $clientStore->getClient($clientId),
                httpClient: new Client(),
                requestFactory: new RequestFactory(),
                streamFactory: new StreamFactory(),
            ),

            // OAuth Metadata
            OAuthMetadata::class => static fn(EnvironmentInterface $env) => new OAuthMetadata(
                issuer: $env->get('OAUTH_ISSUER_URL', 'http://127.0.0.1:8090'),
                authorizationEndpoint: $env->get('OAUTH_AUTHORIZATION_URL', 'https://github.com/login/oauth/authorize'),
                tokenEndpoint: $env->get('OAUTH_TOKEN_URL', 'https://github.com/login/oauth/access_token'),
                responseTypesSupported: ['code'],
                registrationEndpoint: $env->get('OAUTH_REGISTRATION_URL'),
                scopesSupported: ['read', 'write'],
                grantTypesSupported: ['authorization_code', 'refresh_token'],
                tokenEndpointAuthMethodsSupported: ['client_secret_post', 'client_secret_basic'],
                codeChallengeMethodsSupported: ['S256'],
            ),

            // OAuth Router
            McpAuthRouter::class => static fn(
                EnvironmentInterface $env,
                OAuthServerProviderInterface $provider,
            ) => new McpAuthRouter(
                options: new AuthRouterOptions(
                    provider: $provider,
                    issuerUrl: $env->get('OAUTH_ISSUER_URL', 'http://127.0.0.1:8090'),
                    baseUrl: $env->get('OAUTH_SERVER_URL', 'http://127.0.0.1:8090'),
                    serviceDocumentationUrl: $env->get('OAUTH_SERVICE_DOCUMENTATION_URL'),
                    scopesSupported: ['read', 'write'],
                    resourceName: 'MCP Server',
                ),
                responseFactory: new ResponseFactory(),
                streamFactory: new StreamFactory(),
            ),

            // Client Authentication Middleware
            ClientAuthMiddleware::class => static fn(
                OAuthRegisteredClientsStoreInterface $clientStore,
            ) => new ClientAuthMiddleware(
                clientsStore: $clientStore,
                responseFactory: new ResponseFactory(),
                streamFactory: new StreamFactory(),
            ),
        ];
    }

    public function boot(
        MiddlewareRegistryInterface $registry,
        EnvironmentInterface $env,
        McpAuthRouter $oauthRouter,
        OAuthServerProviderInterface $oauthProvider,
    ): void {
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

        // Register OAuth router middleware if enabled
        if ((bool) $env->get('OAUTH_ENABLED', false)) {
            $registry->register($oauthRouter);
        }
    }

    /**
     * @return non-empty-string
     */
    private function getPublicKey(): string
    {
        return <<<PEM
        -----BEGIN PUBLIC KEY-----
        MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAMsar/ISBCpG3r+2YLiW976ou4LVWWo2
        e4n5KG72JBkGC69KpvcqIgKQCqVOXhlcM4KPOlgql/di9GFz5/QhjAcCAwEAAQ==
        -----END PUBLIC KEY-----
        PEM;
    }
}
