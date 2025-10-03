<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Prompts;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\Dto\GetPromptRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Content\PromptMessage;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Schema\Result\CallToolResult;
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
            return ToolResult::error(error: 'Missing prompt ID parameter');
        }

        try {
            // Check if prompt exists
            if (!$this->prompts->has($id)) {
                return ToolResult::error(error: \sprintf("Prompt with ID '%s' not found", $id));
            }

            // Get prompt and process messages
            $prompt = $this->prompts->get($id);
            $messages = $this->processMessageTemplates(messages: $prompt->messages, arguments: $serverRequest->getAttributes());

            // Format the messages for return
            $formattedMessages = [];
            foreach ($messages as $message) {
                $content = $message->content;
                $formattedMessages[] = [
                    'role' => $message->role->value,
                    'content' => $content instanceof TextContent ? $content->text : 'Non-text content',
                ];
            }

            return ToolResult::success(data: [
                'id' => $id,
                'description' => $prompt->prompt->description,
                'messages' => $formattedMessages,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting prompt', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error(error: $e->getMessage());
        }
    }

    /**
     * Processes message templates with the given arguments.
     *
     * @param array<PromptMessage> $messages The messages to process
     * @param array $arguments The arguments to use
     * @return array The processed messages
     */
    private function processMessageTemplates(array $messages, array $arguments): array
    {
        $arguments = \array_combine(
            keys: \array_map(callback: static fn($key) => '{{' . $key . '}}', array: \array_keys(array: $arguments)),
            values: \array_values(array: $arguments),
        );
        $variables = $this->variables;

        return \array_map(callback: static function ($message) use ($variables, $arguments) {
            $content = $message->content;

            if ($content instanceof TextContent) {
                $text = \strtr($content->text, $arguments);
                $text = $variables->resolve(strings: $text);

                $content = new TextContent(text: $text);
            }

            return new PromptMessage(
                role: $message->role,
                content: $content,
            );
        }, array: $messages);
    }
}
