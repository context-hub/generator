<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Action\Resources\Service\JsonSchemaService;
use Butschster\ContextGenerator\McpServer\Attribute\Resource;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use PhpMcp\Schema\Content\TextResourceContents;
use PhpMcp\Schema\Result\ReadResourceResult;
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
                uri: 'ctx://json-schema',
                mimeType: 'application/json',
                text: \json_encode($this->jsonSchema->getSimplifiedSchema()),
            ),
        ]);
    }
}
