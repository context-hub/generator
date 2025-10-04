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
use Tests\TestCase;

#[CoversClass(RenderStrategyFactory::class)]
final class RenderStrategyFactoryTest extends TestCase
{
    private RenderStrategyFactory $factory;

    #[Test]
    public function it_should_create_raw_render_strategy(): void
    {
        $strategy = $this->factory->create(RenderStrategyEnum::Raw);

        $this->assertInstanceOf(RenderStrategyInterface::class, $strategy);
        $this->assertInstanceOf(RawRenderStrategy::class, $strategy);
    }

    #[Test]
    public function it_should_create_llm_friendly_render_strategy(): void
    {
        $strategy = $this->factory->create(RenderStrategyEnum::LLM);

        $this->assertInstanceOf(RenderStrategyInterface::class, $strategy);
        $this->assertInstanceOf(LLMFriendlyRenderStrategy::class, $strategy);
    }

    protected function setUp(): void
    {
        $this->factory = new RenderStrategyFactory();
    }
}
