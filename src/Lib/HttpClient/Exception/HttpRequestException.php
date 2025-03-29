<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient\Exception;

final class HttpRequestException extends HttpException
{
    #[\Override]
    public static function requestFailed(string $url, int $statusCode): self
    {
        return new self(\sprintf('Failed to request URL "%s". Server returned status code %d', $url, $statusCode));
    }

    #[\Override]
    public static function missingRedirectLocation(): self
    {
        return new self('Received a redirect response but no Location header was found');
    }
}
