<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
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
    use CollectionAwareTrait;

    #[Option(name: 'json', description: 'Output as JSON')]
    protected bool $asJson = false;

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
            ): int {
                try {
                    $loader = $this->configPath !== null
                        ? $configProvider->fromPath($this->configPath)
                        : $configProvider->fromDefaultLocation();
                    $loader->load();
                } catch (ConfigLoaderException $e) {
                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));
                    return Command::FAILURE;
                }

                $config = $registry->getConfig();

                if ($this->asJson) {
                    return $this->outputJson($config);
                }

                return $this->outputFormatted($config, $registry);
            },
        );
    }

    private function outputJson(RagConfig $config): int
    {
        $data = [
            'enabled' => $config->enabled,
            'format' => $config->isLegacyFormat() ? 'legacy' : 'new',
            'vectorizer' => [
                'platform' => $config->vectorizer->platform,
                'model' => $config->vectorizer->model,
            ],
            'transformer' => [
                'chunk_size' => $config->transformer->chunkSize,
                'overlap' => $config->transformer->overlap,
            ],
            'servers' => [],
            'collections' => [],
        ];

        foreach ($config->servers as $name => $server) {
            $data['servers'][$name] = [
                'driver' => $server->driver,
                'endpoint_url' => $server->endpointUrl,
                'embeddings_dimension' => $server->embeddingsDimension,
                'embeddings_distance' => $server->embeddingsDistance,
            ];
        }

        foreach ($config->collections as $name => $collection) {
            $data['collections'][$name] = [
                'server' => $collection->server,
                'collection' => $collection->collection,
                'description' => $collection->description,
                'embeddings_dimension' => $collection->embeddingsDimension,
                'embeddings_distance' => $collection->embeddingsDistance,
                'transformer' => $collection->transformer !== null ? [
                    'chunk_size' => $collection->transformer->chunkSize,
                    'overlap' => $collection->transformer->overlap,
                ] : null,
            ];
        }

        $this->output->writeln(\json_encode($data, \JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function outputFormatted(RagConfig $config, RagRegistryInterface $registry): int
    {
        $this->output->title('RAG Knowledge Base Status');

        $this->output->writeln(\sprintf('Enabled: <info>%s</info>', $config->enabled ? 'Yes' : 'No'));
        $this->output->writeln(\sprintf('Format:  <info>%s</info>', $config->isLegacyFormat() ? 'Legacy' : 'Multi-collection'));
        $this->output->writeln('');

        // Global Vectorizer
        $this->output->section('Vectorizer Configuration');
        $this->output->writeln(\sprintf('  Platform: <info>%s</info>', $config->vectorizer->platform));
        $this->output->writeln(\sprintf('  Model:    <info>%s</info>', $config->vectorizer->model));
        $this->output->writeln('');

        // Default Transformer
        $this->output->section('Default Transformer');
        $this->output->writeln(\sprintf('  Chunk Size: <info>%d</info>', $config->transformer->chunkSize));
        $this->output->writeln(\sprintf('  Overlap:    <info>%d</info>', $config->transformer->overlap));
        $this->output->writeln('');

        // Servers
        if (!empty($config->servers)) {
            $this->output->section('Servers');
            foreach ($config->servers as $name => $server) {
                $this->output->writeln(\sprintf('  <comment>%s</comment>:', $name));
                $this->output->writeln(\sprintf('    Driver:     <info>%s</info>', $server->driver));
                $this->output->writeln(\sprintf('    Endpoint:   <info>%s</info>', $server->endpointUrl));
                $this->output->writeln(\sprintf('    Dimensions: <info>%d</info>', $server->embeddingsDimension));
                $this->output->writeln(\sprintf('    Distance:   <info>%s</info>', $server->embeddingsDistance));
                $this->output->writeln('');
            }
        }

        // Collections
        try {
            $collections = $this->getTargetCollections($registry);
        } catch (\InvalidArgumentException $e) {
            $this->output->error($e->getMessage());
            return Command::FAILURE;
        }

        if (!empty($collections)) {
            $this->output->section('Collections');
            foreach ($collections as $name) {
                $coll = $config->getCollection($name);
                $this->output->writeln(\sprintf('  <comment>%s</comment>:', $name));
                $this->output->writeln(\sprintf('    Server:     <info>%s</info>', $coll->server));
                $this->output->writeln(\sprintf('    Collection: <info>%s</info>', $coll->collection));
                if ($coll->description !== null) {
                    $this->output->writeln(\sprintf('    Description: %s', $coll->description));
                }
                if ($coll->embeddingsDimension !== null) {
                    $this->output->writeln(\sprintf('    Dimensions: <info>%d</info> (override)', $coll->embeddingsDimension));
                }
                if ($coll->transformer !== null) {
                    $this->output->writeln(\sprintf('    Chunk Size: <info>%d</info> (override)', $coll->transformer->chunkSize));
                    $this->output->writeln(\sprintf('    Overlap:    <info>%d</info> (override)', $coll->transformer->overlap));
                }
                $this->output->writeln('');
            }
        }

        return Command::SUCCESS;
    }
}
