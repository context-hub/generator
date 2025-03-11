<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Github;

use Butschster\ContextGenerator\Source\GithubSource;

/**
 * Interface for fetching content from GitHub repositories
 */
interface GithubContentFetcherInterface
{
    /**
     * Fetch file content from GitHub
     *
     * @param GithubSource $source The GitHub source
     * @param string $path File path within the repository
     * @return string The file content
     * @throws \RuntimeException If fetching the content fails
     */
    public function fetchContent(GithubSource $source, string $path): string;
}
