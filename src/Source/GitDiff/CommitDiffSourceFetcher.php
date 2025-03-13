<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;
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
     */
    public function __construct(
        private SourceModifierRegistry $modifiers,
        private FinderInterface $finder = new CommitDiffFinder(),
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
    ) {}

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof CommitDiffSource;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof CommitDiffSource) {
            throw new \InvalidArgumentException('Source must be an instance of CommitDiffSource');
        }

        // Ensure the repository exists
        if (!\is_dir($source->repository)) {
            throw new \RuntimeException(\sprintf('Git repository "%s" does not exist', $source->repository));
        }

        // Use the finder to get the diffs
        $finderResult = $this->finder->find($source);

        // Extract diffs from the finder result
        $diffs = $this->extractDiffsFromFinderResult($finderResult);

        // Format the output
        return $this->formatOutput($diffs, $finderResult->treeView, $source);
    }

    /**
     * Extract diffs from the finder result
     *
     * @return array<string, array{file: string, diff: string, stats: string}>
     */
    private function extractDiffsFromFinderResult(FinderResult $finderResult): array
    {
        $diffs = [];
        foreach ($finderResult->files as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            // Get the original path and diff content
            $originalPath = \method_exists($file, 'getOriginalPath')
                ? $file->getOriginalPath()
                : $file->getRelativePathname();
            $diffContent = $file->getContents();

            // Get the stats for this file
            $stats = '';
            if (\method_exists($file, 'getStats')) {
                $stats = $file->getStats();
            } else {
                // Try to extract stats from the diff content
                \preg_match('/^(.*?)(?=diff --git)/s', $diffContent, $matches);
                if (!empty($matches[1])) {
                    $stats = \trim($matches[1]);
                }
            }

            $diffs[$originalPath] = [
                'file' => $originalPath,
                'diff' => $diffContent,
                'stats' => $stats,
            ];
        }
        return $diffs;
    }

    /**
     * Format the diffs for output
     *
     * @param array<string, array{file: string, diff: string, stats: string}> $diffs
     */
    private function formatOutput(
        array $diffs,
        string $treeView,
        CommitDiffSource $source,
    ): string {
        $builder = $this->builderFactory->create();

        // Handle empty diffs case
        if (empty($diffs)) {
            $builder
                ->addTitle("Git Diff for Commit Range: {$source->commit}", 1)
                ->addText("No changes found in this commit range.");

            return $builder->build();
        }

        // Add a header with the commit range
        $builder
            ->addTitle("Git Diff for Commit Range: {$source->commit}", 1)
            ->addTitle($source->getDescription(), 2)
            ->addTitle("Summary of Changes", 2)
            ->addTreeView($treeView);

        // Add each diff
        foreach ($diffs as $file => $diffData) {
            // Add stats if requested
            if ($source->showStats && !empty($diffData['stats'])) {
                $builder
                    ->addTitle("Stats for {$file}", 2)
                    ->addCodeBlock($diffData['stats']);
            }

            // Add the diff
            $builder
                ->addTitle("Diff for {$file}", 2)
                ->addCodeBlock($diffData['diff'], 'diff');

            // Apply modifiers if available
            if (!empty($source->modifiers)) {
                foreach ($source->modifiers as $modifierId) {
                    if ($this->modifiers->has($modifierId)) {
                        $modifier = $this->modifiers->get($modifierId);
                        if ($modifier->supports($file)) {
                            $context = [
                                'file' => $file,
                                'source' => $source,
                            ];
                            // Note: This approach may need to be revised since we're now using ContentBuilder
                            // and not directly manipulating the content string
                            $content = $builder->build();
                            $content = $modifier->modify($content, $context);
                            // Create a new builder with the modified content
                            $builder = ContentBuilder::create();
                            $builder->addText($content);
                        }
                    }
                }
            }
        }

        return $builder->build();
    }
}
