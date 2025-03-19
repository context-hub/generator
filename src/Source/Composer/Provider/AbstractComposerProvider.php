<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer\Provider;

use Butschster\ContextGenerator\Source\Composer\Client\ComposerClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for Composer package providers
 */
abstract readonly class AbstractComposerProvider implements ComposerProviderInterface
{
    public function __construct(
        protected ComposerClientInterface $client,
        protected LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Extract package versions from composer.lock
     *
     * @param array<string, mixed>|null $lockData Parsed composer.lock data
     * @param bool $includeDevDependencies Whether to include dev dependencies
     * @return array<string, array<string, mixed>> Array of package name => version info
     */
    protected function extractPackageVersionsFromLock(?array $lockData, bool $includeDevDependencies): array
    {
        if ($lockData === null) {
            return [];
        }

        $versions = [];

        // Process regular packages
        if (isset($lockData['packages']) && \is_array($lockData['packages'])) {
            foreach ($lockData['packages'] as $package) {
                if (!isset($package['name']) || !\is_string($package['name'])) {
                    continue;
                }

                $versions[$package['name']] = [
                    'version' => $package['version'] ?? '',
                    'description' => $package['description'] ?? '',
                    'source' => $package['source'] ?? [],
                    'time' => $package['time'] ?? '',
                ];
            }
        }

        // Process dev packages if requested
        if ($includeDevDependencies && isset($lockData['packages-dev']) && \is_array($lockData['packages-dev'])) {
            foreach ($lockData['packages-dev'] as $package) {
                if (!isset($package['name']) || !\is_string($package['name'])) {
                    continue;
                }

                // Skip if already included in regular packages
                if (isset($versions[$package['name']])) {
                    continue;
                }

                $versions[$package['name']] = [
                    'version' => $package['version'] ?? '',
                    'description' => $package['description'] ?? '',
                    'source' => $package['source'] ?? [],
                    'time' => $package['time'] ?? '',
                ];
            }
        }

        return $versions;
    }
}
