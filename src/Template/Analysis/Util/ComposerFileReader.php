<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Util;

use Butschster\ContextGenerator\Application\FSPath;
use Spiral\Files\FilesInterface;

/**
 * Utility for reading and parsing composer.json files
 */
final readonly class ComposerFileReader
{
    public function __construct(
        private FilesInterface $files,
    ) {}

    /**
     * Read and parse composer.json from project root
     */
    public function readComposerFile(FSPath $projectRoot): ?array
    {
        $composerPath = $projectRoot->join('composer.json');

        if (!$composerPath->exists()) {
            return null;
        }

        $content = $this->files->read($composerPath->toString());

        if ($content === '') {
            return null;
        }

        $decoded = \json_decode($content, true);

        if (!\is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Check if a package is present in composer dependencies
     */
    public function hasPackage(array $composer, string $packageName): bool
    {
        // Check in require section
        if (isset($composer['require']) && \array_key_exists($packageName, $composer['require'])) {
            return true;
        }

        // Check in require-dev section
        if (isset($composer['require-dev']) && \array_key_exists($packageName, $composer['require-dev'])) {
            return true;
        }

        return false;
    }

    /**
     * Get package version from composer dependencies
     */
    public function getPackageVersion(array $composer, string $packageName): ?string
    {
        return $composer['require'][$packageName] ?? $composer['require-dev'][$packageName] ?? null;
    }

    /**
     * Get all packages from composer file
     */
    public function getAllPackages(array $composer): array
    {
        $packages = [];

        if (isset($composer['require'])) {
            $packages = \array_merge($packages, $composer['require']);
        }

        if (isset($composer['require-dev'])) {
            $packages = \array_merge($packages, $composer['require-dev']);
        }

        return $packages;
    }
}
