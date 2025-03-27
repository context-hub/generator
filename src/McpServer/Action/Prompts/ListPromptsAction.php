<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\ListPromptsResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListPromptsAction
{
    public function __construct(
        private LoggerInterface $logger,
        private McpItemsRegistry $registry,
    ) {}

    #[Get(path: '/prompts/list', name: 'prompts.list')]
    public function __invoke(ServerRequestInterface $request): ListPromptsResult
    {
        $this->logger->info('Listing available prompts');

        $prompts = [];
        foreach ($this->registry->getPrompts() as $prompt) {
            $prompts[] = $prompt;
        }

        return new ListPromptsResult($prompts);
    }
}
