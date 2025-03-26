<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Psr\Log\LoggerInterface;

final readonly class DocumentCompilerConfig
{
    /**
     * @param string $outputPath Base path for document output
     * @param string $rootPath Root path for source files
     * @param FilesInterface $files File system interface
     * @param SourceFetcherRegistry $sourceFetcherRegistry Source fetcher registry
     * @param SourceModifierRegistry $modifierRegistry Source modifier registry
     * @param ContentBuilderFactory $builderFactory Content builder factory
     * @param HasPrefixLoggerInterface&LoggerInterface $logger Logger instance
     * @param array<string, mixed> $additionalOptions Additional compiler options
     */
    public function __construct(
        public string $outputPath,
        public string $rootPath,
        public FilesInterface $files,
        public SourceFetcherRegistry $sourceFetcherRegistry,
        public SourceModifierRegistry $modifierRegistry,
        public ContentBuilderFactory $builderFactory,
        public LoggerInterface $logger,
        public array $additionalOptions = [],
    ) {}
}
