<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\McpServer\Prompt\Exception\PromptParsingException;
use Mcp\Types\Prompt;
use Mcp\Types\PromptArgument;
use Mcp\Types\PromptMessage;
use Mcp\Types\Role;
use Mcp\Types\TextContent;

/**
 * Factory for creating Prompt objects from configuration arrays.
 */
final readonly class PromptConfigFactory
{
    /**
     * Creates a Prompt object from a configuration array.
     *
     * @param array<string, mixed> $config The prompt configuration
     * @throws PromptParsingException If the configuration is invalid
     */
    public function createFromConfig(array $config): PromptDefinition
    {
        // Validate required fields
        if (empty($config['id']) || !\is_string($config['id'])) {
            throw new PromptParsingException('Prompt must have a non-empty id');
        }

        // Create arguments from schema if provided
        $arguments = $this->createArgumentsFromSchema($config['schema'] ?? null);

        // Parse messages
        $messages = [];
        if (isset($config['messages']) && \is_array($config['messages'])) {
            // We store messages in a separate property in the registry
            // but don't add them to the Prompt DTO directly
            $messages = $this->parseMessages($config['messages']);
        }

        return new PromptDefinition(
            id: $config['id'],
            prompt: new Prompt(
                name: $config['id'],
                description: $config['description'] ?? null,
                arguments: $arguments,
            ),
            messages: $messages,
        );
    }

    /**
     * Creates PromptArgument objects from a JSON schema.
     *
     * @param array<string, mixed>|null $schema The JSON schema
     * @return array<PromptArgument> The created arguments
     */
    private function createArgumentsFromSchema(?array $schema): array
    {
        if (empty($schema)) {
            return [];
        }

        $arguments = [];

        // Process properties from schema
        if (isset($schema['properties']) && \is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                $required = false;

                // Check if this property is required
                if (isset($schema['required']) && \is_array($schema['required'])) {
                    $required = \in_array($name, $schema['required'], true);
                }

                $description = $property['description'] ?? null;

                $arguments[] = new PromptArgument(
                    name: $name,
                    description: $description,
                    required: $required,
                );
            }
        }

        return $arguments;
    }

    /**
     * Parses message configurations into PromptMessage objects.
     *
     * @param array<mixed> $messagesConfig The messages configuration
     * @return array<PromptMessage> The parsed messages
     * @throws PromptParsingException If the message configuration is invalid
     */
    private function parseMessages(array $messagesConfig): array
    {
        $messages = [];

        foreach ($messagesConfig as $index => $messageConfig) {
            if (!\is_array($messageConfig)) {
                throw new PromptParsingException(
                    \sprintf(
                        'Message at index %d must be an array',
                        $index,
                    ),
                );
            }

            if (!isset($messageConfig['role']) || !\is_string($messageConfig['role'])) {
                throw new PromptParsingException(
                    \sprintf(
                        'Message at index %d must have a valid role',
                        $index,
                    ),
                );
            }

            if (!isset($messageConfig['content']) || !\is_string($messageConfig['content'])) {
                throw new PromptParsingException(
                    \sprintf(
                        'Message at index %d must have a valid content',
                        $index,
                    ),
                );
            }

            try {
                $role = Role::from($messageConfig['role']);
            } catch (\ValueError) {
                throw new PromptParsingException(
                    \sprintf(
                        'Invalid role "%s" at index %d',
                        $messageConfig['role'],
                        $index,
                    ),
                );
            }

            $messages[] = new PromptMessage(
                role: $role,
                content: new TextContent(text: $messageConfig['content']),
            );
        }

        return $messages;
    }
}
