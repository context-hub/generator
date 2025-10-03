<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

/**
 * Builds platform-specific binary names based on current environment.
 */
final readonly class BinaryNameBuilder
{
    /**
     * Builds a platform-specific binary filename.
     *
     * @param string $baseName The base name of the binary (e.g., 'ctx')
     * @param string $version The version (e.g., '1.0.0')
     * @param string $type The type of binary ('bin' or 'phar')
     * @return string The constructed filename
     * @throws \RuntimeException If platform or architecture detection fails
     */
    public function buildPlatformSpecificName(string $baseName, string $version, string $type): string
    {
        if ($type === 'phar') {
            return $this->buildGenericName(baseName: $baseName, type: $type);
        }

        try {
            $platform = $this->detectPlatform();
            $architecture = $this->detectArchitecture();

            $extension = ($platform->isWindows() && $type === 'bin') ? $platform->extension() : '';

            return match ($type) {
                'bin' => \sprintf(
                    "%s-%s-%s-%s%s",
                    $baseName,
                    $version,
                    $platform->value,
                    $architecture->value,
                    $extension,
                ),
                default => throw new \InvalidArgumentException(message: 'Invalid type provided: ' . $type),
            };
        } catch (\Throwable $e) {
            throw new \RuntimeException(message: "Failed to build platform-specific binary name: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Builds a generic (legacy) binary filename.
     *
     * @param string $baseName The base name of the binary (e.g., 'ctx')
     * @param string $type The type of binary ('bin' or 'phar')
     * @return string The constructed filename
     */
    public function buildGenericName(string $baseName, string $type): string
    {
        return match ($type) {
            'phar' => "{$baseName}.phar",
            'bin' => $baseName,
            default => throw new \InvalidArgumentException(message: 'Invalid type provided: ' . $type),
        };
    }

    /**
     * Detects the current platform (linux, darwin, windows).
     */
    private function detectPlatform(): Platform
    {
        return Platform::detect();
    }

    /**
     * Detects the current architecture (amd64, arm64).
     */
    private function detectArchitecture(): Architecture
    {
        return Architecture::detect();
    }
}
