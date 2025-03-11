<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Git;

use Butschster\ContextGenerator\Fetcher\Git\Source\CommitGitSource;

/**
 * Factory for creating the appropriate Git source for a given commit reference
 */
final readonly class GitSourceFactory
{
    /**
     * @param array<GitSourceInterface> $sources List of available Git sources
     */
    public function __construct(
        private array $sources = [],
    ) {}

    /**
     * Create a Git source for the given commit reference
     *
     * @param string $commitReference The commit reference(s)
     * @return GitSourceInterface The appropriate Git source
     * @throws \InvalidArgumentException If no source supports the given reference
     */
    public function create(string $commitReference): GitSourceInterface
    {
        return $this->createForSingleReference($commitReference);
    }

    /**
     * Create a Git source for a single commit reference
     *
     * @param string $commitReference The commit reference
     * @return GitSourceInterface The appropriate Git source
     * @throws \InvalidArgumentException If no source supports the given reference
     */
    private function createForSingleReference(string $commitReference): GitSourceInterface
    {
        // Find the first source that supports this reference
        foreach ($this->sources as $source) {
            if ($source->supports($commitReference)) {
                return $source;
            }
        }

        // If no specific source is found, return a default source
        // This would be a simple commit reference
        return new CommitGitSource();
    }
}
