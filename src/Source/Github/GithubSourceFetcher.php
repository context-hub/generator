<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for GitHub repository sources
 *
 * @implements SourceFetcherInterface<GithubSource>
 */
final readonly class GithubSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private GithubFinder $finder,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        #[LoggerPrefix(prefix: 'github-source')]
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

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof GithubSource) {
            $errorMessage = 'Source must be an instance of GithubSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException(message: $errorMessage);
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
        $repository = new GithubRepository(repository: $source->repository, branch: $source->branch);

        // Create builder
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addTitle(title: $source->getDescription(), level: 2)
            ->addDescription(
                description: \sprintf('Repository: %s. Branch: %s', $repository->getUrl(), $repository->branch),
            );

        // Find files using the finder and get the FinderResult
        $this->logger?->debug('Finding files in repository', [
            'repository' => $repository->getUrl(),
            'branch' => $repository->branch,
        ]);
        $finderResult = $this->finder->find(source: $source);
        $fileCount = $finderResult->count();
        $this->logger?->debug('Files found in repository', [
            'fileCount' => $fileCount,
        ]);

        // Add tree view if requested
        if ($source->showTreeView) {
            $this->logger?->debug('Adding tree view to output');
            $builder->addTreeView(treeView: $finderResult->treeView);
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

            $fileContent = $modifiersApplier->apply($file->getContents(), $path);

            $language = $this->detectLanguage(filePath: $path);
            $this->logger?->debug('Adding file to content', [
                'file' => $path,
                'language' => $language,
                'contentLength' => \strlen(string: $fileContent),
            ]);

            $builder
                ->addCodeBlock(
                    code: \trim(string: $fileContent),
                    language: $language,
                    path: $path,
                );
        }

        $content = $builder->build();
        $this->logger?->info('GitHub source content fetched successfully', [
            'repository' => $repository->getUrl(),
            'branch' => $repository->branch,
            'fileCount' => $fileCount,
            'contentLength' => \strlen(string: $content),
        ]);

        // Return built content
        return $content;
    }

    private function detectLanguage(string $filePath): ?string
    {
        $extension = \pathinfo(path: $filePath, flags: PATHINFO_EXTENSION);

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
