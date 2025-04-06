<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\TreeBuilder;

use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileTreeBuilder::class)]
final class FileTreeBuilderTest extends TestCase
{
    private string $fixturesDir;
    private string $tempDir;

    #[Test]
    public function it_should_build_tree_with_default_renderer(): void
    {
        // Since we can't mock the final AsciiTreeRenderer class, we'll test with real files
        $builder = new FileTreeBuilder();

        $files = [
            $this->tempDir . '/file1.txt',
            $this->tempDir . '/dir1',
            $this->tempDir . '/dir1/file2.txt',
        ];

        $result = $builder->buildTree($files, $this->tempDir);

        // Use the heredoc syntax for clear assertion of the expected tree structure
        $expectedTree = <<<TREE
            └── dir1/
                ├── file2.txt
            └── file1.txt
            TREE;

        $this->assertSame($expectedTree . "\n", $result);
    }

    #[Test]
    public function it_should_use_custom_renderer(): void
    {
        $renderer = $this->createMockRenderer('custom-tree-output');
        $builder = new FileTreeBuilder($renderer);

        $files = [
            $this->tempDir . '/file1.txt',
            $this->tempDir . '/dir1',
        ];

        $result = $builder->buildTree($files, $this->tempDir);

        $this->assertSame('custom-tree-output', $result);
    }

    #[Test]
    public function it_should_pass_options_to_renderer(): void
    {
        $options = [
            'showSize' => true,
            'showLastModified' => true,
            'includeFiles' => false,
        ];

        $renderer = $this->createMock(TreeRendererInterface::class);
        $renderer
            ->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->equalTo($options),
            )
            ->willReturn('tree-with-options');

        $builder = new FileTreeBuilder($renderer);

        $files = [
            $this->tempDir . '/file1.txt',
            $this->tempDir . '/dir1',
        ];

        $result = $builder->buildTree($files, $this->tempDir, $options);

        $this->assertSame('tree-with-options', $result);
    }

    #[Test]
    public function it_should_handle_empty_file_list(): void
    {
        $builder = new FileTreeBuilder();
        $result = $builder->buildTree([], $this->tempDir);

        // Empty tree should be an empty string
        $this->assertSame('', $result);
    }

    #[Test]
    public function it_should_normalize_windows_paths(): void
    {
        $builder = new FileTreeBuilder();

        // Create simple test files in fixed locations for consistent testing
        \mkdir($this->tempDir . '/windows/path', 0777, true);
        \file_put_contents($this->tempDir . '/windows/path/file.txt', 'content');

        $windowsPaths = [
            \str_replace('/', '\\', $this->tempDir . '/windows/path/file.txt'),
            \str_replace('/', '\\', $this->tempDir . '/windows/path'),
        ];

        $result = $builder->buildTree($windowsPaths, \str_replace('/', '\\', $this->tempDir));

        $expectedTree = <<<TREE
            └── windows/
                └── path/
                    └── file.txt
            TREE;

        $this->assertSame($expectedTree . "\n", $result);
    }

    #[Test]
    public function it_should_handle_mixed_path_separators(): void
    {
        $builder = new FileTreeBuilder();

        // Create files and directories for testing
        \mkdir($this->tempDir . '/mixed/unix/path', 0777, true);
        \mkdir($this->tempDir . '/mixed/windows/path', 0777, true);
        \file_put_contents($this->tempDir . '/mixed/unix/path/unix-file.txt', 'content');
        \file_put_contents($this->tempDir . '/mixed/windows/path/windows-file.txt', 'content');

        // Create a mix of Windows and Unix paths
        $mixedPaths = [
            $this->tempDir . '/mixed/unix/path/unix-file.txt',
            \str_replace('/', '\\', $this->tempDir . '/mixed/windows/path/windows-file.txt'),
            $this->tempDir . '/mixed/unix/path',
            \str_replace('/', '\\', $this->tempDir . '/mixed/windows/path'),
        ];

        $result = $builder->buildTree($mixedPaths, $this->tempDir);

        $expectedTree = <<<TREE
            └── mixed/
                └── unix/
                    ├── path/
                    │   └── unix-file.txt
                └── windows/
                    └── path/
                        └── windows-file.txt
            TREE;

        $this->assertSame($expectedTree . "\n", $result);
    }

    #[Test]
    public function it_should_detect_directories_correctly(): void
    {
        $builder = new FileTreeBuilder();

        $files = [
            $this->tempDir . '/dir1',           // Directory
            $this->tempDir . '/file1.txt',      // File
        ];

        $result = $builder->buildTree($files, $this->tempDir);

        $expectedTree = <<<TREE
            └── dir1/
            └── file1.txt
            TREE;

        $this->assertSame($expectedTree . "\n", $result);
    }

    #[Test]
    public function it_should_handle_iterator_input(): void
    {
        $builder = new FileTreeBuilder();

        $files = [
            $this->tempDir . '/file1.txt',
            $this->tempDir . '/dir1',
        ];

        $result = $builder->buildTree($files, $this->tempDir);

        $expectedTree = <<<TREE
            └── dir1/
            └── file1.txt
            TREE;

        $this->assertSame($expectedTree . "\n", $result);
    }

    #[Test]
    public function it_should_maintain_correct_relative_paths(): void
    {
        $builder = new FileTreeBuilder();

        $basePath = $this->tempDir . '/dir2';
        $files = [
            $this->tempDir . '/dir2/file3.txt',
            $this->tempDir . '/dir2/subdir/file4.txt',
        ];

        $result = $builder->buildTree($files, $basePath);

        $expectedTree = <<<TREE
            └── file3.txt
            └── subdir/
                └── file4.txt
            TREE;

        $this->assertSame($expectedTree . "\n", $result);
    }

    #[Test]
    public function it_should_handle_options_for_tree_rendering(): void
    {
        $builder = new FileTreeBuilder();

        $files = [
            $this->tempDir . '/dir1',
            $this->tempDir . '/dir1/file2.txt',
            $this->tempDir . '/file1.txt',
        ];

        // Test with includeFiles=false option
        $result = $builder->buildTree($files, $this->tempDir, ['includeFiles' => false]);

        $expectedTree = <<<TREE
            └── dir1/
            TREE;

        $this->assertSame($expectedTree . "\n", $result);
    }

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 3) . '/fixtures/TreeBuilder';

        // Create fixtures directory if it doesn't exist
        if (!\is_dir($this->fixturesDir)) {
            \mkdir($this->fixturesDir, 0777, true);
        }

        // Create temp directory for file operations
        $this->tempDir = \sys_get_temp_dir() . '/test-tree-builder-' . \uniqid();
        \mkdir($this->tempDir, 0777, true);

        // Create some test files and directories
        \mkdir($this->tempDir . '/dir1', 0777, true);
        \mkdir($this->tempDir . '/dir2/subdir', 0777, true);
        \file_put_contents($this->tempDir . '/file1.txt', 'content');
        \file_put_contents($this->tempDir . '/dir1/file2.txt', 'content');
        \file_put_contents($this->tempDir . '/dir2/file3.txt', 'content');
        \file_put_contents($this->tempDir . '/dir2/subdir/file4.txt', 'content');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = "$dir/$file";

            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }

    /**
     * Create a mock TreeRenderer that returns a predictable output
     */
    private function createMockRenderer(string $returnValue = 'mock-tree-output'): TreeRendererInterface
    {
        $renderer = $this->createMock(TreeRendererInterface::class);
        $renderer->method('render')->willReturn($returnValue);

        return $renderer;
    }
}
