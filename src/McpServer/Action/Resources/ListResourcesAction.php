<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\Resource;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListResourcesAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private McpItemsRegistry $registry,
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
        $documents = $this->configLoader->load();

        foreach ($documents->getItems() as $document) {
            $resources[] = new Resource(
                name: \sprintf(
                    '[%s] %s',
                    $document->outputPath,
                    $document->description,
                ),
                uri: 'ctx://document/' . $document->outputPath,
                description: \sprintf(
                    '%s. Tags: %s',
                    $document->description,
                    \implode(', ', $document->getTags()),
                ),
                mimeType: 'application/markdown',
            );
        }

        return new ListResourcesResult(resources: $resources);
    }
}
