<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

interface GithubClientInterface
{
    /**
     * Get repository contents from the GitHub API
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $path Path within the repository
     * @param string $branch Repository branch or tag
     */
    public function getContents(string $owner, string $repo, string $path = '', string $branch = 'main'): array;

    /**
     * Get file content from GitHub API
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $path File path within the repository
     * @param string $branch Repository branch or tag
     * @return string File content
     */
    public function getFileContent(string $owner, string $repo, string $path, string $branch = 'main'): string;

    /**
     * Set the GitHub API token
     *
     * @param string|null $token GitHub API token
     */
    public function setToken(?string $token): void;
}
