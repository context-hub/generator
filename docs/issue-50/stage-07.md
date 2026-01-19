# Stage 7: Module Schema Migration

## Overview

Migrate all existing source and modifier bootloaders to register their JSON Schema definitions. This is the largest
stage - each module's bootloader gets an injection of `SchemaDefinitionRegistry` and registers its specific schema.

## Files

**MODIFY:**

- `src/Source/File/FileSourceBootloader.php` - Add fileSource schema
- `src/Source/Url/UrlSourceBootloader.php` - Add urlSource schema
- `src/Source/Text/TextSourceBootloader.php` - Add textSource schema
- `src/Source/Github/GithubSourceBootloader.php` - Add githubSource schema
- `src/Source/Gitlab/GitlabSourceBootloader.php` - Add gitlabSource, gitlabServerConfig schemas
- `src/Source/GitDiff/GitDiffSourceBootloader.php` - Add gitDiffSource, gitDiffRender schemas
- `src/Source/Tree/TreeSourceBootloader.php` - Add treeSource schema
- `src/Source/Composer/ComposerSourceBootloader.php` - Add composerSource schema
- `src/Source/Mcp/McpSourceBootloader.php` - Add mcpSource, mcpServerConfig schemas (if exists)
- `src/Source/Docs/DocsSourceBootloader.php` - Add docsSource schema
- `src/Modifier/PhpContentFilter/PhpContentFilterBootloader.php` - Add php-content-filter schema
- `src/Modifier/PhpDocs/PhpDocsModifierBootloader.php` - Add php-docs schema
- `src/Modifier/Sanitizer/SanitizerModifierBootloader.php` - Add sanitizer schema
- `src/Tool/ToolBootloader.php` - Add tool, toolCommand, httpRequest schemas (if exists)
- `src/Prompt/PromptBootloader.php` - Add prompt schema (if exists)

**CREATE:**

- `tests/Integration/JsonSchema/SchemaComparisonTest.php` - Compare generated vs existing schema

## Code References

- `json-schema.json:650-750` - fileSource definition
- `json-schema.json:750-800` - urlSource definition
- `json-schema.json:800-830` - textSource definition
- `json-schema.json:830-900` - githubSource definition
- `json-schema.json:900-1000` - gitlabSource, gitlabServerConfig definitions
- `json-schema.json:1000-1100` - gitDiffSource, gitDiffRender definitions
- `json-schema.json:1100-1180` - treeSource definition
- `json-schema.json:1180-1250` - composerSource definition
- `json-schema.json:1250-1350` - mcpSource, mcpServerConfig definitions
- `json-schema.json:450-550` - tool, toolCommand, httpRequest definitions
- `json-schema.json:550-650` - prompt definition
- `json-schema.json:1350-1500` - php-content-filter, php-docs, sanitizer definitions

## Implementation Details

### Pattern for Module Schema Registration

Each bootloader follows this pattern:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\JsonSchema\Property\{
    StringProperty, BooleanProperty, ArrayProperty, RefProperty, ObjectProperty, IntegerProperty
};
use Butschster\ContextGenerator\JsonSchema\Combinator\OneOf;
// ... other imports

final class FileSourceBootloader extends Bootloader
{
    // ... existing code ...

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        FileSourceFactory $factory,
        SchemaDefinitionRegistry $schemaRegistry,  // <-- ADD THIS
    ): void {
        $registry->register(FileSourceFetcher::class);
        $sourceRegistry->register($factory);
        
        $this->registerSchema($schemaRegistry);  // <-- ADD THIS
    }

    private function registerSchema(SchemaDefinitionRegistry $registry): void
    {
        $registry->define('fileSource')
            ->required('sourcePaths')
            ->property('sourcePaths', RefProperty::to('sourcePaths'))
            ->property('filePattern', RefProperty::to('filePattern')->default('*.*'))
            ->property('excludePatterns', ArrayProperty::strings()
                ->description('Patterns to exclude files')
                ->default([]))
            ->property('notPath', ArrayProperty::strings()
                ->description('Patterns to exclude by path')
                ->default([]))
            ->property('path', RefProperty::to('patternConstraint'))
            ->property('contains', RefProperty::to('patternConstraint'))
            ->property('notContains', RefProperty::to('patternConstraint'))
            ->property('size', RefProperty::to('patternConstraint'))
            ->property('date', RefProperty::to('patternConstraint'))
            ->property('ignoreUnreadableDirs', BooleanProperty::new()
                ->description('Whether to ignore unreadable directories')
                ->default(false))
            ->property('showTreeView', BooleanProperty::new()
                ->description('Whether to show directory tree')
                ->default(true))
            ->property('treeView', OneOf::create(
                BooleanProperty::new()->description('Whether to show tree view'),
                RefProperty::to('treeViewConfig'),
            )->description('Tree view configuration'))
            ->property('modifiers', RefProperty::to('modifiers'));
    }
}
```

### FileSourceBootloader Schema

```php
private function registerSchema(SchemaDefinitionRegistry $registry): void
{
    $registry->define('fileSource')
        ->required('sourcePaths')
        ->property('sourcePaths', RefProperty::to('sourcePaths'))
        ->property('filePattern', RefProperty::to('filePattern')->default('*.*'))
        ->property('excludePatterns', ArrayProperty::strings()->default([]))
        ->property('notPath', ArrayProperty::strings()->default([]))
        ->property('path', RefProperty::to('patternConstraint'))
        ->property('contains', RefProperty::to('patternConstraint'))
        ->property('notContains', RefProperty::to('patternConstraint'))
        ->property('size', RefProperty::to('patternConstraint'))
        ->property('date', RefProperty::to('patternConstraint'))
        ->property('ignoreUnreadableDirs', BooleanProperty::new()->default(false))
        ->property('showTreeView', BooleanProperty::new()->default(true))
        ->property('treeView', OneOf::create(
            BooleanProperty::new(),
            RefProperty::to('treeViewConfig'),
        ))
        ->property('modifiers', RefProperty::to('modifiers'));
}
```

### UrlSourceBootloader Schema

```php
private function registerSchema(SchemaDefinitionRegistry $registry): void
{
    $registry->define('urlSource')
        ->required('urls')
        ->property('urls', ArrayProperty::of(
            StringProperty::new()->format('uri'),
        )->minItems(1)->description('List of URLs to fetch'))
        ->property('selector', StringProperty::new()
            ->description('CSS selector to extract specific content'))
        ->property('headers', ObjectProperty::new()
            ->description('Custom HTTP headers')
            ->additionalProperties(StringProperty::new()));
}
```

### TextSourceBootloader Schema

```php
private function registerSchema(SchemaDefinitionRegistry $registry): void
{
    $registry->define('textSource')
        ->required('content')
        ->property('content', StringProperty::new()
            ->description('Text content'))
        ->property('tag', StringProperty::new()
            ->description('Tag to help LLM understand content')
            ->default('instruction'));
}
```

### GitDiffSourceBootloader Schema

```php
private function registerSchema(SchemaDefinitionRegistry $registry): void
{
    // gitDiffRender
    $registry->define('gitDiffRender')
        ->property('strategy', StringProperty::new()
            ->enum(['raw', 'llm'])
            ->default('raw'))
        ->property('showStats', BooleanProperty::new()->default(true))
        ->property('showLineNumbers', BooleanProperty::new()->default(false))
        ->property('contextLines', IntegerProperty::new()
            ->minimum(0)
            ->default(3));

    // gitDiffSource
    $registry->define('gitDiffSource')
        ->property('repository', StringProperty::new()
            ->description('Path to git repository'))
        ->property('render', OneOf::create(
            RefProperty::to('gitDiffRender'),
            StringProperty::new()->enum(['raw', 'llm']),
        ))
        ->property('commit', StringProperty::new()
            ->enum([
                'last', 'last-5', 'last-10', 'last-week', 'last-month',
                'unstaged', 'staged', 'wip', 'main-diff', 'master-diff',
                'develop-diff', 'today', 'last-24h', 'yesterday',
                'last-2weeks', 'last-quarter', 'last-year',
                'stash', 'stash-last', 'stash-1', 'stash-2', 'stash-3',
                'stash-all', 'stash-latest-2', 'stash-latest-3', 'stash-latest-5',
                'stash-before-pull', 'stash-wip', 'stash-untracked', 'stash-index',
            ])
            ->default('staged'))
        ->property('filePattern', RefProperty::to('filePattern')->default('*.*'))
        ->property('path', RefProperty::to('patternConstraint')->default([]))
        ->property('notPath', ArrayProperty::strings()->default([]))
        ->property('contains', RefProperty::to('patternConstraint')->default([]))
        ->property('notContains', RefProperty::to('patternConstraint')->default([]))
        ->property('showStats', BooleanProperty::new()->default(true))
        ->property('modifiers', RefProperty::to('modifiers'));
}
```

### SanitizerModifierBootloader Schema

```php
private function registerSchema(SchemaDefinitionRegistry $registry): void
{
    $registry->define('sanitizer')
        ->property('rules', ArrayProperty::of(
            OneOf::create(
                // Keyword rule
                ObjectProperty::new()
                    ->required('type', 'keywords')
                    ->property('type', StringProperty::new()->enum(['keyword']))
                    ->property('name', StringProperty::new())
                    ->property('keywords', ArrayProperty::strings())
                    ->property('replacement', StringProperty::new())
                    ->property('caseSensitive', BooleanProperty::new())
                    ->property('removeLines', BooleanProperty::new()),
                // Regex rule
                ObjectProperty::new()
                    ->required('type')
                    ->property('type', StringProperty::new()->enum(['regex']))
                    ->property('name', StringProperty::new())
                    ->property('patterns', ObjectProperty::new()
                        ->additionalProperties(StringProperty::new()))
                    ->property('usePatterns', ArrayProperty::of(
                        StringProperty::new()->enum([
                            'credit-card', 'email', 'api-key', 'ip-address',
                            'jwt', 'phone-number', 'password-field', 'url',
                            'social-security', 'aws-key', 'private-key', 'database-conn',
                        ]),
                    )),
                // Comment rule
                ObjectProperty::new()
                    ->required('type')
                    ->property('type', StringProperty::new()->enum(['comment']))
                    ->property('name', StringProperty::new())
                    ->property('fileHeaderComment', StringProperty::new())
                    ->property('classComment', StringProperty::new())
                    ->property('methodComment', StringProperty::new())
                    ->property('frequency', IntegerProperty::new()->minimum(0))
                    ->property('randomComments', ArrayProperty::strings()),
            ),
        )->description('Array of sanitization rules'));
}
```

### Integration Test

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\JsonSchema;

use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Tests\TestCase;

final class SchemaComparisonTest extends TestCase
{
    public function testGeneratedSchemaMatchesExisting(): void
    {
        $registry = $this->getContainer()->get(SchemaDefinitionRegistry::class);
        
        $generated = $registry->build();
        $existing = json_decode(
            file_get_contents(__DIR__ . '/../../../json-schema.json'),
            true,
        );
        
        // Compare definitions exist
        $generatedDefs = array_keys($generated['definitions'] ?? []);
        $existingDefs = array_keys($existing['definitions'] ?? []);
        
        sort($generatedDefs);
        sort($existingDefs);
        
        $this->assertEquals(
            $existingDefs,
            $generatedDefs,
            'Definition keys should match',
        );
        
        // Compare root properties
        $this->assertEquals(
            array_keys($existing['properties'] ?? []),
            array_keys($generated['properties'] ?? []),
            'Root properties should match',
        );
    }

    public function testGeneratedSchemaValidatesExistingConfigs(): void
    {
        $registry = $this->getContainer()->get(SchemaDefinitionRegistry::class);
        $schema = $registry->build();
        
        // Use a JSON Schema validator to test against sample configs
        // This requires a validation library like justinrainbow/json-schema
    }
}
```

## Migration Order

Recommended order (based on dependencies):

1. **Text source** (simplest, no refs to other source types)
2. **URL source** (simple, no complex refs)
3. **File source** (uses treeViewConfig, modifiers)
4. **Tree source** (similar to file)
5. **Git diff source** (uses gitDiffRender)
6. **GitHub source** (similar to file)
7. **GitLab source** (uses gitlabServerConfig)
8. **Composer source** (uses treeViewConfig)
9. **MCP source** (uses mcpServerConfig)
10. **Docs source** (simple)
11. **PHP Content Filter modifier**
12. **PHP Docs modifier**
13. **Sanitizer modifier** (complex rules)
14. **Tool definitions**
15. **Prompt definitions**

## Definition of Done

- [ ] All source bootloaders inject `SchemaDefinitionRegistry`
- [ ] `fileSource` schema registered in `FileSourceBootloader`
- [ ] `urlSource` schema registered in `UrlSourceBootloader`
- [ ] `textSource` schema registered in `TextSourceBootloader`
- [ ] `githubSource` schema registered in `GithubSourceBootloader`
- [ ] `gitlabSource`, `gitlabServerConfig` schemas registered
- [ ] `gitDiffSource`, `gitDiffRender` schemas registered
- [ ] `treeSource` schema registered
- [ ] `composerSource` schema registered
- [ ] `mcpSource`, `mcpServerConfig` schemas registered
- [ ] `docsSource` schema registered
- [ ] `php-content-filter` modifier schema registered
- [ ] `php-docs` modifier schema registered
- [ ] `sanitizer` modifier schema registered
- [ ] `tool`, `toolCommand`, `httpRequest` schemas registered
- [ ] `prompt` schema registered
- [ ] `importFilter` schema registered
- [ ] `schema:build` generates complete schema without errors
- [ ] Integration test passes: generated matches existing schema structure
- [ ] All existing `context.yaml` files validate against generated schema
- [ ] Documentation updated with module registration examples

## Dependencies

**Requires:** Stage 6 (Core Schema Definitions)
**Enables:** Production use, CI integration
