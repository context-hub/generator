<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * Registry for storing prompt configurations.
 * @template TPrompt of PromptDefinition
 * @implements RegistryInterface<TPrompt>
 */
#[Singleton]
final class PromptRegistry implements RegistryInterface, PromptProviderInterface, PromptRegistryInterface
{
    /** @var array<non-empty-string, TPrompt> */
    private array $prompts = [];

    public function register(PromptDefinition $prompt): void
    {
        /**
         * @psalm-suppress InvalidPropertyAssignmentValue
         */
        $this->prompts[$prompt->id] = $prompt;
    }

    public function get(string $name): PromptDefinition
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'No prompt with the name "%s" exists',
                    $name,
                ),
            );
        }

        return $this->prompts[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->prompts[$name]);
    }

    public function all(): array
    {
        return $this->prompts;
    }

    /**
     * Gets the type of the registry.
     */
    public function getType(): string
    {
        return 'prompts';
    }

    /**
     * Gets all items in the registry.
     *
     * @return array<TPrompt>
     */
    public function getItems(): array
    {
        return \array_values($this->prompts);
    }

    public function jsonSerialize(): array
    {
        return [
            'prompts' => \array_values($this->prompts),
        ];
    }
}
