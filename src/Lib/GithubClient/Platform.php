<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

enum Platform: string
{
    case Linux = 'linux';
    case Macos = 'darwin';
    case Windows = 'windows';

    public static function detect(): self
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => self::Linux,
            'Darwin' => self::Macos,
            'Windows' => self::Windows,
            default => throw new \RuntimeException('Unsupported platform: ' . PHP_OS_FAMILY),
        };
    }

    public function isWindows(): bool
    {
        return $this === self::Windows;
    }

    public function extension(): string
    {
        return $this->isWindows() ? '.exe' : '';
    }
}
