# Configuration System Guide

## 1. Overview

The Configuration System is a flexible framework for loading, parsing, and managing configuration data from various
sources. It supports multiple file formats (JSON, YAML, PHP), imports from local and remote sources, and provides a
plugin-based architecture for extending functionality.

## 2. Purpose and Use Cases

This system is designed to:

- Load configuration from local or remote sources
- Support multiple configuration formats (JSON, YAML, PHP)
- Allow importing configurations from other locations
- Process configuration through a plugin pipeline
- Compile documents based on the configuration

**Common Use Cases:**

- Loading application configuration settings
- Creating a documentation builder that compiles documents from various sources
- Building a context generator that imports and processes content from different locations

## 3. Core Components Breakdown

### 3.1 Configuration Loading

The configuration loading process is handled by several key components:

#### `ConfigLoaderInterface`

The primary interface for configuration loaders, which defines methods for loading configurations.

```php
interface ConfigLoaderInterface
{
    public function load(): RegistryInterface;
    public function loadRawConfig(): array;
    public function isSupported(): bool;
}
```

#### `ConfigLoader`

The main implementation of `ConfigLoaderInterface` that uses readers and parsers to load configuration:

```php
final readonly class ConfigLoader implements ConfigLoaderInterface
{
    public function __construct(
        private string $configPath,
        private ReaderInterface $reader,
        private ConfigParserInterface $parser,
        private ?LoggerInterface $logger = null,
    ) {}
    
    // Implementation methods
}
```

#### `ConfigLoaderFactory`

Creates configuration loaders based on different scenarios:

```php
final readonly class ConfigLoaderFactory implements ConfigLoaderFactoryInterface
{
    // Creates loaders for directories, specific files, or string content
    public function create(string $configPath): ConfigLoaderInterface;
    public function createForFile(string $configPath): ConfigLoaderInterface;
    public function createFromString(string $jsonConfig): ConfigLoaderInterface;
}
```

#### `ConfigurationProvider`

Provides helpers for loading configuration from different sources:

```php
final readonly class ConfigurationProvider
{
    // Different methods for loading configuration
    public function fromString(string $jsonConfig): ConfigLoaderInterface;
    public function fromPath(string $configPath): ConfigLoaderInterface;
    public function fromDefaultLocation(): ConfigLoaderInterface;
}
```

### 3.2 Configuration Readers

Readers handle reading and parsing specific file formats:

#### `ReaderInterface`

```php
interface ReaderInterface
{
    public function read(string $path): array;
    public function supports(string $path): bool;
    public function getSupportedExtensions(): array;
}
```

Available readers:

- `JsonReader` - For JSON files (.json)
- `YamlReader` - For YAML files (.yaml, .yml)
- `PhpReader` - For PHP files (.php)
- `StringJsonReader` - For JSON content provided as a string

### 3.3 Configuration Parsing

The system uses a plugin-based approach to parse configuration:

#### `ConfigParserInterface`

```php
interface ConfigParserInterface
{
    public function parse(array $config): ConfigRegistry;
}
```

#### `ConfigParserPluginInterface`

```php
interface ConfigParserPluginInterface
{
    public function getConfigKey(): string;
    public function updateConfig(array $config, string $rootPath): array;
    public function parse(array $config, string $rootPath): ?RegistryInterface;
    public function supports(array $config): bool;
}
```

Key parser plugins:

- `ImportParserPlugin` - Handles import directives
- `DocumentsParserPlugin` - Processes document definitions
- `VariablesParserPlugin` - Handles variable substitution

### 3.4 Import System

The import system allows loading configuration from external sources:

#### `ImportSourceInterface`

```php
interface ImportSourceInterface
{
    public function getName(): string;
    public function supports(SourceConfigInterface $config): bool;
    public function load(SourceConfigInterface $config): array;
    public function allowedSections(): array;
}
```

Available import sources:

- `LocalImportSource` - Imports from local filesystem
- `UrlImportSource` - Imports from remote URLs

#### `ImportResolver`

Resolves and processes import directives, handling path resolution and circular dependency detection:

```php
final readonly class ImportResolver
{
    public function resolveImports(
        array $config,
        string $basePath,
        array &$parsedImports = [],
        CircularImportDetectorInterface $detector = new CircularImportDetector(),
    ): array;
}
```

### 3.5 Document Compilation

The document system uses the configuration to compile documents:

#### `Document`

Represents a document to be compiled:

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
}
```

#### `DocumentCompiler`

Compiles documents with their sources:

```php
final readonly class DocumentCompiler
{
    public function compile(Document $document): CompiledDocument;
    public function buildContent(ErrorCollection $errors, Document $document): CompiledDocument;
}
```

## 4. Configuration Structure

A typical configuration file structure:

```yaml
# Optional variable declarations
variables:
  BASE_PATH: /path/to/base
  API_VERSION: v1

# Import other configurations
import:
  - path: another-config.yaml  # Local path
    pathPrefix: api/v1          # Optional prefix for document output paths
  - type: url
    url: https://example.com/config.json

# Document definitions
documents:
  - description: API Documentation
    outputPath: docs/api.md
    overwrite: true    # Whether to overwrite existing file
    tags: [ api, docs ]  # Optional tags
    modifiers: [ ... ]   # Optional modifiers
    sources:
      - type: file     # Source type
        sourcePaths: # Paths to source files
          - src/Api
          - src/Controllers
```

## 5. Usage Examples

### Basic Configuration Loading

```php
// Using the ConfigurationProvider to load configuration
$provider = $container->get(ConfigurationProvider::class);

// Load from a specific path
$loader = $provider->fromPath('config/context.yaml');
$config = $loader->load();

// Load from default location
$loader = $provider->fromDefaultLocation();
$config = $loader->load();

// Load from a string
$jsonString = '{"documents": [...]}';
$loader = $provider->fromString($jsonString);
$config = $loader->load();
```

### Compiling Documents

```php
// Get the document compiler
$compiler = $container->get(DocumentCompiler::class);

// Get document registry
$registry = $container->get(ConfigLoaderInterface::class)->load();

// Compile each document
foreach ($registry->getItems() as $document) {
    $result = $compiler->compile($document);
    
    if ($result->errors->hasErrors()) {
        foreach ($result->errors as $error) {
            echo "Error: $error\n";
        }
    }
}
```

### Creating Import Sources

```php
// Creating a local import source
$localSource = new LocalImportSource($files, $readers);

// Creating a URL import source
$urlSource = new UrlImportSource($httpClient, $variableResolver);

// Register sources in the registry
$registry = new ImportSourceRegistry();
$registry->register($localSource);
$registry->register($urlSource);
```

## 6. Architecture Diagram

```
                 ┌───────────────────┐
                 │ConfigurationProvider│
                 └──────────┬────────┘
                            │
                            ▼
┌────────────────┐    ┌─────────────────┐    ┌───────────────────┐
│ConfigLoaderFactory│─▶│ConfigLoader     │─▶│ConfigParser       │
└────────────────┘    └─────────────────┘    └───────────┬───────┘
                                                         │
                      ┌───────────────────────────────────────────┐
                      │                                           │
                      ▼                                           ▼
             ┌──────────────────┐                     ┌──────────────────┐
             │Reader (JSON/YAML/PHP)│                     │ParserPluginRegistry│
             └──────────────────┘                     └──────────┬───────┘
                                                                 │
                      ┌───────────────────────────────────────────────┐
                      │                                           │
                      ▼                                           ▼
             ┌──────────────────┐                     ┌──────────────────┐
             │ImportParserPlugin │                     │DocumentsParserPlugin│
             └──────────┬───────┘                     └──────────────────┘
                        │
                        ▼
             ┌──────────────────┐
             │ImportResolver    │
             └──────────┬───────┘
                        │
           ┌────────────┴────────────┐
           │                         │
           ▼                         ▼
┌──────────────────┐       ┌──────────────────┐
│LocalImportSource │       │UrlImportSource   │
└──────────────────┘       └──────────────────┘
```

## 7. Common Implementation Patterns

### 1. Service Registration using Bootloaders

The system uses bootloaders to register services:

```php
final class ConfigLoaderBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            // Service registrations
            ConfigReaderRegistry::class => /* ... */,
            ConfigLoaderFactoryInterface::class => ConfigLoaderFactory::class,
            // ...
        ];
    }
}
```

### 2. Plugin Registration

Plugins are registered in a registry:

```php
$parserPluginRegistry = new ParserPluginRegistry([
    $importParserPlugin,
    $documentsParserPlugin,
    // Other plugins
]);
```

### 3. Error Handling

The system uses specialized exceptions and error collections:

```php
try {
    $config = $reader->read($path);
} catch (ReaderException $e) {
    $logger->error('Failed to read configuration', [
        'path' => $path,
        'error' => $e->getMessage(),
    ]);
    // Handle error appropriately
}
```

## 8. Best Practices

1. **Use Dependency Injection**: The system is designed to work with a DI container.

2. **Leverage Bootloaders**: Register services through bootloaders to ensure proper initialization order.

3. **Add Logging**: Most components accept a PSR logger for debugging and monitoring.

4. **Use Appropriate Readers**: Select the right reader for each file format.

5. **Handle Import Paths Carefully**: Be mindful of relative and absolute paths when importing.

6. **Watch for Circular Dependencies**: The import system has circular dependency detection but be careful with complex
   import graphs.

7. **Use Source Prefixing**: When importing configurations, use pathPrefix to organize outputs.

## 9. Extension Points

The system can be extended in several ways:

1. **Custom Readers**: Implement `ReaderInterface` for new file formats.

2. **Parser Plugins**: Create plugins implementing `ConfigParserPluginInterface`.

3. **Import Sources**: Implement `ImportSourceInterface` for new import sources.

4. **Document Sources**: Create new sources implementing `SourceInterface`.

5. **Modifiers**: Create modifiers that implement `Modifier` interface.

## 10. Troubleshooting

### Common Issues

1. **Configuration not found**
    - Check if the file exists and is readable
    - Verify the path is correct (relative vs. absolute)

2. **Import failures**
    - Check network connectivity for URL imports
    - Verify file permissions for local imports
    - Look for circular dependencies

3. **Parsing errors**
    - Validate the syntax of JSON/YAML files
    - Check for proper structure and required fields

4. **Document compilation issues**
    - Check if sources exist and are accessible
    - Verify output directories are writable
    - Look for errors in source parsing
