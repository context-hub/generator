<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

use Symfony\Component\Finder\SplFileInfo;

/**
 * Filter items based on their content
 * @implements FilterInterface<SplFileInfo>
 */
final class ContentsFilter extends AbstractFilter
{
    public function __construct(
        private readonly string|array|null $contains = null,
        private readonly string|array|null $notContains = null,
    ) {}

    public function apply(array $items): array
    {
        if (empty($this->contains) && empty($this->notContains)) {
            return $items;
        }

        return \array_filter($items, function (SplFileInfo $item): bool {
            if ($item->isDir()) {
                return true;
            }

            try {
                // Fetch content (this would need to be implemented based on your source type)
                $content = $item->getContents();

                // Check "contains" patterns
                if (!empty($this->contains) && !$this->contentContains($content, $this->contains)) {
                    return false;
                }

                // Check "notContains" patterns
                if (!empty($this->notContains) && $this->contentContains($content, $this->notContains)) {
                    return false;
                }

                return true;
            } catch (\Throwable) {
                // Log the error or handle it as needed
                return false;
            }
        });
    }

    /**
     * Check if content contains any of the specified patterns
     *
     * @param string $content The content to check
     * @param string|array<string> $patterns Pattern(s) to look for
     * @return bool Whether any pattern was found
     */
    private function contentContains(string $content, string|array $patterns): bool
    {
        $patternArray = \is_array($patterns) ? $patterns : [$patterns];

        foreach ($patternArray as $pattern) {
            if (\str_contains($content, $pattern)) {
                return true;
            }

            // Also check if it's a regex pattern
            if (FileHelper::isRegex($pattern)) {
                if (\preg_match($pattern, $content)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Fetch content for a GitHub item
     *
     * @param array<string, mixed> $item GitHub API item
     * @return string File content
     * @throws \RuntimeException If content cannot be fetched
     */
    private function fetchContent(array $item): string
    {
        // For GitHub files, the content URL is in the "download_url" property
        $url = $item['download_url'] ?? null;

        if (!$url) {
            throw new \RuntimeException("No download URL found for item");
        }

        $request = $this->requestFactory->createRequest('GET', $url);

        // Add authentication if token is provided
        if ($this->githubToken) {
            $request = $request->withHeader('Authorization', 'token ' . $this->githubToken);
        }

        // Add user agent
        $request = $request->withHeader('User-Agent', 'Context-Generator-Bot');

        // Send the request
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("Failed to fetch content: " . $response->getReasonPhrase());
        }

        return $response->getBody()->getContents();
    }
}