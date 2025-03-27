<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\Resource;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListResourcesAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
    ) {}

    public function __invoke(ServerRequestInterface $request): ListResourcesResult
    {
        $this->logger->info('Listing available resources');

        $documents = $this->configLoader->load();
        $resources = [
            new Resource(
                name: 'List of available contexts',
                uri: 'ctx://list',
                description: 'Returns a list of available contexts of project in document format',
                mimeType: 'text/markdown',
            ),
            new Resource(
                name: 'Json Schema of context generator',
                uri: 'ctx://json-schema',
                description: 'Returns a simplified JSON schema of the context generator',
                mimeType: 'application/json',
            ),
        ];

        foreach ($documents->getItems() as $document) {
            $resources[] = new Resource(
                name: $document->outputPath,
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
