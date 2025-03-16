<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Fetcher for git commit diffs
 * @implements SourceFetcherInterface<CommitDiffSource>
 */
final readonly class CommitDiffSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param SourceModifierRegistry $modifiers Registry of content modifiers
     * @param FinderInterface $finder Finder for filtering diffs
     * @param ContentBuilderFactory $builderFactory Factory for creating ContentBuilder instances
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private FinderInterface $finder = new CommitDiffFinder(),
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof CommitDiffSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof CommitDiffSource) {
            $errorMessage = 'Source must be an instance of CommitDiffSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->info('Fetching git diff source content', [
            'description' => $source->getDescription(),
            'repository' => $source->repository,
            'commit' => $source->commit,
            'showStats' => $source->showStats,
            'hasModifiers' => !empty($source->modifiers),
        ]);

        // Ensure the repository exists
        if (!\is_dir($source->repository)) {
            $errorMessage = \sprintf('Git repository "%s" does not exist', $source->repository);
            $this->logger?->error($errorMessage);
            throw new \RuntimeException($errorMessage);
        }

        // Use the finder to get the diffs
        $this->logger?->debug('Finding git diffs', [
            'repository' => $source->repository,
            'commit' => $source->commit,
        ]);
        $finderResult = $this->finder->find($source);

        // Extract diffs from the finder result
        $this->logger?->debug('Extracting diffs from finder result');
        $diffs = $this->extractDiffsFromFinderResult($finderResult);
        $this->logger?->debug('Diffs extracted', ['diffCount' => \count($diffs)]);

        // Format the output
        $this->logger?->debug('Formatting output');
        $content = $this->formatOutput($diffs, $finderResult->treeView, $source);

        $this->logger?->info('Git diff source content fetched successfully', [
            'diffCount' => \count($diffs),
            'contentLength' => \strlen($content),
        ]);

        return $content;
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
            $originalPath = \method_exists($file, 'getOriginalPath')
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
            if (\method_exists($file, 'getStats')) {
                $this->logger?->debug('Getting stats from file method');
                $stats = $file->getStats();
            } else {
                $this->logger?->debug('Extracting stats from diff content');
                // Try to extract stats from the diff content
                \preg_match('/^(.*?)(?=diff --git)/s', $diffContent, $matches);
                if (!empty($matches[1])) {
                    $stats = \trim($matches[1]);
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
                'diffLength' => \strlen($diffContent),
                'hasStats' => !empty($stats),
            ]);
        }

        $this->logger?->debug('All diffs extracted', ['diffCount' => \count($diffs)]);
        return $diffs;
    }

    /**
     * Format the diffs for output
     *
     * @param array<string, array{file: string, diff: string, stats: string}> $diffs
     */
    private function formatOutput(array $diffs, string $treeView, CommitDiffSource $source): string
    {
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory->create();

        // Handle empty diffs case
        if (empty($diffs)) {
            $this->logger?->info('No diffs found for commit range', ['commit' => $source->commit]);
            $builder
                ->addTitle("Git Diff for Commit Range: {$source->commit}", 1)
                ->addText("No changes found in this commit range.");

            return $builder->build();
        }

        // Add a header with the commit range
        $this->logger?->debug('Adding header and tree view to output');
        $builder
            ->addTitle("Git Diff for Commit Range: {$source->commit}", 1)
            ->addTitle($source->getDescription(), 2)
            ->addTitle("Summary of Changes", 2)
            ->addTreeView($treeView);

        // Add each diff
        $this->logger?->debug('Processing diffs for output', ['diffCount' => \count($diffs)]);
        foreach ($diffs as $file => $diffData) {
            $this->logger?->debug('Adding diff to output', ['file' => $file]);

            // Add stats if requested
            if ($source->showStats && !empty($diffData['stats'])) {
                $this->logger?->debug('Adding stats for file', ['file' => $file]);
                $builder
                    ->addTitle("Stats for {$file}", 2)
                    ->addCodeBlock($diffData['stats']);
            }

            // Add the diff
            $this->logger?->debug('Adding diff content for file', [
                'file' => $file,
                'diffLength' => \strlen($diffData['diff']),
            ]);
            $builder
                ->addTitle("Diff for {$file}", 2)
                ->addCodeBlock($diffData['diff'], 'diff');

            // Apply modifiers if available
            if (!empty($source->modifiers)) {
                $this->logger?->debug('Applying modifiers to diff', [
                    'file' => $file,
                    'modifierCount' => \count($source->modifiers),
                ]);
            }
        }

        $content = $builder->build();
        $this->logger?->debug('Output formatted', ['contentLength' => \strlen($content)]);
        return $content;
    }
}
