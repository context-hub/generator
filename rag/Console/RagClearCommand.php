<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Butschster\ContextGenerator\Rag\Store\StoreRegistryInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'rag:clear',
    description: 'Clear all entries from RAG knowledge base',
)]
final class RagClearCommand extends BaseCommand
{
    use CollectionAwareTrait;

    #[Option(shortcut: 'f', description: 'Force clear without confirmation')]
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

                $this->output->title('RAG Clear');
                $this->output->writeln(\sprintf('Collections: <info>%s</info>', \implode(', ', $collections)));
                $this->output->writeln('');

                $cleared = 0;
                $skipped = 0;

                foreach ($collections as $collectionName) {
                    $this->outputCollectionHeader($this->output, $collectionName, 'Clearing');

                    $collectionConfig = $registry->getConfig()->getCollection($collectionName);
                    $this->output->writeln(\sprintf('  Target: <info>%s</info>', $collectionConfig->collection));

                    $store = $storeRegistry->getStore($collectionName);

                    if (!$store instanceof ManagedStoreInterface) {
                        $this->output->writeln('  <comment>Store does not support clearing</comment>');
                        $skipped++;
                        continue;
                    }

                    if (!$this->force) {
                        $confirm = $this->output->confirm(
                            \sprintf('  Clear all entries in "%s"?', $collectionName),
                            false,
                        );
                        if (!$confirm) {
                            $this->output->writeln('  <comment>Skipped</comment>');
                            $skipped++;
                            continue;
                        }
                    }

                    $this->output->write('  Clearing... ');
                    try {
                        $store->drop();
                        $store->setup();
                        $this->output->writeln('<info>Done</info>');
                        $cleared++;
                    } catch (\Throwable $e) {
                        $this->output->writeln('<error>Failed</error>');
                        $this->output->error(\sprintf('  Error: %s', $e->getMessage()));
                    }
                }

                $this->output->writeln('');
                if ($cleared > 0) {
                    $this->output->success(\sprintf('Cleared %d collection(s)', $cleared));
                }
                if ($skipped > 0) {
                    $this->output->writeln(\sprintf('<comment>Skipped %d collection(s)</comment>', $skipped));
                }

                return Command::SUCCESS;
            },
        );
    }
}
