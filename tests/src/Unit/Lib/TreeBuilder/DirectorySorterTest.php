<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\TreeBuilder;

use Butschster\ContextGenerator\Lib\TreeBuilder\DirectorySorter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectorySorter::class)]
final class DirectorySorterTest extends TestCase
{
    #[Test]
    public function it_should_sort_empty_array(): void
    {
        $this->assertSame([], DirectorySorter::sort([]));
    }

    #[Test]
    public function it_should_not_sort_single_item_array(): void
    {
        $dirs = ['/path/to/dir'];
        $this->assertSame($dirs, DirectorySorter::sort($dirs));
    }

    #[Test]
    public function it_should_sort_paths_by_depth_then_alphabetically(): void
    {
        $dirs = [
            '/c/deep/path',
            '/a',
            '/b/middle',
            '/a/child',
            '/b',
        ];

        $expected = [
            '/a',
            '/b',
            '/a/child',
            '/b/middle',
            '/c/deep/path',
        ];

        $this->assertSame($expected, DirectorySorter::sort($dirs));
    }

    #[Test]
    public function it_should_ensure_parent_directories_come_before_children(): void
    {
        $dirs = [
            '/parent/child/grandchild',
            '/parent',
            '/parent/child',
            '/other',
        ];

        $expected = [
            '/other',
            '/parent',
            '/parent/child',
            '/parent/child/grandchild',
        ];

        $this->assertSame($expected, DirectorySorter::sort($dirs));
    }

    #[Test]
    public function it_should_normalize_windows_paths_to_unix_style(): void
    {
        $dirs = [
            'parent\\child',
            'parent',
            'other\\path',
        ];

        $expected = [
            'other/path',
            'parent',
            'parent/child',
        ];

        $this->assertSame($expected, DirectorySorter::sort($dirs));
    }

    #[Test]
    public function it_should_remove_windows_drive_letters(): void
    {
        $dirs = [
            'C:\\parent\\child',
            'C:\\parent',
            'D:\\other',
        ];

        $expected = [
            '/other',
            '/parent',
            '/parent/child',
        ];

        $this->assertSame($expected, DirectorySorter::sort($dirs));
    }

    #[Test]
    public function it_should_remove_duplicate_paths(): void
    {
        $dirs = [
            '/path/one',
            '/path/two',
            '/path/one', // Duplicate
            '/path/three',
            '/path/two/', // Duplicate with trailing slash
        ];

        $expected = [
            '/path/one',
            '/path/three',
            '/path/two',
        ];

        $this->assertSame($expected, DirectorySorter::sort($dirs));
    }

    #[Test]
    public function it_should_handle_iterator_input(): void
    {
        $dirs = new \ArrayIterator([
            '/path/b',
            '/path/a',
        ]);

        $expected = [
            '/path/a',
            '/path/b',
        ];

        $this->assertSame($expected, DirectorySorter::sort($dirs));
    }

    #[Test]
    public function it_should_sort_preserving_separators(): void
    {
        $dirs = [
            'windows\\path',
            '/unix/path',
            'windows\\another',
        ];

        $sorted = DirectorySorter::sortPreservingSeparators($dirs);

        // We should get another, path, unix/path in alphabetical order
        $this->assertContains('windows\\another', $sorted);
        $this->assertContains('windows\\path', $sorted);
        $this->assertContains('/unix/path', $sorted);

        // Check that windows\another comes before windows\path
        $anotherPos = \array_search('windows\\another', $sorted);
        $pathPos = \array_search('windows\\path', $sorted);
        $this->assertLessThan($pathPos, $anotherPos, "windows\\another should come before windows\\path");

        // Check that we have the same number of items
        $this->assertCount(\count($dirs), $sorted);
    }

    #[Test]
    public function it_should_preserve_original_drive_letters_when_preserving_separators(): void
    {
        $dirs = [
            'C:\\windows\\path',
            'D:\\other\\path',
            'C:\\windows\\another',
        ];

        $sorted = DirectorySorter::sortPreservingSeparators($dirs);

        // The original format with drive letters should be preserved
        foreach ($sorted as $path) {
            $this->assertContains($path, $dirs, "Original path format with drive letter should be preserved");
        }

        // All items should be included
        $this->assertCount(\count($dirs), $sorted);

        // Check that C:\windows\another comes before C:\windows\path alphabetically
        $anotherPos = \array_search('C:\\windows\\another', $sorted);
        $pathPos = \array_search('C:\\windows\\path', $sorted);
        if ($anotherPos !== false && $pathPos !== false) {
            $this->assertLessThan($pathPos, $anotherPos, "C:\\windows\\another should come before C:\\windows\\path");
        }
    }

    #[Test]
    public function it_should_handle_empty_array_when_preserving_separators(): void
    {
        $this->assertSame([], DirectorySorter::sortPreservingSeparators([]));
    }

    #[Test]
    public function it_should_preserve_original_path_format(): void
    {
        $dirs = [
            'C:\\Program Files\\App',
            '/var/www/html',
            'C:\\Users\\user\\Documents',
        ];

        $sorted = DirectorySorter::sortPreservingSeparators($dirs);

        // The order might change but the original format should be preserved
        foreach ($sorted as $path) {
            $this->assertContains($path, $dirs, "Original path format should be preserved");
        }

        // Check that we have the same number of items
        $this->assertCount(\count($dirs), $sorted);
    }

    #[Test]
    public function it_should_handle_consistent_path_comparison_with_drive_letters(): void
    {
        $dirs = [
            'C:\\parent\\child\\file.txt',
            'C:\\parent\\file.txt',
            'D:\\other\\file.txt',
        ];

        $sortedPaths = DirectorySorter::sort($dirs);

        // Check for correct alphabetical sorting and parent-child relationships
        $this->assertEquals('/other/file.txt', $sortedPaths[0]);
        $this->assertEquals('/parent/file.txt', $sortedPaths[1]);
        $this->assertEquals('/parent/child/file.txt', $sortedPaths[2]);
    }
}
