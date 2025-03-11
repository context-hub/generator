<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Fetcher\Git\CommitDiffFinder;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\Source\CommitDiffSource;
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
        $content = '';
        // Handle empty diffs case
        if (empty($diffs)) {
            return "# Git Diff for Commit Range: {$source->commit}\n\nNo changes found in this commit range.\n";
        }

        // Add a header with the commit range
        $content .= "# Git Diff for Commit Range: {$source->commit}\n\n";

        // Add a tree view summary of changed files
        $content .= "## Summary of Changes\n\n";
        $content .= "```\n";
        $content .= $treeView;
        $content .= "```\n\n";

        // Add each diff
        foreach ($diffs as $file => $diffData) {
            // Add stats if requested
            if ($source->showStats && !empty($diffData['stats'])) {
                $content .= "## Stats for {$file}\n\n";
                $content .= "```\n{$diffData['stats']}\n```\n\n";
            }

            // Add the diff
            $content .= "## Diff for {$file}\n\n";
            $content .= "```diff\n{$diffData['diff']}\n```\n\n";

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
                            $content = $modifier->modify($content, $context);
                        }
                    }
                }
            }
        }

        return $content;
    }
}
