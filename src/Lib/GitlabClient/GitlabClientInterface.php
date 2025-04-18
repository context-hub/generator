<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GitlabClient;

use Butschster\ContextGenerator\Lib\GitlabClient\Model\GitlabRepository;

/**
 * Interface for GitLab API client
 */
interface GitlabClientInterface
{
    /**
     * Get repository contents from the GitLab API
     *
     * @param GitlabRepository $repository GitLab repository
     * @param string $path Path within the repository
     * @return array<array<string, mixed>> Repository contents
     */
    public function getContents(GitlabRepository $repository, string $path = ''): array;

    /**
     * Get file content from GitLab API
     *
     * @param GitlabRepository $repository GitLab repository
     * @param string $path Path to the file
     * @return string File content
     */
    public function getFileContent(GitlabRepository $repository, string $path): string;

    /**
     * Set the GitLab API token
     *
     * @param string|null $token GitLab API token
     */
    public function setToken(?string $token): void;

    /**
     * Set the GitLab server URL
     *
     * @param string $serverUrl GitLab server URL
     */
    public function setServerUrl(string $serverUrl): void;

    /**
     * Set custom HTTP headers for API requests
     *
     * @param array<string, string> $headers Custom HTTP headers
     */
    public function setHeaders(array $headers): void;
}
