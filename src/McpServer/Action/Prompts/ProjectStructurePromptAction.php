<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Attribute\Prompt;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use PhpMcp\Schema\Content\PromptMessage;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Schema\Enum\Role;
use PhpMcp\Schema\Result\GetPromptResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Prompt(
    name: 'project-structure',
    description: 'Tries to guess the project structure',
)]
final readonly class ProjectStructurePromptAction
{
    public function __construct(
        #[LoggerPrefix(prefix: 'prompts.project-structure')]
        private LoggerInterface $logger,
    ) {}

    #[Get(path: '/prompt/project-structure', name: 'prompts.project-structure')]
    public function __invoke(ServerRequestInterface $request): GetPromptResult
    {
        $this->logger->info('Getting project-structure prompt');

        return new GetPromptResult(
            messages: [
                new PromptMessage(
                    role: Role::User,
                    content: new TextContent(
                        text: "Look at available contexts and try to find the project structure. If there is no context for structure. Request structure from context using JSON schema. Provide the result in JSON format",
                    ),
                ),
            ],
        );
    }
}
