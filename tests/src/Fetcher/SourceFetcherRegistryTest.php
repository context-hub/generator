<?php

declare(strict_types=1);

namespace Tests\Fetcher;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\SourceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SourceFetcherRegistryTest extends TestCase
{
    #[Test]
    public function it_should_register_fetchers(): void
    {
        $fetcher = $this->createMock(SourceFetcherInterface::class);
        $registry = new SourceFetcherRegistry();

        $result = $registry->register($fetcher);

        $this->assertSame($registry, $result);
    }

    #[Test]
    public function it_should_find_supporting_fetcher(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $supportingFetcher = $this->createMock(SourceFetcherInterface::class);
        $supportingFetcher
            ->expects($this->once())
            ->method('supports')
            ->with($source)
            ->willReturn(true);

        $nonSupportingFetcher = $this->createMock(SourceFetcherInterface::class);
        $nonSupportingFetcher
            ->expects($this->once())
            ->method('supports')
            ->with($source)
            ->willReturn(false);

        $registry = new SourceFetcherRegistry(fetchers: [$nonSupportingFetcher, $supportingFetcher]);

        $result = $registry->findFetcher($source);

        $this->assertSame($supportingFetcher, $result);
    }

    #[Test]
    public function it_should_throw_exception_when_no_fetcher_found(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $fetcher = $this->createMock(SourceFetcherInterface::class);
        $fetcher
            ->expects($this->once())
            ->method('supports')
            ->with($source)
            ->willReturn(false);

        $registry = new SourceFetcherRegistry(fetchers: [$fetcher]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No fetcher found for source of type ' . $source::class);

        $registry->findFetcher($source);
    }

    #[Test]
    public function it_should_initialize_with_empty_fetchers_array(): void
    {
        $registry = new SourceFetcherRegistry();
        $source = $this->createMock(SourceInterface::class);

        $this->expectException(\RuntimeException::class);

        $registry->findFetcher($source);
    }
}
