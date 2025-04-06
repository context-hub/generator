# Readers and Parsers Guide

## 1. Overview

The Readers and Parsers system forms the foundation of the configuration loading process, enabling the application to
read configuration from various file formats and parse them into structured data. Readers handle the reading and initial
parsing of configuration files, while Parsers process the parsed data through a plugin-based architecture to transform
and enhance the configuration.

## 2. Purpose and Use Cases

The Readers and Parsers system serves several key purposes:

- **Format Support**: Read configuration from different file formats (JSON, YAML, PHP)
- **Extensible Parsing**: Process configuration through a plugin pipeline
- **Standardization**: Convert varying input formats into a standardized internal representation
- **Data Transformation**: Enhance raw configuration with additional processing (variables, imports)

**Common Use Cases:**

- Loading and parsing configuration files in different formats
- Processing configuration with specialized plugins for features like imports and variables
- Transforming raw configuration into structured registries for use in the application

## 3. Components Breakdown

### 3.1 Reader Components

#### `ReaderInterface`

The core interface for all configuration readers:

```php
interface ReaderInterface
{
    public function read(string $path): array;
    public function supports(string $path): bool;
    public function getSupportedExtensions(): array;
}
```

#### `AbstractReader`

Base implementation with common functionality:

```php
abstract readonly class AbstractReader implements ReaderInterface
{
    public function __construct(
        protected FilesInterface $files,
        protected ?LoggerInterface $logger = null,
    ) {}
    
    public function read(string $path): array;
    public function supports(string $path): bool;
    abstract protected function parseContent(string $content): array;
}
```

#### Available Readers

- `JsonReader` - Reads and parses JSON files
- `YamlReader` - Reads and parses YAML files
- `PhpReader` - Reads PHP files that return arrays or RegistryInterface instances
- `StringJsonReader` - Reads JSON from a string (used for inline configurations)

#### `ConfigReaderRegistry`

Registry for accessing readers by file extension:

```php
final readonly class ConfigReaderRegistry
{
    public function __construct(
        private array $readers,
    ) {}
    
    public function has(string $ext): bool;
    public function get(string $ext): ReaderInterface;
}
```

### 3.2 Parser Components

#### `ConfigParserInterface`

Interface for configuration parsers:

```php
interface ConfigParserInterface
{
    public function parse(array $config): ConfigRegistry;
}
```

#### `ConfigParser`

Main implementation that uses plugins for parsing:

```php
final readonly class ConfigParser implements ConfigParserInterface
{
    public function __construct(
        private string $rootPath,
        private ParserPluginRegistry $pluginRegistry,
        private ?LoggerInterface $logger = null,
    ) {}
    
    public function parse(array $config): ConfigRegistry;
    private function preprocessConfig(array $config): array;
}
```

#### `CompositeConfigParser`

Combines multiple parsers:

```php
final readonly class CompositeConfigParser implements ConfigParserInterface
{
    private array $parsers;
    
    public function __construct(
        ConfigParserInterface ...$parsers,
    ) {}
    
    public function parse(array $config): ConfigRegistry;
}
```

### 3.3 Parser Plugin System

#### `ConfigParserPluginInterface`

Interface for parser plugins:

```php
interface ConfigParserPluginInterface
{
    public function getConfigKey(): string;
    public function updateConfig(array $config, string $rootPath): array;
    public function parse(array $config, string $rootPath): ?RegistryInterface;
    public function supports(array $config): bool;
}
```

#### `ParserPluginRegistry`

Registry for parser plugins:

```php
final class ParserPluginRegistry
{
    public function __construct(
        private array $plugins = [],
    ) {}
    
    public function register(ConfigParserPluginInterface $plugin): void;
    public function getPlugins(): array;
}
```

#### Core Parser Plugins

- `ImportParserPlugin` - Processes `import` directives to include other configurations
- `DocumentsParserPlugin` - Processes `documents` section to create document definitions
- `VariablesParserPlugin` - Processes `variables` section for variable substitution

## 4. Reading and Parsing Process

The reading and parsing process follows these steps:

1. **Reader Selection**: Based on file extension, select an appropriate reader
2. **File Reading**: Read the file content using the selected reader
3. **Initial Parsing**: Parse the raw content into a PHP array structure
4. **Plugin Preprocessing**: Run the array through plugins that can update the configuration (e.g., imports)
5. **Plugin Parsing**: Process the updated configuration with plugins that generate registries
6. **Registry Collection**: Collect all registries into a ConfigRegistry

## 5. Configuration Formats

The system supports multiple configuration formats:

### 5.1 JSON Format

```json
{
  "variables": {
    "VERSION": "1.0",
    "BASE_PATH": "/docs"
  },
  "import": [
    {
      "path": "other-config.json"
    }
  ],
  "documents": [
    {
      "description": "API Documentation",
      "outputPath": "${BASE_PATH}/api.md",
      "sources": [
        {
          "type": "file",
          "sourcePaths": [
            "src/Api"
          ]
        }
      ]
    }
  ]
}
```

### 5.2 YAML Format

```yaml
variables:
  VERSION: 1.0
  BASE_PATH: /docs

import:
  - path: other-config.yaml

documents:
  - description: API Documentation
    outputPath: ${BASE_PATH}/api.md
    sources:
      - type: file
        sourcePaths:
          - src/Api
```

### 5.3 PHP Format

```php
<?php

return [
    'variables' => [
        'VERSION' => '1.0',
        'BASE_PATH' => '/docs',
    ],
    'import' => [
        [
            'path' => 'other-config.php',
        ],
    ],
    'documents' => [
        [
            'description' => 'API Documentation',
            'outputPath' => '${BASE_PATH}/api.md',
            'sources' => [
                [
                    'type' => 'file',
                    'sourcePaths' => ['src/Api'],
                ],
            ],
        ],
    ],
];
```

## 6. Usage Examples

### 6.1 Reading Configuration Files

```php
// Get reader registry
$readerRegistry = $container->get(ConfigReaderRegistry::class);

// Get reader for a specific file extension
$reader = $readerRegistry->get('json');

// Check if file is supported
if ($reader->supports('config.json')) {
    // Read and parse the file
    $config = $reader->read('config.json');
}
```

### 6.2 Parsing Configuration

```php
// Create a parser
$parser = new ConfigParser(
    rootPath: '/path/to/root',
    pluginRegistry: $container->get(ParserPluginRegistry::class),
    logger: $container->get(LoggerInterface::class)
);

// Parse configuration
$configRegistry = $parser->parse($config);

// Access specific registries
if ($configRegistry->has('documents')) {
    $documentRegistry = $configRegistry->get('documents', DocumentRegistry::class);
    $documents = $documentRegistry->getItems();
}
```

### 6.3 Using the ConfigLoader

```php
// Get config loader
$loader = $container->get(ConfigLoaderInterface::class);

// Load configuration
try {
    $registry = $loader->load();
    
    // Process the registry
    $documents = $registry->getItems();
    
    foreach ($documents as $document) {
        // Process each document
    }
} catch (ConfigLoaderException $e) {
    // Handle loading error
}
```

### 6.4 Creating a Custom Parser Plugin

```php
final readonly class CustomParserPlugin implements ConfigParserPluginInterface
{
    public function getConfigKey(): string
    {
        return 'custom';
    }
    
    public function supports(array $config): bool
    {
        return isset($config['custom']) && is_array($config['custom']);
    }
    
    public function updateConfig(array $config, string $rootPath): array
    {
        // Optional preprocessing logic
        return $config;
    }
    
    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }
        
        // Parse the custom section
        $customConfig = $config['custom'];
        
        // Create and return a registry
        $registry = new CustomRegistry();
        
        // Process the custom configuration
        
        return $registry;
    }
}
```

## 7. Architecture Diagram

```
┌─────────────────┐
│Configuration File│
└────────┬────────┘
         │
         ▼
┌────────────────┐    ┌───────────────────┐
│ConfigReaderRegistry│─▶│Reader (JSON/YAML/PHP)│
└────────────────┘    └─────────┬─────────┘
                                │
                                ▼
                      ┌───────────────────┐
                      │Initial PHP Array   │
                      └─────────┬─────────┘
                                │
                                ▼
┌────────────────┐    ┌───────────────────┐
│ParserPluginRegistry│◀─┤ConfigParser       │
└────────┬───────┘    └───────────────────┘
         │
         ▼
┌────────────────┐
│Parser Plugins   │
│                │
│1. ImportParser │
│2. VariablesParser│
│3. DocumentsParser│
│4. Custom Plugins│
└────────┬───────┘
         │
         ▼
┌───────────────────┐
│ConfigRegistry     │
│(with parsed data) │
└───────────────────┘
```

## 8. Best Practices

### 1. Choose the Right Format

- **JSON**: Good for machine-readable configs and integration with other systems
- **YAML**: Better for human-edited configs with comments and less syntax overhead
- **PHP**: Useful for dynamic configurations or when PHP logic is needed

### 2. Organize Parser Plugins

- Register plugins in the right order (preprocessing first, then content parsers)
- Use dependency injection to provide plugins with needed services
- Design plugins to be independent and focused on a single responsibility

### 3. Error Handling

- Wrap parsing operations in try-catch blocks to handle format errors
- Provide detailed error messages about where parsing failed
- Log parsing errors with context information

### 4. Extension and Customization

- Create custom readers for specialized formats
- Implement parser plugins for domain-specific configuration sections
- Use the composite parser pattern for complex parsing pipelines

### 5. Performance Considerations

- Cache parsed configurations when appropriate
- Use lazy loading for expensive operations
- Consider the overhead of parsing complex formats (YAML is slower than JSON)

## 9. Extension Points

### 1. Custom Readers

Create a custom reader by implementing `ReaderInterface`:

```php
final readonly class CustomFormatReader implements ReaderInterface
{
    public function __construct(
        private FilesInterface $files,
        private ?LoggerInterface $logger = null,
    ) {}
    
    public function read(string $path): array
    {
        // Read and parse the custom format
        return [/* parsed data */];
    }
    
    public function supports(string $path): bool
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $extension === 'custom';
    }
    
    public function getSupportedExtensions(): array
    {
        return ['custom'];
    }
}
```

### 2. Custom Parser Plugins

Create a custom parser plugin by implementing `ConfigParserPluginInterface`:

```php
final readonly class CustomConfigPlugin implements ConfigParserPluginInterface
{
    public function getConfigKey(): string
    {
        return 'custom_section';
    }
    
    public function supports(array $config): bool
    {
        return isset($config['custom_section']);
    }
    
    public function updateConfig(array $config, string $rootPath): array
    {
        // Preprocessing logic
        return $config;
    }
    
    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        // Parsing logic
        return new CustomRegistry(/* parsed data */);
    }
}
```

### 3. Custom Registries

Create a custom registry by implementing `RegistryInterface`:

```php
final class CustomRegistry implements RegistryInterface
{
    private array $items = [];
    
    public function add($item): self
    {
        $this->items[] = $item;
        return $this;
    }
    
    public function getType(): string
    {
        return 'custom_items';
    }
    
    public function getItems(): array
    {
        return $this->items;
    }
    
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
```

## 10. Troubleshooting

### Common Issues

#### 1. Parse Errors in Configuration Files

**Problem**: Reader fails to parse a configuration file.

**Solutions**:

- Check file syntax (JSON/YAML validation)
- Verify file encoding (use UTF-8)
- Look for special characters that might cause parsing issues

#### 2. Plugin Not Processing a Section

**Problem**: A parser plugin doesn't process its expected section.

**Solutions**:

- Verify the section exists in the config
- Check if plugin's `supports()` method returns true
- Ensure plugin is registered in the right order

#### 3. Missing Imported Configurations

**Problem**: Imported configurations are not included.

**Solutions**:

- Check import paths (relative vs. absolute)
- Verify file permissions and existence
- Look for circular import issues

#### 4. Variables Not Resolved

**Problem**: Variables in the configuration are not replaced with their values.

**Solutions**:

- Check variable syntax (${VAR_NAME})
- Verify variables are defined in the configuration
- Ensure VariablesParserPlugin is registered and running

#### 5. Registry Type Mismatches

**Problem**: Getting a registry with an unexpected type.

**Solutions**:

- Check if the registry exists in the ConfigRegistry
- Verify the expected class type matches the actual registry type
- Look for plugin parsing issues that might create wrong registry types
