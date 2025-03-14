<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient\Exception;

final class HttpClientNotAvailableException extends HttpException
{
    public function __construct()
    {
        parent::__construct(
            'HTTP client not available. Install psr/http-client implementation (like guzzlehttp/guzzle)',
        );
    }
}
