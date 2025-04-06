# Document Compilation Guide

## 1. Overview

The Document Compilation System is a component responsible for transforming configuration-defined documents into actual
output files. It processes document definitions, collects content from various sources, applies modifiers, and produces
compiled documents with proper formatting and structure.

## 2. Purpose and Use Cases

The Document Compilation System serves several key purposes:

- **Generate Documentation**: Create markdown, text, or other documentation formats
- **Aggregate Content**: Collect content from multiple sources into a single document
- **Transform Content**: Apply modifications and formatting to source content
- **Organize Documentation**: Structure documentation with consistent formatting and organization

**Common Use Cases:**

- Generating API documentation from source code
- Creating technical specifications from multiple input files
- Building knowledge bases from scattered content sources
- Producing user manuals with consistent formatting and organization

## 3. Components Breakdown

### 3.1 Document Definition

#### `Document` Class

The central entity representing a document to be compiled:

```php
final class Document implements \JsonSerializable
{
    public function __construct(
        public readonly string $description,
        public readonly string $outputPath,
        public readonly bool $overwrite = true,
        private array $modifiers = [],
        private array $tags = [],
        SourceInterface ...$sources,
    ) {}
    
    // Methods for managing sources, modifiers, and tags
    public function addSource(SourceInterface ...$sources): self;
    public function addModifier(Modifier ...$modifiers): self;
    public function addTag(string ...$tags): self;
    // Getter methods
}
```

#### `DocumentRegistry` Class

A collection of document definitions:

```php
final class DocumentRegistry implements RegistryInterface
{
    private array $documents = [];
    
    public function register(Document $document): self;
    public function getItems(): array;
    // RegistryInterface implementation
}
```

### 3.2 Document Compilation Process

#### `DocumentCompiler` Class

Responsible for compiling documents:

```php
final readonly class DocumentCompiler
{
    public function __construct(
        private FilesInterface $files,
        private SourceParserInterface $parser,
        private string $basePath,
        private SourceModifierRegistry $modifierRegistry,
        private VariableResolver $variables,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private ?LoggerInterface $logger = null,
    ) {}
    
    public function compile(Document $document): CompiledDocument;
    public function buildContent(ErrorCollection $errors, Document $document): CompiledDocument;
}
```

#### `CompiledDocument` Class

Represents the result of compilation:

```php
final readonly class CompiledDocument
{
    public function __construct(
        public string|\Stringable $content,
        public ErrorCollection $errors,
    ) {}
}
```

### 3.3 Error Handling

#### `ErrorCollection` Class

Collects errors during compilation:

```php
final class ErrorCollection implements \Countable, \IteratorAggregate
{
    private array $errors = [];
    
    public function add(\Stringable|string $error): void;
    public function hasErrors(): bool;
    public function count(): int;
    public function getIterator(): \Traversable;
}
```

#### `SourceError` Class

Represents an error from a specific source:

```php
final readonly class SourceError implements \Stringable
{
    public function __construct(
        public SourceInterface $source,
        public \Throwable $exception,
    ) {}
    
    public function getSourceDescription(): string;
    public function __toString(): string;
}
```

### 3.4 Document Parsing

#### `DocumentsParserPlugin` Class

Plugin for parsing document definitions from configuration:

```php
final readonly class DocumentsParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private SourceProviderInterface $sources,
        private ModifierResolver $modifierResolver = new ModifierResolver(),
        private ?LoggerInterface $logger = null,
    ) {}
    
    // Implementation methods for config parsing
}
```

## 4. Document Configuration Structure

A document definition in configuration follows this structure:

```yaml
documents:
  - description: "API Documentation"
    outputPath: "docs/api.md"
    overwrite: true    # Optional, default: true
    tags: # Optional tags for categorization
      - api
      - reference
    modifiers: # Optional modifiers to apply to all sources
      - type: trim
      - type: replace
        search: "oldText"
        replace: "newText"
    sources: # Content sources
      - type: file
        sourcePaths:
          - "src/Api"
          - "src/Controllers"
        # Source-specific options...

      - type: markdown
        content: |
          # Additional Content
          This is custom markdown content.
```

## 5. Compilation Process

The document compilation process follows these steps:

1. **Document Validation**: Verify the document has required fields (description, outputPath)
2. **Output Path Resolution**: Resolve variables in the output path
3. **Overwrite Check**: If `overwrite` is false and the file exists, skip compilation
4. **Content Building**: Build document content from all sources
    - Add document title (from description)
    - Add document tags if present
    - Process each source:
        - Add source description if present
        - Parse source content
        - Apply modifiers (source-specific and document-level)
        - Add content to the builder
5. **Error Collection**: Collect any errors that occur during compilation
6. **Directory Creation**: Ensure the output directory exists
7. **File Writing**: Write the compiled content to the output file

## 6. Usage Examples

### Basic Document Definition

```php
// Create a document
$document = Document::create(
    description: 'API Documentation',
    outputPath: 'docs/api.md',
    overwrite: true,
    tags: ['api', 'reference']
);

// Add sources
$document->addSource(
    new FileSource([
        'sourcePaths' => ['src/Api', 'src/Controllers'],
        'description' => 'API Source Files'
    ])
);

// Add modifiers
$document->addModifier(
    new TrimModifier(),
    new ReplaceModifier(['search' => '...', 'replace' => '...'])
);

// Compile the document
$compiler = new DocumentCompiler(/* dependencies */);
$result = $compiler->compile($document);

// Check for errors
if ($result->errors->hasErrors()) {
    foreach ($result->errors as $error) {
        echo "Error: $error\n";
    }
}
```

### Configuration-Based Compilation

```php
// Load document registry from configuration
$configLoader = $container->get(ConfigLoaderInterface::class);
$registry = $configLoader->load();

// Get document compiler
$compiler = $container->get(DocumentCompiler::class);

// Compile all documents
foreach ($registry->getItems() as $document) {
    try {
        $result = $compiler->compile($document);
        
        if ($result->errors->hasErrors()) {
            // Handle errors
        }
    } catch (\Throwable $e) {
        // Handle compilation failure
    }
}
```

## 7. Architecture Diagram

```
┌───────────────────┐
│ConfigurationSystem│
└────────┬──────────┘
         │
         ▼
┌───────────────────┐    ┌───────────────────┐
│DocumentsParserPlugin│──▶│DocumentRegistry   │
└────────┬──────────┘    └────────┬──────────┘
         │                        │
         │                        │
         ▼                        ▼
┌───────────────────┐    ┌───────────────────┐
│SourceProvider     │    │DocumentCompiler   │
└────────┬──────────┘    └────────┬──────────┘
         │                        │
         │                        │
         ▼                        ▼
┌───────────────────┐    ┌───────────────────┐    ┌───────────────────┐
│Source (File/URL/..)│──▶│ContentBuilder     │──▶│CompiledDocument   │
└───────────────────┘    └───────────────────┘    └───────────────────┘
         │                        ▲
         │                        │
         ▼                        │
┌───────────────────┐    ┌───────────────────┐
│SourceParser       │──▶│ModifiersApplier   │
└───────────────────┘    └───────────────────┘
```

## 8. Best Practices

### 1. Organize Document Sources

- Group related sources in a single document
- Use clear source descriptions to identify content origins
- Order sources logically for coherent document flow

### 2. Use Document Tags

- Apply consistent tags for better organization
- Use tags for categorization and filtering
- Consider hierarchical tag structures for complex documentation

### 3. Apply Appropriate Modifiers

- Use document-level modifiers for global formatting
- Apply source-specific modifiers for targeted transformations
- Combine modifiers to achieve complex transformations

### 4. Handle Errors Gracefully

- Check for compilation errors
- Provide meaningful error messages
- Consider fallback options for critical documents

### 5. Structure Output Paths

- Organize output paths logically
- Use variables for dynamic path components
- Ensure path directories exist

### 6. Logging and Debugging

- Enable logging for complex compilations
- Log each step of the compilation process
- Include source information in error messages

## 9. Extension Points

The Document Compilation System can be extended in several ways:

### 1. Custom Sources

Create new sources by implementing `SourceInterface`:

```php
final class CustomSource implements SourceInterface
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function parseContent(
        SourceParserInterface $parser, 
        ModifiersApplier $modifiers
    ): string
    {
        // Custom content generation logic
        $content = // ...
        
        // Apply modifiers to the content
        return $modifiers->apply($content);
    }
    
    // Implement other interface methods
}
```

### 2. Custom Modifiers

Create new modifiers by implementing the `Modifier` interface:

```php
final class CustomModifier implements Modifier
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    public function apply(string $content): string
    {
        // Custom modification logic
        return // transformed content
    }
    
    // Implement other interface methods
}
```

### 3. Custom Content Builders

Extend the content building process:

```php
final class CustomContentBuilder extends ContentBuilder
{
    // Override or add methods to customize content building
    public function addCustomBlock(string $content): self
    {
        // Custom block handling
        return $this;
    }
}
```

## 10. Troubleshooting

### Common Issues

#### 1. Output File Not Created

**Problem**: Document compilation completes but no output file is created.

**Solutions**:

- Check file permissions on the output directory
- Verify the output path is valid and resolved correctly
- Ensure the `overwrite` flag is true if the file already exists

#### 2. Missing or Empty Content

**Problem**: Compiled document exists but has missing or empty content.

**Solutions**:

- Check if source paths exist and contain content
- Verify that sources are correctly configured
- Look for errors in source parsing
- Check if modifiers are removing too much content

#### 3. Formatting Issues

**Problem**: Content is not formatted as expected.

**Solutions**:

- Review modifier ordering (they are applied in sequence)
- Check for source-specific formatting issues
- Verify content builder is working correctly

#### 4. Variable Resolution Failures

**Problem**: Variables in paths or content are not resolved.

**Solutions**:

- Check variable syntax (${VARIABLE} format)
- Verify variables are defined in the configuration
- Check if variable resolver is properly injected

#### 5. Performance Issues

**Problem**: Document compilation is slow for large documents.

**Solutions**:

- Limit the number of sources per document
- Use more specific source paths instead of broad directories
- Optimize expensive modifiers
- Consider caching mechanisms for frequently used content

## 11. Implementation Notes

### Performance Considerations

1. **Source Loading**: Sources are loaded and processed sequentially to minimize memory usage.

2. **Error Collection**: Errors are collected during compilation rather than failing immediately to allow partial
   compilation.

3. **Path Resolution**: Variables in paths are resolved before file operations to avoid unnecessary file system checks.

### Extensibility

1. **Plugin System**: The document parsing is implemented as a plugin to allow for different document formats.

2. **Modular Components**: Each component (sources, modifiers, builders) is designed to be extended.

3. **Content Building**: The content building process uses a builder pattern for flexibility and extensibility.
