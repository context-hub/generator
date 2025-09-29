<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientBootloader;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;

final class GithubClientBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [
            HttpClientBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GithubClientInterface::class => static fn(
                HttpClientInterface $httpClient,
                EnvironmentInterface $env,
            ): GithubClientInterface => new GithubClient(
                httpClient: $httpClient,
                token: $env->get('GITHUB_TOKEN'),
            ),
        ];
    }
}
