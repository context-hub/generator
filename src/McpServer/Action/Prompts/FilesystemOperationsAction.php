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
    name: 'filesystem-ops',
    description: 'Guidance for using filesystem operations like reading, writing, moving, renaming, and updating files.',
)]
final readonly class FilesystemOperationsAction
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Get(path: '/prompt/filesystem-ops', name: 'prompts.filesystem-ops')]
    public function __invoke(ServerRequestInterface $request): GetPromptResult
    {
        $this->logger->info('Getting filesystem operations prompt');

        return new GetPromptResult(
            messages: [
                new PromptMessage(
                    role: Role::USER,
                    content: new TextContent(
                        text: "Whenever you need to do file operations, use these tools: file-info, file-read, file-write, file-move, file-rename",
                    ),
                ),
            ],
        );
    }
}
