<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\RenderStrategy;

use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Enum\RenderStrategyEnum;

/**
 * Factory for creating render strategy instances
 */
final readonly class RenderStrategyFactory
{
    /**
     * Available render strategies
     *
     * @var array<non-empty-string, class-string<RenderStrategyInterface>> Map of strategy enum to class name
     */
    private const array STRATEGIES = [
        RenderStrategyEnum::Raw->value => RawRenderStrategy::class,
        RenderStrategyEnum::LLM->value => LLMFriendlyRenderStrategy::class,
    ];

    /**
     * Create a render strategy instance based on the render config
     *
     * @param RenderStrategyEnum $strategy The render strategy enum
     * @return RenderStrategyInterface The render strategy instance
     * @throws \InvalidArgumentException If the strategy is not valid
     */
    public function create(RenderStrategyEnum $strategy): RenderStrategyInterface
    {
        if (!isset(self::STRATEGIES[$strategy->value])) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Invalid render strategy "%s". Valid strategies are: %s',
                    $strategy->value,
                    \implode(', ', \array_keys(self::STRATEGIES)),
                ),
            );
        }

        $className = self::STRATEGIES[$strategy->value];
        return new $className();
    }
}
