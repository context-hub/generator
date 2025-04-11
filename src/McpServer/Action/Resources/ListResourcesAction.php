<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\McpServer\McpConfig;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\Resource;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListResourcesAction
{
    public function __construct(
        #[LoggerPrefix(prefix: 'resources.list')]
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private McpItemsRegistry $registry,
        private McpConfig $config,
    ) {}

    #[Get(path: '/resources/list', name: 'resources.list')]
    public function __invoke(ServerRequestInterface $request): ListResourcesResult
    {
        $this->logger->info('Listing available resources');

        $resources = [];

        // Get resources from registry
        foreach ($this->registry->getResources() as $resource) {
            $resources[] = $resource;
        }

        // Add document resources from config loader
        $config = new ConfigRegistryAccessor($this->configLoader->load());

        foreach ($config->getDocuments() as $document) {
            $tags = \implode(', ', $document->getTags());

            $resources[] = new Resource(
                name: $this->config->getDocumentNameFormat(
                    path: $document->outputPath,
                    description: $document->description,
                    tags: $tags,
                ),
                uri: 'ctx://document/' . $document->outputPath,
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
