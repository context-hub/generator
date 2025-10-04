<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Butschster\ContextGenerator\Application\FSPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FSPath::class)]
final class FSPathWindowsTest extends TestCase
{
    private const string WINDOWS_DIRECTORY_SEPARATOR = '\\';

    private bool $isSeparatorOverridden = false;

    public static function provideRelativePathTests(): \Generator
    {
        yield 'same path' => [
            'C:\\Users\\test',
            'C:\\Users\\test',
            '.',
        ];

        yield 'child directory' => [
            'C:\\Users',
            'C:\\Users\\test',
            'test',
        ];

        yield 'sibling directory' => [
            'C:\\Users\\admin',
            'C:\\Users\\test',
            '..\\test',
        ];

        yield 'different drives' => [
            'D:\\Work',
            'C:\\Users',
            'C:\\Users',  // Should return absolute path when drives differ
        ];

        yield 'deeper path' => [
            'C:\\Users',
            'C:\\Users\\test\\documents\\file.txt',
            'test\\documents\\file.txt',
        ];
    }

    #[Test]
    public function it_should_create_path_from_string(): void
    {
        $path = FSPath::create('C:\\Users\\test');

        $this->assertSame('C:\\Users\\test', $path->toString());
    }

    #[Test]
    public function it_should_join_paths_correctly(): void
    {
        $base = FSPath::create('C:\\Users');

        $joined = $base->join('test', 'documents');
        $this->assertSame('C:\\Users\\test\\documents', $joined->toString());

        $joined = $base->join('');
        $this->assertSame('C:\\Users', $joined->toString());

        $joined = $base->join('test', 'documents', '..\\..\\Current');
        $this->assertSame('C:\\Users\\Current', $joined->toString());
    }

    #[Test]
    public function it_should_handle_name_operations(): void
    {
        $path = FSPath::create('C:\\Users\\test\\document.txt');

        $newPath = $path->withName('new.txt');
        $this->assertSame('C:\\Users\\test\\new.txt', $newPath->toString());

        $extension = $path->extension();
        $this->assertSame('txt', $extension);
    }

    #[Test]
    public function it_should_handle_extension_operations(): void
    {
        $path = FSPath::create('C:\\Users\\test\\document.txt');

        $newPath = $path->withExt('md');
        $this->assertSame('C:\\Users\\test\\document.md', $newPath->toString());

        $newPath = $path->withExt('.md');
        $this->assertSame('C:\\Users\\test\\document.md', $newPath->toString());

        $newPath = $path->withStem('report');
        $this->assertSame('C:\\Users\\test\\report.txt', $newPath->toString());
    }

    #[Test]
    public function it_should_get_parent_directory(): void
    {
        $path = FSPath::create('C:\\Users\\test\\document.txt');

        $parent = $path->parent();
        $this->assertSame('C:\\Users\\test', $parent->toString());

        $grandParent = $parent->parent();
        $this->assertSame('C:\\Users', $grandParent->toString());
    }

    #[Test]
    public function it_should_handle_root_paths(): void
    {
        $root = FSPath::create('C:\\');

        // Root parent should be itself
        $this->assertSame('C:\\', $root->parent()->toString());

        // These operations should not change a root path
        $this->assertSame('C:\\', $root->withName('test')->toString());
        $this->assertSame('C:\\', $root->withExt('txt')->toString());
        $this->assertSame('C:\\', $root->withStem('test')->toString());
    }

    #[Test]
    public function it_should_check_path_types(): void
    {
        $absolutePath = FSPath::create('C:\\Users\\test');
        $this->assertTrue($absolutePath->isAbsolute());
        $this->assertFalse($absolutePath->isRelative());

        $relativePath = FSPath::create('Users\\test');
        $this->assertFalse($relativePath->isAbsolute());
        $this->assertTrue($relativePath->isRelative());
    }

    #[Test]
    #[DataProvider('provideRelativePathTests')]
    public function it_should_handle_relative_paths(string $from, string $to, string $expected): void
    {
        $fromPath = FSPath::create($from);
        $toPath = FSPath::create($to);

        $relativePath = $toPath->relativeTo($fromPath);
        $this->assertSame($expected, $relativePath->toString());
    }

    #[Test]
    public function it_should_normalize_paths(): void
    {
        // Mixed separators
        $path = FSPath::create('C:/Users\\test/documents');
        $this->assertSame('C:\\Users\\test\\documents', $path->toString());

        // Double separators
        $path = FSPath::create('C:\\\\Users\\\\\\test');
        $this->assertSame('C:\\Users\\test', $path->toString());

        // Dot segments
        $path = FSPath::create('C:\\Users\\.\\test\\..\\admin');
        $this->assertSame('C:\\Users\\admin', $path->toString());
    }

    #[Test]
    public function it_should_split_path_into_parts(): void
    {
        $path = FSPath::create('C:\\Users\\test\\documents\\file.txt');

        $parts = $path->parts();
        $this->assertSame(['C:', 'Users', 'test', 'documents', 'file.txt'], $parts);
    }

    #[Test]
    public function it_should_handle_stringable_interface(): void
    {
        $path = FSPath::create('C:\\Users\\test');

        $this->assertSame('C:\\Users\\test', (string) $path);
        $this->assertSame($path->toString(), (string) $path);
    }

    #[Test]
    public function it_should_handle_empty_paths(): void
    {
        $path = FSPath::create('');

        $this->assertSame('.', $path->toString());
        $this->assertFalse($path->isAbsolute());
        $this->assertTrue($path->isRelative());
    }

    #[Test]
    public function it_should_properly_add_extension_prefix_when_changing_stem(): void
    {
        // Test specifically for the bug where withStem doesn't add the period before extension
        $path = FSPath::create('C:\\Users\\test\\document.txt');

        $newPath = $path->withStem('report');
        $this->assertSame('C:\\Users\\test\\report.txt', $newPath->toString());

        // Also test with no extension
        $path = FSPath::create('C:\\Users\\test\\document');

        $newPath = $path->withStem('report');
        $this->assertSame('C:\\Users\\test\\report', $newPath->toString());
    }

    protected function setUp(): void
    {
        // Override directory separator for testing on non-Windows systems
        if (DIRECTORY_SEPARATOR !== self::WINDOWS_DIRECTORY_SEPARATOR) {
            FSPath::setDirectorySeparator(self::WINDOWS_DIRECTORY_SEPARATOR);
            $this->isSeparatorOverridden = true;
        }
    }

    protected function tearDown(): void
    {
        // Reset directory separator if it was overridden
        if ($this->isSeparatorOverridden) {
            FSPath::setDirectorySeparator(null);
        }
    }
}
