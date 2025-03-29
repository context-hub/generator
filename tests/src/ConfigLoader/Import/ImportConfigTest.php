<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\ConfigLoader\Import\ImportConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportConfig::class)]
final class ImportConfigTest extends TestCase
{
    #[Test]
    public function it_should_create_from_array_with_required_fields(): void
    {
        $config = ImportConfig::fromArray(['path' => 'config.json'], '/base/path');

        $this->assertSame('config.json', $config->path);
        $this->assertSame('/base/path/config.json', $config->absolutePath);
        $this->assertNull($config->pathPrefix);
        $this->assertFalse($config->hasWildcard);
    }

    #[Test]
    public function it_should_create_from_array_with_all_fields(): void
    {
        $config = ImportConfig::fromArray(
            [
                'path' => 'config.json',
                'pathPrefix' => 'prefix',
            ],
            '/base/path',
        );

        $this->assertSame('config.json', $config->path);
        $this->assertSame('/base/path/config.json', $config->absolutePath);
        $this->assertSame('prefix', $config->pathPrefix);
        $this->assertFalse($config->hasWildcard);
    }

    #[Test]
    public function it_should_detect_wildcards_in_path(): void
    {
        $config = ImportConfig::fromArray(['path' => '*.json'], '/base/path');

        $this->assertTrue($config->hasWildcard);
        $this->assertSame('*.json', $config->path);
        $this->assertSame('/base/path/*.json', $config->absolutePath);
    }

    #[Test]
    public function it_should_handle_absolute_paths(): void
    {
        $config = ImportConfig::fromArray(['path' => '/absolute/path/config.json'], '/base/path');

        $this->assertSame('/absolute/path/config.json', $config->path);
        $this->assertSame('/absolute/path/config.json', $config->absolutePath);
    }

    #[Test]
    public function it_should_throw_exception_for_missing_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Import configuration must have a 'path' property");

        ImportConfig::fromArray([], '/base/path');
    }

    #[Test]
    public function it_should_normalize_paths_with_trailing_slashes(): void
    {
        $config = ImportConfig::fromArray(['path' => 'config.json'], '/base/path/');

        $this->assertSame('/base/path/config.json', $config->absolutePath);
    }

    #[Test]
    public function it_should_handle_nested_paths(): void
    {
        $config = ImportConfig::fromArray(['path' => 'nested/dir/config.json'], '/base/path');

        $this->assertSame('nested/dir/config.json', $config->path);
        $this->assertSame('/base/path/nested/dir/config.json', $config->absolutePath);
    }
}
