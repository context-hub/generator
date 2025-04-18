<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GitlabClient\Model;

/**
 * GitLab repository model
 */
final readonly class GitlabRepository
{
    public string $projectId;

    /**
     * Create a new GitLab repository
     *
     * @param string $repository Repository name in format "group/project"
     * @param string $branch Branch name
     */
    public function __construct(
        public string $repository,
        public string $branch = 'main',
    ) {
        // Convert repository name to URL-encoded project ID for API calls
        $this->projectId = \urlencode($repository);
    }

    /**
     * Get the full repository URL (without server URL)
     */
    public function getPath(): string
    {
        return $this->repository;
    }

    /**
     * Get the full repository URL (for display purposes)
     *
     * @param string $serverUrl Base GitLab server URL
     */
    public function getUrl(string $serverUrl = 'https://gitlab.com'): string
    {
        return \rtrim($serverUrl, '/') . '/' . $this->repository;
    }
}
