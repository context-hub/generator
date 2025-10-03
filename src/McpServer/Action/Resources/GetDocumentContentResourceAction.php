<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use PhpMcp\Schema\Content\TextResourceContents;
use PhpMcp\Schema\Result\ReadResourceResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class GetDocumentContentResourceAction
{
    public function __construct(
        #[LoggerPrefix(prefix: 'resources.ctx.document')]
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private DocumentCompiler $compiler,
    ) {}

    #[Get(path: '/resource/ctx/document/{path:.*}', name: 'resources.ctx.document')]
    public function __invoke(ServerRequestInterface $request): ReadResourceResult
    {
        $path = $request->getAttribute('path');
        $this->logger->info('Getting document content', ['path' => $path]);

        $config = new ConfigRegistryAccessor(registry: $this->configLoader->load());
        $contents = [];

        foreach ($config->getDocuments() as $document) {
            if ($document->outputPath === $path) {
                $contents[] = new TextResourceContents(
                    uri: 'ctx://document/' . $document->outputPath,
                    mimeType: 'text/markdown',
                    text: (string) $this->compiler->buildContent(errors: new ErrorCollection(), document: $document)->content,
                );

                break;
            }
        }

        return new ReadResourceResult(contents: $contents);
    }
}
