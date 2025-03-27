<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;

final readonly class DocumentCompilerFactory
{
    public function __construct(
        private FilesInterface $files,
        private HasPrefixLoggerInterface $logger,
        private SourceFetcherRegistryFactory $sourceFetcherRegistryFactory,
        private SourceModifierRegistry $modifierRegistry,
        private ContentBuilderFactory $contentBuilderFactory,
    ) {}

    public function create(
        Directories $dirs,
        ?string $githubToken = null,
    ): DocumentCompiler {
        return new DocumentCompiler(
            files: $this->files,
            parser: $this->sourceFetcherRegistryFactory->create(dirs: $dirs, githubToken: $githubToken),
            basePath: $dirs->outputPath,
            modifierRegistry: $this->modifierRegistry,
            builderFactory: $this->contentBuilderFactory,
            logger: $this->logger->withPrefix('documents'),
        );
    }
}
