<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Context7Client\Exception;

use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;

class Context7ClientException extends HttpException
{
    public static function searchFailed(string $query, int $statusCode): self
    {
        return new self(\sprintf('Context7 library search failed for query "%s". Server returned status code %d', $query, $statusCode));
    }

    public static function documentationFetchFailed(string $libraryId, int $statusCode): self
    {
        return new self(\sprintf('Context7 documentation fetch failed for library "%s". Server returned status code %d', $libraryId, $statusCode));
    }

    public static function rateLimited(): self
    {
        return new self('Rate limited due to too many requests. Please try again later.');
    }

    public static function unauthorized(): self
    {
        return new self('Unauthorized. Please check your API key.');
    }

    public static function libraryNotFound(string $libraryId): self
    {
        return new self(\sprintf('The library "%s" does not exist. Please try with a different library ID.', $libraryId));
    }

    public static function unexpectedResponseFormat(): self
    {
        return new self('Unexpected response format from Context7 API');
    }

    public static function noDocumentationFound(string $libraryId): self
    {
        return new self(\sprintf('No documentation found for library: %s', $libraryId));
    }
}
