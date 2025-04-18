<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Lib\GitlabClient\GitlabClient;
use Butschster\ContextGenerator\Lib\GitlabClient\GitlabClientInterface;
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
