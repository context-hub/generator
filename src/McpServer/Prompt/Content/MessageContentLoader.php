<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Content;

use Butschster\ContextGenerator\McpServer\Prompt\Exception\PromptParsingException;

/**
 * Abstract base class for loading message content from different sources.
 */
abstract readonly class MessageContentLoader
{
    /**
     * Checks if this loader can handle the given message configuration.
     */
    abstract public function canHandle(array $messageConfig): bool;

    /**
     * Loads the content for the message.
     *
     * @throws PromptParsingException If content loading fails
     */
    abstract public function loadContent(array $messageConfig): string;

    /**
     * Validates that the message configuration is valid for this loader.
     *
     * @throws PromptParsingException If the configuration is invalid
     */
    protected function validateMessageConfig(array $messageConfig): void
    {
        if (!isset($messageConfig['role']) || !\is_string($messageConfig['role'])) {
            throw new PromptParsingException('Message must have a valid role');
        }
    }
}
