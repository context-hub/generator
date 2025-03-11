<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Fetcher\Github\GithubContentFetcherInterface;
use Butschster\ContextGenerator\Source\GithubSource;
use Butschster\ContextGenerator\Source\SourceModifierRegistry;
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
        private GithubContentFetcherInterface $contentFetcher,
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

        $content = '';

        // Find files using the finder and get the FinderResult
        $finderResult = $this->finder->find($source);

        // Add tree view if requested
        if ($source->showTreeView) {
            $content .= "```\n";
            $content .= $finderResult->treeView;
            $content .= "```\n\n";
        }

        // Fetch and add the content of each file
        foreach ($finderResult->files as $path) {
            $fileContent = $this->contentFetcher->fetchContent($source, $path);

            // Apply modifiers if available
            if (!empty($source->modifiers)) {
                foreach ($source->modifiers as $modifierId) {
                    if ($this->modifiers->has($modifierId)) {
                        $modifier = $this->modifiers->get($modifierId);
                        if ($modifier->supports($path)) {
                            $context = [
                                'path' => $path,
                                'source' => $source,
                            ];
                            $fileContent = $modifier->modify($fileContent, $context);
                        }
                    }
                }
            }

            $content .= "```\n";
            $content .= "// Path: {$path}\n";
            $content .= \trim($fileContent) . "\n\n";
            $content .= "```\n";
        }

        return $content;
    }
}