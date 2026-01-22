<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Butschster\ContextGenerator\McpServer\Tool\RagToolExecutorInterface;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\Console\RagClearCommand;
use Butschster\ContextGenerator\Rag\Console\RagIndexCommand;
use Butschster\ContextGenerator\Rag\Console\RagInitCommand;
use Butschster\ContextGenerator\Rag\Console\RagReindexCommand;
use Butschster\ContextGenerator\Rag\Console\RagStatusCommand;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Butschster\ContextGenerator\Rag\Loader\FileSystemLoader;
use Butschster\ContextGenerator\Rag\Service\IndexerService;
use Butschster\ContextGenerator\Rag\Service\RetrieverService;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use Butschster\ContextGenerator\Rag\Store\StoreFactory;
use Butschster\ContextGenerator\Rag\Store\StoreRegistry;
use Butschster\ContextGenerator\Rag\Store\StoreRegistryInterface;
use Butschster\ContextGenerator\Rag\Tool\RagToolExecutor;
use Butschster\ContextGenerator\Rag\Tool\RagToolFactory;
use Butschster\ContextGenerator\Rag\Tool\RagToolParserPlugin;
use Butschster\ContextGenerator\Rag\Tool\RagToolRegistry;
use Butschster\ContextGenerator\Rag\Tool\RagToolRegistryInterface;
use Butschster\ContextGenerator\Rag\Vectorizer\VectorizerFactory;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;

#[Singleton]
final class RagBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [
            ConfigLoaderBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Registries
            RagRegistryInterface::class => RagRegistry::class,
            RagToolRegistryInterface::class => RagToolRegistry::class,
            RagToolRegistry::class => RagToolRegistry::class,

            // Factories
            StoreFactory::class => StoreFactory::class,
            VectorizerFactory::class => VectorizerFactory::class,
            MetadataFactory::class => MetadataFactory::class,

            // Store registry for multi-collection support
            StoreRegistryInterface::class => static fn(
                RagRegistryInterface $ragRegistry,
                StoreFactory $factory,
            ): StoreRegistryInterface => new StoreRegistry(
                ragRegistry: $ragRegistry,
                factory: $factory,
            ),

            // Symfony AI Store components (created from registry config)
            // Uses first/default collection for backward compatibility
            StoreInterface::class => static fn(
                StoreFactory $factory,
                RagRegistryInterface $registry,
            ): StoreInterface => $factory->create($registry->getConfig()),

            VectorizerInterface::class => static fn(
                VectorizerFactory $factory,
                RagRegistryInterface $registry,
            ): VectorizerInterface => $factory->create($registry->getConfig()),

            // Provide RagConfig from registry for convenience
            RagConfig::class => static fn(RagRegistryInterface $registry): RagConfig => $registry->getConfig(),

            // Service factory for multi-collection support
            ServiceFactory::class => ServiceFactory::class,

            // RAG tool factory for dynamic tools
            RagToolFactory::class => RagToolFactory::class,

            // RAG tool executor for MCP tool handler
            RagToolExecutorInterface::class => RagToolExecutor::class,

            // Legacy IndexerService binding - uses default/first collection
            IndexerService::class => static function (ServiceFactory $factory): IndexerService {
                $collectionNames = $factory->getCollectionNames();
                if (empty($collectionNames)) {
                    throw new \RuntimeException('No RAG collections configured');
                }

                return $factory->getIndexer($collectionNames[0]);
            },

            // Legacy RetrieverService binding - uses default/first collection
            RetrieverService::class => static function (ServiceFactory $factory): RetrieverService {
                $collectionNames = $factory->getCollectionNames();
                if (empty($collectionNames)) {
                    throw new \RuntimeException('No RAG collections configured');
                }

                return $factory->getRetriever($collectionNames[0]);
            },

            // Loader
            FileSystemLoader::class => FileSystemLoader::class,
        ];
    }

    public function boot(
        ConfigLoaderBootloader $configLoader,
        RagParserPlugin $ragParser,
        RagToolParserPlugin $ragToolParser,
        ConsoleBootloader $console,
    ): void {
        // Register parser plugins
        $configLoader->registerParserPlugin($ragParser);
        $configLoader->registerParserPlugin($ragToolParser);

        // Register CLI commands
        $console->addCommand(RagStatusCommand::class);
        $console->addCommand(RagInitCommand::class);
        $console->addCommand(RagIndexCommand::class);
        $console->addCommand(RagClearCommand::class);
        $console->addCommand(RagReindexCommand::class);
    }
}
