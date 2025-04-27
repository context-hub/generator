<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container;

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

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(PromptDefinition $prompt): void
    {
        /**
         * @psalm-suppress InvalidPropertyAssignmentValue
         */
        $this->prompts[$prompt->id] = $prompt;
    }

    public function get(string $name, array $arguments = []): PromptDefinition
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'No prompt with the name "%s" exists',
                    $name,
                ),
            );
        }

        return $this->container->get(PromptMessageProcessor::class)->process($this->prompts[$name], $arguments);
    }

    public function has(string $name): bool
    {
        return isset($this->prompts[$name]);
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

    public function getType(): string
    {
        return 'prompts';
    }

    public function allPrompts(): array
    {
        return $this->getItems();
    }

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
        return $this->getItems();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getItems());
    }
}
