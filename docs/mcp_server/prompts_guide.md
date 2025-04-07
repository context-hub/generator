# MCP Server Prompts Guide

## Overview

The MCP Server Prompts component provides a framework for defining, registering, and managing prompt definitions for AI
interactions. It allows the application to store structured prompts with arguments and messages, which can be referenced
by ID when needed.

## Architecture

The prompts system is based on a registry pattern with the following key components:

```
PromptRegistry (implements PromptRegistryInterface, PromptProviderInterface)
├── Registers and stores prompt definitions
├── Provides access to prompts by ID
└── Manages prompt definitions loaded from configuration

PromptDefinition
├── Represents a complete prompt with ID, schema, and messages
└── Contains the Prompt DTO and message list

PromptConfigFactory
└── Creates PromptDefinition objects from configuration arrays
```

The system integrates with the `Mcp\Types` namespace which provides DTOs like:

- `Prompt`: Core prompt definition with name, description, and arguments
- `PromptArgument`: Definition of arguments a prompt accepts
- `PromptMessage`: Individual message in a prompt with role and content
- `Role`: Enum defining message roles (system, user, assistant)
- `TextContent`: Content container for messages

## Creating and Configuring Prompts

### Prompt Definition Structure

A prompt is defined with the following properties:

- `id`: Unique identifier for the prompt
- `description`: Human-readable description
- `schema`: JSON schema defining the arguments the prompt accepts
- `messages`: List of messages representing the prompt template

### Configuration Example

Prompts are defined in configuration files under the `prompts` key:

```php
// In your configuration file
return [
    'prompts' => [
        [
            'id' => 'explain-code',
            'description' => 'Explain a code snippet in detail',
            'schema' => [
                'properties' => [
                    'language' => [
                        'description' => 'Programming language of the code'
                    ],
                    'code' => [
                        'description' => 'The code to explain'
                    ]
                ],
                'required' => ['code']
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a coding expert who explains code clearly.'
                ],
                [
                    'role' => 'user',
                    'content' => 'Explain this {{language}} code: {{code}}'
                ]
            ]
        ],
        // More prompts...
    ]
];
```

## Registering Prompts

Prompts are registered in the `PromptRegistry` through the `PromptParserPlugin` which parses the configuration:

```php
// This happens automatically via the McpPromptBootloader
$promptDefinition = $promptFactory->createFromConfig($config);
$promptRegistry->register($promptDefinition);
```

## Using Prompts

### Basic Usage

```php
// Get a prompt by ID
$prompt = $promptProvider->get('explain-code');

// Check if a prompt exists
if ($promptProvider->has('generate-test')) {
    // Use the prompt
}

// Get all available prompts
$allPrompts = $promptProvider->all();
```

### Working with Prompt Arguments

```php
// Get a prompt
$prompt = $promptProvider->get('explain-code');

// Access prompt arguments
foreach ($prompt->prompt->arguments as $argument) {
    echo "Argument: {$argument->name}" . PHP_EOL;
    echo "Description: {$argument->description}" . PHP_EOL;
    echo "Required: " . ($argument->required ? 'Yes' : 'No') . PHP_EOL;
}
```

### Working with Prompt Messages

```php
// Get a prompt
$prompt = $promptProvider->get('explain-code');

// Access prompt messages
foreach ($prompt->messages as $message) {
    echo "Role: {$message->role->value}" . PHP_EOL;
    echo "Content: {$message->content->text}" . PHP_EOL;
}
```

## Error Handling

The prompts system provides specialized exceptions:

- `PromptParsingException`: Thrown when a prompt configuration cannot be parsed

```php
try {
    $prompt = $promptFactory->createFromConfig($config);
} catch (PromptParsingException $e) {
    $logger->error('Prompt parsing failed', [
        'error' => $e->getMessage()
    ]);
}
```

## Creating Custom Prompt Templates

### Best Practices for Prompt Design

1. **Clear Instructions**: Make system messages clear and specific about the desired output
2. **Variable Placeholders**: Use consistent placeholder syntax like `{{variable_name}}`
3. **Required vs Optional**: Mark only truly necessary arguments as required
4. **Descriptive Schema**: Provide helpful descriptions for each argument
5. **Progressive Disclosure**: Start with essential instructions, then add details

### Example: Creating a Question-Answering Prompt

```php
$promptDefinition = new PromptDefinition(
    id: 'answer-question',
    prompt: new Prompt(
        name: 'answer-question',
        description: 'Answer a question based on provided context',
        arguments: [
            new PromptArgument(
                name: 'question',
                description: 'The question to be answered',
                required: true
            ),
            new PromptArgument(
                name: 'context',
                description: 'Contextual information to help answer the question',
                required: true
            )
        ]
    ),
    messages: [
        new PromptMessage(
            role: Role::SYSTEM,
            content: new TextContent(
                text: 'You are a helpful assistant that answers questions based on the provided context.'
            )
        ),
        new PromptMessage(
            role: Role::USER,
            content: new TextContent(
                text: 'Context: {{context}}\n\nQuestion: {{question}}\n\nPlease answer the question based only on the provided context.'
            )
        )
    ]
);

$promptRegistry->register($promptDefinition);
```

## Extending the Prompts System

### Creating a Custom Prompt Factory

If you need specialized prompt creation logic:

```php
final readonly class CustomPromptFactory
{
    public function createCustomPrompt(string $id, string $description, array $args): PromptDefinition
    {
        // Create PromptArgument objects from args
        $arguments = array_map(
            fn($arg) => new PromptArgument(
                name: $arg['name'],
                description: $arg['description'] ?? null,
                required: $arg['required'] ?? false
            ),
            $args
        );
        
        // Create the prompt
        return new PromptDefinition(
            id: $id,
            prompt: new Prompt(
                name: $id,
                description: $description,
                arguments: $arguments
            ),
            messages: [
                // Default messages...
            ]
        );
    }
}
```

## Related Components

- **Config System**: Prompts are loaded through the configuration system.
- **DTO Classes**: The `Mcp\Types` namespace provides data transfer objects.
- **JSON Schema**: Argument definitions follow JSON Schema conventions.

## Common Issues

1. **Invalid Role**: Ensure roles match the allowed values in the `Role` enum.
2. **Missing Required Fields**: Check that all required configuration fields are present.
3. **Duplicate Prompts**: Avoid registering multiple prompts with the same ID.
4. **Message Format**: Ensure message content is properly formatted as strings.
