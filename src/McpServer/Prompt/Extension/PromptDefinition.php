<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Extension;

use Butschster\ContextGenerator\McpServer\Prompt\PromptType;
use Mcp\Types\Prompt;
use Mcp\Types\PromptMessage;

final readonly class PromptDefinition implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public Prompt $prompt,
        /** @var PromptMessage[] */
        public array $messages = [],
        public PromptType $type = PromptType::Prompt,
        /** @var PromptExtension[] */
        public array $extensions = [],
        /** @var string[] */
        public array $tags = [],
    ) {}

    public function withMessages(array $messages): self
    {
        return new self(
            id: $this->id,
            prompt: $this->prompt,
            messages: $messages,
            type: $this->type,
            extensions: $this->extensions,
            tags: $this->tags,
        );
    }

    public function jsonSerialize(): array
    {
        $schema = [
            'properties' => [],
            'required' => [],
        ];

        foreach ($this->prompt->arguments as $argument) {
            $schema['properties'][$argument->name] = [
                'description' => $argument->description,
            ];

            if ($argument->required) {
                $schema['required'][] = $argument->name;
            }
        }

        return \array_filter([
            'id' => $this->id,
            'type' => $this->type->value,
            'description' => $this->prompt->description,
            'schema' => $schema,
            'messages' => $this->messages,
            'extend' => $this->serializeExtensions(),
            'tags' => $this->tags,
        ], static fn($value) => $value !== null && $value !== []);
    }

    /**
     * Serializes the extensions for JSON output.
     *
     * @return array<mixed>|null The serialized extensions or null if empty
     */
    private function serializeExtensions(): ?array
    {
        if (empty($this->extensions)) {
            return null;
        }

        // Convert extensions to the format used in configuration
        return \array_map(static function (PromptExtension $ext) {
            $args = [];
            foreach ($ext->arguments as $arg) {
                $args[$arg->name] = $arg->value;
            }
            return ['id' => $ext->templateId, 'arguments' => $args];
        }, $this->extensions);
    }
}
