<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Butschster\ContextGenerator\McpServer\Attribute\Prompt;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\GetPromptResult;
use Mcp\Types\PromptMessage;
use Mcp\Types\Role;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Prompt(
    name: 'available-context',
    description: 'Provides a list of available contexts',
)]
final readonly class AvailableContextPromptAction
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Get(path: '/prompt/available-context', name: 'prompts.available-context')]
    public function __invoke(ServerRequestInterface $request): GetPromptResult
    {
        $this->logger->info('Getting available-context prompt');

        return new GetPromptResult(
            messages: [
                new PromptMessage(
                    role: Role::USER,
                    content: new TextContent(
                        text: "Provide list of available contexts in JSON format",
                    ),
                ),
            ],
        );
    }
}
