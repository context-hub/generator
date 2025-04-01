<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Prompts;

use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\GetPromptResult;
use Mcp\Types\PromptMessage;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class GetPromptAction
{
    public function __construct(
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

        $prompt = $this->prompts->get($id);
        $messages = $this->processMessageTemplates($prompt->messages, $request->getAttributes());

        return new GetPromptResult(
            messages: $messages,
            description: $prompt->prompt->description,
        );
    }

    /**
     * Processes message templates with the given arguments.
     *
     * @param array<PromptMessage> $messages The messages to process
     * @param array $arguments The arguments to use
     * @return array The processed messages
     */
    private function processMessageTemplates(array $messages, array $arguments): array
    {
        $arguments = \array_combine(
            \array_map(static fn($key) => '{{' . $key . '}}', \array_keys($arguments)),
            \array_values($arguments),
        );
        return \array_map(static function ($message) use ($arguments) {
            $content = $message->content;

            if ($content instanceof TextContent) {
                $content = new TextContent(\strtr($content->text, $arguments));
            }

            return new PromptMessage(
                role: $message->role,
                content: $content,
            );
        }, $messages);
    }
}
