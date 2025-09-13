# Creating New Source Types in CTX

This guide explains how to create a new source type for the CTX context generator. We'll use the recently implemented
`GuideSource` as a reference example.

## Overview

CTX uses a modular architecture for source types, following these key patterns:

- **Source Class**: Extends `BaseSource` and implements data structure
- **Factory Class**: Creates source instances from configuration
- **Fetcher Class**: Handles content retrieval and formatting
- **Bootloader Class**: Registers the source with the system
- **Schema Definition**: Adds JSON schema validation

## Step-by-Step Implementation

### 1. Create the Source Directory Structure

```bash
mkdir -p src/Source/YourSource
```

### 2. Implement the Source Class

Create `src/Source/YourSource/YourSource.php`:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\YourSource;

use Butschster\ContextGenerator\Source\BaseSource;

/**
 * Source for [your source description]
 */
final class YourSource extends BaseSource
{
    /**
     * @param string $description Human-readable description
     * @param array<non-empty-string> $tags
     */
    public function __construct(
        public readonly string $yourProperty,
        // Add your properties here
        string $description = '',
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags);
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'your_source',
            ...parent::jsonSerialize(),
            'yourProperty' => $this->yourProperty,
            // Add your properties here
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
```

### 3. Implement the Factory Class

Create `src/Source/YourSource/YourSourceFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\YourSource;

use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Factory for creating YourSource instances
 */
final readonly class YourSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'your_source';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating your source', [
            'config' => $config,
        ]);

        // Validate required properties
        if (!isset($config['yourProperty']) || !\is_string($config['yourProperty'])) {
            throw new \RuntimeException('Your source must have a "yourProperty" string property');
        }

        return new YourSource(
            yourProperty: $config['yourProperty'],
            description: $config['description'] ?? '',
            tags: $config['tags'] ?? [],
        );
    }
}
```

### 4. Implement the Fetcher Class

Create `src/Source/YourSource/YourSourceFetcher.php`:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\YourSource;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\Block\TextBlock;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for your sources
 * @implements SourceFetcherInterface<YourSource>
 */
final readonly class YourSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private VariableResolver $variableResolver = new VariableResolver(),
        #[LoggerPrefix(prefix: 'your-source')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof YourSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof YourSource) {
            $errorMessage = 'Source must be an instance of YourSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $description = $this->variableResolver->resolve($source->getDescription());

        $this->logger?->info('Fetching your source content', [
            'description' => $description,
            'yourProperty' => $source->yourProperty,
        ]);

        // Create builder
        $builder = $this->builderFactory
            ->create()
            ->addDescription($description);

        // Add your content logic here
        $content = $this->variableResolver->resolve($source->yourProperty);
        $builder->addBlock(new TextBlock($modifiersApplier->apply($content, 'your.ext'), 'CONTENT'));

        $builder->addSeparator();

        $finalContent = $builder->build();
        $this->logger?->info('Your source content fetched successfully', [
            'contentLength' => \strlen($finalContent),
        ]);

        return $finalContent;
    }
}
```

### 5. Implement the Bootloader Class

Create `src/Source/YourSource/YourSourceBootloader.php`:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\YourSource;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class YourSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            YourSourceFetcher::class => YourSourceFetcher::class,
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        YourSourceFactory $factory,
    ): void {
        $registry->register(YourSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
```

### 6. Register the Source in the Kernel

Add your bootloader to `src/Application/Kernel.php`:

```php
// Add the import
use Butschster\ContextGenerator\Source\YourSource\YourSourceBootloader;

// Add to the sources section in defineBootloaders()
// Sources
TextSourceBootloader::class,
FileSourceBootloader::class,
// ... other sources
YourSourceBootloader::class, // Add this line
```

### 7. Update the JSON Schema

Edit `json-schema.json`:

1. **Add to the source type enum**:

```json
"enum": [
"file",
"url",
"text",
// ... other types
"your_source"
],
```

2. **Add the conditional schema reference**:

```json
{
  "if": {
    "properties": {
      "type": {
        "const": "your_source"
      }
    }
  },
  "then": {
    "$ref": "#/definitions/yourSource"
  }
}
```

3. **Add the source definition in the `definitions` section**:

```json
"yourSource": {
"type": "object",
"description": "Source for [your description]",
"required": [
"type",
"yourProperty"
],
"properties": {
"type": {
"type": "string",
"enum": [
"your_source"
],
"description": "Source type - your_source"
},
"yourProperty": {
"type": "string",
"description": "Description of your property"
},
"description": {
"type": "string",
"description": "Human-readable description of the source"
},
"tags": {
"type": "array",
"items": {
"type": "string"
},
"description": "List of tags for this source"
}
},
"additionalProperties": false
}
```

## Testing Your Source Type

### 1. Run Code Style Checks

```bash
composer cs-fix
```

### 2. Run Tests

```bash
composer test
```

### 3. Create a Test Configuration

Create a YAML file to test your source:

```yaml
documents:
  - description: "Test of YourSource"
    outputPath: ".context/test-your-source.md"
    sources:
      - type: your_source
        yourProperty: "test value"
        description: "Test source"
        tags: [ "test" ]
```

### 4. Generate Context

```bash
php ctx generate -c your-test.yaml
```

## Key Architecture Patterns

### BaseSource Extension

- Always extend `BaseSource` for common functionality
- Override `jsonSerialize()` to include your properties
- Use readonly properties for immutability

### Factory Pattern

- Extend `AbstractSourceFactory`
- Implement validation in the `create()` method
- Use descriptive error messages

### Fetcher Pattern

- Implement `SourceFetcherInterface`
- Use `ContentBuilderFactory` for consistent formatting
- Apply modifiers to content for processing
- Use structured logging for debugging

### Bootloader Registration

- Register both fetcher and factory
- Follow the naming convention: `{Type}SourceBootloader`

### Schema Integration

- Add type to enum for validation
- Create conditional schema for type-specific validation
- Define complete schema with required and optional properties

## Common Content Blocks

The CTX system provides several content block types:

- `TextBlock` - Plain text content with tags
- `CodeBlock` - Code content with syntax highlighting
- `CommentBlock` - Comments and documentation

## Variable Resolution

Use `VariableResolver` to process configuration values:

```php
$resolvedValue = $this->variableResolver->resolve($source->property);
```

This handles environment variable substitution and other dynamic values.

## Error Handling

- Throw `\RuntimeException` for configuration errors
- Throw `\InvalidArgumentException` for invalid source types
- Use structured logging for debugging information

## Best Practices

1. **Naming**: Use descriptive names following the pattern `{Type}Source`
2. **Validation**: Always validate required configuration properties
3. **Logging**: Include comprehensive logging for debugging
4. **Documentation**: Add clear descriptions and examples
5. **Testing**: Create test configurations to verify functionality
6. **Schema**: Provide complete JSON schema validation
7. **Immutability**: Use readonly properties where possible
8. **Error Messages**: Provide clear, actionable error messages

## Real-World Example

The `GuideSource` implementation demonstrates all these patterns:

- Stores information about sources as guidelines
- Supports examples and metadata
- Uses structured content building
- Includes comprehensive schema validation
- Follows all architectural patterns

You can find the complete implementation in `src/Source/Guide/` as a reference for your own source types.

## Additional Resources & References

### **Core Interfaces and Contracts**

Study these key interfaces to understand the system contracts:

- `src/Source/SourceInterface.php` - Base source contract
- `src/Source/Registry/SourceFactoryInterface.php` - Factory contract
- `src/Source/Fetcher/SourceFetcherInterface.php` - Fetcher contract
- `src/Source/Registry/SourceRegistryInterface.php` - Registry contract

### **Base Classes**

Extend these base classes for common functionality:

- `src/Source/BaseSource.php` - Base source implementation
- `src/Source/Registry/AbstractSourceFactory.php` - Base factory with utilities
- `src/Application/Bootloader/` - Spiral bootloader patterns

### **Existing Source Examples**

Reference these implementations for different complexity levels:

#### **Simple Sources**

- `src/Source/Text/` - Minimal text source (good starting point)
- `src/Source/Tree/` - Directory tree visualization

#### **Intermediate Sources**

- `src/Source/Url/` - HTTP content fetching with selectors
- `src/Source/Guide/` - Documentation with examples and metadata

#### **Advanced Sources**

- `src/Source/File/` - Complex file system operations with filtering
- `src/Source/Github/` - External API integration with authentication
- `src/Source/MCP/` - Model Context Protocol integration

#### **Specialized Sources**

- `src/Source/GitDiff/` - Git operations with multiple render strategies
- `src/Source/Composer/` - Package manager integration
- `src/Source/Docs/` - Documentation system integration

### **Content Building System**

Learn about content formatting from these components:

- `src/Lib/Content/ContentBuilderFactory.php` - Content builder creation
- `src/Lib/Content/Block/` - Available content block types:
    - `TextBlock.php` - Plain text with tags
    - `CodeBlock.php` - Code with syntax highlighting
    - `CommentBlock.php` - Comments and documentation
- `src/Lib/Content/Renderer/` - Content rendering to markdown

### **Configuration and Schema**

Understand configuration patterns:

- `json-schema.json` - Complete JSON schema definitions
- `context.yaml` - Example configuration file
- `src/Config/` - Configuration parsing and validation
- Examples in existing source definitions for schema patterns

### **Testing Patterns**

Study testing approaches:

- `tests/src/Source/` - Source-specific test examples
- `tests/src/Integration/` - Integration test patterns
- Test configurations in individual source directories

### **Utility Classes**

Leverage these helper components:

#### **Variable Processing**

- `src/Lib/Variable/VariableResolver.php` - Environment variable resolution
- Support for `${VAR}` and `{{VAR}}` syntax

#### **Content Modification**

- `src/Modifier/` - Content transformation system
- `src/Modifier/ModifiersApplierInterface.php` - Modifier application

#### **Logging and Debugging**

- `src/Application/Logger/LoggerPrefix.php` - Structured logging
- Use logger prefixes for debugging specific sources

### **Architecture Documentation**

For deeper architectural understanding:

- `src/Application/Kernel.php` - Application bootstrapping
- `src/Source/Registry/` - Source registration system
- Spiral Framework documentation for bootloader patterns

### **Configuration Examples**

Find configuration examples in:

- Project root `context.yaml` - Production configuration
- Individual source directories - Specific examples
- `examples/` directory (if present) - Additional samples

### **Command Line Interface**

Understand CLI usage:

```bash
php ctx --help              # General help
php ctx generate --help     # Generation command help
php ctx schema              # View JSON schema
php ctx init                # Create new configuration
```

### **Development Workflow**

Follow this development process:

1. **Study** existing similar sources in `src/Source/`
2. **Review** contracts in interface files
3. **Implement** following the patterns in base classes
4. **Test** using the composer commands
5. **Validate** with JSON schema generation
6. **Document** using the Guide source type

### **Common Patterns Reference**

#### **Error Handling**

- Configuration errors: `\RuntimeException`
- Type errors: `\InvalidArgumentException`
- Structured error messages with context

#### **Immutability**

- Use `readonly` properties where possible
- Immutable DTOs and value objects
- No mutable state in source classes

#### **Dependency Injection**

- Constructor injection following Spiral patterns
- Service registration via bootloaders
- Interface-based dependencies

### **Getting Help**

When developing new sources:

1. **Check** existing implementations for similar functionality
2. **Review** interface documentation and contracts
3. **Test** incrementally with small configurations
4. **Use** verbose logging (`-vvv`) to debug issues
5. **Validate** schema with `php ctx schema` command