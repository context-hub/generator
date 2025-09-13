# Creating New MCP Tools in CTX

This guide explains how to create new MCP (Model Context Protocol) tools in the CTX context generator.

## Overview

CTX uses a modern attribute-based approach for defining MCP tools with:

- **Typed DTOs** for input validation using Spiral's JSON Schema Generator
- **Automatic schema generation** from PHP types and attributes
- **Clean separation** between input validation and business logic

## Step-by-Step Guide

### 1. Create the Input DTO Class

Create a DTO (Data Transfer Object) class to define your tool's input parameters:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\YourCategory\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Enum;
use Spiral\JsonSchemaGenerator\Attribute\Constraint\Range;

final readonly class YourToolRequest
{
    public function __construct(
        #[Field(
            description: 'Description of this required parameter',
        )]
        public string $requiredParam,
        
        #[Field(
            description: 'Description of optional parameter with default',
            default: 'default-value',
        )]
        public string $optionalParam = 'default-value',
        
        #[Field(
            description: 'Numeric parameter with range validation',
            default: 10,
        )]
        #[Range(min: 1, max: 100)]
        public int $numericParam = 10,
        
        #[Field(
            description: 'Enum parameter with allowed values',
            default: 'option1',
        )]
        #[Enum(values: ['option1', 'option2', 'option3'])]
        public string $enumParam = 'option1',
        
        #[Field(
            description: 'Optional nullable parameter',
        )]
        public ?string $nullableParam = null,
    ) {}
}
```

### 2. Create the Tool Action Class

Create the main tool action class:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\YourCategory;``

use Butschster\ContextGenerator\McpServer\Action\Tools\YourCategory\Dto\YourToolRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'your-tool-name',
    description: 'Clear description of what your tool does and when to use it',
    title: 'Human-Readable Tool Title',
)]
#[InputSchema(class: YourToolRequest::class)]
final readonly class YourToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        // Inject other dependencies you need
    ) {}

    #[Post(path: '/tools/call/your-tool-name', name: 'tools.your-tool-name')]
    public function __invoke(YourToolRequest $request): CallToolResult
    {
        $this->logger->info('Processing your-tool-name tool');

        try {
            // Access typed parameters directly from the request DTO
            $result = $this->performYourLogic(
                requiredParam: $request->requiredParam,
                optionalParam: $request->optionalParam,
                numericParam: $request->numericParam,
                enumParam: $request->enumParam,
                nullableParam: $request->nullableParam,
            );

            return new CallToolResult([
                new TextContent(
                    text: $result,
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error in your-tool-name tool', [
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }

    private function performYourLogic(
        string $requiredParam,
        string $optionalParam,
        int $numericParam,
        string $enumParam,
        ?string $nullableParam,
    ): string {
        // Your tool's business logic here
        return 'Tool result';
    }
}
```

## Available Spiral JSON Schema Attributes

### Core Attributes

- **`#[Field]`** - Basic field definition with description, title, default value
- **`#[AdditionalProperties]`** - Control additional properties on objects

### Validation Constraints

- **`#[Enum]`** - Restrict to specific values: `#[Enum(values: ['a', 'b', 'c'])]`
- **`#[Range]`** - Numeric ranges: `#[Range(min: 0, max: 100)]`
- **`#[Length]`** - String length: `#[Length(min: 1, max: 255)]`
- **`#[Pattern]`** - Regex validation: `#[Pattern(pattern: '^[a-z]+$')]`
- **`#[MultipleOf]`** - Numeric multiple: `#[MultipleOf(value: 5)]`
- **`#[Items]`** - Array item constraints

### Field Attribute Options

```php
#[Field(
    title: 'Human readable title',
    description: 'Detailed description for AI context',
    default: 'default-value',
    format: Format::Email, // Built-in formats
)]
```

## Directory Structure

Organize your tools following the existing pattern:

```
src/McpServer/Action/Tools/
├── YourCategory/
│   ├── Dto/
│   │   ├── YourToolRequest.php
│   │   └── AnotherToolRequest.php
│   ├── YourToolAction.php
│   └── AnotherToolAction.php
```

## Complex Input Types

### Nested Objects

For complex nested parameters, create separate DTO classes:

```php
// Nested DTO
final readonly class NestedConfig
{
    public function __construct(
        #[Field(description: 'Enable feature')]
        public bool $enabled = false,
        
        #[Field(description: 'Configuration value')]
        public string $value = '',
    ) {}
}

// Main DTO using nested object
final readonly class ComplexToolRequest
{
    public function __construct(
        #[Field(description: 'Basic parameter')]
        public string $basic,
        
        #[Field(description: 'Complex nested configuration')]
        public ?NestedConfig $config = null,
    ) {}
}
```

### Arrays

Handle array inputs with proper typing:

```php
final readonly class ArrayToolRequest
{
    public function __construct(
        #[Field(description: 'List of strings')]
        /** @var string[] */
        public array $stringList = [],
        
        #[Field(description: 'List of integers')]
        /** @var int[] */
        public array $numbers = [],
    ) {}
}
```

## Best Practices

### 1. Naming Conventions

- **Tool names**: Use kebab-case (`my-tool-name`)
- **DTO classes**: Use PascalCase with `Request` suffix (`MyToolRequest`)
- **Action classes**: Use PascalCase with `Action` suffix (`MyToolAction`)

### 2. Error Handling

Always wrap your logic in try-catch blocks and return proper error responses:

```php
try {
    // Your logic
    return new CallToolResult([new TextContent(text: $result)]);
} catch (\Throwable $e) {
    $this->logger->error('Tool error', ['error' => $e->getMessage()]);
    return new CallToolResult([
        new TextContent(text: 'Error: ' . $e->getMessage())
    ], isError: true);
}
```

### 3. Logging

Use the injected logger for debugging and monitoring:

```php
$this->logger->info('Processing tool', ['param' => $request->param]);
$this->logger->error('Tool failed', ['error' => $e->getMessage()]);
```

### 4. Input Validation

- Use readonly DTOs for immutability
- Leverage PHP's type system and Spiral's validation attributes
- Provide clear descriptions for AI context understanding
- Set sensible defaults where appropriate

### 5. Route Naming

Follow the established pattern for route naming:

- Path: `/tools/call/{tool-name}`
- Name: `tools.{category}.{action}` or `tools.{tool-name}`

## Tool Discovery

CTX automatically discovers tools through attribute scanning. No manual registration is required - just create your tool
class with the proper attributes and it will be available in the MCP server.

## Testing Your Tool

1. **Start the MCP server**: `php ctx server`
2. **List tools**: Use the `tools/list` endpoint to verify your tool appears
3. **Test execution**: Call your tool through the MCP protocol
4. **Check logs**: Monitor application logs for any issues

This modern approach provides type safety, automatic validation, and excellent IDE support while maintaining clean
separation of concerns.