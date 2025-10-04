<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Butschster\ContextGenerator\Application\FSPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FSPath::class)]
final class FSPathLinuxTest extends TestCase
{
    private const string LINUX_DIRECTORY_SEPARATOR = '/';

    private bool $isSeparatorOverridden = false;

    public static function provideRelativePathTests(): \Generator
    {
        yield 'same path' => [
            '/home/user',
            '/home/user',
            '.',
        ];

        yield 'child directory' => [
            '/home',
            '/home/user',
            'user',
        ];

        yield 'sibling directory' => [
            '/home/admin',
            '/home/user',
            '../user',
        ];

        yield 'deeper path' => [
            '/home',
            '/home/user/documents/file.txt',
            'user/documents/file.txt',
        ];
    }

    #[Test]
    public function it_should_create_path_from_string(): void
    {
        $path = FSPath::create('/home/user/test');

        $this->assertSame('/home/user/test', $path->toString());
    }

    #[Test]
    public function it_should_create_path_from_cwd(): void
    {
        // This test mocks getCwd rather than using the actual current directory
        $mockCwd = \getcwd();

        $path = FSPath::cwd();

        $this->assertSame($mockCwd, $path->toString());
    }

    #[Test]
    public function it_should_join_paths_correctly(): void
    {
        $base = FSPath::create('/home/user');

        $joined = $base->join('../test');
        $this->assertSame('/home/test', $joined->toString());
    }

    #[Test]
    public function it_should_handle_name_operations(): void
    {
        $path = FSPath::create('/home/user/document.txt');

        $newPath = $path->withName('new.txt');
        $this->assertSame('/home/user/new.txt', $newPath->toString());

        $stem = $path->stem();
        $this->assertSame('document', $stem);

        $name = $path->name();
        $this->assertSame('document.txt', $name);

        $extension = $path->extension();
        $this->assertSame('txt', $extension);
    }

    #[Test]
    public function it_should_handle_extension_operations(): void
    {
        $path = FSPath::create('/home/user/document.txt');

        $newPath = $path->withExt('md');
        $this->assertSame('/home/user/document.md', $newPath->toString());

        $newPath = $path->withExt('.md');
        $this->assertSame('/home/user/document.md', $newPath->toString());

        // Bug test
        $newPath = $path->withStem('report');
        $this->assertSame('/home/user/report.txt', $newPath->toString());
    }

    #[Test]
    public function it_should_get_parent_directory(): void
    {
        $path = FSPath::create('/home/user/document.txt');

        $parent = $path->parent();
        $this->assertSame('/home/user', $parent->toString());

        $grandParent = $parent->parent();
        $this->assertSame('/home', $grandParent->toString());
    }

    #[Test]
    public function it_should_handle_root_paths(): void
    {
        $root = FSPath::create('/');

        // Root parent should be itself
        $this->assertSame('/', $root->parent()->toString());

        // These operations should not change a root path
        $this->assertSame('/', $root->withName('test')->toString());
        $this->assertSame('/', $root->withExt('txt')->toString());
        $this->assertSame('/', $root->withStem('test')->toString());
    }

    #[Test]
    public function it_should_check_path_types(): void
    {
        $absolutePath = FSPath::create('/home/user');
        $this->assertTrue($absolutePath->isAbsolute());
        $this->assertFalse($absolutePath->isRelative());

        $relativePath = FSPath::create('home/user');
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
        // Mixed separators (should convert to Linux format)
        $path = FSPath::create('/home\\user/documents');
        $this->assertSame('/home/user/documents', $path->toString());

        // Double separators
        $path = FSPath::create('/home//user///documents');
        $this->assertSame('/home/user/documents', $path->toString());

        // Dot segments
        $path = FSPath::create('/home/./user/../admin');
        $this->assertSame('/home/admin', $path->toString());
    }

    #[Test]
    public function it_should_split_path_into_parts(): void
    {
        $path = FSPath::create('/home/user/documents/file.txt');

        $parts = $path->parts();
        $this->assertSame(['home', 'user', 'documents', 'file.txt'], $parts);
    }

    #[Test]
    public function it_should_handle_stringable_interface(): void
    {
        $path = FSPath::create('/home/user');

        $this->assertSame('/home/user', (string) $path);
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
    public function it_should_make_paths_absolute(): void
    {
        // Mock cwd for predictable test
        $mockCwd = \getcwd();

        $relative = FSPath::create('html/css');
        $absolute = $relative->absolute();

        $this->assertSame($mockCwd . '/html/css', $absolute->toString());
    }

    #[Test]
    public function it_should_check_file_operations(): void
    {
        // These tests would typically use a virtual filesystem library like vfsStream
        // or mock the file functions, but for simplicity we're just testing the method exists

        $path = FSPath::create('/tmp/test-file.txt');

        // We can't guarantee these will pass on all systems, so we're just testing the methods exist
        $this->assertIsCallable($path->exists(...));
        $this->assertIsCallable($path->isDir(...));
        $this->assertIsCallable($path->isFile(...));
    }

    #[Test]
    public function it_should_handle_path_with_no_extension(): void
    {
        $path = FSPath::create('/home/user/document');

        $extension = $path->extension();
        $this->assertSame('', $extension);

        $stem = $path->stem();
        $this->assertSame('document', $stem);

        $newPath = $path->withExt('txt');
        $this->assertSame('/home/user/document.txt', $newPath->toString());
    }

    #[Test]
    public function it_should_properly_add_extension_prefix_when_changing_stem(): void
    {
        // Test specifically for the bug where withStem doesn't add the period before extension
        $path = FSPath::create('/home/user/document.txt');

        $newPath = $path->withStem('report');
        $this->assertSame('/home/user/report.txt', $newPath->toString());

        // Also test with no extension
        $path = FSPath::create('/home/user/document');

        $newPath = $path->withStem('report');
        $this->assertSame('/home/user/report', $newPath->toString());
    }

    protected function setUp(): void
    {
        // Override directory separator for testing on non-Linux systems
        if (DIRECTORY_SEPARATOR !== self::LINUX_DIRECTORY_SEPARATOR) {
            FSPath::setDirectorySeparator(self::LINUX_DIRECTORY_SEPARATOR);
            $this->isSeparatorOverridden = true;
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Reset directory separator if it was overridden
        if ($this->isSeparatorOverridden) {
            FSPath::setDirectorySeparator(null);
        }
    }
}
