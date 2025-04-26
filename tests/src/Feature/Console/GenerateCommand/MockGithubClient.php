<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Lib\GithubClient\ReleaseManager;

final class MockGithubClient implements GithubClientInterface
{
    /** @var array<string, array<string, array>> Repository content by repository/path */
    private array $directoryContents = [];

    /** @var array<string, array<string, string>> File contents by repository/path */
    private array $fileContents = [];

    /** @var string|null The last used token */
    private ?string $token = null;

    public function getContents(GithubRepository $repository, string $path = ''): array
    {
        $repoKey = $repository->repository . '@' . $repository->branch;

        // Default empty response
        return $this->directoryContents[$repoKey][$path] ?? [];
    }

    public function getFileContent(GithubRepository $repository, string $path): string
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

    public function getReleaseManager(GithubRepository $repository): ReleaseManager
    {
        throw new \RuntimeException('ReleaseManager is not implemented in this mock');
    }

    /**
     * Add a mock file to the repository
     *
     * @param string $repository Repository in owner/repo format
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
     * @param string $repository Repository in owner/repo format
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
}
