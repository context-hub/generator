<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'rag:clear',
    description: 'Clear all entries from RAG knowledge base',
)]
final class RagClearCommand extends BaseCommand
{
    #[Option(shortcut: 'f', description: 'Force clear without confirmation')]
    protected bool $force = false;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'env',
        shortcut: 'e',
        description: 'Path to .env file (e.g., .env.local)',
    )]
    protected ?string $envFile = null;

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
    ): int {
        $dirs = $dirs
            ->determineRootPath($this->configPath)
            ->withEnvFile($this->envFile);

        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $dirs,
                ],
            ),
            scope: function (
                ConfigurationProvider $configProvider,
                RagRegistryInterface $registry,
                Container $container,
            ): int {
                // Load configuration to trigger RagParserPlugin
                try {
                    if ($this->configPath !== null) {
                        $configLoader = $configProvider->fromPath($this->configPath);
                    } else {
                        $configLoader = $configProvider->fromDefaultLocation();
                    }
                    $configLoader->load();
                } catch (ConfigLoaderException $e) {
                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));
                    return Command::FAILURE;
                }

                if (!$registry->isEnabled()) {
                    $this->output->error('RAG is not enabled in configuration. Add "rag.enabled: true" to context.yaml');
                    return Command::FAILURE;
                }

                $config = $registry->getConfig();

                $this->output->title('RAG Clear');
                $this->output->writeln(\sprintf('Collection: <info>%s</info>', $config->store->collection));
                $this->output->writeln('');

                // Get store after config is loaded
                $store = $container->get(StoreInterface::class);

                if (!$store instanceof ManagedStoreInterface) {
                    $this->output->error('Store does not support clearing operations.');
                    return Command::FAILURE;
                }

                if (!$this->force) {
                    $confirm = $this->output->confirm(
                        'This will delete all entries in the knowledge base. Continue?',
                        false,
                    );

                    if (!$confirm) {
                        $this->output->writeln('<comment>Operation cancelled.</comment>');
                        return Command::SUCCESS;
                    }
                }

                $this->output->write('Clearing knowledge base... ');

                try {
                    $store->drop();
                    $store->setup();
                    $this->output->writeln('<info>Done</info>');
                    $this->output->success('Knowledge base cleared successfully.');
                } catch (\Throwable $e) {
                    $this->output->writeln('<error>Failed</error>');
                    $this->output->error(\sprintf('Failed to clear knowledge base: %s', $e->getMessage()));
                    return Command::FAILURE;
                }

                return Command::SUCCESS;
            },
        );
    }
}
