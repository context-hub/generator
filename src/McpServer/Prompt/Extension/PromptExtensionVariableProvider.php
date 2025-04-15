<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Extension;

use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;

/**
 * Variable provider that provides values from prompt extension arguments.
 */
final readonly class PromptExtensionVariableProvider implements VariableProviderInterface
{
    /**
     * @var array<string, string> The variables from extension arguments
     */
    private array $variables;

    /**
     * @param PromptExtensionArgument[] $arguments The extension arguments
     */
    public function __construct(array $arguments)
    {
        $this->variables = $this->createVariablesFromArguments($arguments);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->variables);
    }

    public function get(string $name): ?string
    {
        return $this->variables[$name] ?? null;
    }

    /**
     * Creates a variables map from extension arguments.
     *
     * @param PromptExtensionArgument[] $arguments The extension arguments
     * @return array<string, string> The variables map
     */
    private function createVariablesFromArguments(array $arguments): array
    {
        $variables = [];

        foreach ($arguments as $argument) {
            $variables[$argument->name] = $argument->value;
        }

        return $variables;
    }
}
