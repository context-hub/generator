<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer\Provider;

use Butschster\ContextGenerator\Source\Composer\Package\ComposerPackageCollection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Composite provider that aggregates multiple Composer package providers
 */
final class CompositeComposerProvider implements ComposerProviderInterface
{
    /** @var array<ComposerProviderInterface> */
    private array $providers = [];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        ComposerProviderInterface ...$providers,
    ) {
        foreach ($providers as $provider) {
            $this->addProvider(provider: $provider);
        }
    }

    /**
     * Add a provider to the composite
     */
    public function addProvider(ComposerProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Get packages from all registered providers
     *
     * @param string $composerPath Path to composer.json or directory containing it
     * @param bool $includeDevDependencies Whether to include dev dependencies
     * @return ComposerPackageCollection Collection of packages from all providers
     */
    public function getPackages(string $composerPath, bool $includeDevDependencies = false): ComposerPackageCollection
    {
        $this->logger->info('Getting packages from composite provider', [
            'composerPath' => $composerPath,
            'includeDevDependencies' => $includeDevDependencies,
            'providerCount' => \count(value: $this->providers),
        ]);

        // If no providers, return empty collection
        if (empty($this->providers)) {
            $this->logger->warning('No providers registered in composite provider');
            return new ComposerPackageCollection();
        }

        // Create a combined collection
        $combinedPackages = new ComposerPackageCollection();

        // Try each provider
        $successCount = 0;
        $errorCount = 0;

        foreach ($this->providers as $index => $provider) {
            try {
                $this->logger->debug('Fetching packages from provider', [
                    'providerIndex' => $index,
                    'providerClass' => $provider::class,
                ]);

                $packages = $provider->getPackages($composerPath, $includeDevDependencies);

                // Merge packages into combined collection, new packages override existing ones
                foreach ($packages as $packageName => $package) {
                    if ($combinedPackages->has(name: $packageName)) {
                        $this->logger->debug('Package already exists in collection, overriding', [
                            'package' => $packageName,
                        ]);
                    }
                    $combinedPackages->add(package: $package);
                }

                $this->logger->info('Successfully fetched packages from provider', [
                    'providerIndex' => $index,
                    'providerClass' => $provider::class,
                    'packageCount' => $packages->count(),
                ]);

                $successCount++;
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to fetch packages from provider', [
                    'providerIndex' => $index,
                    'providerClass' => $provider::class,
                    'error' => $e->getMessage(),
                ]);

                $errorCount++;
            }
        }

        $this->logger->info('Completed fetching packages from all providers', [
            'successCount' => $successCount,
            'errorCount' => $errorCount,
            'totalPackages' => $combinedPackages->count(),
        ]);

        return $combinedPackages;
    }
}
