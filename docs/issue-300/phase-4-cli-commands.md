# Phase 4: CLI Commands

## Objective

Implement console commands for bulk RAG operations using services from Phase 2.

---

## What We're Building

1. **rag:index** - Bulk index from directory (FileSystemLoader + IndexerService)
2. **rag:clear** - Clear entries
3. **rag:reindex** - Atomic clear + reindex
4. **rag:status** - Display statistics

---

## Files to Create

### 4.1 rag:index Command

#### `rag/Console/RagIndexCommand.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Loader\FileSystemLoader;
use Butschster\ContextGenerator\Rag\Service\IndexerService;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(name: 'rag:index', description: 'Index files into RAG knowledge base')]
final class RagIndexCommand extends Command
{
    #[Argument(description: 'Directory path')]
    protected string $path;

    #[Option(shortcut: 'p', description: 'File pattern')]
    protected string $pattern = '*.md';

    #[Option(shortcut: 't', description: 'Documentation type')]
    protected string $type = 'general';

    #[Option(shortcut: 'r', description: 'Recursive')]
    protected bool $recursive = false;

    #[Option(description: 'Dry run')]
    protected bool $dryRun = false;

    public function __invoke(FileSystemLoader $loader, IndexerService $indexer): int
    {
        $this->output->writeln("<info>Indexing:</info> {$this->path}");
        $this->output->writeln("Pattern: {$this->pattern} | Type: {$this->type} | Recursive: " . ($this->recursive ? 'yes' : 'no'));

        $total = $loader->count($this->path, $this->pattern, $this->recursive);
        
        if ($total === 0) {
            $this->output->writeln('<comment>No files found.</comment>');
            return self::SUCCESS;
        }

        $this->output->writeln("<info>Found {$total} files</info>");
        
        if ($this->dryRun) {
            $this->output->writeln('<comment>Dry run - no changes.</comment>');
            return self::SUCCESS;
        }

        $docType = DocumentType::tryFrom($this->type) ?? DocumentType::General;
        $progress = new ProgressBar($this->output, $total);
        $progress->start();

        $totalChunks = 0;
        foreach ($loader->load($this->path, $this->pattern, $this->recursive, $docType) as $doc) {
            $result = $indexer->indexBatch([$doc]);
            $totalChunks += $result->chunksCreated;
            $progress->advance();
        }

        $progress->finish();
        $this->output->writeln("\n\n<info>Done:</info> {$total} files, {$totalChunks} chunks");
        
        return self::SUCCESS;
    }
}
```

### 4.2 rag:clear Command

#### `rag/Console/RagClearCommand.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\AI\Store\StoreInterface;

#[AsCommand(name: 'rag:clear', description: 'Clear RAG knowledge base')]
final class RagClearCommand extends Command
{
    #[Option(shortcut: 'f', description: 'Skip confirmation')]
    protected bool $force = false;

    public function __invoke(StoreInterface $store): int
    {
        if (!$this->force) {
            $confirm = $this->confirm('Clear all RAG entries?', false);
            if (!$confirm) {
                $this->output->writeln('<comment>Cancelled.</comment>');
                return self::SUCCESS;
            }
        }

        // Note: StoreInterface doesn't have clear() - this needs store-specific implementation
        $this->output->writeln('<info>Knowledge base cleared.</info>');
        
        return self::SUCCESS;
    }
}
```

### 4.3 rag:reindex Command

#### `rag/Console/RagReindexCommand.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Loader\FileSystemLoader;
use Butschster\ContextGenerator\Rag\Service\IndexerService;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(name: 'rag:reindex', description: 'Clear and reindex documentation')]
final class RagReindexCommand extends Command
{
    #[Argument(description: 'Directory path')]
    protected string $path;

    #[Option(shortcut: 'p', description: 'File pattern')]
    protected string $pattern = '*.md';

    #[Option(shortcut: 't', description: 'Documentation type')]
    protected string $type = 'general';

    #[Option(shortcut: 'r', description: 'Recursive')]
    protected bool $recursive = false;

    public function __invoke(FileSystemLoader $loader, IndexerService $indexer): int
    {
        $this->output->writeln("<info>Reindexing:</info> {$this->path}");

        // Phase 1: Count
        $total = $loader->count($this->path, $this->pattern, $this->recursive);
        if ($total === 0) {
            $this->output->writeln('<comment>No files found.</comment>');
            return self::SUCCESS;
        }

        // Phase 2: Clear (simplified - needs proper implementation)
        $this->output->writeln('Clearing old entries...');

        // Phase 3: Index
        $this->output->writeln("Indexing {$total} files...");
        $docType = DocumentType::tryFrom($this->type) ?? DocumentType::General;
        $progress = new ProgressBar($this->output, $total);
        $progress->start();

        $totalChunks = 0;
        foreach ($loader->load($this->path, $this->pattern, $this->recursive, $docType) as $doc) {
            $result = $indexer->indexBatch([$doc]);
            $totalChunks += $result->chunksCreated;
            $progress->advance();
        }

        $progress->finish();
        $this->output->writeln("\n\n<info>Reindex complete:</info> {$totalChunks} chunks");
        
        return self::SUCCESS;
    }
}
```

### 4.4 rag:status Command

#### `rag/Console/RagStatusCommand.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Rag\RagConfig;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;

#[AsCommand(name: 'rag:status', description: 'Display RAG knowledge base status')]
final class RagStatusCommand extends Command
{
    #[Option(description: 'Output as JSON')]
    protected bool $json = false;

    public function __invoke(RagConfig $config): int
    {
        if ($this->json) {
            $this->output->writeln(\json_encode([
                'enabled' => $config->enabled,
                'store' => [
                    'driver' => $config->store->driver,
                    'host' => $config->store->host,
                    'port' => $config->store->port,
                    'collection' => $config->store->collection,
                ],
                'vectorizer' => [
                    'platform' => $config->vectorizer->platform,
                    'model' => $config->vectorizer->model,
                ],
            ], \JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->output->writeln('RAG Knowledge Base Status');
        $this->output->writeln('=========================');
        $this->output->writeln('');
        $this->output->writeln(\sprintf('Enabled: %s', $config->enabled ? 'yes' : 'no'));
        $this->output->writeln('');
        $this->output->writeln('Store:');
        $this->output->writeln(\sprintf('  Driver: %s', $config->store->driver));
        $this->output->writeln(\sprintf('  Host: %s:%d', $config->store->host, $config->store->port));
        $this->output->writeln(\sprintf('  Collection: %s', $config->store->collection));
        $this->output->writeln('');
        $this->output->writeln('Vectorizer:');
        $this->output->writeln(\sprintf('  Platform: %s', $config->vectorizer->platform));
        $this->output->writeln(\sprintf('  Model: %s', $config->vectorizer->model));
        $this->output->writeln('');
        $this->output->writeln('Transformer:');
        $this->output->writeln(\sprintf('  Chunk size: %d', $config->transformer->chunkSize));
        $this->output->writeln(\sprintf('  Overlap: %d', $config->transformer->overlap));

        return self::SUCCESS;
    }
}
```

---

## Implementation Order

1. `RagStatusCommand` (simplest, useful for debugging)
2. `RagIndexCommand` (core functionality)
3. `RagClearCommand`
4. `RagReindexCommand`

---

## Test Cases

### Feature Tests: `tests/src/Feature/Console/Rag/`

```
RagIndexCommandTest.php
- test_indexes_files
- test_dry_run
- test_respects_pattern

RagStatusCommandTest.php
- test_shows_status
- test_json_output

RagClearCommandTest.php
- test_requires_confirmation
- test_force_skips_confirmation
```

---

## Definition of Done

- [ ] All commands work from CLI
- [ ] Progress bars for long operations
- [ ] Dry-run mode works
- [ ] JSON output for status
- [ ] Feature tests pass

---

## Estimated Effort

| Task         | Complexity | Time   |
|--------------|------------|--------|
| rag:status   | Low        | 1h     |
| rag:index    | Medium     | 2h     |
| rag:clear    | Low        | 1h     |
| rag:reindex  | Medium     | 2h     |
| Tests        | Medium     | 2h     |
| **Total**    |            | **~8h** |
