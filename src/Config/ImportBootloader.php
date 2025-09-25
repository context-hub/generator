<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Import\ImportResolver;
use Butschster\ContextGenerator\Config\Import\Source\ImportSourceProvider;
use Butschster\ContextGenerator\Config\Import\Source\Local\LocalImportSource;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
use Butschster\ContextGenerator\Config\Import\Source\Url\UrlImportSource;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;

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
                HasPrefixLoggerInterface $logger,
                LocalImportSource $localImportSource,
                UrlImportSource $urlImportSource,
            ) {
                $registry = new ImportSourceRegistry(
                    logger: $logger->withPrefix('import-source-registry'),
                );

                $registry->register($localImportSource);
                $registry->register($urlImportSource);

                return $registry;
            },

            ImportSourceProvider::class => ImportSourceProvider::class,
            ImportResolver::class => ImportResolver::class,
            ImportParserPlugin::class => ImportParserPlugin::class,
        ];
    }

    public function boot(
        ConfigLoaderBootloader $parserRegistry,
        ImportParserPlugin $importParserPlugin,
    ): void {
        $parserRegistry->registerParserPlugin($importParserPlugin);
    }
}
