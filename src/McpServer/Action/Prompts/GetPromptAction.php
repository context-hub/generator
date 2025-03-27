<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\GetPromptResult;
use Mcp\Types\PromptMessage;
use Mcp\Types\Role;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class GetPromptAction
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Get(path: '/prompt/{name}', name: 'prompts.get')]
    public function __invoke(ServerRequestInterface $request): GetPromptResult
    {
        $name = $request->getAttribute('name');
        $this->logger->info('Getting prompt', ['name' => $name]);


        if ($name === 'available-context') {
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

        if ($name === 'project-structure') {
            return new GetPromptResult(
                messages: [
                    new PromptMessage(
                        role: Role::USER,
                        content: new TextContent(
                            text: "Look at available contexts and try to find the project structure. If there is no context for structure. Request structure from context using JSON schema. Provide the result in JSON format",
                        ),
                    ),
                ],
            );
        }

        return new GetPromptResult(
            messages: [
                new PromptMessage(
                    role: Role::USER,
                    content: new TextContent(
                        text: "Error: Prompt not found",
                    ),
                ),
            ],
        );
    }
}
