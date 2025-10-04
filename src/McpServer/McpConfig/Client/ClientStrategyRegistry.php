<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

final class ClientStrategyRegistry
{
    /** @var array<string, ClientStrategyInterface> */
    private array $strategies;

    public function __construct()
    {
        $this->strategies = [];

        $this->register(new ClaudeDesktopClientStrategy());
        $this->register(new CodexClientStrategy());
        $this->register(new CursorClientStrategy());
        $this->register(new GenericClientStrategy());
    }

    public function register(ClientStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getKey()] = $strategy;
    }

    public function getByKey(string $key): ?ClientStrategyInterface
    {
        $key = \strtolower($key);
        return $this->strategies[$key] ?? null;
    }

    public function getByLabel(string $label): ?ClientStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getLabel() === $label) {
                return $strategy;
            }
        }
        return null;
    }

    /**
     * @return string[] Human-friendly labels for interactive choice
     */
    public function getChoiceLabels(): array
    {
        return \array_map(static fn(ClientStrategyInterface $s) => $s->getLabel(), $this->strategies);
    }

    public function getDefault(): ClientStrategyInterface
    {
        return $this->strategies['claude'];
    }
}
