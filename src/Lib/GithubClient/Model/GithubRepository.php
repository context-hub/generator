<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient\Model;

final readonly class GithubRepository
{
    /**
     * @param string $repository Repository name in the format "owner/repo"
     * @param string $branch Repository branch or tag
     */
    public function __construct(
        public string $repository,
        public string $branch = 'main',
    ) {
        if (!\preg_match(pattern: '/^([^\/]+)\/([^\/]+)$/', subject: $repository)) {
            throw new \InvalidArgumentException(
                message: "Invalid repository format: $repository. Expected format: owner/repo",
            );
        }
    }

    /**
     * Get the URL for the repository
     */
    public function getUrl(): string
    {
        return \sprintf("https://github.com/%s", $this->repository);
    }
}
