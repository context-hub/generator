<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactory;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Parser\ParserPluginRegistry;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\DocumentsParserPlugin;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Modifier\Alias\AliasesRegistry;
use Butschster\ContextGenerator\Modifier\Alias\ModifierAliasesParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceParserInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Config\Proxy;
use Spiral\Files\FilesInterface;

final class ConfigLoaderBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ParserPluginRegistry::class => static function () {
                $modifierResolver = new ModifierResolver(
                    aliasesRegistry: $aliases = new AliasesRegistry(),
                );

                return new ParserPluginRegistry([
                    new ModifierAliasesParserPlugin(
                        aliasesRegistry: $aliases,
                    ),
                    new DocumentsParserPlugin(
                        modifierResolver: $modifierResolver,
                    ),
                ]);
            },

            ConfigurationProvider::class => static fn(
                ConfigLoaderFactoryInterface $configLoaderFactory,
                FilesInterface $files,
                Directories $dirs,
                HasPrefixLoggerInterface $logger,
                ParserPluginRegistry $pluginRegistry,
            ) => new ConfigurationProvider(
                loaderFactory: $configLoaderFactory,
                files: $files,
                dirs: $dirs,
                logger: $logger->withPrefix('config-provider'),
                parserPlugins: $pluginRegistry->getPlugins(),
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

            ConfigLoaderFactoryInterface::class => static fn(
                FilesInterface $files,
                Directories $dirs,
                HasPrefixLoggerInterface $logger,
            ) => new ConfigLoaderFactory(
                files: $files,
                dirs: $dirs,
                logger: $logger->withPrefix('config-loader'),
            ),

            ConfigLoaderInterface::class => new Proxy(
                interface: ConfigLoaderInterface::class,
            ),
        ];
    }
}
