<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\TextResourceContents;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class GetDocumentContentResourceAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private DocumentCompiler $compiler,
    ) {}

    public function __invoke(ServerRequestInterface $request): ReadResourceResult
    {
        $path = $request->getAttribute('path');
        $this->logger->info('Getting document content', ['path' => $path]);

        $documents = $this->configLoader->load();
        $contents = [];

        foreach ($documents->getItems() as $document) {
            if ($document->outputPath === $path) {
                $contents[] = new TextResourceContents(
                    text: (string) $this->compiler->buildContent(new ErrorCollection(), $document)->content,
                    uri: 'ctx://document/' . $document->outputPath,
                    mimeType: 'text/markdown',
                );

                break;
            }
        }

        return new ReadResourceResult($contents);
    }
}
