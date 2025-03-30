<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Lib\GithubClient\GithubClient;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
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
