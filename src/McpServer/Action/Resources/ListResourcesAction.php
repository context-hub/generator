<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\McpServer\McpConfig;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use PhpMcp\Schema\Resource;
use PhpMcp\Schema\Result\ListResourcesResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListResourcesAction
{
    public function __construct(
        #[LoggerPrefix(prefix: 'resources.list')]
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private ReferenceProviderInterface $provider,
        private McpConfig $config,
    ) {}

    #[Get(path: '/resources/list', name: 'resources.list')]
    public function __invoke(ServerRequestInterface $request): ListResourcesResult
    {
        $this->logger->info('Listing available resources');

        $resources = [];

        // Get resources from registry
        foreach ($this->provider->getResources() as $resource) {
            $resources[] = $resource;
        }

        // Add document resources from config loader
        $config = new ConfigRegistryAccessor(registry: $this->configLoader->load());

        foreach ($config->getDocuments() as $document) {
            $tags = \implode(separator: ', ', array: $document->getTags());

            $resources[] = new Resource(
                uri: 'ctx://document/' . $document->outputPath,
                name: $this->config->getDocumentNameFormat(
                    path: $document->outputPath,
                    description: $document->description,
                    tags: $tags,
                ),
                description: \sprintf(
                    '%s. Tags: %s',
                    $document->description,
                    $tags,
                ),
                mimeType: 'application/markdown',
            );
        }

        return new ListResourcesResult(resources: $resources);
    }
}
