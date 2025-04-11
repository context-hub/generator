<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Butschster\ContextGenerator\McpServer\Tool\Exception\ToolExecutionException;
use Butschster\ContextGenerator\McpServer\Tool\ToolHandlerFactory;
use Butschster\ContextGenerator\McpServer\Tool\ToolProviderInterface;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ExecuteCustomToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ToolProviderInterface $toolProvider,
        private ToolHandlerFactory $toolHandler,
    ) {}

    #[Post(path: '/tools/call/{id}', name: 'tools.execute')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Executing custom tool');
        $toolId = $request->getAttribute('id');

        // Get params from the parsed body for POST requests
        $parsedBody = (array) $request->getParsedBody();

        if (empty($toolId) || !\is_string($toolId)) {
            $this->logger->warning('No tool ID provided');
            return new CallToolResult(
                content: [new TextContent(text: 'No tool ID provided')],
                isError: true,
            );
        }

        try {
            // Check if the tool exists
            if (!$this->toolProvider->has($toolId)) {
                $this->logger->warning('Tool not found', ['id' => $toolId]);
                return new CallToolResult(
                    content: [new TextContent(text: \sprintf('Tool with ID "%s" not found', $toolId))],
                    isError: true,
                );
            }

            // Get the tool definition
            $tool = $this->toolProvider->get($toolId);

            // Execute the tool
            $result = $this->toolHandler->createHandlerForTool($tool)->execute($tool, $parsedBody);

            // Format the result
            $output = \sprintf(
                "Executed tool: %s\n\n%s",
                $tool->description,
                $result['output'] ?? 'No output',
            );

            if (isset($result['commands']) && \is_array($result['commands'])) {
                $commandsOutput = [];
                foreach ($result['commands'] as $commandResult) {
                    $commandsOutput[] = \sprintf(
                        "Command: %s\nExit Code: %d\nSuccess: %s\n\n%s",
                        $commandResult['command'],
                        $commandResult['exitCode'],
                        $commandResult['success'] ? 'Yes' : 'No',
                        $commandResult['output'] ?? 'No output',
                    );
                }

                if (!empty($commandsOutput)) {
                    $output .= "\n\n## Command Details\n\n" . \implode("\n---\n", $commandsOutput);
                }
            }

            return new CallToolResult(
                content: [new TextContent(text: $output)],
                isError: !($result['success'] ?? true),
            );
        } catch (ToolExecutionException $e) {
            $this->logger->error('Tool execution failed', [
                'id' => $toolId,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult(
                content: [new TextContent(text: \sprintf('Tool execution failed: %s', $e->getMessage()))],
                isError: true,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during tool execution', [
                'id' => $toolId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return new CallToolResult(
                content: [new TextContent(text: \sprintf('Unexpected error: %s', $e->getMessage()))],
                isError: true,
            );
        }
    }
}
