<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\CommitGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Git\GitClientInterface;
use Butschster\ContextGenerator\Source\GitDiff\Git\GitClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating the appropriate Git source for a given commit reference
 */
final readonly class GitSourceFactory
{
    /**
     * @param array<GitSourceInterface> $sources List of available Git sources
     * @param GitClientInterface $gitClient Git client for executing commands
     * @param LoggerInterface $logger PSR Logger instance
     */
    public function __construct(
        private array $sources = [],
        private GitClientInterface $gitClient = new GitClient(),
        private LoggerInterface $logger = new NullLogger(),
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
        $this->logger->debug('Finding Git source for commit reference', [
            'commitReference' => $commitReference,
        ]);

        // Find the first source that supports this reference
        foreach ($this->sources as $source) {
            if ($source->supports($commitReference)) {
                $this->logger->debug('Found supporting Git source', [
                    'source' => $source::class,
                    'commitReference' => $commitReference,
                ]);
                return $source;
            }
        }

        $this->logger->info('No specific Git source found, using default CommitGitSource', [
            'commitReference' => $commitReference,
        ]);

        // If no specific source is found, return a default source
        // This would be a simple commit reference
        return new CommitGitSource($this->gitClient, $this->logger);
    }
}
