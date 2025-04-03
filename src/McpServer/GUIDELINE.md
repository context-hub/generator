# Guidelines for Developing MCP Actions

### Action Classes & Structure

1. **Location**:
    - Place actions in the appropriate directory under `src/McpServer/Action/`
    - Group related actions in subdirectories (e.g., `Tools/Prompts/` for prompt-related tool actions)

2. **Class Attributes**:
    - For tool actions, use the `#[Tool]` attribute at the class level to register it as a tool
    - For prompt actions, use the `#[Prompt]` attribute at the class level
    - For resource actions, use the `#[Resource]` attribute at the class level

3. **Input Schemas**:
    - Use `#[InputSchema]` attributes at the class level to define parameters
    - Make each parameter's purpose clear in its description (helpful for LLMs)
    - Specify if parameters are required and provide default values where appropriate

4. **Method Attributes**:
    - Use routing attributes (`#[Get]` or `#[Post]`) on the `__invoke` method
    - Follow the pattern `/tools/call/{tool-name}` for tool action paths
    - Provide a meaningful route name (e.g., `tools.prompts.list`)

### Implementation Guidelines

1. **Class Design**:
    - Make classes `final readonly`
    - Use constructor property promotion
    - Implement the `__invoke` method to handle the request

2. **Dependency Injection**:
    - Always inject `LoggerInterface` for consistent logging
    - Inject other required services through the constructor

3. **Error Handling**:
    - Wrap main logic in try/catch blocks
    - Return appropriate error messages in a `CallToolResult` with `isError: true`
    - Log errors with context information

4. **Response Format**:
    - For tools, return a `CallToolResult` containing `TextContent` instances
    - Structure responses as clear, well-formatted JSON when returning multiple items
    - Provide clear success/error messages

### Registration Process

Actions are not registered manually - they are discovered and registered automatically through the following process:

1. **Attribute Discovery**:
    - The `McpItemsRegistry` scans action classes for attributes (`Tool`, `Prompt`, `Resource`)
    - It registers them in the appropriate collections based on their attributes

2. **Routing Registration**:
    - The `RouteRegistrar` registers routes based on the routing attributes (`Get`, `Post`, `Route`)

3. **Server Configuration**:
    - The `Server` class maps MCP API methods to the registered routes

### Key Fixes for Your Current Implementation

After reviewing your code and the existing patterns, here are corrections needed for your pull request:

1. **Missing Attributes**:
    - Your tool action classes are correctly placed but are missing the `#[Tool]` class attribute (the actual issue!)
    - Without this attribute, the `McpItemsRegistry` cannot identify them as tools

2. **Error Handling**:
    - Ensure consistent try/catch blocks with appropriate error logging
    - Return errors with `isError: true` flag

3. **Response Format**:
    - Ensure JSON responses use a consistent structure for easier consumption by LLMs

## Final Version of Action Classes

Here is how to properly implement your two tool actions with all required attributes:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Prompts;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'prompts-list',
    description: 'Use this tool to get a complete list of available prompts and their descriptions. This is useful when you need to discover what prompts exist and determine which might be helpful for your current task. No parameters required.',
)]
final readonly class ListPromptsToolAction
{
    // Rest of your implementation...
}
```

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Prompts;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'prompt-get',
    description: 'Use this tool when you already know the specific prompt ID and need to retrieve its full content. First use prompts-list tool to discover available prompts, then use this tool to get the detailed content of a specific prompt you need. Requires the prompt ID as a parameter.',
)]
#[InputSchema(
    name: 'id',
    type: 'string',
    description: 'The ID of the prompt to retrieve (required). You can find valid prompt IDs by first using the prompts-list tool.',
    required: true,
)]
final readonly class GetPromptToolAction
{
    // Rest of your implementation...
}
```

By adding these attributes, your tool actions will be properly registered in the MCP system and available for use by
LLMs.