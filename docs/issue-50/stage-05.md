# Stage 5: Bootloader & Console Command

## Overview

Create the Spiral Framework integration: a bootloader that registers the singleton registry and a console command to
build the schema. This connects the schema builder to the application lifecycle.

## Files

**CREATE:**

- `src/JsonSchema/SchemaBuilderBootloader.php` - Registers singleton, core definitions
- `src/JsonSchema/Console/BuildSchemaCommand.php` - `schema:build` command

**MODIFY:**

- `src/Application/Kernel.php` - Register `SchemaBuilderBootloader`
- `src/Application/Bootloader/ConsoleBootloader.php` - Register command (if needed)

## Code References

- `src/Source/Registry/SourceRegistryBootloader.php:12-18` - Singleton registry pattern
- `src/Modifier/ModifierBootloader.php:14-17` - Simple bootloader pattern
- `src/Console/SchemaCommand.php:1-95` - Existing schema command (inspiration)
- `src/Console/BaseCommand.php:22-61` - Base command pattern
- `src/Application/Kernel.php:65-97` - Bootloader registration

## Implementation Details

### SchemaBuilderBootloader

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\JsonSchema\Console\BuildSchemaCommand;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class SchemaBuilderBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            SchemaDefinitionRegistryInterface::class => SchemaDefinitionRegistry::class,
            SchemaDefinitionRegistry::class => SchemaDefinitionRegistry::class,
        ];
    }

    public function init(ConsoleBootloader $console): void
    {
        $console->addCommand(BuildSchemaCommand::class);
    }

    /**
     * Register core definitions used by multiple modules.
     * Called after all module bootloaders have registered their schemas.
     */
    public function boot(SchemaDefinitionRegistry $registry): void
    {
        $this->registerCoreDefinitions($registry);
        $this->registerRootSchema($registry);
    }

    private function registerCoreDefinitions(SchemaDefinitionRegistry $registry): void
    {
        // These are shared definitions referenced by multiple sources
        // Only register if not already registered by a module
        
        // sourcePaths - used by file, github, gitlab, tree, composer
        if (!$registry->has('sourcePaths')) {
            $registry->register(
                \Butschster\ContextGenerator\JsonSchema\Definition\OneOfDefinition::create(
                    'sourcePaths',
                    Property\StringProperty::new()->description('Path to file or directory'),
                    Property\ArrayProperty::strings()->description('List of paths'),
                ),
            );
        }

        // filePattern - used by file, github, gitlab, git_diff, tree, composer
        if (!$registry->has('filePattern')) {
            $registry->register(
                \Butschster\ContextGenerator\JsonSchema\Definition\OneOfDefinition::create(
                    'filePattern',
                    Property\StringProperty::new()->description('Pattern to match files'),
                    Property\ArrayProperty::strings()->description('List of patterns'),
                ),
            );
        }

        // patternConstraint - used by file, gitlab, git_diff, tree, composer
        if (!$registry->has('patternConstraint')) {
            $registry->register(
                \Butschster\ContextGenerator\JsonSchema\Definition\OneOfDefinition::create(
                    'patternConstraint',
                    Property\StringProperty::new(),
                    Property\ArrayProperty::strings(),
                ),
            );
        }

        // visibilityOptions - used by php-content-filter, php-docs
        if (!$registry->has('visibilityOptions')) {
            $registry->define('visibilityOptions')
                ->description('Visibility filter options')
                ->property('type', Property\StringProperty::new()->enum(['array']))
                ->property('items', Property\ObjectProperty::new()
                    ->property('type', Property\StringProperty::new()->enum(['string']))
                    ->property('enum', Property\ArrayProperty::of(
                        Property\StringProperty::new()->enum(['public', 'protected', 'private']),
                    )),
                );
        }
    }

    private function registerRootSchema(SchemaDefinitionRegistry $registry): void
    {
        // $schema property
        $registry->addRootProperty(
            '$schema',
            Property\StringProperty::new()->description('URL to the JSON schema'),
        );

        // Root oneOf - at least one of documents, prompts, or tools required
        $registry->addRootOneOf(['required' => ['documents']]);
        $registry->addRootOneOf(['required' => ['prompts']]);
        $registry->addRootOneOf(['required' => ['tools']]);
    }
}
```

### BuildSchemaCommand

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\JsonSchema\Exception\SchemaValidationException;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'schema:build',
    description: 'Build JSON schema from registered definitions',
)]
final class BuildSchemaCommand extends BaseCommand
{
    #[Option(
        name: 'output',
        shortcut: 'o',
        description: 'Output file path (relative to project root)',
    )]
    protected string $outputPath = 'json-schema.json';

    #[Option(
        name: 'validate',
        description: 'Only validate schema without writing file',
    )]
    protected bool $validateOnly = false;

    #[Option(
        name: 'pretty',
        description: 'Pretty print JSON output',
    )]
    protected bool $pretty = true;

    public function __invoke(
        SchemaDefinitionRegistry $registry,
        FilesInterface $files,
        DirectoriesInterface $dirs,
    ): int {
        $this->output->title('JSON Schema Builder');

        // Show registered definitions count
        $definitionCount = $registry->count();
        $this->output->writeln(sprintf(
            'Found <info>%d</info> registered definitions',
            $definitionCount,
        ));

        if ($definitionCount === 0) {
            $this->output->warning('No definitions registered. Schema will be minimal.');
        }

        // Build schema (includes validation)
        try {
            $schema = $registry->build();
        } catch (SchemaValidationException $e) {
            $this->output->error('Schema validation failed');
            
            foreach ($e->errors as $error) {
                $this->output->writeln("  <error>✗</error> {$error}");
            }
            
            if ($e->missingReferences !== []) {
                $this->output->newLine();
                $this->output->writeln('<comment>Missing definitions:</comment>');
                foreach ($e->missingReferences as $ref) {
                    $this->output->writeln("  - {$ref}");
                }
            }
            
            return Command::FAILURE;
        }

        $this->output->success('Schema validated successfully');

        // If validate-only, stop here
        if ($this->validateOnly) {
            $this->output->note('Validate-only mode - no file written');
            return Command::SUCCESS;
        }

        // Encode to JSON
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($schema, $flags);
        if ($json === false) {
            $this->output->error('Failed to encode schema to JSON: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        // Determine output path
        $fullPath = $dirs->getRootPath()->join($this->outputPath)->toString();

        // Write file
        if (!$files->write($fullPath, $json . "\n")) {
            $this->output->error("Failed to write schema to {$fullPath}");
            return Command::FAILURE;
        }

        $this->output->success("Schema written to {$this->outputPath}");
        
        // Show some stats
        $this->output->table(
            ['Metric', 'Value'],
            [
                ['Definitions', (string) count($schema['definitions'] ?? [])],
                ['Root properties', (string) count($schema['properties'] ?? [])],
                ['File size', $this->formatBytes(strlen($json))],
            ],
        );

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 2) . ' MB';
    }
}
```

### Kernel Registration

Add to `src/Application/Kernel.php`:

```php
use Butschster\ContextGenerator\JsonSchema\SchemaBuilderBootloader;

// In defineBootloaders() array, add near the end:
SchemaBuilderBootloader::class,
```

**Important**: Place `SchemaBuilderBootloader` after all source/modifier bootloaders so they register their schemas
first, then the core bootloader fills in shared definitions.

### Command Output Example

```
$ php ctx schema:build

JSON Schema Builder
===================

Found 45 registered definitions

 [OK] Schema validated successfully

 [OK] Schema written to json-schema.json

 -------------- -------
  Metric         Value
 -------------- -------
  Definitions    45
  Root props     12
  File size      48.3 KB
 -------------- -------
```

```
$ php ctx schema:build --validate

JSON Schema Builder
===================

Found 45 registered definitions

 [OK] Schema validated successfully

 ! [NOTE] Validate-only mode - no file written
```

```
$ php ctx schema:build  # with missing reference

JSON Schema Builder
===================

Found 44 registered definitions

 [ERROR] Schema validation failed

  ✗ Missing definition 'treeViewConfig' (referenced by: definitions.fileSource, definitions.gitlabSource)

Missing definitions:
  - treeViewConfig
```

## Definition of Done

- [ ] `SchemaBuilderBootloader` registered as singleton
- [ ] `SchemaDefinitionRegistry` injectable in other bootloaders
- [ ] `BuildSchemaCommand` registered and runnable
- [ ] Command validates schema before writing
- [ ] `--validate` flag works (no file write)
- [ ] `--output` flag allows custom path
- [ ] `--pretty` flag controls formatting (default: true)
- [ ] Error messages clearly show missing references
- [ ] Bootloader registered in Kernel after source/modifier bootloaders
- [ ] Integration test: command executes without errors

## Dependencies

**Requires:** Stage 4 (Registry & Validation)
**Enables:** Stage 6 (Core Schema Definitions), Stage 7 (Module Migration)
