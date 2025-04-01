<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
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
        private PromptProviderInterface $prompts,
        private ConfigLoaderInterface $configLoader,
    ) {}

    #[Get(path: '/prompts/list', name: 'prompts.list')]
    public function __invoke(ServerRequestInterface $request): ListPromptsResult
    {
        $this->configLoader->load();
        $this->logger->info('Listing available prompts');

        $prompts = [];
        foreach ($this->registry->getPrompts() as $prompt) {
            $prompts[] = $prompt;
        }

        foreach ($this->prompts->all() as $prompt) {
            $prompts[] = $prompt->prompt;
        }

        return new ListPromptsResult($prompts);
    }
}
