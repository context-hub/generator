<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\GetPromptResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class GetPromptAction
{
    public function __construct(
        #[LoggerPrefix(prefix: 'prompts.get')]
        private LoggerInterface $logger,
        private PromptProviderInterface $prompts,
    ) {}

    #[Get(path: 'prompt/{id}', name: 'prompts.get')]
    public function __invoke(ServerRequestInterface $request): GetPromptResult
    {
        $id = $request->getAttribute('id');
        $this->logger->info('Getting prompt', ['id' => $id]);

        if (!$this->prompts->has($id)) {
            return new GetPromptResult([]);
        }

        $prompt = $this->prompts->get($id, $request->getAttributes());

        return new GetPromptResult(
            messages: $prompt->messages,
            description: $prompt->prompt->description,
        );
    }
}
