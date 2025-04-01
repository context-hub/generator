<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Import\ImportResolver;
use Butschster\ContextGenerator\Config\Import\Source\ComposerImportSource;
use Butschster\ContextGenerator\Config\Import\Source\GitHubImportSource;
use Butschster\ContextGenerator\Config\Import\Source\ImportSourceProvider;
use Butschster\ContextGenerator\Config\Import\Source\LocalImportSource;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
use Butschster\ContextGenerator\Config\Import\Source\UrlImportSource;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Source\Composer\Client\ComposerClientInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;
use Spiral\Files\FilesInterface;

/**
 * Bootloader for Import-related components
 *
 * This bootloader initializes all import sources, the import registry,
 * resolver, and import parser plugin. It ensures proper dependency
 * injection between these components.
 */
#[Singleton]
final class ImportBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Import source registry with all sources registered
            ImportSourceRegistry::class => static function (
                FilesInterface $files,
                ConfigReaderRegistry $readers,
                HttpClientInterface $httpClient,
                GithubClientInterface $githubClient,
                ComposerClientInterface $composerClient,
                HasPrefixLoggerInterface $logger,
            ) {
                $registry = new ImportSourceRegistry(
                    logger: $logger->withPrefix('import-source-registry'),
                );

                // Register all import sources

                // Local import source (default)
                $registry->register(
                    new LocalImportSource(
                        files: $files,
                        readers: $readers,
                        logger: $logger->withPrefix('import-source-local'),
                    ),
                );

                // GitHub import source
                $registry->register(
                    new GitHubImportSource(
                        githubClient: $githubClient,
                        logger: $logger->withPrefix('import-source-github'),
                    ),
                );

                // Composer import source
                $registry->register(
                    new ComposerImportSource(
                        files: $files,
                        composerClient: $composerClient,
                        readers: $readers,
                        logger: $logger->withPrefix('import-source-composer'),
                    ),
                );

                // URL import source
                $registry->register(
                    new UrlImportSource(
                        httpClient: $httpClient,
                        logger: $logger->withPrefix('import-source-url'),
                    ),
                );

                return $registry;
            },

            // Import resolver
            ImportResolver::class => static function (
                FilesInterface $files,
                Directories $dirs,
                ImportSourceRegistry $sourceRegistry,
                HasPrefixLoggerInterface $logger,
            ) {
                return new ImportResolver(
                    dirs: $dirs,
                    files: $files,
                    sourceRegistry: $sourceRegistry,
                    logger: $logger->withPrefix('import-resolver'),
                );
            },

            // Import source provider
            ImportSourceProvider::class => static function (
                ImportSourceRegistry $sourceRegistry,
                HasPrefixLoggerInterface $logger,
            ) {
                return new ImportSourceProvider(
                    sourceRegistry: $sourceRegistry,
                    logger: $logger->withPrefix('import-sources'),
                );
            },

            // Import parser plugin
            ImportParserPlugin::class => static function (
                ImportResolver $importResolver,
                HasPrefixLoggerInterface $logger,
            ) {
                return new ImportParserPlugin(
                    importResolver: $importResolver,
                    logger: $logger->withPrefix('import-parser'),
                );
            },
        ];
    }

    public function boot(
        ConfigLoaderBootloader $parserRegistry,
        ImportParserPlugin $importParserPlugin,
    ): void {
        // Register import parser plugin with the registry
        $parserRegistry->registerParserPlugin($importParserPlugin);
    }
}
