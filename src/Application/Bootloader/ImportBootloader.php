<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Import\ImportResolver;
use Butschster\ContextGenerator\Config\Import\Source\ImportSourceProvider;
use Butschster\ContextGenerator\Config\Import\Source\Local\LocalImportSource;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
use Butschster\ContextGenerator\Config\Import\Source\Url\UrlImportSource;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
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
                VariableResolver $variables,
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

                // URL import source
                $registry->register(
                    new UrlImportSource(
                        httpClient: $httpClient,
                        variables: $variables,
                        logger: $logger->withPrefix('import-source-url'),
                    ),
                );

                return $registry;
            },

            // Import source provider
            ImportSourceProvider::class => static fn(
                ImportSourceRegistry $sourceRegistry,
                HasPrefixLoggerInterface $logger,
            ) => new ImportSourceProvider(
                sourceRegistry: $sourceRegistry,
                logger: $logger->withPrefix('import-sources'),
            ),

            // Import resolver
            ImportResolver::class => static fn(
                FilesInterface $files,
                Directories $dirs,
                ImportSourceProvider $sourceProvider,
                HasPrefixLoggerInterface $logger,
            ) => new ImportResolver(
                dirs: $dirs,
                files: $files,
                sourceProvider: $sourceProvider,
                logger: $logger->withPrefix('import-resolver'),
            ),

            // Import parser plugin
            ImportParserPlugin::class => static fn(
                ImportResolver $importResolver,
                HasPrefixLoggerInterface $logger,
            ) => new ImportParserPlugin(
                importResolver: $importResolver,
                logger: $logger->withPrefix('import-parser'),
            ),
        ];
    }

    public function boot(
        ConfigLoaderBootloader $parserRegistry,
        ImportParserPlugin $importParserPlugin,
    ): void {
        $parserRegistry->registerParserPlugin($importParserPlugin);
    }
}
