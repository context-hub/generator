<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\McpServer\Prompt\Exception\PromptParsingException;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptExtension;
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

        // Determine prompt type
        $type = PromptType::fromString($config['type'] ?? null);

        // Parse extensions if provided
        $extensions = [];
        if (isset($config['extend']) && \is_array($config['extend'])) {
            $extensions = $this->parseExtensions($config['extend']);
        }

        // Parse tags if provided
        $tags = [];
        if (isset($config['tags']) && \is_array($config['tags'])) {
            $tags = $this->parseTags($config['tags']);
        }

        // Validate that prompts have instructions (messages or extensions)
        // Templates can have empty messages as they may be just structural
        if ($type === PromptType::Prompt && empty($messages) && empty($extensions)) {
            throw new PromptParsingException(
                \sprintf('Prompt "%s" must have either messages or extend a template', $config['id']),
            );
        }

        // Templates should have messages to be useful for extension
        if ($type === PromptType::Template && empty($messages)) {
            throw new PromptParsingException(
                \sprintf('Template "%s" must have messages to be extended by prompts', $config['id']),
            );
        }

        return new PromptDefinition(
            id: $config['id'],
            prompt: new Prompt(
                name: $config['id'],
                description: $config['description'] ?? null,
                arguments: $arguments,
            ),
            messages: $messages,
            type: $type,
            extensions: $extensions,
            tags: $tags,
        );
    }

    /**
     * Parses tags from configuration.
     *
     * @param array<mixed> $tagsConfig The tags configuration
     * @return array<string> The parsed tags
     * @throws PromptParsingException If the tags configuration is invalid
     */
    private function parseTags(array $tagsConfig): array
    {
        $tags = [];

        foreach ($tagsConfig as $index => $tag) {
            if (!\is_string($tag)) {
                throw new PromptParsingException(
                    \sprintf(
                        'Tag at index %d must be a string',
                        $index,
                    ),
                );
            }

            // Add the tag if it's not empty
            if (!empty($tag)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Parses extension configurations.
     *
     * @param array<mixed> $extensionConfigs The extension configurations
     * @return array<PromptExtension> The parsed extensions
     * @throws PromptParsingException If the extension configuration is invalid
     */
    private function parseExtensions(array $extensionConfigs): array
    {
        if (empty($extensionConfigs)) {
            return [];
        }

        $extensions = [];

        foreach ($extensionConfigs as $index => $extensionConfig) {
            if (!\is_array($extensionConfig)) {
                throw new PromptParsingException(
                    \sprintf(
                        'Extension at index %d must be an array',
                        $index,
                    ),
                );
            }

            try {
                $extensions[] = PromptExtension::fromArray($extensionConfig);
            } catch (\InvalidArgumentException $e) {
                throw new PromptParsingException(
                    \sprintf(
                        'Invalid extension at index %d: %s',
                        $index,
                        $e->getMessage(),
                    ),
                    previous: $e,
                );
            }
        }

        return $extensions;
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
        if (empty($messagesConfig)) {
            return [];
        }

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
