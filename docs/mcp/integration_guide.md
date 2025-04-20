# MCP Server Integration Guide

This guide explains how to integrate both the Tools and Prompts components in your application.

## Overview

The MCP Server provides two key components:

1. **Tools** - For executing external commands and processes
2. **Prompts** - For managing AI prompt templates

These components can be used independently or together to create powerful workflow automations.

## Setting Up Both Components

### 1. Register Bootloaders

First, register both bootloaders in your application:

```php
use Butschster\ContextGenerator\McpServer\Tool\McpToolBootloader;
use Butschster\ContextGenerator\McpServer\Prompt\McpPromptBootloader;

// In your application bootloader registration
$app->bootloader(new McpToolBootloader());
$app->bootloader(new McpPromptBootloader());
```

### 2. Create Configuration Files

Create configuration files with both prompts and tools sections:

```php
// config/mcp.php
return [
    'prompts' => [
        // Prompt definitions...
    ],
    'tools' => [
        // Tool definitions...
    ]
];
```

## Integration Patterns

### Using Tools to Generate Content for Prompts

A common pattern is using tools to gather data that is then fed into prompts:

```php
public function generateDocumentation(
    ToolProviderInterface $toolProvider,
    PromptProviderInterface $promptProvider, 
    CommandExecutorInterface $commandExecutor
): string {
    // 1. Execute a tool to analyze code
    $analysisTool = $toolProvider->get('code-analyzer');
    $handler = new RunToolHandler($commandExecutor);
    $analysisResult = $handler->execute($analysisTool);
    
    // 2. Get the documentation prompt
    $docPrompt = $promptProvider->get('generate-docs');
    
    // 3. Use the tool's output as input to the prompt
    $promptArguments = [
        'code_analysis' => $analysisResult['output'],
        'language' => 'php'
    ];
    
    // 4. Generate documentation using the LLM service
    return $this->llmService->complete($docPrompt, $promptArguments);
}
```

### Workflow Automation

You can create a workflow that combines multiple tools and prompts:

```php
public function automatedCodeReview(
    string $pullRequestUrl,
    ToolProviderInterface $toolProvider,
    PromptProviderInterface $promptProvider
): array {
    // 1. Use a tool to fetch code from PR
    $fetchTool = $toolProvider->get('fetch-pr');
    $fetchResult = $this->toolHandler->execute($fetchTool, [
        'PR_URL' => $pullRequestUrl
    ]);
    
    // 2. Use another tool to run static analysis
    $analyzeTool = $toolProvider->get('static-analysis');
    $analysisResult = $this->toolHandler->execute($analyzeTool);
    
    // 3. Get the code review prompt
    $reviewPrompt = $promptProvider->get('code-review');
    
    // 4. Send the code and analysis to the LLM
    $review = $this->llmService->complete($reviewPrompt, [
        'code' => $fetchResult['output'],
        'static_analysis' => $analysisResult['output']
    ]);
    
    // 5. Use a tool to post the review back to the PR
    $postTool = $toolProvider->get('post-comment');
    $this->toolHandler->execute($postTool, [
        'PR_URL' => $pullRequestUrl,
        'COMMENT' => $review
    ]);
    
    return [
        'pr_url' => $pullRequestUrl,
        'review' => $review
    ];
}
```

## Extension Example: Context-Aware Tool Execution

Here's an example of extending the system to create context-aware tool execution:

```php
final readonly class ContextAwareToolHandler implements ToolHandlerInterface
{
    public function __construct(
        private ToolHandlerInterface $defaultHandler,
        private PromptProviderInterface $promptProvider,
        private LlmServiceInterface $llmService,
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(string $type): bool
    {
        return $type === 'context-aware';
    }
    
    public function execute(ToolDefinition $tool): array
    {
        // 1. Get context data
        $contextData = $this->getContextData();
        
        // 2. Use a prompt to determine how to execute the tool
        $strategyPrompt = $this->promptProvider->get('tool-execution-strategy');
        $strategy = $this->llmService->complete($strategyPrompt, [
            'tool_id' => $tool->id,
            'context' => json_encode($contextData)
        ]);
        
        // 3. Modify the tool definition based on the strategy
        $modifiedTool = $this->applyStrategy($tool, $strategy);
        
        // 4. Use the default handler to execute the modified tool
        return $this->defaultHandler->execute($modifiedTool);
    }
    
    private function getContextData(): array
    {
        // Implementation to gather context data
    }
    
    private function applyStrategy(ToolDefinition $tool, string $strategy): ToolDefinition
    {
        // Implementation to modify tool based on strategy
    }
}
```

## Best Practices for Integration

1. **Dependency Injection**: Use constructor injection to access the tool and prompt registries.
2. **Error Handling**: Implement proper error handling for both tools and prompts.
3. **Logging**: Log all tool executions and prompt completions for debugging.
4. **Security**: Validate inputs before passing them to tools or prompts.
5. **Idempotency**: Design tools and prompts to be idempotent when possible.
6. **Testing**: Write tests that mock the tool and prompt providers.

## Common Integration Patterns

### Chain of Tools

Execute multiple tools in sequence, where each tool's output is the input for the next:

```php
public function chainToolExecution(array $toolIds, array $initialInput = []): array
{
    $results = [];
    $input = $initialInput;
    
    foreach ($toolIds as $toolId) {
        $tool = $this->toolProvider->get($toolId);
        $result = $this->toolHandler->execute($tool, $input);
        $results[$toolId] = $result;
        $input = array_merge($input, $result);
    }
    
    return $results;
}
```

### Prompt-Tool-Prompt Pattern

Use a prompt to interpret a request, a tool to gather data, and another prompt to format the response:

```php
public function queryDatabase(string $userQuestion): string
{
    // 1. Use a prompt to convert the question to SQL
    $sqlPrompt = $this->promptProvider->get('natural-to-sql');
    $sql = $this->llmService->complete($sqlPrompt, [
        'question' => $userQuestion
    ]);
    
    // 2. Use a tool to execute the SQL
    $dbTool = $this->toolProvider->get('database-query');
    $queryResult = $this->toolHandler->execute($dbTool, [
        'SQL_QUERY' => $sql
    ]);
    
    // 3. Use another prompt to format the results
    $formatPrompt = $this->promptProvider->get('format-db-results');
    return $this->llmService->complete($formatPrompt, [
        'question' => $userQuestion,
        'sql' => $sql,
        'results' => $queryResult['output']
    ]);
}
```

## Conclusion

By integrating the Tools and Prompts components, you can create powerful workflows that combine external command
execution with AI-powered content generation. This approach enables building complex systems that leverage the best of
both traditional command-line tools and modern large language models.
