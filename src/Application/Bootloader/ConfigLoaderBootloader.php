<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Import\Merger\ConfigMergerInterface;
use Butschster\ContextGenerator\Config\Import\Merger\ConfigMergerProviderInterface;
use Butschster\ContextGenerator\Config\Import\Merger\ConfigMergerRegistry;
use Butschster\ContextGenerator\Config\Import\Merger\VariablesConfigMerger;
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
use Butschster\ContextGenerator\Document\DocumentConfigMerger;
use Butschster\ContextGenerator\Document\DocumentsParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\AliasesRegistry;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Config\Proxy;
use Spiral\Core\FactoryInterface;
use Spiral\Files\FilesInterface;

/**
 * Bootloader for configuration loading components
 */
#[Singleton]
final class ConfigLoaderBootloader extends Bootloader
{
    /** @var ConfigParserPluginInterface[] */
    private array $parserPlugins = [];

    /** @var ConfigMergerInterface[] */
    private array $mergers = [];

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

    /**
     * Register additional config merger
     */
    public function registerMerger(ConfigMergerInterface $merger): void
    {
        $this->mergers[] = $merger;
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            AliasesRegistry::class => AliasesRegistry::class,
            ModifierResolver::class => ModifierResolver::class,
            ParserPluginRegistry::class => fn(
                ImportParserPlugin $importParserPlugin,
                DocumentsParserPlugin $documentsParserPlugin,
            ) => new ParserPluginRegistry([
                // todo: think about priority when registering plugins
                ...$this->parserPlugins,
                $documentsParserPlugin,
                $importParserPlugin,
            ]),

            DocumentCompiler::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
            ) => $factory->make(DocumentCompiler::class, [
                'basePath' => (string) $dirs->getOutputPath(),
            ]),

            ConfigReaderRegistry::class => static fn(
                FilesInterface $files,
                JsonReader $jsonReader,
                YamlReader $yamlReader,
                PhpReader $phpReader,
            ) => new ConfigReaderRegistry(
                readers: [
                    'json' => $jsonReader,
                    'yaml' => $yamlReader,
                    'yml' => $yamlReader,
                    'php' => $phpReader,
                ],
            ),

            ConfigLoaderFactoryInterface::class => ConfigLoaderFactory::class,
            ConfigLoaderInterface::class => new Proxy(
                interface: ConfigLoaderInterface::class,
            ),

            ConfigMergerProviderInterface::class => function (
                ConfigMergerRegistry $registry,
            ) {
                foreach ($this->mergers as $merger) {
                    $registry->register($merger);
                }

                return $registry;
            },
        ];
    }

    public function init(
        DocumentConfigMerger $documentConfigMerger,
        VariablesConfigMerger $variablesConfigMerger,
    ): void {
        $this->registerMerger($documentConfigMerger);
        $this->registerMerger($variablesConfigMerger);
    }
}
