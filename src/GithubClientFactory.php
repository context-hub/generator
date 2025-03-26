<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Lib\GithubClient\GithubClient;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;

final readonly class GithubClientFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $defaultToken = null,
    ) {}

    public function create(?string $token = null): GithubClient
    {
        return new GithubClient(
            httpClient: $this->httpClient,
            token: $token ?? $this->defaultToken,
        );
    }
}
