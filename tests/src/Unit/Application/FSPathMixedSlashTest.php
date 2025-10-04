<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Butschster\ContextGenerator\Application\FSPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FSPath::class)]
final class FSPathMixedSlashTest extends TestCase
{
    public static function provideRelativePathsWithMixedSlashes(): \Generator
    {
        yield 'mixed slashes same path' => [
            'C:/Users/test',
            'C:\\Users\\test',
            '.',
            '.',
        ];

        yield 'mixed slashes child directory' => [
            'C:/Users',
            'C:\\Users\\test',
            'test',
            'test',
        ];

        yield 'mixed slashes sibling directory' => [
            'C:/Users/admin',
            'C:\\Users\\test',
            '..\\test',
            '../test',
        ];

        yield 'mixed slashes deeper path' => [
            'D:/projects',
            'D:/projects/app/public/index.php',
            'app\\public\\index.php',
            'app/public/index.php',
        ];
    }

    #[Test]
    public function it_should_normalize_windows_paths_with_forward_slashes(): void
    {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $path = FSPath::create('C:/Users/test/documents');
        $this->assertSame('C:\\Users\\test\\documents', $path->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $path = FSPath::create('C:/Users/test/documents');
        $this->assertSame('C:/Users/test/documents', $path->toString());
    }

    #[Test]
    public function it_should_join_mixed_slash_paths(): void
    {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $base = FSPath::create('C:/Users');
        $joined = $base->join('test\\documents', 'files/data');
        $this->assertSame('C:\\Users\\test\\documents\\files\\data', $joined->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $base = FSPath::create('C:/Users');
        $joined = $base->join('test\\documents', 'files/data');
        $this->assertSame('C:/Users/test/documents/files/data', $joined->toString());
    }

    #[Test]
    public function it_should_handle_typical_git_style_paths(): void
    {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $path = FSPath::create('D:/git/project/.git/config');
        $this->assertSame('D:\\git\\project\\.git\\config', $path->toString());

        $parent = $path->parent();
        $this->assertSame('D:\\git\\project\\.git', $parent->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $path = FSPath::create('D:/git/project/.git/config');
        $this->assertSame('D:/git/project/.git/config', $path->toString());

        $parent = $path->parent();
        $this->assertSame('D:/git/project/.git', $parent->toString());
    }

    #[Test]
    public function it_should_detect_absolute_paths_with_forward_slashes(): void
    {
        // Windows absolute path detection with forward slashes
        FSPath::setDirectorySeparator('\\');
        $path = FSPath::create('C:/Users/test');
        $this->assertTrue($path->isAbsolute());
        $this->assertFalse($path->isRelative());
    }

    #[Test]
    public function it_should_handle_file_operations_with_mixed_slashes(): void
    {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $path = FSPath::create('C:/Users/test/document.txt');

        $extension = $path->extension();
        $this->assertSame('txt', $extension);

        $newPath = $path->withStem('report');
        $this->assertSame('C:\\Users\\test\\report.txt', $newPath->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $path = FSPath::create('C:/Users/test/document.txt');

        $newPath = $path->withStem('report');
        $this->assertSame('C:/Users/test/report.txt', $newPath->toString());
    }

    #[Test]
    public function it_should_handle_mixing_slashes_in_path_segments(): void
    {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $path = FSPath::create('root');
        $mixed = $path->join('folder1/sub1', 'folder2\\sub2');
        $this->assertSame('root\\folder1\\sub1\\folder2\\sub2', $mixed->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $path = FSPath::create('root');
        $mixed = $path->join('folder1/sub1', 'folder2\\sub2');
        $this->assertSame('root/folder1/sub1/folder2/sub2', $mixed->toString());
    }

    #[Test]
    public function it_should_handle_configuration_file_paths(): void
    {
        // Test with Windows separator - specific example from the request
        FSPath::setDirectorySeparator('\\');
        $configPath = FSPath::create('D:/git/context.yaml');
        $this->assertSame('D:\\git\\context.yaml', $configPath->toString());

        $parentDir = $configPath->parent();
        $this->assertSame('D:\\git', $parentDir->toString());

        $newName = $configPath->withName('context.json');
        $this->assertSame('D:\\git\\context.json', $newName->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $configPath = FSPath::create('D:/git/context.yaml');
        $this->assertSame('D:/git/context.yaml', $configPath->toString());

        $parentDir = $configPath->parent();
        $this->assertSame('D:/git', $parentDir->toString());

        $newName = $configPath->withName('context.json');
        $this->assertSame('D:/git/context.json', $newName->toString());
    }

    #[Test]
    #[DataProvider('provideRelativePathsWithMixedSlashes')]
    public function it_should_handle_relative_paths_with_mixed_slashes(
        string $from,
        string $to,
        string $expectedWindows,
        string $expectedUnix,
    ): void {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $fromPath = FSPath::create($from);
        $toPath = FSPath::create($to);
        $relativePath = $toPath->relativeTo($fromPath);
        $this->assertSame($expectedWindows, $relativePath->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $fromPath = FSPath::create($from);
        $toPath = FSPath::create($to);
        $relativePath = $toPath->relativeTo($fromPath);
        $this->assertSame($expectedUnix, $relativePath->toString());
    }

    #[Test]
    public function it_should_split_mixed_slash_paths_into_parts(): void
    {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $path = FSPath::create('C:/Users\\test/documents\\file.txt');
        $parts = $path->parts();
        $this->assertSame(['C:', 'Users', 'test', 'documents', 'file.txt'], $parts);

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $path = FSPath::create('C:/Users\\test/documents\\file.txt');
        $parts = $path->parts();
        $this->assertSame(['C:', 'Users', 'test', 'documents', 'file.txt'], $parts);
    }

    #[Test]
    public function it_should_normalize_path_with_many_mixed_slashes(): void
    {
        // Test with Windows separator
        FSPath::setDirectorySeparator('\\');
        $path = FSPath::create('C:\\\\Users//test\\/documents///file.txt');
        $this->assertSame('C:\\Users\\test\\documents\\file.txt', $path->toString());

        // Test with Unix separator
        FSPath::setDirectorySeparator('/');
        $path = FSPath::create('C:\\\\Users//test\\/documents///file.txt');
        $this->assertSame('C:/Users/test/documents/file.txt', $path->toString());
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Always reset directory separator at the end of each test
        FSPath::setDirectorySeparator(null);
    }
}
