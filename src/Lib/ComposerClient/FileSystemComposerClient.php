<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ComposerClient;

use Butschster\ContextGenerator\Source\Composer\Exception\ComposerNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client that interacts with Composer package data through the filesystem
 */
final readonly class FileSystemComposerClient implements ComposerClientInterface
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function loadComposerData(string $path): array
    {
        // If path is a directory, append composer.json
        if (\is_dir($path)) {
            $path = \rtrim($path, '/') . '/composer.json';
        }

        // Check if composer.json exists
        if (!\file_exists($path)) {
            $this->logger->error('composer.json not found', ['path' => $path]);
            throw ComposerNotFoundException::fileNotFound($path);
        }

        // Read composer.json
        $composerJson = \file_get_contents($path);
        if ($composerJson === false) {
            $this->logger->error('Failed to read composer.json', ['path' => $path]);
            throw ComposerNotFoundException::cannotParse($path, 'Failed to read file');
        }

        // Parse composer.json
        $composerData = \json_decode($composerJson, true);
        if (!\is_array($composerData) || \json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Failed to parse composer.json', [
                'path' => $path,
                'error' => \json_last_error_msg(),
            ]);
            throw ComposerNotFoundException::cannotParse($path, \json_last_error_msg());
        }

        return $composerData;
    }

    public function tryLoadComposerLock(string $path): ?array
    {
        $lockPath = \rtrim($path, '/') . '/composer.lock';

        if (!\file_exists($lockPath)) {
            $this->logger->info('composer.lock not found', ['path' => $lockPath]);
            return null;
        }

        // Read composer.lock
        $lockJson = \file_get_contents($lockPath);
        if ($lockJson === false) {
            $this->logger->warning('Failed to read composer.lock', ['path' => $lockPath]);
            return null;
        }

        // Parse composer.lock
        $lockData = \json_decode($lockJson, true);
        if (!\is_array($lockData) || \json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Failed to parse composer.lock', [
                'path' => $lockPath,
                'error' => \json_last_error_msg(),
            ]);
            return null;
        }

        return $lockData;
    }

    public function getVendorDir(array $composerData, string $basePath): string
    {
        // Check if composer.json has a custom vendor-dir configuration
        if (isset($composerData['config']['vendor-dir']) && \is_string($composerData['config']['vendor-dir'])) {
            return $composerData['config']['vendor-dir'];
        }

        // Check if vendor directory exists in the base path
        $defaultVendorDir = 'vendor';
        $vendorPath = $basePath . '/' . $defaultVendorDir;

        if (\is_dir($vendorPath)) {
            return $defaultVendorDir;
        }

        // If vendor directory doesn't exist, try to find it
        $possibleVendorDirs = ['vendor', 'vendors', 'lib', 'libs', 'packages', 'deps'];

        foreach ($possibleVendorDirs as $dir) {
            if (\is_dir($basePath . '/' . $dir)) {
                $this->logger->info('Found alternative vendor directory', ['directory' => $dir]);
                return $dir;
            }
        }

        // Default to 'vendor' if nothing else is found
        return $defaultVendorDir;
    }
}
