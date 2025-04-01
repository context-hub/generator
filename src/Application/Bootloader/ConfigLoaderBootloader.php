<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactory;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Parser\ParserPluginRegistry;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Config\Reader\JsonReader;
use Butschster\ContextGenerator\Config\Reader\PhpReader;
use Butschster\ContextGenerator\Config\Reader\YamlReader;
use Butschster\ContextGenerator\DirectoriesInterface;
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

/**
 * Bootloader for configuration loading components
 */
#[Singleton]
final class ConfigLoaderBootloader extends Bootloader
{
    /** @var ConfigParserPluginInterface[] */
    private array $parserPlugins = [];

    #[\Override]
    public function defineDependencies(): array
    {
        return [
            ImportBootloader::class,
        ];
    }

    /**
     * Register additional parser plugins
     */
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
                ImportParserPlugin $importParserPlugin,
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
                    $importParserPlugin, // Injected from ImportBootloader
                    ...$this->parserPlugins,
                ]);
            },

            ConfigurationProvider::class => static fn(
                ConfigLoaderFactoryInterface $configLoaderFactory,
                FilesInterface $files,
                DirectoriesInterface $dirs,
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
                DirectoriesInterface $dirs,
                SourceModifierRegistry $registry,
                ContentBuilderFactory $builderFactory,
                HasPrefixLoggerInterface $logger,
            ) => new DocumentCompiler(
                files: $files,
                parser: $parser,
                basePath: $dirs->getOutputPath(),
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

            ConfigLoaderFactoryInterface::class => static fn(
                ConfigReaderRegistry $readers,
                ParserPluginRegistry $pluginRegistry,
                FilesInterface $files,
                DirectoriesInterface $dirs,
                HasPrefixLoggerInterface $logger,
            ) => new ConfigLoaderFactory(
                readers: $readers,
                pluginRegistry: $pluginRegistry,
                dirs: $dirs,
                logger: $logger->withPrefix('config-loader'),
            ),

            ConfigLoaderInterface::class => new Proxy(
                interface: ConfigLoaderInterface::class,
            ),
        ];
    }
}
