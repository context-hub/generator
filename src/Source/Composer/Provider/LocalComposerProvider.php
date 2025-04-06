<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer\Provider;

use Butschster\ContextGenerator\Lib\ComposerClient\ComposerClientInterface;
use Butschster\ContextGenerator\Source\Composer\Package\ComposerPackageCollection;
use Butschster\ContextGenerator\Source\Composer\Package\ComposerPackageInfo;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class LocalComposerProvider extends AbstractComposerProvider
{
    public function __construct(
        ComposerClientInterface $client,
        private string $basePath = '.',
        LoggerInterface $logger = new NullLogger(),
    ) {
        parent::__construct($client, $logger);
    }

    /**
     * Get packages from a local Composer project
     *
     * @param string $composerPath Path to composer.json or directory containing it
     * @param bool $includeDevDependencies Whether to include dev dependencies
     * @return ComposerPackageCollection Collection of packages
     */
    public function getPackages(string $composerPath, bool $includeDevDependencies = false): ComposerPackageCollection
    {
        $this->logger->info('Getting packages from local composer project', [
            'composerPath' => $composerPath,
            'includeDevDependencies' => $includeDevDependencies,
        ]);

        // Load and parse composer.json
        $composerData = $this->client->loadComposerData($composerPath);

        // Try to load composer.lock for more accurate version information
        $lockData = $this->client->tryLoadComposerLock(\dirname($this->basePath . '/composer.json'));
        $packageVersions = $this->extractPackageVersionsFromLock($lockData, $includeDevDependencies);

        // Get vendor directory
        $vendorDir = $this->client->getVendorDir($composerData, $this->basePath);

        // Create package collection
        $packages = new ComposerPackageCollection();

        // Process regular dependencies
        $this->processPackages(
            $packages,
            $composerData['require'] ?? [],
            $packageVersions,
            $vendorDir,
        );

        // Process dev dependencies if requested
        if ($includeDevDependencies && isset($composerData['require-dev']) && \is_array($composerData['require-dev'])) {
            $this->processPackages(
                $packages,
                $composerData['require-dev'],
                $packageVersions,
                $vendorDir,
                true,
            );
        }

        $this->logger->info('Found packages', [
            'count' => $packages->count(),
        ]);

        return $packages;
    }

    /**
     * Process packages and add them to the collection
     *
     * @param ComposerPackageCollection $packages The collection to add packages to
     * @param array<string, string> $dependencies The dependencies from composer.json
     * @param array<string, array<string, mixed>> $packageVersions Versions from composer.lock
     * @param string $vendorDir Vendor directory path
     * @param bool $isDev Whether these are dev dependencies
     */
    private function processPackages(
        ComposerPackageCollection $packages,
        array $dependencies,
        array $packageVersions,
        string $vendorDir,
        bool $isDev = false,
    ): void {
        foreach ($dependencies as $packageName => $constraintVersion) {
            // Skip php and ext-* dependencies
            if ($packageName === 'php' || \str_starts_with($packageName, 'ext-')) {
                continue;
            }

            // Skip if already included in regular dependencies (for dev deps)
            if ($isDev && $packages->has($packageName)) {
                continue;
            }

            $packagePath = $this->basePath . '/' . $vendorDir . '/' . $packageName;

            if (!\is_dir($packagePath)) {
                $this->logger->warning('Package directory not found', [
                    'package' => $packageName,
                    'path' => $packagePath,
                    'isDev' => $isDev,
                ]);
                continue;
            }

            // Get actual version from lock file if available
            $version = $packageVersions[$packageName]['version'] ?? $constraintVersion;
            $extraData = $packageVersions[$packageName] ?? [];

            // Try to load package's composer.json
            try {
                $packageComposerData = $this->client->loadComposerData($packagePath);

                // If we have extra data from lock file, merge it with composer.json
                if (!empty($extraData) && isset($extraData['description'])) {
                    $packageComposerData['description'] ??= $extraData['description'];
                }

                $packages->add(
                    new ComposerPackageInfo(
                        name: $packageName,
                        path: $packagePath,
                        version: $version,
                        composerConfig: $packageComposerData,
                    ),
                );
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to load package composer.json', [
                    'package' => $packageName,
                    'path' => $packagePath,
                    'error' => $e->getMessage(),
                    'isDev' => $isDev,
                ]);

                // Add the package with minimal info
                $packages->add(
                    new ComposerPackageInfo(
                        name: $packageName,
                        path: $packagePath,
                        version: $version,
                        composerConfig: ['description' => $extraData['description'] ?? ''],
                    ),
                );
            }
        }
    }
}
