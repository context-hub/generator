<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Source\PhpClassSource;
use Butschster\ContextGenerator\SourceInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @implements SourceFetcherInterface<PhpClassSource>
 */
final readonly class PhpFileSourceFetcher extends FileSourceFetcher
{
    public function __construct(
        string $basePath,
        FileTreeBuilder $treeBuilder = new FileTreeBuilder(),
        private PhpClassParser $classParser = new PhpClassParser(),
    ) {
        parent::__construct($basePath, $treeBuilder);
    }

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof PhpClassSource;
    }

    protected function getContent(SplFileInfo $file, SourceInterface $source): string
    {
        return \str_replace(
            ['<?php', 'declare(strict_types=1);'],
            '',
            $source->onlySignatures ? $this->classParser->parse($file->getContents()) : $file->getContents(),
        );
    }
}
