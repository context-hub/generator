<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Lib\Variable\Provider\ConfigVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Mcp\Types\PromptMessage;
use Mcp\Types\TextContent;

final readonly class PromptMessageProcessor
{
    public function __construct(
        private VariableResolver $variables,
    ) {}

    public function process(PromptDefinition $prompt, array $arguments = []): PromptDefinition
    {
        $variables = $this->variables;

        return $prompt->withMessages(\array_map(static function ($message) use ($variables, $arguments) {
            $content = $message->content;

            if ($content instanceof TextContent) {
                $text = $variables->with(
                    new VariableReplacementProcessor(new ConfigVariableProvider($arguments)),
                )->resolve($content->text);

                $content = new TextContent($text);
            }

            return new PromptMessage(
                role: $message->role,
                content: $content,
            );
        }, $prompt->messages));
    }
}
