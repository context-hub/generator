<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Extension;

/**
 * Represents a template extension configuration.
 */
final readonly class PromptExtension
{
    /**
     * @param string $templateId The ID of the template to extend
     * @param PromptExtensionArgument[] $arguments The arguments to pass to the template
     */
    public function __construct(
        public string $templateId,
        public array $arguments = [],
    ) {}

    /**
     * Creates a PromptExtension from a configuration array.
     *
     * @param array<string, mixed> $config The extension configuration
     * @return self The created PromptExtension
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    public static function fromArray(array $config): self
    {
        if (empty($config['id']) || !\is_string($config['id'])) {
            throw new \InvalidArgumentException('Extension must have a template ID');
        }

        $arguments = [];
        if (isset($config['arguments']) && \is_array($config['arguments'])) {
            foreach ($config['arguments'] as $name => $value) {
                if (!\is_string($name) || !\is_string($value)) {
                    throw new \InvalidArgumentException(
                        \sprintf('Extension argument "%s" must have a string value', $name),
                    );
                }

                $arguments[] = new PromptExtensionArgument($name, $value);
            }
        }

        return new self($config['id'], $arguments);
    }
}
