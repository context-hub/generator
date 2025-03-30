<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\GithubClientFactory;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;

final class GitClientBootloader extends Bootloader
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
            ) => new GithubClientFactory(
                httpClient: $httpClient,
                defaultToken: $env->get('GITHUB_TOKEN'),
            ),
        ];
    }
}
