<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context;

use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ContextRequestAction
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentCompiler $documentCompiler,
        private ConfigurationProviderFactory $configurationProviderFactory,
        private Directories $dirs,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->info('Handling context-request action');

        // Get the json parameter from POST body
        $parsedBody = $request->getParsedBody();
        $json = $parsedBody['json'] ?? '';

        if (empty($json)) {
            return new JsonResponse([
                'error' => 'Missing JSON parameter',
            ], 400);
        }

        try {
            $configProvider = $this->configurationProviderFactory->create($this->dirs);

            $loader = $configProvider->fromString($json);
            $documents = $loader->load()->getItems();
            $compiledDocuments = [];

            foreach ($documents as $document) {
                $compiledDocuments[$document->outputPath] = [
                    'content' => (string) $this->documentCompiler->buildContent(
                        new ErrorCollection(),
                        $document,
                    )->content,
                ];
            }

            return new JsonResponse([
                'documents' => $compiledDocuments,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing context request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
