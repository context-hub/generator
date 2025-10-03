<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Enum\RenderStrategyEnum;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\RenderStrategyFactory;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\RenderStrategyInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Fetcher for git commit diffs
 * @implements SourceFetcherInterface<GitDiffSource>
 */
final readonly class GitDiffSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private GitDiffFinder $finder,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private RenderStrategyFactory $renderStrategyFactory = new RenderStrategyFactory(),
        #[LoggerPrefix(prefix: 'commit-diff-source')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof GitDiffSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof GitDiffSource) {
            $errorMessage = 'Source must be an instance of GitDiffSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException(message: $errorMessage);
        }

        $this->logger?->info('Fetching git diff source content', [
            'description' => $source->getDescription(),
            'repository' => $source->repository,
            'commit' => $source->commit,
            'hasModifiers' => !empty($source->modifiers),
        ]);

        // Use the finder to get the diffs
        $this->logger?->debug('Finding git diffs', [
            'repository' => $source->repository,
            'commit' => $source->commit,
        ]);

        try {
            $finderResult = $this->finder->find(source: $source);

            // Extract diffs from the finder result
            $this->logger?->debug('Extracting diffs from finder result');
            $diffs = $this->extractDiffsFromFinderResult(finderResult: $finderResult);
            $this->logger?->debug('Diffs extracted', ['diffCount' => \count(value: $diffs)]);

            // Format the output using the specified render strategy
            $this->logger?->debug('Using render strategy to format output', [
                'strategy' => $source->renderConfig->strategy,
            ]);

            $content = $this->renderOutput(diffs: $diffs, treeView: $finderResult->treeView, source: $source);

            $this->logger?->info('Git diff source content fetched successfully', [
                'diffCount' => \count(value: $diffs),
                'contentLength' => \strlen(string: $content),
                'renderStrategy' => $source->renderConfig->strategy,
            ]);

            return $content;
        } catch (\Throwable $e) {
            $this->logger?->error('Error fetching git diff content', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract diffs from the finder result
     *
     * @return array<string, array{file: string, diff: string, stats: string}>
     */
    private function extractDiffsFromFinderResult(FinderResult $finderResult): array
    {
        $diffs = [];
        $fileCount = $finderResult->count();
        $this->logger?->debug('Processing files for diff extraction', ['fileCount' => $fileCount]);

        foreach ($finderResult->files as $index => $file) {
            if (!$file instanceof SplFileInfo) {
                $this->logger?->warning('Skipping non-SplFileInfo file', [
                    'fileType' => $file::class,
                    'index' => $index,
                ]);
                continue;
            }

            // Get the original path and diff content
            $originalPath = \method_exists(object_or_class: $file, method: 'getOriginalPath')
                ? $file->getOriginalPath()
                : $file->getRelativePathname();

            $this->logger?->debug('Processing diff file', [
                'file' => $originalPath,
                'index' => $index + 1,
                'total' => $fileCount,
            ]);

            $diffContent = $file->getContents();

            // Get the stats for this file
            $stats = '';
            if (\method_exists(object_or_class: $file, method: 'getStats')) {
                $this->logger?->debug('Getting stats from file method');
                $stats = $file->getStats();
            } else {
                $this->logger?->debug('Extracting stats from diff content');
                // Try to extract stats from the diff content
                \preg_match(pattern: '/^(.*?)(?=diff --git)/s', subject: $diffContent, matches: $matches);
                if (!empty($matches[1])) {
                    $stats = \trim(string: $matches[1]);
                    $this->logger?->debug('Stats extracted from diff content');
                } else {
                    $this->logger?->debug('No stats found in diff content');
                }
            }

            $diffs[$originalPath] = [
                'file' => $originalPath,
                'diff' => $diffContent,
                'stats' => $stats,
            ];

            $this->logger?->debug('Diff processed', [
                'file' => $originalPath,
                'diffLength' => \strlen(string: $diffContent),
                'hasStats' => !empty($stats),
            ]);
        }

        $this->logger?->debug('All diffs extracted', ['diffCount' => \count(value: $diffs)]);
        return $diffs;
    }

    /**
     * Render the diffs using the specified strategy
     *
     * @param array<string, array{file: string, diff: string, stats: string}> $diffs
     */
    private function renderOutput(array $diffs, string $treeView, GitDiffSource $source): string
    {
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory->create();

        try {
            // Handle empty diffs case
            if (empty($diffs)) {
                $this->logger?->info('No diffs found for commit range', ['commit' => $source->commit]);
                $builder
                    ->addTitle(title: "Git Diff for Commit Range: {$source->commit}")
                    ->addText(text: "No changes found in this commit range.");

                return $builder->build();
            }

            $builder
                ->addTitle(title: "Git Diff for Commit Range: {$source->commit}")
                ->addTitle(title: $source->getDescription(), level: 2)
                ->addTitle(title: "Summary of Changes", level: 2)
                ->addTreeView(treeView: $treeView);

            // Get the appropriate render strategy
            $strategy = $this->getRenderStrategy(strategy: $source->renderConfig->strategy);

            // Use the strategy to render the output
            $content = $builder
                ->merge(builder: $strategy->render($diffs, $source->renderConfig))
                ->build();

            $this->logger?->debug('Output rendered using strategy', [
                'contentLength' => \strlen(string: $content),
            ]);

            return $content;
        } catch (\Throwable $e) {
            // Log the error and fall back to raw rendering if there's an issue with the strategy
            $this->logger?->error('Error using render strategy, falling back to raw rendering', [
                'strategy' => $source->renderConfig->strategy,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $builder->build();
        }
    }

    /**
     * Get the appropriate render strategy for the given strategy name
     */
    private function getRenderStrategy(RenderStrategyEnum $strategy): RenderStrategyInterface
    {
        return $this->renderStrategyFactory->create(strategy: $strategy);
    }
}
