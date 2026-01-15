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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'rag:status',
    description: 'Display RAG knowledge base status and configuration',
)]
final class RagStatusCommand extends BaseCommand
{
    #[Option(name: 'json', description: 'Output as JSON')]
    protected bool $asJson = false;

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
            ): int {
                // Load configuration to trigger RagParserPlugin
                try {
                    if ($this->configPath !== null) {
                        $loader = $configProvider->fromPath($this->configPath);
                    } else {
                        $loader = $configProvider->fromDefaultLocation();
                    }
                    $loader->load();
                } catch (ConfigLoaderException $e) {
                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));
                    return Command::FAILURE;
                }

                $config = $registry->getConfig();

                if ($this->asJson) {
                    $this->output->writeln(\json_encode([
                        'enabled' => $config->enabled,
                        'store' => [
                            'driver' => $config->store->driver,
                            'endpoint_url' => $config->store->endpointUrl,
                            'collection' => $config->store->collection,
                            'embeddings_dimension' => $config->store->embeddingsDimension,
                        ],
                        'vectorizer' => [
                            'platform' => $config->vectorizer->platform,
                            'model' => $config->vectorizer->model,
                        ],
                        'transformer' => [
                            'chunk_size' => $config->transformer->chunkSize,
                            'overlap' => $config->transformer->overlap,
                        ],
                    ], \JSON_PRETTY_PRINT));

                    return Command::SUCCESS;
                }

                $this->output->title('RAG Knowledge Base Status');

                $this->output->writeln(\sprintf('Enabled: <info>%s</info>', $config->enabled ? 'Yes' : 'No'));
                $this->output->writeln('');

                $this->output->section('Store Configuration');
                $this->output->writeln(\sprintf('  Driver:     <info>%s</info>', $config->store->driver));
                $this->output->writeln(\sprintf('  Endpoint:   <info>%s</info>', $config->store->endpointUrl));
                $this->output->writeln(\sprintf('  Collection: <info>%s</info>', $config->store->collection));
                $this->output->writeln(\sprintf('  Dimensions: <info>%d</info>', $config->store->embeddingsDimension));
                $this->output->writeln('');

                $this->output->section('Vectorizer Configuration');
                $this->output->writeln(\sprintf('  Platform: <info>%s</info>', $config->vectorizer->platform));
                $this->output->writeln(\sprintf('  Model:    <info>%s</info>', $config->vectorizer->model));
                $this->output->writeln('');

                $this->output->section('Transformer Configuration');
                $this->output->writeln(\sprintf('  Chunk Size: <info>%d</info>', $config->transformer->chunkSize));
                $this->output->writeln(\sprintf('  Overlap:    <info>%d</info>', $config->transformer->overlap));

                return Command::SUCCESS;
            },
        );
    }
}
