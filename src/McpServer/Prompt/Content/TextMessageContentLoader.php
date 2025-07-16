<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Content;

use Butschster\ContextGenerator\McpServer\Prompt\Exception\PromptParsingException;

/**
 * Loads message content from the 'content' property (existing functionality).
 */
final readonly class TextMessageContentLoader extends MessageContentLoader
{
    public function canHandle(array $messageConfig): bool
    {
        return isset($messageConfig['content']) && \is_string($messageConfig['content']);
    }

    public function loadContent(array $messageConfig): string
    {
        $this->validateMessageConfig($messageConfig);

        if (!isset($messageConfig['content']) || !\is_string($messageConfig['content'])) {
            throw new PromptParsingException('Message must have a valid content property');
        }

        return $messageConfig['content'];
    }
}
