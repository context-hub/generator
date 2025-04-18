<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\GitlabClient\Model\GitlabRepository;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * @implements SourceFetcherInterface<GitlabSource>
 */
final readonly class GitlabSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private GitlabFinder $finder,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        #[LoggerPrefix(prefix: 'gitlab-source')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof GitlabSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof GitlabSource) {
            $errorMessage = 'Source must be an instance of GitlabSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->info('Fetching GitLab source content', [
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
        $repository = new GitlabRepository($source->repository, $source->branch);

        // Create builder
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription(), 2);

        // Determine server URL from source or default
        $serverUrl = $source->server->url;

        $builder->addDescription(
            \sprintf('Repository: %s. Branch: %s', $repository->getUrl($serverUrl), $repository->branch),
        );

        // Find files using the finder and get the FinderResult
        $this->logger?->debug('Finding files in repository', [
            'repository' => $repository->getPath(),
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

            $fileContent = $modifiersApplier->apply($file->getContents(), $path);

            $language = $this->detectLanguage($path);
            $this->logger?->debug('Adding file to content', [
                'file' => $path,
                'language' => $language,
                'contentLength' => \strlen($fileContent),
            ]);

            $builder
                ->addCodeBlock(
                    code: \trim($fileContent),
                    language: $language,
                    path: $path,
                );
        }

        $content = $builder->build();
        $this->logger?->info('GitLab source content fetched successfully', [
            'repository' => $repository->getPath(),
            'branch' => $repository->branch,
            'fileCount' => $fileCount,
            'contentLength' => \strlen($content),
        ]);

        // Return built content
        return $content;
    }

    /**
     * Detect language from file path
     */
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
