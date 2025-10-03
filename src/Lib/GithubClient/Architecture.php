<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

enum Architecture: string
{
    case Amd64 = 'amd64';
    case Arm64 = 'arm64';

    public static function detect(): self
    {
        $arch = \php_uname(mode: 'm');

        return match (\strtolower(string: $arch)) {
            'x86_64', 'amd64' => self::Amd64,
            'aarch64', 'arm64' => self::Arm64,
            // Add more mappings as needed
            default => throw new \RuntimeException(message: 'Unsupported architecture: ' . $arch),
        };
    }
}
