<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Context7Client;

use Butschster\ContextGenerator\Lib\Context7Client\Model\LibrarySearchResult;

interface Context7ClientInterface
{
    /**
     * Search for available documentation libraries in Context7
     *
     * @param string $query Search query for libraries
     * @param int $maxResults Maximum number of results to return (default is 2)
     * @return LibrarySearchResult Search results with count and library list
     *
     * @throws Context7ClientException If the search request fails
     */
    public function searchLibraries(string $query, int $maxResults = 2): LibrarySearchResult;

    /**
     * Fetch documentation for a specific library
     *
     * @param string $libraryId The library ID to fetch documentation for
     * @param int|null $tokens Maximum number of tokens to return (optional)
     * @param string|null $topic Specific topic to focus on (optional)
     * @return string The library documentation content
     *
     * @throws Context7ClientException If the documentation fetch fails
     */
    public function fetchLibraryDocumentation(string $libraryId, ?int $tokens = null, ?string $topic = null): string;
}
