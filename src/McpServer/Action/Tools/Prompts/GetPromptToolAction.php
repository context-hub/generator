<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Prompts;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\Dto\GetPromptRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'prompt-get',
    description: 'Use this tool when you already know the specific prompt ID and need to retrieve its full content. First use prompts-list tool to discover available prompts, then use this tool to get the detailed content of a specific prompt you need. Requires the prompt ID as a parameter.',
    title: 'Get Prompt by ID',
)]
#[InputSchema(class: GetPromptRequest::class)]
final readonly class GetPromptToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private PromptProviderInterface $prompts,
        private VariableResolver $variables,
    ) {}

    #[Post(path: '/tools/call/prompt-get', name: 'tools.prompts.get')]
    public function __invoke(GetPromptRequest $request, ServerRequestInterface $serverRequest): CallToolResult
    {
        $this->logger->info('Getting prompt via tool action');

        // Get prompt ID from request
        $id = $request->id;

        if (empty($id)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing prompt ID parameter',
                ),
            ], isError: true);
        }

        try {
            // Check if prompt exists
            if (!$this->prompts->has($id)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: Prompt with ID '%s' not found", $id),
                    ),
                ], isError: true);
            }

            // Get prompt and process messages
            $prompt = $this->prompts->get($id);
            $messages = $this->processMessageTemplates($prompt->messages, $serverRequest->getAttributes());

            // Format the messages for return
            $formattedMessages = [];
            foreach ($messages as $message) {
                $content = $message->content;
                $formattedMessages[] = [
                    'role' => $message->role->value,
                    'content' => $content instanceof TextContent ? $content->text : 'Non-text content',
                ];
            }

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'id' => $id,
                        'description' => $prompt->prompt->description,
                        'messages' => $formattedMessages,
                    ], JSON_PRETTY_PRINT),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting prompt', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }

    /**
     * Processes message templates with the given arguments.
     *
     * @param array<\Mcp\Types\PromptMessage> $messages The messages to process
     * @param array $arguments The arguments to use
     * @return array The processed messages
     */
    private function processMessageTemplates(array $messages, array $arguments): array
    {
        $arguments = \array_combine(
            \array_map(static fn($key) => '{{' . $key . '}}', \array_keys($arguments)),
            \array_values($arguments),
        );
        $variables = $this->variables;

        return \array_map(static function ($message) use ($variables, $arguments) {
            $content = $message->content;

            if ($content instanceof TextContent) {
                $text = \strtr($content->text, $arguments);
                $text = $variables->resolve($text);

                $content = new TextContent($text);
            }

            return new \Mcp\Types\PromptMessage(
                role: $message->role,
                content: $content,
            );
        }, $messages);
    }
}
