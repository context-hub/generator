<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Loader\FileSystemLoader;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Butschster\ContextGenerator\Rag\Service\IndexerService;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'rag:reindex',
    description: 'Clear and reindex files into RAG knowledge base',
)]
final class RagReindexCommand extends BaseCommand
{
    #[Argument(description: 'Directory path to index (relative to project root)')]
    protected string $path;

    #[Option(shortcut: 'p', description: 'File pattern (e.g., "*.md", "*.txt")')]
    protected string $pattern = '*.md';

    #[Option(shortcut: 't', description: 'Document type: architecture, api, testing, convention, tutorial, reference, general')]
    protected string $type = 'general';

    #[Option(shortcut: 'r', description: 'Recursive search')]
    protected bool $recursive = true;

    #[Option(shortcut: 'f', description: 'Force reindex without confirmation')]
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
                FileSystemLoader $loader,
                DirectoriesInterface $dirs,
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

                $fullPath = $dirs->getRootPath()->join($this->path)->toString();

                if (!\is_dir($fullPath)) {
                    $this->output->error(\sprintf('Directory not found: %s', $this->path));
                    return Command::FAILURE;
                }

                $config = $registry->getConfig();

                $this->output->title('RAG Reindex');
                $this->output->writeln(\sprintf('Collection: <info>%s</info>', $config->store->collection));
                $this->output->writeln(\sprintf('Path:       <info>%s</info>', $this->path));
                $this->output->writeln(\sprintf('Pattern:    <info>%s</info>', $this->pattern));
                $this->output->writeln(\sprintf('Type:       <info>%s</info>', $this->type));
                $this->output->writeln(\sprintf('Recursive:  <info>%s</info>', $this->recursive ? 'Yes' : 'No'));
                $this->output->writeln('');

                $total = $loader->count($fullPath, $this->pattern, $this->recursive);

                if ($total === 0) {
                    $this->output->warning('No files found matching the pattern.');
                    return Command::SUCCESS;
                }

                $this->output->writeln(\sprintf('Found <info>%d</info> files to index', $total));

                if (!$this->force) {
                    $confirm = $this->output->confirm(
                        'This will clear the knowledge base and reindex. Continue?',
                        false,
                    );

                    if (!$confirm) {
                        $this->output->writeln('<comment>Operation cancelled.</comment>');
                        return Command::SUCCESS;
                    }
                }

                // Get services after config is loaded (so factories have resolved variables)
                $store = $container->get(StoreInterface::class);
                $indexer = $container->get(IndexerService::class);

                // Step 1: Clear
                if ($store instanceof ManagedStoreInterface) {
                    $this->output->writeln('');
                    $this->output->write('Clearing existing entries... ');
                    try {
                        $store->drop();
                        $store->setup();
                        $this->output->writeln('<info>Done</info>');
                    } catch (\Throwable $e) {
                        $this->output->writeln('<e>Failed</e>');
                        $this->output->error(\sprintf('Failed to clear: %s', $e->getMessage()));
                        return Command::FAILURE;
                    }
                }

                // Step 2: Index
                $this->output->writeln('Indexing files...');
                $this->output->writeln('');

                $docType = DocumentType::tryFrom($this->type) ?? DocumentType::General;

                $progressBar = new ProgressBar($this->output, $total);
                $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');
                $progressBar->start();

                $totalChunks = 0;
                $totalTime = 0.0;

                foreach ($loader->load($fullPath, $this->pattern, $this->recursive, $docType) as $doc) {
                    $result = $indexer->indexBatch([$doc]);
                    $totalChunks += $result->chunksCreated;
                    $totalTime += $result->processingTimeMs;
                    $progressBar->advance();
                }

                $progressBar->finish();

                $this->output->writeln('');
                $this->output->writeln('');
                $this->output->success(\sprintf(
                    'Reindexed %d files into %d chunks (%.2fs)',
                    $total,
                    $totalChunks,
                    $totalTime / 1000,
                ));

                return Command::SUCCESS;
            },
        );
    }
}
