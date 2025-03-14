<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;

/**
 * Fetcher for GitHub repository sources
 *
 * @implements SourceFetcherInterface<GithubSource>
 */
final readonly class GithubSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private FinderInterface $finder,
        private SourceModifierRegistry $modifiers,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
    ) {}

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof GithubSource;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof GithubSource) {
            throw new \InvalidArgumentException('Source must be an instance of GithubSource');
        }

        // Parse repository from string
        $repository = GithubRepository::fromString($source->repository, $source->branch);

        // Create builder
        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription(), 2)
            ->addDescription(
                \sprintf('Repository: %s. Branch: %s', $repository->getUrl(), $repository->branch),
            );

        // Find files using the finder and get the FinderResult
        $finderResult = $this->finder->find($source);

        // Add tree view if requested
        if ($source->showTreeView) {
            $builder->addTreeView($finderResult->treeView);
        }

        // Fetch and add the content of each file
        foreach ($finderResult->files as $file) {
            $fileContent = $file->getContents();

            $path = $file->getRelativePathname();

            // Apply modifiers if available
            if (!empty($source->modifiers)) {
                foreach ($source->modifiers as $modifierId) {
                    if ($this->modifiers->has($modifierId)) {
                        $modifier = $this->modifiers->get($modifierId);
                        if ($modifier->supports($path)) {
                            $fileContent = $modifier->modify($fileContent, $modifierId->context);
                        }
                    }
                }
            }

            $builder
                ->addCodeBlock(
                    code: \trim((string) $fileContent),
                    language: $this->detectLanguage($path),
                    path: $path,
                );
        }

        // Return built content
        return $builder->build();
    }

    private function detectLanguage(string $filePath): ?string
    {
        $extension = \pathinfo($filePath, PATHINFO_EXTENSION);

        if (empty($extension)) {
            return null;
        }

        return $extension;
    }
}
