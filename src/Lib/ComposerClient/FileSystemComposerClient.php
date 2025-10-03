<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ComposerClient;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Source\Composer\Exception\ComposerNotFoundException;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

/**
 * Client that interacts with Composer package data through the filesystem
 */
final readonly class FileSystemComposerClient implements ComposerClientInterface
{
    public function __construct(
        private FilesInterface $files,
        #[LoggerPrefix(prefix: 'composer-client')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function loadComposerData(string $path): array
    {
        // If path is a directory, append composer.json
        if (\is_dir(filename: $path)) {
            $path = \rtrim(string: $path, characters: '/') . '/composer.json';
        }

        // Check if composer.json exists
        if (!$this->files->exists($path)) {
            $this->logger?->error('composer.json not found', ['path' => $path]);
            throw ComposerNotFoundException::fileNotFound(path: $path);
        }

        // Read composer.json
        $composerJson = $this->files->read($path);

        // Parse composer.json
        $composerData = \json_decode(json: $composerJson, associative: true);
        if (!\is_array(value: $composerData) || \json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->error('Failed to parse composer.json', [
                'path' => $path,
                'error' => \json_last_error_msg(),
            ]);
            throw ComposerNotFoundException::cannotParse(path: $path, reason: \json_last_error_msg());
        }

        return $composerData;
    }

    public function tryLoadComposerLock(string $path): ?array
    {
        $lockPath = \rtrim(string: $path, characters: '/') . '/composer.lock';

        if (!$this->files->exists($lockPath)) {
            $this->logger?->info('composer.lock not found', ['path' => $lockPath]);
            return null;
        }

        // Read composer.lock
        try {
            $lockJson = $this->files->read($lockPath);
        } catch (FilesException $e) {
            $this->logger?->error('Failed to read composer.lock', [
                'path' => $lockPath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        // Parse composer.lock
        $lockData = \json_decode(json: $lockJson, associative: true);
        if (!\is_array(value: $lockData) || \json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->warning('Failed to parse composer.lock', [
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
        if (isset($composerData['config']['vendor-dir']) && \is_string(value: $composerData['config']['vendor-dir'])) {
            return $composerData['config']['vendor-dir'];
        }

        // Check if vendor directory exists in the base path
        $defaultVendorDir = 'vendor';
        $vendorPath = $basePath . '/' . $defaultVendorDir;

        if (\is_dir(filename: $vendorPath)) {
            return $defaultVendorDir;
        }

        // If vendor directory doesn't exist, try to find it
        $possibleVendorDirs = ['vendor', 'vendors', 'lib', 'libs', 'packages', 'deps'];

        foreach ($possibleVendorDirs as $dir) {
            if (\is_dir(filename: $basePath . '/' . $dir)) {
                $this->logger?->info('Found alternative vendor directory', ['directory' => $dir]);
                return $dir;
            }
        }

        // Default to 'vendor' if nothing else is found
        return $defaultVendorDir;
    }
}
