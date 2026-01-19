<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class DirectoryListToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_lists_directory_contents(): void
    {
        // Arrange
        $this->createFile('file1.txt', 'content');
        $this->createFile('file2.txt', 'content');
        $this->createFile('subdir/file3.txt', 'content');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'file1.txt');
        $this->assertContentContains($result, 'file2.txt');
    }

    #[Test]
    public function it_lists_with_depth(): void
    {
        // Arrange
        $this->createFile('level1/level2/level3/deep.txt', 'content');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'depth' => 3,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'level1');
        $this->assertContentContains($result, 'level2');
    }

    #[Test]
    public function it_shows_tree_view(): void
    {
        // Arrange
        $this->createFile('src/App.php', '<?php');
        $this->createFile('src/Kernel.php', '<?php');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => 'src',
            'showTree' => true,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'App.php');
        $this->assertContentContains($result, 'Kernel.php');
    }

    #[Test]
    public function it_filters_by_pattern(): void
    {
        // Arrange
        $this->createFile('app.php', '<?php');
        $this->createFile('app.js', 'js');
        $this->createFile('style.css', 'css');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'pattern' => '*.php',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'app.php');
    }

    #[Test]
    public function it_filters_directories_only(): void
    {
        // Arrange
        $this->createFile('dir1/file.txt', 'content');
        $this->createFile('dir2/file.txt', 'content');
        $this->createFile('file.txt', 'root file');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'type' => 'directory',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'dir1');
        $this->assertContentContains($result, 'dir2');
    }

    #[Test]
    public function it_fails_for_non_existent_directory(): void
    {
        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => 'non-existent-dir',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_truncates_results_when_exceeding_max_results(): void
    {
        // Arrange - create more files than maxResults
        for ($i = 1; $i <= 10; $i++) {
            $this->createFile("file{$i}.txt", 'content');
        }

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'maxResults' => 5,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = $result->getContent();
        $data = \json_decode((string) $content, true);

        $this->assertSame(5, $data['count']);
        $this->assertSame(11, $data['totalMatched']); // 10 files + context.yaml
        $this->assertTrue($data['truncated']);
        $this->assertArrayHasKey('warning', $data);
        $this->assertStringContainsString('truncated', $data['warning']);
    }

    #[Test]
    public function it_returns_all_results_when_under_max_results(): void
    {
        // Arrange
        $this->createFile('file1.txt', 'content');
        $this->createFile('file2.txt', 'content');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'maxResults' => 100,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = $result->getContent();
        $data = \json_decode((string) $content, true);

        $this->assertSame($data['count'], $data['totalMatched']);
        $this->assertArrayNotHasKey('truncated', $data);
        $this->assertArrayNotHasKey('warning', $data);
    }

    #[Test]
    public function it_returns_unlimited_results_when_max_results_is_zero(): void
    {
        // Arrange - create many files
        for ($i = 1; $i <= 20; $i++) {
            $this->createFile("file{$i}.txt", 'content');
        }

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'maxResults' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = $result->getContent();
        $data = \json_decode((string) $content, true);

        $this->assertSame(21, $data['count']); // 20 files + context.yaml
        $this->assertSame(21, $data['totalMatched']);
        $this->assertArrayNotHasKey('truncated', $data);
    }

    #[Test]
    public function it_includes_total_matched_in_response(): void
    {
        // Arrange
        $this->createFile('file1.txt', 'content');
        $this->createFile('file2.txt', 'content');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = $result->getContent();
        $data = \json_decode((string) $content, true);

        $this->assertArrayHasKey('totalMatched', $data);
        $this->assertSame($data['count'], $data['totalMatched']);
    }

    #[Test]
    public function it_truncates_with_tree_view(): void
    {
        // Arrange
        for ($i = 1; $i <= 10; $i++) {
            $this->createFile("file{$i}.txt", 'content');
        }

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'maxResults' => 3,
            'showTree' => true,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = $result->getContent();
        $data = \json_decode((string) $content, true);

        $this->assertSame(3, $data['count']);
        $this->assertTrue($data['truncated']);
        $this->assertArrayHasKey('treeView', $data);
        $this->assertArrayHasKey('warning', $data);
    }
}
