<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Butschster\ContextGenerator\Rag\Store\StoreRegistryInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'rag:init',
    description: 'Initialize RAG collection in vector store',
)]
final class RagInitCommand extends BaseCommand
{
    use CollectionAwareTrait;

    #[Option(shortcut: 'f', description: 'Force recreate collection if it already exists')]
    protected bool $force = false;

    #[Option(name: 'config-file', shortcut: 'c', description: 'Path to configuration file')]
    protected ?string $configPath = null;

    #[Option(name: 'env', shortcut: 'e', description: 'Path to .env file (e.g., .env.local)')]
    protected ?string $envFile = null;

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
    ): int {
        $dirs = $dirs
            ->determineRootPath($this->configPath)
            ->withEnvFile($this->envFile);

        return $container->runScope(
            bindings: new Scope(bindings: [DirectoriesInterface::class => $dirs]),
            scope: function (
                ConfigurationProvider $configProvider,
                RagRegistryInterface $registry,
                VariableResolver $variableResolver,
                StoreRegistryInterface $storeRegistry,
            ): int {
                try {
                    $configLoader = $this->configPath !== null
                        ? $configProvider->fromPath($this->configPath)
                        : $configProvider->fromDefaultLocation();
                    $configLoader->load();
                } catch (ConfigLoaderException $e) {
                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));
                    return Command::FAILURE;
                }

                if (!$registry->isEnabled()) {
                    $this->output->error('RAG is not enabled in configuration. Add "rag.enabled: true" to context.yaml');
                    return Command::FAILURE;
                }

                try {
                    $collections = $this->getTargetCollections($registry);
                } catch (\InvalidArgumentException $e) {
                    $this->output->error($e->getMessage());
                    return Command::FAILURE;
                }

                $config = $registry->getConfig();

                $this->output->title('RAG Collection Initialization');
                $this->output->writeln(\sprintf('Collections: <info>%s</info>', \implode(', ', $collections)));
                $this->output->writeln('');

                $initialized = 0;
                $skipped = 0;
                $failed = 0;

                foreach ($collections as $collectionName) {
                    $this->outputCollectionHeader($this->output, $collectionName, 'Initializing');

                    $collectionConfig = $config->getCollection($collectionName);
                    $serverConfig = $config->getServer($collectionConfig->server);

                    $endpointUrl = $variableResolver->resolve($serverConfig->endpointUrl);
                    $apiKey = $variableResolver->resolve($serverConfig->apiKey);
                    $qdrantCollection = $variableResolver->resolve($collectionConfig->collection);

                    $dimension = $collectionConfig->getEffectiveEmbeddingsDimension($serverConfig);
                    $distance = $collectionConfig->getEffectiveEmbeddingsDistance($serverConfig);

                    $this->output->writeln(\sprintf('  Server:     <info>%s</info>', $collectionConfig->server));
                    $this->output->writeln(\sprintf('  Driver:     <info>%s</info>', $serverConfig->driver));
                    $this->output->writeln(\sprintf('  Endpoint:   <info>%s</info>', $endpointUrl));
                    $this->output->writeln(\sprintf('  Collection: <info>%s</info>', $qdrantCollection));
                    $this->output->writeln(\sprintf('  Dimensions: <info>%d</info>', $dimension));
                    $this->output->writeln(\sprintf('  Distance:   <info>%s</info>', $distance));

                    if ($serverConfig->driver !== 'qdrant') {
                        $result = $this->initializeGenericStore($storeRegistry, $collectionName);
                    } else {
                        $result = $this->initializeQdrantStore(
                            storeRegistry: $storeRegistry,
                            collectionName: $collectionName,
                            endpointUrl: $endpointUrl,
                            apiKey: $apiKey,
                            qdrantCollection: $qdrantCollection,
                        );
                    }

                    match ($result) {
                        'initialized' => $initialized++,
                        'skipped' => $skipped++,
                        'failed' => $failed++,
                    };
                }

                $this->output->writeln('');
                if ($initialized > 0) {
                    $this->output->success(\sprintf('Initialized %d collection(s)', $initialized));
                }
                if ($skipped > 0) {
                    $this->output->writeln(\sprintf('<comment>Skipped %d collection(s)</comment>', $skipped));
                }
                if ($failed > 0) {
                    $this->output->writeln(\sprintf('<error>Failed %d collection(s)</error>', $failed));
                }

                return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
            },
        );
    }

    private function initializeQdrantStore(
        StoreRegistryInterface $storeRegistry,
        string $collectionName,
        string $endpointUrl,
        string $apiKey,
        string $qdrantCollection,
    ): string {
        $httpClient = HttpClient::create();
        $headers = $apiKey !== '' ? ['api-key' => $apiKey] : [];

        // Check connection
        $this->output->write('  Checking connection... ');
        try {
            $response = $httpClient->request('GET', \rtrim($endpointUrl, '/') . '/collections', [
                'headers' => $headers,
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->output->writeln('<e>Failed</e>');
                $this->output->error(\sprintf('  Qdrant returned status %d', $response->getStatusCode()));
                return 'failed';
            }
            $this->output->writeln('<info>OK</info>');
        } catch (\Throwable $e) {
            $this->output->writeln('<e>Failed</e>');
            $this->output->error(\sprintf('  Cannot connect: %s', $e->getMessage()));
            return 'failed';
        }

        // Check if collection exists
        $this->output->write(\sprintf('  Checking collection "%s"... ', $qdrantCollection));
        $collectionExists = false;

        try {
            $response = $httpClient->request(
                'GET',
                \rtrim($endpointUrl, '/') . '/collections/' . $qdrantCollection,
                ['headers' => $headers],
            );

            if ($response->getStatusCode() === 200) {
                $collectionExists = true;
                $this->output->writeln('<comment>Exists</comment>');
            } else {
                $this->output->writeln('<comment>Not found</comment>');
            }
        } catch (\Throwable $e) {
            if (\str_contains($e->getMessage(), '404')) {
                $this->output->writeln('<comment>Not found</comment>');
            } else {
                $this->output->writeln('<e>Error</e>');
                $this->output->error(\sprintf('  Failed to check: %s', $e->getMessage()));
                return 'failed';
            }
        }

        if ($collectionExists) {
            if (!$this->force) {
                $this->output->writeln('  <comment>Collection already exists. Use --force to recreate.</comment>');
                return 'skipped';
            }

            $confirm = $this->output->confirm(
                '  Collection exists. DROP and recreate?',
                false,
            );

            if (!$confirm) {
                return 'skipped';
            }

            return $this->recreateStore($storeRegistry, $collectionName);
        }

        return $this->createStore($storeRegistry, $collectionName);
    }

    private function initializeGenericStore(StoreRegistryInterface $storeRegistry, string $collectionName): string
    {
        return $this->createStore($storeRegistry, $collectionName);
    }

    private function createStore(StoreRegistryInterface $storeRegistry, string $collectionName): string
    {
        $store = $storeRegistry->getStore($collectionName);

        if (!$store instanceof ManagedStoreInterface) {
            $this->output->writeln('  <comment>Store does not support initialization</comment>');
            return 'skipped';
        }

        $this->output->write('  Creating collection... ');
        try {
            $store->setup();
            $this->output->writeln('<info>Done</info>');
            return 'initialized';
        } catch (\Throwable $e) {
            $this->output->writeln('<e>Failed</e>');
            $this->output->error(\sprintf('  Error: %s', $e->getMessage()));
            return 'failed';
        }
    }

    private function recreateStore(StoreRegistryInterface $storeRegistry, string $collectionName): string
    {
        $store = $storeRegistry->getStore($collectionName);

        if (!$store instanceof ManagedStoreInterface) {
            $this->output->writeln('  <comment>Store does not support recreation</comment>');
            return 'skipped';
        }

        $this->output->write('  Dropping existing... ');
        try {
            $store->drop();
            $this->output->writeln('<info>Done</info>');
        } catch (\Throwable $e) {
            $this->output->writeln('<e>Failed</e>');
            $this->output->error(\sprintf('  Error: %s', $e->getMessage()));
            return 'failed';
        }

        $this->output->write('  Creating new... ');
        try {
            $store->setup();
            $this->output->writeln('<info>Done</info>');
            return 'initialized';
        } catch (\Throwable $e) {
            $this->output->writeln('<e>Failed</e>');
            $this->output->error(\sprintf('  Error: %s', $e->getMessage()));
            return 'failed';
        }
    }
}
