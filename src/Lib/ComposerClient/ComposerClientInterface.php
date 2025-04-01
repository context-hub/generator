<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ComposerClient;

use Butschster\ContextGenerator\Source\Composer\Exception\ComposerNotFoundException;

/**
 * Interface for clients that interact with Composer package data
 */
interface ComposerClientInterface
{
    /**
     * Load and parse composer.json file
     *
     * @param string $path Path to composer.json or directory containing it
     * @return array<string, mixed> Parsed composer.json data
     * @throws ComposerNotFoundException If composer.json can't be found or parsed
     */
    public function loadComposerData(string $path): array;

    /**
     * Try to load composer.lock file
     *
     * @param string $path Path to directory containing composer.lock
     * @return array<string, mixed>|null Parsed composer.lock data or null if not found
     */
    public function tryLoadComposerLock(string $path): ?array;

    /**
     * Get the vendor directory from composer.json or use default
     *
     * @param array<string, mixed> $composerData Parsed composer.json data
     * @param string $basePath Base path
     * @return string Vendor directory path (relative to composer.json)
     */
    public function getVendorDir(array $composerData, string $basePath): string;
}
