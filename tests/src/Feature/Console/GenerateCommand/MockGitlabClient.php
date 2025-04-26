<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Butschster\ContextGenerator\Lib\GitlabClient\GitlabClientInterface;
use Butschster\ContextGenerator\Lib\GitlabClient\Model\GitlabRepository;

final class MockGitlabClient implements GitlabClientInterface
{
    /** @var array<string, array<string, array>> Repository content by repository/path */
    private array $directoryContents = [];

    /** @var array<string, array<string, string>> File contents by repository/path */
    private array $fileContents = [];

    /** @var string|null The last used token */
    private ?string $token = null;

    /** @var string The last used server URL */
    private string $serverUrl = 'https://gitlab.com';

    /** @var array<string, string> Custom headers */
    private array $headers = [];

    public function getContents(GitlabRepository $repository, string $path = ''): array
    {
        $repoKey = $repository->repository . '@' . $repository->branch;

        // Default empty response
        return $this->directoryContents[$repoKey][$path] ?? [];
    }

    public function getFileContent(GitlabRepository $repository, string $path): string
    {
        $repoKey = $repository->repository . '@' . $repository->branch;

        if (isset($this->fileContents[$repoKey][$path])) {
            return $this->fileContents[$repoKey][$path];
        }

        throw new \RuntimeException("File content not found for {$path} in repository {$repository->repository}");
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function setServerUrl(string $serverUrl): void
    {
        $this->serverUrl = $serverUrl;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * Add a mock file to the repository
     *
     * @param string $repository Repository in group/project format
     * @param string $path File path within the repository
     * @param string $content File content
     * @param string $branch Repository branch
     */
    public function addFile(string $repository, string $path, string $content, string $branch = 'main'): void
    {
        $repoKey = $repository . '@' . $branch;
        $this->fileContents[$repoKey][$path] = $content;
    }

    /**
     * Add a mock directory to the repository
     *
     * @param string $repository Repository in group/project format
     * @param string $path Directory path within the repository
     * @param array $files Array of file metadata
     * @param string $branch Repository branch
     */
    public function addDirectory(string $repository, string $path, array $files, string $branch = 'main'): void
    {
        $repoKey = $repository . '@' . $branch;
        $this->directoryContents[$repoKey][$path] = $files;
    }

    /**
     * Get the last used token
     */
    public function getLastUsedToken(): ?string
    {
        return $this->token;
    }

    /**
     * Get the last used server URL
     */
    public function getLastUsedServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * Get the last used headers
     */
    public function getLastUsedHeaders(): array
    {
        return $this->headers;
    }
}
