# Import System Guide

## 1. Overview

The Import System is a component of the Configuration System that allows loading and merging configuration from multiple
sources, both local files and remote URLs. It provides a flexible way to organize configuration across multiple files,
reuse common configurations, and fetch remote configurations.

## 2. Purpose and Use Cases

The Import System serves several key purposes:

- **Modularize Configuration**: Split large configuration files into smaller, more manageable pieces
- **Share Common Configurations**: Reuse configuration components across multiple projects
- **Fetch Remote Configurations**: Load configuration from remote sources like APIs or repositories
- **Create Configuration Hierarchies**: Build layered configurations with defaults and overrides

**Common Use Cases:**

- Importing base configuration templates
- Splitting a large context configuration into smaller domain-specific files
- Fetching remote configurations maintained in a central repository
- Creating organization-wide configuration standards with local overrides

## 3. Components Breakdown

### 3.1 Core Import Components

#### `ImportParserPlugin`

Responsible for processing the `import` section in configuration files:

```php
final readonly class ImportParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ImportResolver $importResolver,
        #[LoggerPrefix(prefix: 'import-parser')]
        private ?LoggerInterface $logger = null,
    ) {}
    
    // Methods for parsing imports
}
```

#### `ImportResolver`

Handles the actual resolution of import sources:

```php
final readonly class ImportResolver
{
    public function resolveImports(
        array $config,
        string $basePath,
        array &$parsedImports = [],
        CircularImportDetectorInterface $detector = new CircularImportDetector(),
    ): array;
    
    // Methods for handling different import types
}
```

#### `CircularImportDetector`

Prevents infinite import loops:

```php
final class CircularImportDetector implements CircularImportDetectorInterface
{
    private array $importStack = [];
    
    public function wouldCreateCircularDependency(string $path): bool;
    public function beginProcessing(string $path): void;
    public function endProcessing(string $path): void;
}
```

### 3.2 Import Sources

The system supports different types of import sources:

#### `ImportSourceInterface`

The core interface for all import sources:

```php
interface ImportSourceInterface
{
    public function getName(): string;
    public function supports(SourceConfigInterface $config): bool;
    public function load(SourceConfigInterface $config): array;
    public function allowedSections(): array;
}
```

#### `AbstractImportSource`

Base implementation for import sources:

```php
abstract class AbstractImportSource implements ImportSourceInterface
{
    protected function readConfig(string $path, ReaderInterface $reader): array;
    protected function processSelectiveImports(array $config, SourceConfigInterface $sourceConfig): array;
    // Other helper methods
}
```

#### `LocalImportSource`

Imports configuration from local filesystem:

```php
final class LocalImportSource extends AbstractImportSource
{
    public function __construct(
        private readonly FilesInterface $files,
        private readonly ConfigReaderRegistry $readers,
        ?LoggerInterface $logger = null,
    ) {}

    // Implementation methods
}
```

#### `UrlImportSource`

Imports configuration from remote URLs:

```php
final class UrlImportSource extends AbstractImportSource
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly VariableResolver $variables,
        ?LoggerInterface $logger = null,
    ) {}

    // Implementation methods
}
```

### 3.3 Import Source Configurations

For each source type, there's a corresponding configuration:

#### `SourceConfigInterface`

```php
interface SourceConfigInterface
{
    public function getPath(): string;
    public function getType(): string;
}
```

#### `LocalSourceConfig`

```php
final class LocalSourceConfig extends AbstractSourceConfig
{
    public function __construct(
        string $path,
        private readonly string $absolutePath,
        private readonly bool $hasWildcard = false,
        ?string $pathPrefix = null,
        ?array $selectiveDocuments = null,
    ) {}
    
    // Methods specific to local source config
}
```

#### `UrlSourceConfig`

```php
final readonly class UrlSourceConfig implements SourceConfigInterface
{
    public function __construct(
        public string $url,
        public int $ttl = 300,
        public readonly array $headers = [],
    ) {}
    
    // Methods specific to URL source config
}
```

### 3.4 Path Handling Components

The system includes utilities for path handling:

#### `PathMatcher`

Converts glob patterns to regex for path matching:

```php
final readonly class PathMatcher
{
    private string $regex;
    
    public function __construct(private string $pattern);
    public static function containsWildcard(string $path): bool;
    public function isMatch(string $path): bool;
}
```

#### `WildcardPathFinder`

Finds files matching a glob pattern:

```php
final readonly class WildcardPathFinder
{
    public function __construct(
        private FilesInterface $files,
        #[LoggerPrefix(prefix: 'wildcard-path-finder')]
        private ?LoggerInterface $logger = null,
    ) {}
    
    public function findMatchingPaths(string $pattern, string $basePath): array;
}
```

#### Path Prefixers

Apply prefixes to paths in imported configurations:

- `PathPrefixer` - Base abstract class
- `DocumentOutputPathPrefixer` - Applies prefixes to document output paths
- `SourcePathPrefixer` - Applies prefixes to source paths

## 4. Import Configuration Structure

The import section in a configuration file follows this structure:

```yaml
import:
  # Local file import (default type)
  - path: relative/path/to/file.yaml

  # Local file import with explicit type
  - type: local
    path: absolute/path/to/file.json

  # Local file import with wildcard matching
  - path: "configs/*.yaml"

  # Local file import with selective documents
  - path: all-documents.yaml
    docs: # Only import specific documents
      - "api/*.md"
      - "getting-started.md"

  # Local file import with path prefixing
  - path: components.yaml
    pathPrefix: ui/components  # Prefix for document output paths

  # Remote URL import
  - type: url
    url: https://example.com/config.json
    ttl: 3600  # Cache time in seconds
    headers: # Optional HTTP headers
      Authorization: "Bearer token123"
      Accept: "application/json"
```

## 5. Usage Examples

### Basic Import Usage

```yaml
# main-config.yaml
import:
  - path: base-config.yaml
  - path: project-specific.yaml

# Additional configuration that might override imported values
variables:
  VERSION: 2.0

documents:
  - description: Combined Documentation
    outputPath: docs/combined.md
    # ...
```

### Importing With Wildcards

```yaml
# Import all configuration files in a directory
import:
  - path: "configs/*.yaml"  # Match all YAML files in configs directory
  - path: "configs/**/*.yaml"  # Match all YAML files in configs and subdirectories
```

### Selective Document Import

```yaml
# Only import specific documents from a configuration file
import:
  - path: all-documents.yaml
    docs:
      - "api/*.md"  # All API documentation
      - "getting-started.md"  # Specific document
```

### URL Import With Authentication

```yaml
# Import configuration from a remote URL
import:
  - type: url
    url: https://api.example.com/config
    ttl: 1800  # Cache for 30 minutes
    headers:
      Authorization: "Bearer ${API_TOKEN}"  # Variable substitution
      Accept: "application/json"
```

### Import With Path Prefixing

```yaml
# Add prefix to all document output paths in the imported configuration
import:
  - path: components.yaml
    pathPrefix: ui/components
```

## 6. Architecture Diagram

```
┌─────────────────┐
│Configuration File│
└────────┬────────┘
         │
         ▼
┌────────────────┐    ┌──────────────────┐
│ImportParserPlugin│──▶│ImportResolver    │
└────────────────┘    └──────────┬───────┘
                                 │
         ┌─────────────────────────────────────────┐
         │                                         │
         ▼                                         ▼
┌──────────────────┐                    ┌──────────────────┐
│LocalSourceConfig │                    │UrlSourceConfig   │
└──────────┬───────┘                    └──────────┬───────┘
           │                                       │
           ▼                                       ▼
┌──────────────────┐                    ┌──────────────────┐
│LocalImportSource │                    │UrlImportSource   │
└──────────┬───────┘                    └──────────┬───────┘
           │                                       │
           │                                       │
           ▼                                       ▼
    ┌─────────────────┐                   ┌─────────────────┐
    │Configuration     │                   │Configuration     │
    │Reader            │                   │from URL         │
    └─────────┬───────┘                   └─────────┬───────┘
              │                                     │
              └────────────────┬──────────────────┘
                               │
                               ▼
                     ┌───────────────────┐
                     │Merged Configuration│
                     └───────────────────┘
```

## 7. Best Practices

### 1. Organize Imports Logically

- Group related configurations in separate files
- Use a hierarchical structure (base → domain → project)
- Place common configurations in a shared location

### 2. Be Mindful of Import Order

- Later imports override earlier ones for the same keys
- Use this to implement layered configurations (defaults → specifics)

### 3. Use Path Prefixing for Organization

- Apply path prefixes to keep output documents organized
- Match the prefix to the logical structure of your project

### 4. Cache Remote Imports

- Set appropriate TTL values for URL imports
- Longer for stable configurations, shorter for frequently changing ones

### 5. Use Selective Imports

- Only import what you need to keep configurations clean
- Use document selectors to pick specific parts of large configurations

### 6. Secure Remote Imports

- Use HTTPS for all URL imports
- Apply proper authentication headers
- Consider using environment variables for sensitive tokens

## 8. Extension Points

The Import System can be extended in several ways:

### 1. Custom Import Sources

Create a new import source by implementing `ImportSourceInterface`:

```php
final class CustomImportSource extends AbstractImportSource
{
    public function getName(): string
    {
        return 'custom';
    }
    
    public function supports(SourceConfigInterface $config): bool
    {
        return $config->getType() === 'custom';
    }
    
    public function load(SourceConfigInterface $config): array
    {
        // Custom loading logic
    }
    
    public function allowedSections(): array
    {
        return ['documents', 'variables'];
    }
}
```

### 2. Custom Source Configurations

Create a new source configuration by implementing `SourceConfigInterface`:

```php
final class CustomSourceConfig implements SourceConfigInterface
{
    public function __construct(
        private readonly string $customPath,
        private readonly array $options = [],
    ) {}
    
    public function getPath(): string
    {
        return $this->customPath;
    }
    
    public function getType(): string
    {
        return 'custom';
    }
    
    // Add custom methods as needed
}
```

### 3. Custom Path Prefixers

Create a new path prefixer by extending `PathPrefixer`:

```php
final readonly class CustomPathPrefixer extends PathPrefixer
{
    public function applyPrefix(array $config, string $pathPrefix): array
    {
        // Custom prefix application logic
        return $config;
    }
}
```

## 9. Troubleshooting

### Common Issues and Solutions

#### 1. Circular Import Detection

**Problem**: Error about circular imports detected.

**Solution**: Check your import structure for cycles:

- A imports B, B imports C, C imports A
- Use simpler import hierarchies

#### 2. File Not Found Errors

**Problem**: Imported files cannot be found.

**Solutions**:

- Check if paths are relative to the correct base directory
- Verify file permissions
- For wildcards, check if any matching files exist

#### 3. Network Issues with URL Imports

**Problem**: Unable to fetch from URL.

**Solutions**:

- Check network connectivity
- Verify URL is accessible and returns valid JSON/YAML
- Check authentication headers if required

#### 4. Unexpected Configuration Results

**Problem**: Final configuration doesn't match expectations.

**Solutions**:

- Check import order (later imports override earlier ones)
- Verify that all imports are processed successfully
- Look for merge conflicts in imported configurations

#### 5. Wildcard Path Issues

**Problem**: Wildcard patterns not matching expected files.

**Solutions**:

- Check pattern syntax
- Use `**` for recursive directory matching
- Check if files have the correct extensions

## 10. Implementation Notes

### Performance Considerations

1. **Caching**: URL imports are cached based on their TTL to avoid repeated network requests.

2. **Processed Imports Tracking**: The system tracks processed imports to avoid processing the same file multiple times.

3. **Selective Loading**: The system allows loading only specific parts of imported configurations.

### Security Considerations

1. **Local File Access**: Local imports are restricted to files within the project directory structure.

2. **URL Validation**: URL imports should be restricted to trusted domains in production environments.

3. **Variable Expansion**: Sensitive data like API tokens can be provided through environment variables rather than
   hardcoded in configuration.
