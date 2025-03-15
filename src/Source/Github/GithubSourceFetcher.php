<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Log\LoggerInterface;

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
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof GithubSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof GithubSource) {
            $errorMessage = 'Source must be an instance of GithubSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->info('Fetching GitHub source content', [
            'repository' => $source->repository,
            'branch' => $source->branch,
            'hasModifiers' => !empty($source->modifiers),
            'showTreeView' => $source->showTreeView,
        ]);

        // Parse repository from string
        $this->logger?->debug('Parsing repository from string', [
            'repository' => $source->repository,
            'branch' => $source->branch,
        ]);
        $repository = GithubRepository::fromString($source->repository, $source->branch);

        // Create builder
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription(), 2)
            ->addDescription(
                \sprintf('Repository: %s. Branch: %s', $repository->getUrl(), $repository->branch),
            );

        // Find files using the finder and get the FinderResult
        $this->logger?->debug('Finding files in repository', [
            'repository' => $repository->getUrl(),
            'branch' => $repository->branch,
        ]);
        $finderResult = $this->finder->find($source);
        $fileCount = $finderResult->count();
        $this->logger?->debug('Files found in repository', [
            'fileCount' => $fileCount,
        ]);

        // Add tree view if requested
        if ($source->showTreeView) {
            $this->logger?->debug('Adding tree view to output');
            $builder->addTreeView($finderResult->treeView);
        }

        // Fetch and add the content of each file
        $this->logger?->debug('Processing repository files');
        foreach ($finderResult->files as $index => $file) {
            $path = $file->getRelativePathname();
            $this->logger?->debug('Processing file', [
                'file' => $path,
                'index' => $index + 1,
                'total' => $fileCount,
            ]);

            $fileContent = $file->getContents();
            $originalLength = \strlen((string) $fileContent);

            // Apply modifiers if available
            if (!empty($source->modifiers)) {
                $this->logger?->debug('Applying modifiers to file', [
                    'file' => $path,
                    'modifierCount' => \count($source->modifiers),
                ]);

                foreach ($source->modifiers as $modifierId) {
                    if ($this->modifiers->has($modifierId)) {
                        $modifier = $this->modifiers->get($modifierId);
                        $modifierClass = $modifier !== null ? $modifier::class : self::class;

                        if ($modifier->supports($path)) {
                            $this->logger?->debug('Applying modifier', [
                                'file' => $path,
                                'modifier' => $modifierClass,
                                'modifierId' => (string) $modifierId,
                            ]);

                            $fileContent = $modifier->modify($fileContent, $modifierId->context);

                            $this->logger?->debug('Modifier applied', [
                                'file' => $path,
                                'modifier' => $modifierClass,
                                'originalLength' => $originalLength,
                                'modifiedLength' => \strlen($fileContent),
                            ]);
                        } else {
                            $this->logger?->debug('Modifier not applicable to file', [
                                'file' => $path,
                                'modifier' => $modifierClass,
                            ]);
                        }
                    } else {
                        $this->logger?->warning('Modifier not found', [
                            'modifierId' => (string) $modifierId,
                        ]);
                    }
                }
            }

            $language = $this->detectLanguage($path);
            $this->logger?->debug('Adding file to content', [
                'file' => $path,
                'language' => $language,
                'contentLength' => \strlen((string) $fileContent),
            ]);

            $builder
                ->addCodeBlock(
                    code: \trim((string) $fileContent),
                    language: $language,
                    path: $path,
                );
        }

        $content = $builder->build();
        $this->logger?->info('GitHub source content fetched successfully', [
            'repository' => $repository->getUrl(),
            'branch' => $repository->branch,
            'fileCount' => $fileCount,
            'contentLength' => \strlen($content),
        ]);

        // Return built content
        return $content;
    }

    private function detectLanguage(string $filePath): ?string
    {
        $extension = \pathinfo($filePath, PATHINFO_EXTENSION);

        $this->logger?->debug('Detecting language for file', [
            'file' => $filePath,
            'extension' => $extension,
        ]);

        if (empty($extension)) {
            return null;
        }

        return $extension;
    }
}
