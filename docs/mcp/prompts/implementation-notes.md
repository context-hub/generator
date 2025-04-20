# Prompt Tagging and Import Filtering Implementation

This document provides technical details about the implementation of the prompt tagging and import filtering system.

## Architecture Overview

The implementation consists of several components:

1. **Filter Configuration**: 
   - Stores filter settings from import configuration in `FilterConfig` class
   - Supports filtering by IDs and tags with include/exclude functionality

2. **Filter Strategies**:
   - Defines matching logic (`ANY` or `ALL`) for filter conditions
   - Implemented as PHP 8.1+ enum in `FilterStrategy`

3. **Filter Interface and Implementations**:
   - Common interface `PromptFilterInterface` with `shouldInclude()` method
   - Concrete filter implementations for different filtering requirements:
     - `IdPromptFilter`: Filters prompts by their IDs
     - `TagPromptFilter`: Filters prompts by tag combinations
     - `CompositePromptFilter`: Combines multiple filters with AND/OR logic

4. **Integration with Import System**:
   - Modified `AbstractSourceConfig` to support filter configuration
   - Added filter support to `LocalSourceConfig` and `UrlSourceConfig`
   - Enhanced `PromptConfigMerger` to apply filters during import process

## Component Details

### FilterConfig

`FilterConfig` stores and normalizes the raw filter configuration from YAML:

```php
final readonly class FilterConfig implements \JsonSerializable
{
    public function __construct(private ?array $config = null) {}
    
    public function getConfig(): ?array { ... }
    public function isEmpty(): bool { ... }
    public static function fromArray(?array $config): self { ... }
}
```

### PromptFilterInterface

The common interface for all filter implementations:

```php
interface PromptFilterInterface
{
    /**
     * Checks if the prompt configuration should be included based on filter criteria.
     */
    public function shouldInclude(array $promptConfig): bool;
}
```

### Filter Strategies

The main filter implementations:

1. **IdPromptFilter**: Includes prompts with matching IDs
2. **TagPromptFilter**: Filters prompts based on tag criteria:
   - Includes prompts with tags matching the include list (based on strategy)
   - Excludes prompts with tags matching the exclude list
3. **CompositePromptFilter**: Combines multiple filters using AND/OR logic

### PromptFilterFactory

Factory class that builds appropriate filter instances based on configuration:

```php
final readonly class PromptFilterFactory
{
    public function createFromConfig(?array $filterConfig): ?PromptFilterInterface { ... }
}
```

### Import System Integration

1. **Source Configurations**:
   - `SourceConfigInterface` now includes `getFilter()` method
   - `AbstractSourceConfig` stores `FilterConfig` instance
   - Concrete implementations (`LocalSourceConfig`, `UrlSourceConfig`) parse filter configuration

2. **Config Merging**:
   - `PromptConfigMerger` uses `PromptFilterFactory` to create filters
   - Applies filters during merging process to include/exclude prompts

## Example Usage

### Basic Import with ID Filtering:

```yaml
import:
  - type: url
    url: "https://example.com/prompts.yaml"
    filter:
      ids: ["prompt1", "prompt2"]
```

### Complex Import with Tag Filtering:

```yaml
import:
  - type: local
    path: "./prompts.yaml"
    filter:
      tags:
        include: ["coding", "python"]
        exclude: ["advanced"]
      match: "all"
```

## Performance Considerations

- Filter evaluation happens during import merging, adding minimal overhead
- Filters are applied in memory without requiring database queries
- Complex filter combinations are optimized through early rejection patterns

## Future Enhancements

Potential areas for future enhancement:

1. **Regular Expression Support**: Add regex pattern matching for IDs and tags
2. **Nested Tags**: Support hierarchical tag structures (e.g., "coding:python")
3. **Dynamic Filtering**: Runtime filtering capabilities for prompt queries
4. **CLI Integration**: Command-line tools for importing with filters
