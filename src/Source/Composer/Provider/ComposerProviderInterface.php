<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer\Provider;

use Butschster\ContextGenerator\Source\Composer\Package\ComposerPackageCollection;

/**
 * Interface for providers that retrieve Composer packages
 */
interface ComposerProviderInterface
{
    /**
     * Get packages from a Composer project
     *
     * @param string $composerPath Path to composer.json or directory containing it
     * @param bool $includeDevDependencies Whether to include dev dependencies
     * @return ComposerPackageCollection Collection of packages
     */
    public function getPackages(string $composerPath, bool $includeDevDependencies = false): ComposerPackageCollection;
}
