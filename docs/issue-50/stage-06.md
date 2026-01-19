# Stage 6: Core Schema Definitions

## Overview

Register all shared/core schema definitions that are used across multiple modules. This includes the root schema
structure (documents, prompts, tools, import, settings, etc.) and common definitions like `sourcePaths`, `modifiers`,
`treeViewConfig`, etc.

## Files

**MODIFY:**

- `src/JsonSchema/SchemaBuilderBootloader.php` - Expand `registerCoreDefinitions()` and `registerRootSchema()`

**CREATE:**

- `src/JsonSchema/CoreDefinitions/SourcePathsDefinition.php` - Helper for sourcePaths
- `src/JsonSchema/CoreDefinitions/ModifiersDefinition.php` - Helper for modifiers
- `src/JsonSchema/CoreDefinitions/TreeViewConfigDefinition.php` - Helper for treeView
- `src/JsonSchema/CoreDefinitions/InputSchemaDefinition.php` - Helper for inputSchema

Or alternatively, keep all in bootloader if simpler.

## Code References

- `json-schema.json:15-150` - Root properties structure
- `json-schema.json:580-620` - `sourcePaths`, `filePattern`, `patternConstraint`
- `json-schema.json:700-750` - `treeViewConfig` definition
- `json-schema.json:1350-1450` - `modifiers` definition
- `json-schema.json:550-580` - `inputSchema` definition
- `json-schema.json:170-350` - `document` and `source` definitions

## Implementation Details

### Root Schema Properties

Register in `SchemaBuilderBootloader::registerRootSchema()`:

```php
private function registerRootSchema(SchemaDefinitionRegistry $registry): void
{
    use Butschster\ContextGenerator\JsonSchema\Property\{
        StringProperty, ArrayProperty, ObjectProperty, BooleanProperty, RefProperty
    };
    use Butschster\ContextGenerator\JsonSchema\Combinator\OneOf;

    // $schema
    $registry->addRootProperty(
        '$schema',
        StringProperty::new()->description('URL to the JSON schema for this configuration file'),
    );

    // documents
    $registry->addRootProperty(
        'documents',
        ArrayProperty::of(RefProperty::to('document'))
            ->description('List of documents to generate')
            ->minItems(1),
    );

    // prompts
    $registry->addRootProperty(
        'prompts',
        ArrayProperty::of(RefProperty::to('prompt'))
            ->description('List of prompts to be registered'),
    );

    // tools
    $registry->addRootProperty(
        'tools',
        ArrayProperty::of(RefProperty::to('tool'))
            ->description('List of custom tools to be registered'),
    );

    // import
    $registry->addRootProperty(
        'import',
        ArrayProperty::of(
            OneOf::create(
                StringProperty::new()->description('Path shorthand'),
                RefProperty::to('importConfig'),
            ),
        )->description('List of external configuration files to import'),
    );

    // variables
    $registry->addRootProperty(
        'variables',
        ObjectProperty::new()
            ->description('Custom variables to use throughout configuration')
            ->additionalProperties(
                OneOf::create(
                    StringProperty::new(),
                    Property\NumberProperty::new(),
                    BooleanProperty::new(),
                ),
            ),
    );

    // exclude
    $registry->addRootProperty(
        'exclude',
        ObjectProperty::new()
            ->description('Global exclusion patterns')
            ->property('patterns', ArrayProperty::strings()
                ->description('Glob patterns to exclude files globally'))
            ->property('paths', ArrayProperty::strings()
                ->description('Specific paths to exclude globally')),
    );

    // settings
    $registry->addRootProperty(
        'settings',
        RefProperty::to('settings')->description('Global settings'),
    );

    // rag
    $registry->addRootProperty(
        'rag',
        RefProperty::to('ragConfig')->description('RAG configuration'),
    );

    // Root oneOf - at least one main section required
    $registry->addRootOneOf(['required' => ['documents']]);
    $registry->addRootOneOf(['required' => ['prompts']]);
    $registry->addRootOneOf(['required' => ['tools']]);
}
```

### Core Definitions

Register in `SchemaBuilderBootloader::registerCoreDefinitions()`:

```php
private function registerCoreDefinitions(SchemaDefinitionRegistry $registry): void
{
    $this->registerSourcePaths($registry);
    $this->registerFilePattern($registry);
    $this->registerPatternConstraint($registry);
    $this->registerTreeViewConfig($registry);
    $this->registerModifiers($registry);
    $this->registerInputSchema($registry);
    $this->registerDocument($registry);
    $this->registerSource($registry);
    $this->registerImportConfig($registry);
    $this->registerSettings($registry);
    $this->registerRagConfig($registry);
}

private function registerSourcePaths(SchemaDefinitionRegistry $registry): void
{
    if ($registry->has('sourcePaths')) {return;}
    
    // sourcePaths: string | string[]
    $registry->define('sourcePaths')
        ->property('oneOf', ArrayProperty::of(
            ObjectProperty::new()
                ->property('type', StringProperty::new()->enum(['string']))
                ->property('description', StringProperty::new()),
        ));
    
    // Actually, for oneOf at definition level, we need a different approach
    // Let's use a custom definition or inline it where used
}

private function registerTreeViewConfig(SchemaDefinitionRegistry $registry): void
{
    if ($registry->has('treeViewConfig')) {return;}

    $registry->define('treeViewConfig')
        ->property('enabled', BooleanProperty::new()
            ->description('Whether to show the tree view')
            ->default(true))
        ->property('showSize', BooleanProperty::new()
            ->description('Include file/directory sizes')
            ->default(false))
        ->property('showLastModified', BooleanProperty::new()
            ->description('Include last modified dates')
            ->default(false))
        ->property('showCharCount', BooleanProperty::new()
            ->description('Include character counts')
            ->default(false))
        ->property('includeFiles', BooleanProperty::new()
            ->description('Include files or only directories')
            ->default(true))
        ->property('maxDepth', IntegerProperty::new()
            ->description('Maximum tree depth (0 for unlimited)')
            ->minimum(0)
            ->default(0))
        ->property('dirContext', ObjectProperty::new()
            ->description('Context/descriptions for specific directories')
            ->additionalProperties(StringProperty::new()));
}

private function registerModifiers(SchemaDefinitionRegistry $registry): void
{
    if ($registry->has('modifiers')) {return;}

    $registry->define('modifiers')
        ->description('List of content modifiers to apply')
        ->property('type', StringProperty::new()->enum(['array']))
        ->property('items', OneOf::create(
            StringProperty::new()->description('Modifier identifier or alias'),
            ObjectProperty::new()
                ->required('name')
                ->property('name', StringProperty::new()
                    ->enum(['php-content-filter', 'php-docs', 'sanitizer'])
                    ->description('Modifier identifier'))
                ->property('options', ObjectProperty::new()
                    ->description('Modifier options')),
        ));
}

private function registerInputSchema(SchemaDefinitionRegistry $registry): void
{
    if ($registry->has('inputSchema')) {return;}

    $registry->define('inputSchema')
        ->description('JSON Schema defining input arguments')
        ->property('type', StringProperty::new()->enum(['object'])->default('object'))
        ->property('properties', ObjectProperty::new()
            ->description('Properties defining available arguments')
            ->additionalProperties(ObjectProperty::new()
                ->required('type')
                ->property('type', StringProperty::new()
                    ->enum(['string', 'number', 'boolean', 'array', 'object']))
                ->property('description', StringProperty::new())))
        ->property('required', ArrayProperty::strings()
            ->description('List of required arguments'))
        ->required('properties');
}

private function registerDocument(SchemaDefinitionRegistry $registry): void
{
    if ($registry->has('document')) {return;}

    $registry->define('document')
        ->required('description', 'outputPath', 'sources')
        ->property('description', StringProperty::new()
            ->description('Human-readable description'))
        ->property('outputPath', StringProperty::new()
            ->description('Path where document will be saved')
            ->pattern('^[\\w\\-./]+\\.[\\w]+$'))
        ->property('overwrite', BooleanProperty::new()
            ->description('Whether to overwrite existing files')
            ->default(true))
        ->property('sources', ArrayProperty::of(RefProperty::to('source'))
            ->description('List of content sources'))
        ->property('modifiers', RefProperty::to('modifiers'))
        ->property('tags', ArrayProperty::strings()
            ->description('List of tags'));
}

private function registerSource(SchemaDefinitionRegistry $registry): void
{
    if ($registry->has('source')) {return;}

    // Base source with type discriminator
    // The full source definition uses anyOf with conditionals
    // This is complex - each source type adds its own conditional
    $registry->define('source')
        ->required('type')
        ->property('type', StringProperty::new()
            ->enum([
                'file', 'url', 'text', 'github', 'gitlab',
                'git_diff', 'tree', 'mcp', 'composer', 'docs',
            ])
            ->description('Type of content source'))
        ->property('description', StringProperty::new()
            ->description('Human-readable description'))
        ->property('modifiers', RefProperty::to('modifiers'))
        ->property('tags', ArrayProperty::strings());
    
    // Note: Conditional if/then for each source type will be added
    // by source modules in Stage 7
}
```

### Handling Complex Definitions

Some definitions like `sourcePaths` are `oneOf` at the root level (not an object with properties). For these, we need a
special definition type or handle inline:

```php
// Option 1: Special OneOfDefinition class
final class OneOfDefinition implements DefinitionInterface
{
    private function __construct(
        private readonly string $name,
        private readonly array $options,
    ) {}

    public static function create(string $name, PropertyInterface ...$options): self
    {
        return new self($name, $options);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'oneOf' => array_map(fn($o) => $o->toArray(), $this->options),
        ];
    }

    public function getReferences(): array
    {
        $refs = [];
        foreach ($this->options as $option) {
            $refs = [...$refs, ...$option->getReferences()];
        }
        return $refs;
    }
}

// Usage:
$registry->register(OneOfDefinition::create(
    'sourcePaths',
    StringProperty::new()->description('Path to file or directory'),
    ArrayProperty::strings()->description('List of paths'),
));
```

## Definition of Done

- [ ] All root properties registered ($schema, documents, prompts, tools, import, variables, exclude, settings, rag)
- [ ] Root oneOf constraints registered (documents OR prompts OR tools required)
- [ ] `sourcePaths` definition registered (oneOf string | string[])
- [ ] `filePattern` definition registered (oneOf string | string[])
- [ ] `patternConstraint` definition registered
- [ ] `treeViewConfig` definition registered with all options
- [ ] `modifiers` definition registered with name + options structure
- [ ] `inputSchema` definition registered
- [ ] `document` definition registered with required fields
- [ ] `source` base definition registered with type enum
- [ ] `importConfig` definition registered (local + url variants)
- [ ] `settings` definition registered (mcp, gitlab sections)
- [ ] `ragConfig` definition registered
- [ ] `visibilityOptions` definition registered
- [ ] Running `schema:build` produces valid (but incomplete) schema
- [ ] No validation errors for core definitions

## Dependencies

**Requires:** Stage 5 (Bootloader & Console)
**Enables:** Stage 7 (Module Migration)
