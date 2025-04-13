<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
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

    public function get(string $id): PromptDefinition
    {
        if (!$this->has($id)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'No prompt with the name "%s" exists',
                    $id,
                ),
            );
        }

        return $this->prompts[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->prompts[$id]);
    }

    public function all(): array
    {
        return $this->prompts;
    }

    public function allTemplates(): array
    {
        return \array_filter(
            $this->prompts,
            static fn(PromptDefinition $prompt) => $prompt->type === PromptType::Template,
        );
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
        return \array_values(
            \array_filter(
                $this->prompts,
                static fn(PromptDefinition $prompt) => $prompt->type === PromptType::Prompt,
            ),
        );
    }

    public function jsonSerialize(): array
    {
        // Only serialize regular prompts, not templates
        return [
            'prompts' => $this->getItems(),
        ];
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getItems());
    }
}
