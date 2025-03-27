# MCP Server Attribute-Based Metadata System

This directory contains the attributes used to define metadata for various MCP Server components.

## Overview

The attribute-based metadata system allows defining component metadata directly on the implementing classes. This makes the codebase more maintainable and self-documenting.

## Available Attributes

### `McpItem` (Abstract Base Class)

Base class for all MCP item attributes with common properties:

```php
#[Attribute(Attribute::TARGET_CLASS)]
abstract class McpItem
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
    ) {}
}
```

### `Prompt`

Defines a prompt in the MCP server:

```php
#[Prompt(
    name: 'available-context',
    description: 'Provides a list of available contexts'
)]
final readonly class AvailableContextPromptAction
{
    // Implementation
}
```

### `Resource`

Defines a resource in the MCP server:

```php
#[Resource(
    name: 'Json Schema of context generator',
    description: 'Returns a simplified JSON schema of the context generator',
    uri: 'ctx://json-schema',
    mimeType: 'application/json'
)]
final readonly class JsonSchemaResourceAction
{
    // Implementation
}
```

### `Tool`

Defines a tool in the MCP server:

```php
#[Tool(
    name: 'file-read',
    description: 'Read content from a file within the project directory structure'
)]
final readonly class FileReadToolAction
{
    // Implementation
}
```

### `InputSchema`

Defines input schema parameters for tools. Can be repeated for multiple parameters:

```php
#[Tool(name: 'file-write', description: '...')]
#[InputSchema(
    name: 'path', 
    type: 'string',
    description: 'Path to the file',
    required: true
)]
#[InputSchema(
    name: 'content',
    type: 'string',
    description: 'Content to write',
    required: true
)]
final readonly class FileWriteToolAction
{
    // Implementation
}
```

## Registry System

The `McpItemsRegistry` class scans for these attributes and registers them for use in the MCP Server. It provides methods to access the registered items:

- `getPrompts()`: Returns all registered prompts
- `getResources()`: Returns all registered resources
- `getTools()`: Returns all registered tools
