<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GitlabClient;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientBootloader;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Bootloader for GitLab client
 */
final class GitlabClientBootloader extends Bootloader
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
            GitlabClientInterface::class => GitlabClient::class,
        ];
    }
}
