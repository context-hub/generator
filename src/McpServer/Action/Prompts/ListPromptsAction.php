<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Mcp\Types\ListPromptsResult;
use Mcp\Types\Prompt;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListPromptsAction
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ServerRequestInterface $request): ListPromptsResult
    {
        $this->logger->info('Listing available prompts');

        // Return available prompts in a format that can be converted to ListPromptsResult
        return new ListPromptsResult([
            new Prompt(
                name: 'available-context',
                description: 'Provides a list of available contexts',
            ),
            new Prompt(
                name: 'project-structure',
                description: 'Tries to guess the project structure',
            ),
        ]);
    }
}
