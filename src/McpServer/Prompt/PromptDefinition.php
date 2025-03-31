<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Mcp\Types\Prompt;
use Mcp\Types\PromptMessage;

final readonly class PromptDefinition implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public Prompt $prompt,
        /** @var PromptMessage[] */
        public array $messages = [],
    ) {}

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
            'description' => $this->prompt->description,
            'schema' => $schema,
            'messages' => $this->messages,
        ], static fn($value) => $value !== null && $value !== []);
    }
}
