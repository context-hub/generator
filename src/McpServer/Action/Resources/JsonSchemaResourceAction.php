<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Action\Resources\Service\JsonSchemaService;
use Butschster\ContextGenerator\McpServer\Attribute\Resource;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\TextResourceContents;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Resource(
    name: 'CTX app Json Schema',
    description: 'Returns a simplified JSON schema of CTX',
    uri: 'ctx://json-schema',
    mimeType: 'application/json',
)]
final readonly class JsonSchemaResourceAction
{
    public function __construct(
        #[LoggerPrefix(prefix: 'resources.ctx.json-schema')]
        private LoggerInterface $logger,
        private JsonSchemaService $jsonSchema,
    ) {}

    #[Get(path: '/resource/ctx/json-schema', name: 'resources.ctx.json-schema')]
    public function __invoke(ServerRequestInterface $request): ReadResourceResult
    {
        $this->logger->info('Getting JSON schema');

        return new ReadResourceResult([
            new TextResourceContents(
                text: \json_encode($this->jsonSchema->getSimplifiedSchema()),
                uri: 'ctx://json-schema',
                mimeType: 'application/json',
            ),
        ]);
    }
}
