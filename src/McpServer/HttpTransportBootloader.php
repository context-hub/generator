<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Middleware\AuthMiddleware;
use Butschster\ContextGenerator\McpServer\Middleware\ExceptionHandlerMiddleware;
use Butschster\ContextGenerator\McpServer\Middleware\LoggerMiddleware;
use GuzzleHttp\Client;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Mcp\Server\Authentication\Contract\OAuthRegisteredClientsStoreInterface;
use Mcp\Server\Authentication\Contract\OAuthServerProviderInterface;
use Mcp\Server\Authentication\Contract\OAuthTokenVerifierInterface;
use Mcp\Server\Authentication\Dto\OAuthClientInformation;
use Mcp\Server\Authentication\Dto\OAuthMetadata;
use Mcp\Server\Authentication\Dto\OAuthProtectedResourceMetadata;
use Mcp\Server\Authentication\Handler\AuthorizeHandler;
use Mcp\Server\Authentication\Handler\MetadataHandler;
use Mcp\Server\Authentication\Handler\RegisterHandler;
use Mcp\Server\Authentication\Handler\RevokeHandler;
use Mcp\Server\Authentication\Handler\TokenHandler;
use Mcp\Server\Authentication\Provider\GenericTokenVerifier;
use Mcp\Server\Authentication\Provider\ProxyEndpoints;
use Mcp\Server\Authentication\Provider\ProxyProvider;
use Mcp\Server\Authentication\Provider\TokenIntrospectionClient;
use Mcp\Server\Authentication\Provider\TokenIntrospectionConfig;
use Mcp\Server\Authentication\Router\AuthRouterOptions;
use Mcp\Server\Authentication\Router\McpAuthRouter;
use Mcp\Server\Authentication\Storage\InMemoryClientRepository;
use Mcp\Server\Transports\Middleware\CorsMiddleware;
use Mcp\Server\Transports\Middleware\ProxyAwareMiddleware;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\McpServer\MiddlewareRegistryInterface;

final class HttpTransportBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        $requestFactory = new RequestFactory();
        $streamFactory = new StreamFactory();
        $responseFactory = new ResponseFactory();
        $httpClient = new Client();

        return [
            // OAuth Token Validator
            OAuthTokenVerifierInterface::class => static fn() => new GenericTokenVerifier(
                config: TokenIntrospectionConfig::forGitHub(),
                client: new TokenIntrospectionClient(
                    httpClient: $httpClient,
                    requestFactory: $requestFactory,
                    streamFactory: $streamFactory,
                ),
            ),

            OAuthRegisteredClientsStoreInterface::class => static function (
                EnvironmentInterface $env,
            ) {
                $store = new InMemoryClientRepository();
                $store->registerClient(
                    new OAuthClientInformation(
                        clientId: $env->get('OAUTH_CLIENT_ID'),
                        clientSecret: $env->get('OAUTH_CLIENT_SECRET'),
                        clientIdIssuedAt: \time(),
                        clientSecretExpiresAt: null, // Never expires
                    ),
                );

                return $store;
            },

            // OAuth Server Provider - using proxy to external OAuth server
            OAuthServerProviderInterface::class => static fn(
                EnvironmentInterface $env,
                OAuthTokenVerifierInterface $tokenVerifier,
                OAuthRegisteredClientsStoreInterface $clientStore,
                OAuthMetadata $authMetadata,
            ) => new ProxyProvider(
                endpoints: new ProxyEndpoints(
                    authorizationUrl: $authMetadata->authorizationEndpoint,
                    tokenUrl: $authMetadata->tokenEndpoint,
                    revocationUrl: $authMetadata->revocationEndpoint,
                    registrationUrl: $authMetadata->registrationEndpoint,
                ),
                verifyAccessToken: static fn(string $token) => $tokenVerifier->verifyAccessToken($token),
                getClient: static fn(string $clientId) => $clientStore->getClient($clientId),
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                streamFactory: $streamFactory,
            ),

            AuthRouterOptions::class => static fn(
                EnvironmentInterface $env,
            ) => new AuthRouterOptions(
                issuerUrl: $env->get('OAUTH_ISSUER_URL', 'http://127.0.0.1:8090'),
                baseUrl: $env->get('OAUTH_SERVER_URL', 'http://127.0.0.1:8090'),
                resourceName: 'CTX MCP Server',
            ),

            // OAuth Metadata
            OAuthMetadata::class => static fn(
                AuthRouterOptions $options,
            ) => OAuthMetadata::forGithub(
                issuer: $options->issuerUrl,
            ),

            AuthorizeHandler::class => static fn(
                OAuthServerProviderInterface $provider,
            ) => new AuthorizeHandler(
                provider: $provider,
                responseFactory: $responseFactory,
                streamFactory: $streamFactory,
            ),
            RegisterHandler::class => static fn(
                OAuthRegisteredClientsStoreInterface $clientStore,
            ) => new RegisterHandler(
                clientsStore: $clientStore,
                responseFactory: $responseFactory,
                streamFactory: $streamFactory,
            ),
            TokenHandler::class => static fn(
                OAuthServerProviderInterface $provider,
                OAuthRegisteredClientsStoreInterface $clientStore,
            ) => new TokenHandler(
                provider: $provider,
                responseFactory: $responseFactory,
                streamFactory: $streamFactory,
            ),
            RevokeHandler::class => static fn(
                OAuthServerProviderInterface $provider,
            ) => new RevokeHandler(
                provider: $provider,
                responseFactory: $responseFactory,
                streamFactory: $streamFactory,
            ),
            MetadataHandler::class => static fn(
                AuthRouterOptions $options,
                OAuthMetadata $oauthMetadata,
            ) => new MetadataHandler(
                oauthMetadata: $oauthMetadata,
                protectedResourceMetadata: new OAuthProtectedResourceMetadata(
                    resource: $options->baseUrl ?? $oauthMetadata->issuer,
                    authorizationServers: [$oauthMetadata->issuer],
                    jwksUri: null,
                    scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
                    bearerMethodsSupported: null,
                    resourceSigningAlgValuesSupported: null,
                    resourceName: $options->resourceName,
                    resourceDocumentation: $options->serviceDocumentationUrl,
                ),
                responseFactory: $responseFactory,
                streamFactory: $streamFactory,
            ),

            // OAuth Router
            McpAuthRouter::class => static fn(
                AuthorizeHandler $authorizeHandler,
                RegisterHandler $registerHandler,
                TokenHandler $tokenHandler,
                RevokeHandler $revokeHandler,
                MetadataHandler $metadataHandler,
                OAuthTokenVerifierInterface $tokenVerifier,
            ) => new McpAuthRouter(
                authorizeHandler: $authorizeHandler,
                registerHandler: $registerHandler,
                tokenHandler: $tokenHandler,
                revokeHandler: $revokeHandler,
                metadataHandler: $metadataHandler,
                responseFactory: $responseFactory,
                streamFactory: $streamFactory,
                tokenVerifier: $tokenVerifier,
            ),
        ];
    }

    public function boot(
        MiddlewareRegistryInterface $registry,
        EnvironmentInterface $env,
        McpAuthRouter $oauthRouter,
        ExceptionHandlerMiddleware $exceptionHandler,
        LoggerMiddleware $logger,
        AuthMiddleware $authMiddleware,
    ): void {
        $convertValues = static fn(
            string|null|bool $values,
        ) => \is_string($values) ? \array_map(\trim(...), \explode(',', $values)) : [];

        // Get allowed origins from env or use wildcard
        $allowedOrigins = $convertValues($env->get('MCP_CORS_ALLOWED_ORIGINS', '*'));
        $allowedMethods = $convertValues($env->get('MCP_CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS'));
        $allowedHeaders = $convertValues($env->get('MCP_CORS_ALLOWED_HEADERS', 'Content-Type,Authorization'));

        $registry->register($logger);
        $registry->register($exceptionHandler);

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

        $registry->register($authMiddleware);
    }
}
