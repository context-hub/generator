<?php

declare(strict_types=1);

namespace Tests\Unit\Fetcher;

use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Modifier\ModifiersApplier;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\SourceInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileSourceFetcherTest extends TestCase
{
    private string $basePath = '/test/base/path';
    private SourceModifierRegistry $modifiers;
    private FileSourceFetcher $fetcher;
    private FinderInterface $finder;

    #[Test]
    public function it_should_not_support_other_sources(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $this->assertFalse($this->fetcher->supports($source));
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_source_type(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source must be an instance of FileSource');

        $this->fetcher->fetch($source, new ModifiersApplier([]));
    }

    protected function setUp(): void
    {
        $this->modifiers = new SourceModifierRegistry();
        $this->finder = $this->createMock(FinderInterface::class);

        $this->fetcher = new FileSourceFetcher(
            basePath: $this->basePath,
            finder: $this->finder,
        );
    }
}
