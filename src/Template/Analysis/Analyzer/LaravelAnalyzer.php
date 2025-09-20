<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Analyzes Laravel PHP projects using the improved abstract framework analyzer
 */
final class LaravelAnalyzer extends AbstractFrameworkAnalyzer
{
    /**
     * Laravel-specific packages that indicate a Laravel project
     */
    private const array LARAVEL_PACKAGES = [
        'laravel/framework',
        'laravel/laravel',
    ];

    /**
     * Laravel-specific directories that indicate a Laravel project
     */
    private const array LARAVEL_DIRECTORIES = [
        'app',
        'database',
        'routes',
        'config',
        'resources',
        'storage',
        'bootstrap',
    ];

    /**
     * Laravel-specific files that indicate a Laravel project
     */
    private const array LARAVEL_FILES = [
        'artisan',
        '.env.example',
        'server.php',
    ];

    public function getName(): string
    {
        return 'laravel';
    }

    public function getPriority(): int
    {
        return 100; // High priority - specific framework detection should run first
    }

    protected function getFrameworkPackages(): array
    {
        return self::LARAVEL_PACKAGES;
    }

    protected function getFrameworkDirectories(): array
    {
        return self::LARAVEL_DIRECTORIES;
    }

    protected function getFrameworkFiles(): array
    {
        return self::LARAVEL_FILES;
    }

    /**
     * Laravel-specific confidence enhancements
     */
    #[\Override]
    protected function getAdditionalConfidence(
        FSPath $projectRoot,
        array $composer,
        array $existingDirectories,
    ): float {
        $additionalConfidence = 0.0;

        // Boost confidence if it has Laravel-specific config files
        $configFiles = ['app.php', 'database.php', 'auth.php'];
        $configDir = $projectRoot->join('config');

        if ($configDir->exists()) {
            $foundConfigs = 0;
            foreach ($configFiles as $configFile) {
                if ($configDir->join($configFile)->exists()) {
                    $foundConfigs++;
                }
            }

            if ($foundConfigs > 0) {
                $additionalConfidence += ($foundConfigs / \count($configFiles)) * 0.1;
            }
        }

        // Boost confidence if it has Laravel service providers
        $appDir = $projectRoot->join('app/Providers');
        if ($appDir->exists()) {
            $additionalConfidence += 0.05;
        }

        // Check for Laravel-specific blade templates
        $viewsDir = $projectRoot->join('resources/views');
        if ($viewsDir->exists()) {
            $additionalConfidence += 0.05;
        }

        return $additionalConfidence;
    }

    /**
     * Enhanced metadata with Laravel-specific information
     */
    #[\Override]
    protected function buildMetadata(FSPath $projectRoot, array $composer): array
    {
        $metadata = parent::buildMetadata($projectRoot, $composer);

        // Add Laravel-specific metadata
        $metadata['laravelVersion'] = $this->composerReader->getPackageVersion($composer, 'laravel/framework');
        $metadata['hasArtisan'] = $projectRoot->join('artisan')->exists();
        $metadata['hasEnvExample'] = $projectRoot->join('.env.example')->exists();

        // Check Laravel directory structure completeness
        $coreDirectories = ['app', 'database', 'routes', 'config'];
        $foundCoreDirectories = \array_intersect($metadata['existingDirectories'], $coreDirectories);
        $metadata['coreDirectoryCompleteness'] = \count($foundCoreDirectories) / \count($coreDirectories);

        // Laravel-specific files detection
        $metadata['laravelSpecificFiles'] = $this->detectLaravelSpecificFiles($projectRoot);

        return $metadata;
    }

    /**
     * Detect Laravel-specific files beyond the basic framework files
     */
    private function detectLaravelSpecificFiles(FSPath $projectRoot): array
    {
        $laravelFiles = [];

        // Check for Laravel Mix or Vite config
        if ($projectRoot->join('webpack.mix.js')->exists()) {
            $laravelFiles[] = 'webpack.mix.js';
        }
        if ($projectRoot->join('vite.config.js')->exists()) {
            $laravelFiles[] = 'vite.config.js';
        }

        // Check for Laravel-specific config files
        $configFiles = [
            'config/app.php',
            'config/database.php',
            'config/auth.php',
            'config/cache.php',
            'config/queue.php',
        ];

        foreach ($configFiles as $configFile) {
            if ($projectRoot->join($configFile)->exists()) {
                $laravelFiles[] = $configFile;
            }
        }

        return $laravelFiles;
    }
}
