# Stage 6: CLI Commands Update

## Overview

Update all RAG CLI commands to support `--collection` option. When specified, operate on that collection only. When not
specified, iterate over all collections with clear output per collection.

## Files

**MODIFY:**

- `rag/Console/RagIndexCommand.php` - Add `--collection` option
- `rag/Console/RagStatusCommand.php` - Add `--collection` option, show all or specific
- `rag/Console/RagClearCommand.php` - Add `--collection` option
- `rag/Console/RagInitCommand.php` - Add `--collection` option
- `rag/Console/RagReindexCommand.php` - Add `--collection` option

**CREATE:**

- `tests/src/Rag/Console/RagIndexCommandTest.php` - Tests with collection parameter
- `tests/src/Rag/Console/RagStatusCommandTest.php` - Tests for multi-collection output

## Code References

### Current Command Option Pattern

```php
// rag/Console/RagIndexCommand.php:35-41
#[Option(shortcut: 'p', description: 'File pattern (e.g., "*.md", "*.txt")')]
protected string $pattern = '*.md';

#[Option(shortcut: 't', description: 'Document type: architecture, api, testing, convention, tutorial, reference, general')]
protected string $type = 'general';
```

### Service Factory Access Pattern

```php
// rag/Console/RagIndexCommand.php:62-67
// Get IndexerService after config is loaded
$indexer = $container->get(IndexerService::class);
```

### Output Formatting Pattern

```php
// rag/Console/RagStatusCommand.php:76-83
$this->output->section('Store Configuration');
$this->output->writeln(\sprintf('  Driver:     <info>%s</info>', $config->store->driver));
```

## Implementation Details

### 1. Common Trait for Collection Option

Create a shared trait to avoid duplication:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Spiral\Console\Attribute\Option;

trait CollectionAwareTrait
{
    #[Option(
        name: 'collection',
        description: 'Collection name to operate on (operates on all if not specified)',
    )]
    protected ?string $collection = null;

    /**
     * Get collection names to operate on.
     *
     * @return string[]
     */
    protected function getTargetCollections(RagRegistryInterface $registry): array
    {
        $config = $registry->getConfig();

        if ($this->collection !== null) {
            if (!$config->hasCollection($this->collection)) {
                throw new \InvalidArgumentException(
                    \sprintf('Collection "%s" not found. Available: %s', 
                        $this->collection, 
                        \implode(', ', $config->getCollectionNames())
                    )
                );
            }
            return [$this->collection];
        }

        return $config->getCollectionNames();
    }

    /**
     * Output section header for collection operations.
     */
    protected function outputCollectionHeader(string $collectionName, string $operation): void
    {
        $this->output->section(\sprintf('%s: %s', $operation, $collectionName));
    }
}
```

### 2. Updated RagIndexCommand

```php
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
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'rag:index',
    description: 'Index files into RAG knowledge base',
)]
final class RagIndexCommand extends BaseCommand
{
    use CollectionAwareTrait;

    #[Argument(description: 'Directory path to index (relative to project root)')]
    protected string $path;

    #[Option(shortcut: 'p', description: 'File pattern (e.g., "*.md", "*.txt")')]
    protected string $pattern = '*.md';

    #[Option(shortcut: 't', description: 'Document type: architecture, api, testing, convention, tutorial, reference, general')]
    protected string $type = 'general';

    #[Option(shortcut: 'r', description: 'Recursive search')]
    protected bool $recursive = true;

    #[Option(description: 'Dry run - show what would be indexed without indexing')]
    protected bool $dryRun = false;

    #[Option(name: 'config-file', shortcut: 'c', description: 'Path to configuration file')]
    protected ?string $configPath = null;

    #[Option(name: 'env', shortcut: 'e', description: 'Path to .env file')]
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
                FileSystemLoader $loader,
                DirectoriesInterface $dirs,
                ServiceFactory $serviceFactory,
            ): int {
                // Load configuration
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
                    $this->output->error('RAG is not enabled in configuration');
                    return Command::FAILURE;
                }

                $fullPath = $dirs->getRootPath()->join($this->path)->toString();
                if (!\is_dir($fullPath)) {
                    $this->output->error(\sprintf('Directory not found: %s', $this->path));
                    return Command::FAILURE;
                }

                // Get target collections
                try {
                    $collections = $this->getTargetCollections($registry);
                } catch (\InvalidArgumentException $e) {
                    $this->output->error($e->getMessage());
                    return Command::FAILURE;
                }

                $this->output->title('RAG Index');
                $this->output->writeln(\sprintf('Path:        <info>%s</info>', $this->path));
                $this->output->writeln(\sprintf('Pattern:     <info>%s</info>', $this->pattern));
                $this->output->writeln(\sprintf('Type:        <info>%s</info>', $this->type));
                $this->output->writeln(\sprintf('Recursive:   <info>%s</info>', $this->recursive ? 'Yes' : 'No'));
                $this->output->writeln(\sprintf('Collections: <info>%s</info>', \implode(', ', $collections)));
                $this->output->writeln('');

                $total = $loader->count($fullPath, $this->pattern, $this->recursive);
                if ($total === 0) {
                    $this->output->warning('No files found matching the pattern.');
                    return Command::SUCCESS;
                }

                $this->output->writeln(\sprintf('Found <info>%d</info> files', $total));
                $docType = DocumentType::tryFrom($this->type) ?? DocumentType::General;

                // Index into each collection
                $overallChunks = 0;
                $overallTime = 0.0;

                foreach ($collections as $collectionName) {
                    $this->outputCollectionHeader($collectionName, 'Indexing');

                    $collectionConfig = $registry->getConfig()->getCollection($collectionName);
                    $this->output->writeln(\sprintf('  Target: <info>%s</info>', $collectionConfig->collection));

                    if ($this->dryRun) {
                        $this->output->note('Dry run - no changes made');
                        foreach ($loader->load($fullPath, $this->pattern, $this->recursive, $docType) as $doc) {
                            $this->output->writeln(\sprintf('  • %s', $doc->getMetadata()['source_path'] ?? 'unknown'));
                        }
                        continue;
                    }

                    $indexer = $serviceFactory->getIndexer($collectionName);
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
                    $this->output->writeln(\sprintf(
                        '  <info>✓</info> Indexed %d chunks in %.2fs',
                        $totalChunks,
                        $totalTime / 1000,
                    ));

                    $overallChunks += $totalChunks;
                    $overallTime += $totalTime;
                }

                if (!$this->dryRun) {
                    $this->output->writeln('');
                    $this->output->success(\sprintf(
                        'Total: %d files → %d chunks across %d collection(s) (%.2fs)',
                        $total,
                        $overallChunks,
                        \count($collections),
                        $overallTime / 1000,
                    ));
                }

                return Command::SUCCESS;
            },
        );
    }
}
```

### 3. Updated RagStatusCommand

```php
<?php

// ... (imports)

#[AsCommand(
    name: 'rag:status',
    description: 'Display RAG knowledge base status and configuration',
)]
final class RagStatusCommand extends BaseCommand
{
    use CollectionAwareTrait;

    #[Option(name: 'json', description: 'Output as JSON')]
    protected bool $asJson = false;

    // ... other options

    public function __invoke(/* ... */): int
    {
        // ... load config

        $config = $registry->getConfig();

        if ($this->asJson) {
            return $this->outputJson($config);
        }

        $this->output->title('RAG Knowledge Base Status');
        $this->output->writeln(\sprintf('Enabled: <info>%s</info>', $config->enabled ? 'Yes' : 'No'));
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
        $this->output->section('Servers');
        foreach ($config->servers as $name => $server) {
            $this->output->writeln(\sprintf('  <comment>%s</comment>:', $name));
            $this->output->writeln(\sprintf('    Driver:     <info>%s</info>', $server->driver));
            $this->output->writeln(\sprintf('    Endpoint:   <info>%s</info>', $server->endpointUrl));
            $this->output->writeln(\sprintf('    Dimensions: <info>%d</info>', $server->embeddingsDimension));
            $this->output->writeln('');
        }

        // Collections
        try {
            $collections = $this->getTargetCollections($registry);
        } catch (\InvalidArgumentException $e) {
            $this->output->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->output->section('Collections');
        foreach ($collections as $name) {
            $coll = $config->getCollection($name);
            $this->output->writeln(\sprintf('  <comment>%s</comment>:', $name));
            $this->output->writeln(\sprintf('    Server:     <info>%s</info>', $coll->server));
            $this->output->writeln(\sprintf('    Collection: <info>%s</info>', $coll->collection));
            if ($coll->description !== null) {
                $this->output->writeln(\sprintf('    Description: %s', $coll->description));
            }
            if ($coll->transformer !== null) {
                $this->output->writeln(\sprintf('    Chunk Size: <info>%d</info> (override)', $coll->transformer->chunkSize));
                $this->output->writeln(\sprintf('    Overlap:    <info>%d</info> (override)', $coll->transformer->overlap));
            }
            $this->output->writeln('');
        }

        return Command::SUCCESS;
    }
}
```

### 4. Updated RagClearCommand

```php
// Add CollectionAwareTrait and --collection option
// Modify to iterate over collections:

foreach ($collections as $collectionName) {
    $this->outputCollectionHeader($collectionName, 'Clearing');
    
    $store = $storeRegistry->getStore($collectionName);
    
    if (!$store instanceof ManagedStoreInterface) {
        $this->output->writeln('  <comment>Store does not support clearing</comment>');
        continue;
    }

    if (!$this->force) {
        $confirm = $this->output->confirm(
            \sprintf('Clear all entries in "%s"?', $collectionName),
            false,
        );
        if (!$confirm) {
            $this->output->writeln('  <comment>Skipped</comment>');
            continue;
        }
    }

    $this->output->write('  Clearing... ');
    try {
        $store->drop();
        $store->setup();
        $this->output->writeln('<info>Done</info>');
    } catch (\Throwable $e) {
        $this->output->writeln('<e>Failed</e>');
        $this->output->error($e->getMessage());
    }
}
```

### 5. Updated RagInitCommand

```php
// Add CollectionAwareTrait and --collection option
// Modify to iterate over collections:

foreach ($collections as $collectionName) {
    $this->outputCollectionHeader($collectionName, 'Initializing');
    
    $collectionConfig = $config->getCollection($collectionName);
    $serverConfig = $config->getServer($collectionConfig->server);
    
    $this->output->writeln(\sprintf('  Server:     <info>%s</info>', $collectionConfig->server));
    $this->output->writeln(\sprintf('  Collection: <info>%s</info>', $collectionConfig->collection));
    
    // ... rest of init logic per collection
}
```

### 6. Updated RagReindexCommand

```php
// Add CollectionAwareTrait and --collection option
// Similar pattern to RagIndexCommand, but clears first per collection
```

## Usage Examples

```bash
# Index into all collections
ctx rag:index docs

# Index into specific collection
ctx rag:index docs --collection=project-docs

# Status for all collections
ctx rag:status

# Status for specific collection
ctx rag:status --collection=architecture

# Clear specific collection
ctx rag:clear --collection=project-docs

# Initialize specific collection
ctx rag:init --collection=shared-knowledge --force

# Reindex specific collection
ctx rag:reindex docs --collection=project-docs
```

## Definition of Done

- [ ] `CollectionAwareTrait` created with `--collection` option and helper methods
- [ ] All 5 RAG commands use the trait
- [ ] `getTargetCollections()` returns specified collection or all collections
- [ ] Clear error message when specified collection doesn't exist
- [ ] Each command iterates over collections with clear output headers
- [ ] `RagStatusCommand` shows servers, collections, and their configurations
- [ ] `RagIndexCommand` reports per-collection and total statistics
- [ ] `RagClearCommand` confirms per collection when not using `--force`
- [ ] `RagInitCommand` initializes each collection separately
- [ ] Commands work correctly with legacy single-collection config
- [ ] Unit tests verify collection parameter handling

## Dependencies

**Requires**: Stage 2 (Store Registry), Stage 3 (Service Layer)
**Enables**: Stage 7 (Integration Testing)
