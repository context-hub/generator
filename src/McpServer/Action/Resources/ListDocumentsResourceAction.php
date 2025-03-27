<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\TextResourceContents;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListDocumentsResourceAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
    ) {}

    #[Get(path: '/resource/ctx/list', name: 'resources.ctx.list')]
    public function __invoke(ServerRequestInterface $request): ReadResourceResult
    {
        $this->logger->info('Listing available documents');

        $documents = $this->configLoader->load();
        $contents = [];

        foreach ($documents->getItems() as $document) {
            $contents[] = new TextResourceContents(
                text: \json_encode($document),
                uri: 'ctx://document/' . $document->outputPath,
                mimeType: 'text/markdown',
            );
        }

        return new ReadResourceResult($contents);
    }
}
