<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Import\ImportResolver;
use Butschster\ContextGenerator\Config\Import\Source\ComposerImportSource;
use Butschster\ContextGenerator\Config\Import\Source\GitHubImportSource;
use Butschster\ContextGenerator\Config\Import\Source\ImportSourceProvider;
use Butschster\ContextGenerator\Config\Import\Source\LocalImportSource;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
use Butschster\ContextGenerator\Config\Import\Source\UrlImportSource;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactory;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Parser\ParserPluginRegistry;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Config\Reader\JsonReader;
use Butschster\ContextGenerator\Config\Reader\PhpReader;
use Butschster\ContextGenerator\Config\Reader\YamlReader;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\DocumentsParserPlugin;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Modifier\Alias\AliasesRegistry;
use Butschster\ContextGenerator\Modifier\Alias\ModifierAliasesParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\Source\Registry\SourceProviderInterface;
use Butschster\ContextGenerator\SourceParserInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Config\Proxy;
use Spiral\Files\FilesInterface;

#[Singleton]
final class ConfigLoaderBootloader extends Bootloader
{
    /** @var ConfigParserPluginInterface[] */
    private array $parserPlugins = [];

    public function registerParserPlugin(ConfigParserPluginInterface $plugin): void
    {
        $this->parserPlugins[] = $plugin;
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ParserPluginRegistry::class => function (
                SourceProviderInterface $sourceProvider,
                ImportResolver $importResolver,
                HasPrefixLoggerInterface $logger,
            ) {
                $modifierResolver = new ModifierResolver(
                    aliasesRegistry: $aliases = new AliasesRegistry(),
                );

                return new ParserPluginRegistry([
                    new ModifierAliasesParserPlugin(
                        aliasesRegistry: $aliases,
                    ),
                    new DocumentsParserPlugin(
                        sources: $sourceProvider,
                        modifierResolver: $modifierResolver,
                        logger: $logger->withPrefix('documents-parser-plugin'),
                    ),
                    new ImportParserPlugin(
                        importResolver: $importResolver,
                        logger: $logger->withPrefix('import-parser'),
                    ),
                    ...$this->parserPlugins,
                ]);
            },

            ConfigurationProvider::class => static fn(
                ConfigLoaderFactoryInterface $configLoaderFactory,
                FilesInterface $files,
                Directories $dirs,
                HasPrefixLoggerInterface $logger,
            ) => new ConfigurationProvider(
                loaderFactory: $configLoaderFactory,
                files: $files,
                dirs: $dirs,
                logger: $logger->withPrefix('config-provider'),
            ),

            DocumentCompiler::class => static fn(
                FilesInterface $files,
                SourceParserInterface $parser,
                Directories $dirs,
                SourceModifierRegistry $registry,
                ContentBuilderFactory $builderFactory,
                HasPrefixLoggerInterface $logger,
            ) => new DocumentCompiler(
                files: $files,
                parser: $parser,
                basePath: $dirs->outputPath,
                modifierRegistry: $registry,
                builderFactory: $builderFactory,
                logger: $logger->withPrefix('document-compiler'),
            ),

            ConfigReaderRegistry::class => static function (
                FilesInterface $files,
                HasPrefixLoggerInterface $logger,
            ) {
                // Create readers
                $jsonReader = new JsonReader(
                    files: $files,
                    logger: $logger->withPrefix('json-reader'),
                );

                $yamlReader = new YamlReader(
                    files: $files,
                    logger: $logger->withPrefix('yaml-reader'),
                );

                $phpReader = new PhpReader(
                    files: $files,
                    logger: $logger->withPrefix('php-reader'),
                );

                return new ConfigReaderRegistry(
                    readers: [
                        'json' => $jsonReader,
                        'yaml' => $yamlReader,
                        'yml' => $yamlReader,
                        'php' => $phpReader,
                    ],
                );
            },

            ConfigLoaderFactoryInterface::class => static fn(ConfigReaderRegistry $registry, FilesInterface $files, Directories $dirs, HasPrefixLoggerInterface $logger) => new ConfigLoaderFactory(
                readers: $registry,
                files: $files,
                dirs: $dirs,
                logger: $logger->withPrefix('config-loader'),
            ),

            ImportSourceRegistry::class => static function (
                HasPrefixLoggerInterface $logger,
                FilesInterface $files,
            ) {
                $registry = new ImportSourceRegistry(
                    logger: $logger->withPrefix('import-source-registry'),
                );

                // Local import source (default)
                $registry->register(
                    new LocalImportSource(
                        files: $this->files,
                        readers: $this->readers,
                        logger: $this->getSourceLogger('local'),
                    ),
                );

                // GitHub import source
                $registry->register(
                    new GitHubImportSource(
                        githubClient: $this->githubClient,
                        logger: $this->getSourceLogger('github'),
                    ),
                );

                // Composer import source
                $registry->register(
                    new ComposerImportSource(
                        files: $this->files,
                        composerClient: $this->composerClient,
                        readers: $this->readers,
                        logger: $this->getSourceLogger('composer'),
                    ),
                );

                // URL import source
                $registry->register(
                    new UrlImportSource(
                        httpClient: $this->httpClient,
                        logger: $this->getSourceLogger('url'),
                    ),
                );

                return $registry;
            },

            ImportResolver::class => static fn(
                FilesInterface $files,
                Directories $dirs,
                ImportSourceRegistry $sourceRegistry,
            ) => new ImportResolver(
                dirs: $dirs,
                files: $files,
                sourceRegistry: $sourceRegistry,
            ),

            ImportSourceProvider::class => static function (
                ConfigReaderRegistry $readers,
                FilesInterface $files,
                ImportSourceRegistry $sourceRegistry,
                HasPrefixLoggerInterface $logger,
            ) {
                $provider = new ImportSourceProvider(
                    files: $files,
                    readers: $readers,
                    logger: $logger->withPrefix('import-sources'),
                );


                // Register all available import sources
                $provider->registerSources($sourceRegistry);

                return $provider;
            },

            ConfigLoaderInterface::class => new Proxy(
                interface: ConfigLoaderInterface::class,
            ),
        ];
    }
}
