<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Config;

use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Enum\RenderStrategyEnum;

/**
 * Configuration for Git diff rendering
 */
final readonly class RenderConfig implements \JsonSerializable
{
    /**
     * @param RenderStrategyEnum $strategy The rendering strategy to use
     * @param bool $showStats Whether to show file stats in the output
     * @param int $contextLines Number of context lines to show around changes
     */
    public function __construct(
        public RenderStrategyEnum $strategy = RenderStrategyEnum::Raw,
        public bool $showStats = true,
        public int $contextLines = 3,
    ) {}

    /**
     * Create a RenderConfig from an array configuration
     */
    public static function fromArray(array $data): self
    {
        $strategy = isset($data['strategy'])
            ? RenderStrategyEnum::fromString($data['strategy'])
            : RenderStrategyEnum::Raw;

        return new self(
            strategy: $strategy,
            showStats: $data['showStats'] ?? true,
            contextLines: $data['contextLines'] ?? 3,
        );
    }

    /**
     * Create a RenderConfig from a string representation
     */
    public static function fromString(string $render): self
    {
        return new self(
            strategy: RenderStrategyEnum::fromString($render),
            showStats: true,
            contextLines: 3,
        );
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'strategy' => $this->strategy->value,
            'showStats' => $this->showStats,
            'contextLines' => $this->contextLines,
        ]);
    }
}
