<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient\Exception;

class HttpException extends \RuntimeException
{
    public static function requestFailed(string $url, int $statusCode): self
    {
        return new self(\sprintf('Failed to request URL "%s". Server returned status code %d', $url, $statusCode));
    }

    public static function missingRedirectLocation(): self
    {
        return new self('Received a redirect response but no Location header was found');
    }

    public static function clientNotAvailable(): self
    {
        return new self('HTTP client not available. Install psr/http-client implementation (like guzzlehttp/guzzle)');
    }
}
