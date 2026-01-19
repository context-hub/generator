# RAG Implementation Reference Guide

Quick reference to existing code patterns, files, and examples for implementing the RAG Knowledge Store.

---

## Project Structure Reference

### CTX Main Project

```
/root/repos/context-hub/generator/
├── src/
│   ├── McpServer/                    # MCP server implementation
│   │   ├── Action/Tools/             # Tool actions (reference patterns)
│   │   ├── ActionsBootloader.php     # Tool registration
│   │   ├── Console/                  # MCP-related commands
│   │   └── Projects/                 # Multi-project support
│   └── Rag/                          # NEW: RAG module location
└── tests/
    └── src/
        ├── Feature/Console/          # Console command tests
        └── McpInspector/Tools/       # MCP tool integration tests
```

### CTX MCP Server Package

```
Project: ctx-mcp-server
├── src/
│   ├── Action/ToolResult.php         # Tool result helpers
│   ├── Attribute/                    # Tool, InputSchema, etc.
│   ├── McpConfig.php                 # MCP configuration
│   ├── McpServerBootloader.php       # Server bootloader
│   └── Routing/Attribute/            # Post, Get routes
```

---

## Key Files to Study

### MCP Tool Pattern

| File                      | Purpose               | Location                                            |
|---------------------------|-----------------------|-----------------------------------------------------|
| `PhpStructureAction.php`  | Complete tool example | `src/McpServer/Action/Tools/Php/`                   |
| `PhpStructureHandler.php` | Handler pattern       | `src/McpServer/Action/Tools/Php/`                   |
| `PhpStructureRequest.php` | Request DTO           | `src/McpServer/Action/Tools/Php/Dto/`               |
| `FileSearchAction.php`    | Tool with validation  | `src/McpServer/Action/Tools/Filesystem/FileSearch/` |
| `FileSearchHandler.php`   | Handler with Finder   | `src/McpServer/Action/Tools/Filesystem/FileSearch/` |

### Attributes (ctx-mcp-server)

| File              | Purpose              | Location                 |
|-------------------|----------------------|--------------------------|
| `Tool.php`        | Tool attribute       | `src/Attribute/`         |
| `InputSchema.php` | Schema attribute     | `src/Attribute/`         |
| `McpItem.php`     | Base attribute class | `src/Attribute/`         |
| `Post.php`        | Route attribute      | `src/Routing/Attribute/` |

### Configuration

| File                      | Purpose              | Location                                      |
|---------------------------|----------------------|-----------------------------------------------|
| `McpConfig.php`           | Config class pattern | ctx-mcp-server: `src/McpConfig.php`           |
| `ActionsBootloader.php`   | Tool registration    | `src/McpServer/ActionsBootloader.php`         |
| `McpServerBootloader.php` | DI bindings          | ctx-mcp-server: `src/McpServerBootloader.php` |

### Console Commands

| File                    | Purpose              | Location                          |
|-------------------------|----------------------|-----------------------------------|
| `ProjectAddCommand.php` | Command with options | `src/McpServer/Projects/Console/` |
| `ToolRunCommand.php`    | Interactive command  | `src/McpServer/Tool/Console/`     |
| `BaseCommand.php`       | Base command class   | `src/Console/`                    |

### Tests

| File                       | Purpose           | Location                        |
|----------------------------|-------------------|---------------------------------|
| `PhpStructureToolTest.php` | MCP tool test     | `tests/src/McpInspector/Tools/` |
| `ConsoleTestCase.php`      | Console test base | `tests/src/Feature/Console/`    |

---

## Code Patterns

### 1. MCP Tool Action

```php
// Reference: src/McpServer/Action/Tools/Php/PhpStructureAction.php

<?php
declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'tool-name',
    description: 'Tool description for AI',
    title: 'Human Readable Title',
)]
#[InputSchema(class: RequestDto::class)]
final readonly class ToolAction
{
    public function __construct(
        private ToolHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/tool-name', name: 'tools.tool-name')]
    public function __invoke(RequestDto $request): CallToolResult
    {
        $this->logger->info('Processing tool', ['param' => $request->param]);

        try {
            $result = $this->handler->process($request);
            return ToolResult::text($result);
        } catch (\Throwable $e) {
            $this->logger->error('Tool error', ['error' => $e->getMessage()]);
            return ToolResult::error($e->getMessage());
        }
    }
}
```

### 2. Request DTO

```php
// Reference: src/McpServer/Action/Tools/Php/Dto/PhpStructureRequest.php

<?php
declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php\Dto;

use Butschster\ContextGenerator\McpServer\Project\ProjectAwareRequest;
use Spiral\JsonSchemaGenerator\Attribute\Field;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;

final readonly class RequestDto implements ProjectAwareRequest
{
    public function __construct(
        #[Field(description: 'Required field description')]
        public string $requiredField,
        
        #[Field(description: 'Optional field', default: 'default')]
        public string $optionalField = 'default',
        
        #[Field(description: 'Numeric with range')]
        #[Range(min: 0, max: 100)]
        public int $numericField = 10,
        
        #[Field(description: 'Project identifier')]
        public ?string $project = null,
    ) {}

    public function getProject(): ?string
    {
        return $this->project;
    }
}
```

### 3. Handler Pattern

```php
// Reference: src/McpServer/Action/Tools/Php/PhpStructureHandler.php

<?php
declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php;

final readonly class ToolHandler
{
    public function __construct(
        private SomeDependency $dependency,
    ) {}

    public function process(RequestDto $request): ResultType
    {
        // Business logic here
    }
}
```

### 4. Console Command

```php
// Reference: src/McpServer/Projects/Console/ProjectAddCommand.php

<?php
declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'rag:index',
    description: 'Index documentation into RAG',
)]
final class RagIndexCommand extends BaseCommand
{
    #[Argument(name: 'path', description: 'Path to index')]
    protected string $path;

    #[Option(name: 'type', shortcut: 't', description: 'Document type')]
    protected string $type = 'general';

    #[Option(name: 'recursive', shortcut: 'r', description: 'Recursive')]
    protected bool $recursive = false;

    public function __invoke(
        SomeDependency $dependency,
    ): int {
        // Implementation
        $this->output->success('Done!');
        return Command::SUCCESS;
    }
}
```

### 5. Bootloader

```php
// Reference: src/McpServer/McpServerBootloader.php (ctx-mcp-server)

<?php
declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;

final class RagBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function init(EnvironmentInterface $env): void
    {
        $this->config->setDefaults(RagConfig::CONFIG, [
            'enabled' => (bool) $env->get('RAG_ENABLED', true),
        ]);
    }

    public function defineSingletons(): array
    {
        return [
            InterfaceClass::class => ConcreteClass::class,
        ];
    }
}
```

### 6. InjectableConfig

```php
// Reference: src/McpConfig.php (ctx-mcp-server)

<?php
declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Spiral\Core\InjectableConfig;

final class RagConfig extends InjectableConfig
{
    public const string CONFIG = 'rag';

    protected array $config = [
        'enabled' => true,
        'storage' => ['driver' => 'sqlite'],
    ];

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }
}
```

---

## Tool Registration

### Where to Register Tools

```php
// File: src/McpServer/ActionsBootloader.php
// Method: actions()
// Line: ~150-180

if ($config->isRagOperationsEnabled()) {
    $actions = [
        ...$actions,
        RagStoreAction::class,
        RagSearchAction::class,
        RagManageAction::class,
    ];
}
```

### Where to Register Commands

```php
// File: src/Rag/RagBootloader.php
// Method: boot()

public function boot(ConsoleBootloader $console): void
{
    $console->addCommand(RagIndexCommand::class);
    $console->addCommand(RagClearCommand::class);
}
```

---

## Testing Patterns

### MCP Tool Test

```php
// Reference: tests/src/McpInspector/Tools/PhpStructureToolTest.php

<?php
declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use Tests\McpInspector\McpToolTestCase;

final class RagStoreToolTest extends McpToolTestCase
{
    public function test_stores_content(): void
    {
        $result = $this->executeTool('rag-store', [
            'content' => 'Test content',
            'type' => 'testing',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContains('stored', $result->getText());
    }
}
```

### Console Command Test

```php
// Reference: tests/src/Feature/Console/ConsoleTestCase.php

<?php
declare(strict_types=1);

namespace Tests\Feature\Console\Rag;

use Tests\Feature\Console\ConsoleTestCase;

final class RagIndexCommandTest extends ConsoleTestCase
{
    public function test_indexes_files(): void
    {
        $this->runCommand('rag:index', [
            'path' => $this->tempDir,
            '--type' => 'test',
        ]);

        $this->assertCommandSucceeded();
        $this->assertOutputContains('indexed');
    }
}
```

---

## Useful Interfaces

### From CTX

| Interface              | Purpose               | Location                       |
|------------------------|-----------------------|--------------------------------|
| `DirectoriesInterface` | Get project paths     | `src/DirectoriesInterface.php` |
| `ProjectAwareRequest`  | Multi-project support | `src/McpServer/Project/`       |

### From Spiral

| Interface          | Purpose             | Package        |
|--------------------|---------------------|----------------|
| `InjectableConfig` | Config base class   | `spiral/core`  |
| `Bootloader`       | Module registration | `spiral/boot`  |
| `FilesInterface`   | File operations     | `spiral/files` |
| `LoggerInterface`  | Logging             | `psr/log`      |

---

## External Dependencies

### Already Available

```json
{
  "spiral/core": "~3.15.8",
  "spiral/console": "~3.15.8",
  "spiral/files": "~3.15.8",
  "spiral/boot": "~3.15.8",
  "symfony/finder": "^6.0 | ^7.0",
  "psr/log": "^3.0"
}
```

### SQLite (Built-in PHP)

```php
// No additional dependencies needed
$pdo = new \PDO('sqlite:' . $path);
```

---

## File Locations Quick Reference

### Where to Create New Files

| Component     | Location                    |
|---------------|-----------------------------|
| Value Objects | `src/Rag/Document/`         |
| Storage       | `src/Rag/Storage/`          |
| Chunking      | `src/Rag/Chunker/`          |
| Embedding     | `src/Rag/Embedding/`        |
| MCP Tools     | `src/Rag/MCP/Tools/`        |
| Console       | `src/Rag/Console/`          |
| Config        | `src/Rag/RagConfig.php`     |
| Bootloader    | `src/Rag/RagBootloader.php` |

### Where to Modify Existing Files

| Purpose         | File                                  | Section                |
|-----------------|---------------------------------------|------------------------|
| Register tools  | `src/McpServer/ActionsBootloader.php` | `actions()` method     |
| Add dependency  | `src/McpServer/ActionsBootloader.php` | `defineDependencies()` |
| Config defaults | `src/McpServer/ActionsBootloader.php` | `init()` method        |

---

## Helpful Commands

```bash
# Run specific test
./vendor/bin/phpunit tests/src/McpInspector/Tools/RagStoreToolTest.php

# Run all MCP tests
composer test-mcp

# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run static analysis
composer psalm
```

---

## Links

- [Spiral Framework Docs](https://spiral.dev/docs)
- [MCP Protocol Spec](https://modelcontextprotocol.io/)
- [SQLite FTS5 Docs](https://www.sqlite.org/fts5.html)
