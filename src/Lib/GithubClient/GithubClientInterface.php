<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;

interface GithubClientInterface
{
    /**
     * Get repository contents from the GitHub API
     */
    public function getContents(GithubRepository $repository, string $path = ''): array;

    /**
     * Get file content from GitHub API
     * @return string File content
     */
    public function getFileContent(GithubRepository $repository, string $path): string;

    /**
     * Set the GitHub API token
     *
     * @param string|null $token GitHub API token
     */
    public function setToken(?string $token): void;

    /**
     * Get the release manager for a repository
     *
     * @param GithubRepository $repository Repository
     * @return ReleaseManager Release manager
     */
    public function getReleaseManager(GithubRepository $repository): ReleaseManager;
}
