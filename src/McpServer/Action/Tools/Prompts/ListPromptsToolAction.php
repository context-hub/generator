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
    description: 'Use this tool to get a complete list of available prompts and their descriptions. This is useful when you need to discover what prompts exist and determine which might be helpful for your current task.',
)]
final readonly class ListPromptsToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private McpItemsRegistry $registry,
        private PromptProviderInterface $prompts,
        private ConfigLoaderInterface $configLoader,
    ) {}

    #[Post(path: '/tools/call/prompts-list', name: 'tools.prompts.list')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Listing available prompts via tool action');

        try {
            $this->configLoader->load();

            $promptsList = [];

            // Get prompts from registry
            foreach ($this->registry->getPrompts() as $prompt) {
                $promptsList[] = [
                    'id' => $prompt->name,
                    'description' => $prompt->description,
                ];
            }

            // Get prompts from prompt provider
            foreach ($this->prompts->all() as $prompt) {
                $promptsList[] = [
                    'id' => $prompt->prompt->name,
                    'description' => $prompt->prompt->description,
                ];
            }

            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'count' => \count($promptsList),
                        'prompts' => $promptsList,
                    ], JSON_PRETTY_PRINT),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing prompts', [
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
}
