<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient\Model;

final readonly class GithubRepository
{
    /**
     * @param string $owner Repository owner
     * @param string $name Repository name
     * @param string $branch Repository branch or tag
     */
    public function __construct(
        public string $owner,
        public string $name,
        public string $branch = 'main',
    ) {}

    /**
     * Create a repository from a string in the format "owner/repo"
     *
     * @param string $repository Repository string in format "owner/repo"
     * @param string $branch Repository branch or tag
     * @return self
     * @throws \InvalidArgumentException If repository string is invalid
     */
    public static function fromString(string $repository, string $branch = 'main'): self
    {
        if (!\preg_match('/^([^\/]+)\/([^\/]+)$/', $repository, $matches)) {
            throw new \InvalidArgumentException(
                "Invalid repository format: $repository. Expected format: owner/repo",
            );
        }

        return new self($matches[1], $matches[2], $branch);
    }

    /**
     * Get the full repository name in the format "owner/repo"
     */
    public function getFullName(): string
    {
        return "{$this->owner}/{$this->name}";
    }

    /**
     * Get the URL for the repository
     */
    public function getUrl(): string
    {
        return "https://github.com/{$this->owner}/{$this->name}";
    }
}
