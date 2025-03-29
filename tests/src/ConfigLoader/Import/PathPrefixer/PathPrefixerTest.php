<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import\PathPrefixer;

use Butschster\ContextGenerator\ConfigLoader\Import\PathPrefixer\PathPrefixer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test for the abstract PathPrefixer class
 */
#[CoversClass(PathPrefixer::class)]
final class PathPrefixerTest extends TestCase
{
    /**
     * Test the combinePaths method in the PathPrefixer class
     */
    #[Test]
    public function it_should_properly_combine_paths(): void
    {
        // Create a concrete implementation of the abstract class for testing
        $prefixer = new readonly class extends PathPrefixer {
            public function applyPrefix(array $config, string $pathPrefix): array
            {
                return $config;
            }

            public function combinePaths(string $prefix, string $path): string
            {
                return parent::combinePaths($prefix, $path);
            }

            public function isAbsolutePath(string $path): bool
            {
                return parent::isAbsolutePath($path);
            }
        };

        // Test with various combinations of slashes
        $this->assertEquals('prefix/path', $prefixer->combinePaths('prefix', 'path'));
        $this->assertEquals('prefix/path', $prefixer->combinePaths('prefix/', 'path'));
        $this->assertEquals('prefix/path', $prefixer->combinePaths('prefix', '/path'));
        $this->assertEquals('prefix/path', $prefixer->combinePaths('prefix/', '/path'));

        // Test with nested paths
        $this->assertEquals('prefix/nested/path', $prefixer->combinePaths('prefix', 'nested/path'));
        $this->assertEquals('prefix/nested/path', $prefixer->combinePaths('prefix/', '/nested/path'));
    }

    /**
     * Test the isAbsolutePath method in the PathPrefixer class
     */
    #[Test]
    public function it_should_correctly_identify_absolute_paths(): void
    {
        // Create a concrete implementation of the abstract class for testing
        $prefixer = new readonly class extends PathPrefixer {
            public function applyPrefix(array $config, string $pathPrefix): array
            {
                return $config;
            }

            public function combinePaths(string $prefix, string $path): string
            {
                return parent::combinePaths($prefix, $path);
            }

            public function isAbsolutePath(string $path): bool
            {
                return parent::isAbsolutePath($path);
            }
        };

        // Test absolute and relative paths
        $this->assertTrue($prefixer->isAbsolutePath('/absolute/path'));
        $this->assertFalse($prefixer->isAbsolutePath('relative/path'));
        $this->assertFalse($prefixer->isAbsolutePath('./relative/path'));
        $this->assertFalse($prefixer->isAbsolutePath('../relative/path'));
    }
}
