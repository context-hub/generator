<?php

declare(strict_types=1);

namespace Tests\Unit\Source\GitDiff\RenderStrategy;

use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Enum\RenderStrategyEnum;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\LLMFriendlyRenderStrategy;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\RawRenderStrategy;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\RenderStrategyFactory;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\RenderStrategyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderStrategyFactory::class)]
final class RenderStrategyFactoryTest extends TestCase
{
    private RenderStrategyFactory $factory;

    #[Test]
    public function it_should_create_raw_render_strategy(): void
    {
        $strategy = $this->factory->create(strategy: RenderStrategyEnum::Raw);

        $this->assertInstanceOf(RenderStrategyInterface::class, $strategy);
        $this->assertInstanceOf(RawRenderStrategy::class, $strategy);
    }

    #[Test]
    public function it_should_create_llm_friendly_render_strategy(): void
    {
        $strategy = $this->factory->create(strategy: RenderStrategyEnum::LLM);

        $this->assertInstanceOf(RenderStrategyInterface::class, $strategy);
        $this->assertInstanceOf(LLMFriendlyRenderStrategy::class, $strategy);
    }

    protected function setUp(): void
    {
        $this->factory = new RenderStrategyFactory();
    }
}
