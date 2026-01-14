<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php\Parser;

use Butschster\ContextGenerator\DirectoriesInterface;
use Spiral\Files\FilesInterface;

/**
 * Resolves FQCN to file paths using PSR-4 autoloading rules.
 * Uses Composer's generated autoload mappings to include vendor packages.
 */
final class NamespaceResolver
{
    /** @var array<string, list<string>> PSR-4 mappings [namespace prefix => [paths]] */
    private array $psr4Mappings = [];

    /** @var array<string, string> Project-only PSR-4 mappings for local detection */
    private array $projectMappings = [];

    private bool $initialized = false;

    public function __construct(
        private readonly FilesInterface $files,
        private readonly DirectoriesInterface $dirs,
    ) {}

    /**
     * Resolve FQCN to relative file path.
     *
     * @return string|null Relative path or null if unresolvable
     */
    public function resolve(string $fqcn): ?string
    {
        $this->ensureInitialized();

        $fqcn = \ltrim($fqcn, '\\');

        // Sort by prefix length descending for most specific match
        $prefixes = \array_keys($this->psr4Mappings);
        \usort($prefixes, static fn($a, $b) => \strlen($b) - \strlen($a));

        foreach ($prefixes as $prefix) {
            if (!\str_starts_with($fqcn, $prefix)) {
                continue;
            }

            foreach ($this->psr4Mappings[$prefix] as $basePath) {
                $relativePath = $this->fqcnToPath($fqcn, $prefix, $basePath);

                if ($relativePath !== null) {
                    return $relativePath;
                }
            }
        }

        return null;
    }

    /**
     * Check if FQCN belongs to vendor.
     */
    public function isVendor(string $fqcn): bool
    {
        $this->ensureInitialized();

        $resolvedPath = $this->resolve($fqcn);

        if ($resolvedPath === null) {
            // Unable to resolve - assume vendor/external
            return true;
        }

        return \str_starts_with($resolvedPath, 'vendor/');
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->loadProjectAutoload();
        $this->loadInstalledPackages();
        $this->initialized = true;
    }

    /**
     * Load project's own autoload from composer.json.
     */
    private function loadProjectAutoload(): void
    {
        $composerPath = (string) $this->dirs->getRootPath()->join('composer.json');

        if (!$this->files->exists($composerPath)) {
            return;
        }

        try {
            $composer = \json_decode(
                $this->files->read($composerPath),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            if (isset($composer['autoload']['psr-4'])) {
                foreach ($composer['autoload']['psr-4'] as $namespace => $path) {
                    $this->projectMappings[$namespace] = \rtrim((string) $path, '/');
                    $this->psr4Mappings[$namespace] = [\rtrim((string) $path, '/')];
                }
            }

            if (isset($composer['autoload-dev']['psr-4'])) {
                foreach ($composer['autoload-dev']['psr-4'] as $namespace => $path) {
                    $this->projectMappings[$namespace] = \rtrim((string) $path, '/');
                    $this->psr4Mappings[$namespace] = [\rtrim((string) $path, '/')];
                }
            }
        } catch (\JsonException) {
            // Ignore invalid composer.json
        }
    }

    /**
     * Load installed packages from vendor/composer/installed.json.
     */
    private function loadInstalledPackages(): void
    {
        $installedPath = (string) $this->dirs->getRootPath()->join('vendor/composer/installed.json');

        if (!$this->files->exists($installedPath)) {
            return;
        }

        try {
            $installed = \json_decode(
                $this->files->read($installedPath),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            // Handle both old format (array of packages) and new format (object with packages key)
            $packages = $installed['packages'] ?? $installed;

            foreach ($packages as $package) {
                if (!isset($package['autoload']['psr-4'])) {
                    continue;
                }

                // Get install path (relative to vendor dir)
                $installPath = $package['install-path'] ?? $package['name'];

                foreach ($package['autoload']['psr-4'] as $namespace => $paths) {
                    // Normalize paths to array
                    $pathsArray = \is_array($paths) ? $paths : [$paths];

                    $resolvedPaths = [];
                    foreach ($pathsArray as $path) {
                        $path = \trim((string) $path, '/');
                        // Build full path: vendor/{package-name}/{src-path}
                        $fullPath = $this->normalizeVendorPath($installPath, $path);
                        if ($fullPath !== null) {
                            $resolvedPaths[] = $fullPath;
                        }
                    }

                    if (!empty($resolvedPaths)) {
                        $this->psr4Mappings[$namespace] = $resolvedPaths;
                    }
                }
            }
        } catch (\JsonException) {
            // Keep project mappings
        }
    }

    /**
     * Normalize vendor path from installed.json format.
     * install-path is relative to vendor/composer, e.g. "../ctx/mcp-server"
     */
    private function normalizeVendorPath(string $installPath, string $srcPath): ?string
    {
        // install-path starts with "../" meaning relative to vendor/composer
        // So "../ctx/mcp-server" means "vendor/ctx/mcp-server"
        $installPath = \trim($installPath, '/');

        if (\str_starts_with($installPath, '../')) {
            $vendorRelative = \substr($installPath, 3); // Remove "../"
            $basePath = 'vendor/' . $vendorRelative;
        } elseif (\str_starts_with($installPath, '..')) {
            $vendorRelative = \substr($installPath, 2); // Remove ".."
            $basePath = 'vendor' . $vendorRelative;
        } else {
            $basePath = 'vendor/' . $installPath;
        }

        $srcPath = \trim($srcPath, '/');

        if ($srcPath === '' || $srcPath === '.') {
            return $basePath;
        }

        return $basePath . '/' . $srcPath;
    }

    private function fqcnToPath(string $fqcn, string $namespacePrefix, string $basePath): ?string
    {
        $relative = \substr($fqcn, \strlen($namespacePrefix));

        if ($relative === false || $relative === '') {
            return null;
        }

        $path = \str_replace('\\', '/', $relative);
        $relativePath = \rtrim($basePath, '/') . '/' . $path . '.php';

        // Verify file exists
        $fullPath = (string) $this->dirs->getRootPath()->join($relativePath);

        if (!$this->files->exists($fullPath)) {
            return null;
        }

        return $relativePath;
    }
}
