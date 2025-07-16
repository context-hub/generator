<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Content;

use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\FileMessageContentException;

/**
 * Loads content from HTTP/HTTPS URLs using the built-in HTTP client.
 */
final readonly class UrlFileContentProvider implements FileContentProvider
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function canHandle(string $source): bool
    {
        $url = \filter_var($source, \FILTER_VALIDATE_URL);

        if ($url === false) {
            return false;
        }

        $scheme = \parse_url($url, \PHP_URL_SCHEME);
        return \in_array($scheme, ['http', 'https'], true);
    }

    public function load(string $source): string
    {
        if (!\filter_var($source, \FILTER_VALIDATE_URL)) {
            throw FileMessageContentException::invalidUrl($source);
        }

        try {
            $response = $this->httpClient->getWithRedirects($source, [
                'User-Agent' => 'ContextGenerator/1.0',
                'Accept' => 'text/plain, text/markdown, text/*, */*',
            ]);

            if (!$response->isSuccess()) {
                throw FileMessageContentException::urlLoadFailed(
                    $source,
                    \sprintf('HTTP %d', $response->getStatusCode()),
                );
            }

            $content = $response->getBody();

            if (empty(\trim($content))) {
                throw FileMessageContentException::emptyContent($source);
            }

            return $content;
        } catch (HttpException $e) {
            throw FileMessageContentException::urlLoadFailed($source, $e->getMessage());
        }
    }
}
