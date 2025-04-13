<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

enum PromptType: string
{
    case Prompt = 'prompt';
    case Template = 'template';

    /**
     * Creates a PromptType from a string.
     *
     * @param string|null $type The type string
     * @return self The corresponding PromptType, defaults to PROMPT if null or invalid
     */
    public static function fromString(?string $type): self
    {
        if ($type === null) {
            return self::Prompt;
        }

        return match (\strtolower($type)) {
            'template' => self::Template,
            default => self::Prompt,
        };
    }
}
