# MCP Server Tools Guide

## Overview

The MCP Server Tools component provides a framework for defining, registering, and executing external commands in a
structured and configurable way. It allows the application to interact with command-line tools while providing logging,
error handling, and configuration management.

## Architecture

The tools system is based on a registry pattern with the following key components:

```
ToolRegistry (implements ToolRegistryInterface, ToolProviderInterface)
├── Registers and stores tool definitions
├── Provides access to tools by ID
└── Manages tool definitions loaded from configuration

ToolDefinition
├── Represents a configured tool with commands
└── Contains metadata like ID, description, and command list

ToolCommand
├── Represents a specific command to execute
└── Contains command, arguments, working directory, and environment variables

CommandExecutor (implements CommandExecutorInterface)
└── Executes commands using Symfony Process component

ToolHandlerInterface (implemented by RunToolHandler)
└── Handles the execution of tools by type
```

## Creating and Configuring Tools

### Tool Definition Structure

A tool is defined with the following properties:

- `id`: Unique identifier for the tool
- `description`: Human-readable description
- `commands`: List of commands to execute
- `env`: Optional environment variables for all commands

Each command has:

- `cmd`: The command to execute
- `args`: Command arguments (array of strings)
- `workingDir`: Optional working directory (relative to project root)
- `env`: Optional environment variables specific to this command

### Configuration Example

Tools are defined in configuration files under the `tools` key:

```php
// In your configuration file
return [
    'tools' => [
        [
            'id' => 'lint-php',
            'description' => 'Run PHP linter on source files',
            'commands' => [
                [
                    'cmd' => 'php',
                    'args' => ['-l', 'src/'],
                    'workingDir' => null,
                    'env' => ['PHP_MEMORY_LIMIT' => '256M']
                ]
            ],
            'env' => ['SOME_GLOBAL_VAR' => 'value']
        ],
        // More tools...
    ]
];
```

## Registering Tools

Tools are registered in the `ToolRegistry` through the `ToolParserPlugin` which parses the configuration:

```php
// This happens automatically via the McpToolBootloader
$toolDefinition = ToolDefinition::fromArray($config);
$toolRegistry->register($toolDefinition);
```

## Using Tools

### Basic Usage

```php
// Get a tool by ID
$tool = $toolProvider->get('lint-php');

// Check if a tool exists
if ($toolProvider->has('code-check')) {
    // Use the tool
}

// Get all available tools
$allTools = $toolProvider->all();
```

### Executing Tools

Tools are executed through a `ToolHandlerInterface` implementation:

```php
// Get the appropriate handler
$handler = $container->get(RunToolHandler::class);

// Execute the tool
try {
    $result = $handler->execute($tool);
    
    // $result contains:
    // - 'success': bool indicating overall success
    // - 'output': combined output from all commands
    // - 'commands': array of individual command results
} catch (ToolExecutionException $e) {
    // Handle execution error
}
```

## Error Handling

The tools system provides specialized exceptions:

- `ToolExecutionException`: Thrown when a tool execution fails

```php
try {
    $result = $handler->execute($tool);
} catch (ToolExecutionException $e) {
    $logger->error('Tool execution failed', [
        'tool' => $tool->id,
        'error' => $e->getMessage()
    ]);
}
```

## Extending the Tools System

### Creating a New Tool Type Handler

1. Create a class that implements `ToolHandlerInterface` or extends `AbstractToolHandler`
2. Implement the `supports()` method to indicate what tool type it handles
3. Implement the `execute()` or `doExecute()` method to perform the actual execution
4. Register your handler in the DI container

```php
final readonly class MyCustomToolHandler extends AbstractToolHandler
{
    public function supports(string $type): bool
    {
        return $type === 'my-custom-type';
    }
    
    protected function doExecute(ToolDefinition $tool): array
    {
        // Implement your execution logic
        return [
            'success' => true,
            'output' => 'Execution result',
            // Other result data...
        ];
    }
}
```

## Best Practices

1. **Validation**: Always validate tool configurations to ensure they contain required fields.
2. **Error Handling**: Properly catch and handle exceptions from tool execution.
3. **Logging**: Use the logger to record tool execution events for debugging.
4. **Security**: Be careful with command arguments to prevent command injection.
5. **Environment**: Use environment variables to configure behavior rather than hardcoding values.
6. **Time Limits**: Set appropriate timeouts for long-running commands.

## Related Components

- **Config System**: Tools are loaded through the configuration system.
- **Logging**: Tool execution is logged for monitoring and debugging.
- **Environment**: Environment variables can influence tool behavior.

## Common Issues

1. **Command Not Found**: Ensure the command exists in the path or provide an absolute path.
2. **Permission Denied**: Check file permissions for the command and working directory.
3. **Timeout**: Increase the timeout for long-running commands.
4. **Environment Variables**: Ensure required environment variables are set.

# Custom Tools

The Custom Tools feature allows you to define project-specific commands that can be executed directly from the
configuration files. This enables easy integration of common development tasks, build processes, code analysis, and
more.

## Configuration Format

Custom tools are defined in the `tools` section of the configuration file. Here's the basic structure:

```yaml
tools:
  - id: tool-id                      # Unique identifier for the tool
    description: 'Tool description'  # Human-readable description
    env: # Optional environment variables for all commands
      KEY1: value1
      KEY2: value2
    commands: # List of commands to execute
      - cmd: executable              # Command to run
        args: # Command arguments (array)
          - arg1
          - arg2
        workingDir: path/to/dir      # Optional working directory (relative to project root)
        env: # Optional environment variables for this command
          KEY1: value1
          KEY2: value2
```

## Example Use Cases

### 1. Code Style Fixing

```yaml
tools:
  - id: cs-fixer
    description: 'Fix code style issues'
    commands:
      - cmd: composer
        args: [ 'cs:fix' ]
```

### 2. Static Analysis

```yaml
tools:
  - id: phpstan
    description: 'Run static analysis'
    commands:
      - cmd: vendor/bin/phpstan
        args: [ 'analyse', 'src', '--level', '8' ]
```

### 3. Multi-Step Processes

```yaml
tools:
  - id: test-suite
    description: 'Run full test suite with coverage'
    commands:
      - cmd: composer
        args: [ 'install', '--no-dev' ]
      - cmd: vendor/bin/phpunit
        args: [ '--coverage-html', 'coverage' ]
      - cmd: vendor/bin/infection
        args: [ '--min-msi=80' ]
```

### 4. Deployment

```yaml
tools:
  - id: deploy-staging
    description: 'Deploy to staging environment'
    commands:
      - cmd: bash
        args: [ 'deploy.sh', 'staging' ]
        env:
          DEPLOY_TOKEN: "${STAGING_TOKEN}"
```

## Security Considerations

The custom tools feature includes several security measures:

1. **Environment Variable Controls**:
    - `MCP_CUSTOM_TOOLS_ENABLE`: Enable/disable the custom tools feature (default: `true`)
    - `MCP_TOOL_MAX_RUNTIME`: Maximum runtime for a command in seconds (default: `30`)

## Environment Configuration

### Environment Variables

| Variable                  | Description                              | Default |
|---------------------------|------------------------------------------|---------|
| `MCP_CUSTOM_TOOLS_ENABLE` | Enable/disable custom tools              | `true`  |
| `MCP_TOOL_MAX_RUNTIME`    | Maximum runtime for a command in seconds | `30`    |

## Best Practices

1. **Keep Commands Simple**: Break complex operations into multiple commands
2. **Use Environment Variables**: Avoid hardcoding secrets in tool configurations
3. **Set Appropriate Timeouts**: Adjust the `max_runtime` for long-running commands
4. **Test Thoroughly**: Test custom tools before implementing them in production
5. **Consider Security**: Be cautious about what commands are allowed and who can execute them

# Tools with Arguments

This feature allows you to define tools that accept dynamic arguments at execution time. With this capability, you can
create versatile tools that can be parameterized by LLMs or user input rather than having static command arguments.

## Configuration Structure

To create a tool that accepts arguments, add a `schema` section to your tool definition:

```yaml
tools:
  - id: my-tool
    description: "Tool description"
    schema:
      type: object
      properties:
        arg1:
          type: string
          description: "Description of argument 1"
        arg2:
          type: number
          description: "Description of argument 2"
        flag:
          type: boolean
          description: "A boolean flag"
          default: false
      required:
        - arg1
    commands:
      - cmd: executable
        args:
          - --param={{arg1}}
          - --value={{arg2}}
          - --flag={{flag}}
```

## Schema Structure

The schema follows the JSON Schema format with these key sections:

- `type`: Must be "object"
- `properties`: Object defining each parameter with its type and description
- `required`: Array of required parameter names

### Supported Property Types

- `string`: Text values
- `number`: Numeric values
- `boolean`: True/false values
- `array`: List of values
- `object`: Nested objects

### Property Attributes

Each property can have:

- `type`: Data type (required)
- `description`: Human-readable description (recommended)
- `default`: Default value if not provided

## Template Syntax

You can use argument values in commands with the `{{argument_name}}` syntax:

- In command arguments: `--name={{username}}`
- In environment variables: `TOKEN: "{{api_token}}"`
- In working directory: `workingDir: "projects/{{project_name}}"`

## Executing Tools with Arguments

Use the `ToolService` to execute tools with arguments:

```php
// Get service from container
$toolService = $container->get(ToolService::class);

// Execute with arguments
$result = $toolService->executeWithArguments('my-tool', [
    'arg1' => 'value1',
    'arg2' => 42,
    'flag' => true,
]);
```

### Validation

Arguments are automatically validated against the schema. If validation fails, a `ToolExecutionException` is thrown with
details about the error.

## Advanced Usage

### Conditional Commands

You can conditionally execute commands based on argument values:

```yaml
commands:
  - cmd: php
    args:
      - artisan
      - migrate:fresh
    when: "{{fresh}}"
```

### Complex Templating

You can combine multiple arguments in a single template:

```yaml
args:
  - "{{prefix}}-{{name}}-{{suffix}}"
```

### Using Default System Variables

The tool argument system integrates with the existing variable system, allowing you to combine tool arguments with
system variables:

```yaml
args:
  - "{{name}}-${DATETIME}"
```
