<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Psr\Log\LoggerInterface;

final readonly class GitSourceFactory
{
    /**
     * @param GitSourceInterface[] $sources Array of Git source instances
     */
    public function __construct(
        private GitSourceInterface $fallbackSource,
        #[LoggerPrefix(prefix: 'git-source-factory')]
        private ?LoggerInterface $logger = null,
        private array $sources = [],
    ) {}

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
        $this->logger?->debug('Finding Git source for commit reference', [
            'commitReference' => $commitReference,
        ]);

        // Find the first source that supports this reference
        foreach ($this->sources as $source) {
            if ($source->supports($commitReference)) {
                $this->logger?->debug('Found supporting Git source', [
                    'source' => $source::class,
                    'commitReference' => $commitReference,
                ]);
                return $source;
            }
        }

        $this->logger?->info('No specific Git source found, using fallback CommitGitSource', [
            'commitReference' => $commitReference,
        ]);

        return $this->fallbackSource;
    }
}
