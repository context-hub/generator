<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Psr\Log\LoggerInterface;

final readonly class DocumentCompilerFactory
{
    public function __construct(
        private FilesInterface $files,
        private SourceFetcherRegistryFactory $sourceFetcherRegistryFactory,
        private ModifierRegistryFactory $modifierRegistryFactory,
        private ContentBuilderFactory $contentBuilderFactory,
    ) {}

    public function create(
        string $rootPath,
        string $outputPath,
        LoggerInterface $logger,
        ?string $githubToken = null,
        ?string $envFilePath = null,
        ?string $envFileName = null,
    ): DocumentCompiler {
        $sourceFetcherRegistry = $this->sourceFetcherRegistryFactory->create(
            rootPath: $rootPath,
            logger: $logger,
            githubToken: $githubToken,
            envFilePath: $envFilePath,
            envFileName: $envFileName,
        );

        $modifierRegistry = $this->modifierRegistryFactory->create();

        return new DocumentCompiler(
            files: $this->files,
            parser: $sourceFetcherRegistry,
            basePath: $outputPath,
            modifierRegistry: $modifierRegistry,
            builderFactory: $this->contentBuilderFactory,
            logger: $logger->withPrefix('documents'),
        );
    }
}