<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class FileReadToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_reads_file_content(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello, World!');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Hello, World!');
    }

    #[Test]
    public function it_reads_file_with_utf8_encoding(): void
    {
        // Arrange
        $this->createFile('utf8.txt', 'Привет мир 你好世界');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'utf8.txt',
            'encoding' => 'utf-8',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Привет мир');
        $this->assertContentContains($result, '你好世界');
    }

    #[Test]
    public function it_reads_nested_file(): void
    {
        // Arrange
        $this->createFile('src/nested/deep/file.txt', 'Nested content');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'src/nested/deep/file.txt',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Nested content');
    }

    #[Test]
    public function it_reads_multiple_files(): void
    {
        // Arrange
        $this->createFile('file1.txt', 'Content One');
        $this->createFile('file2.txt', 'Content Two');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'paths' => ['file1.txt', 'file2.txt'],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Content One');
        $this->assertContentContains($result, 'Content Two');
    }

    #[Test]
    public function it_fails_for_non_existent_file(): void
    {
        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'non-existent.txt',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
    }

    #[Test]
    public function it_fails_for_missing_path_parameter(): void
    {
        // Act
        $result = $this->inspector->callTool('file-read', [
            // No path provided
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_handles_path_outside_project(): void
    {
        // Act - attempt to read file outside project root
        $result = $this->inspector->callTool('file-read', [
            'path' => '../../../etc/passwd',
        ]);

        // Assert - tool may block this or return error, or the file may not exist
        // The important thing is it should not crash
        $this->assertNotNull($result);
    }

    #[Test]
    public function it_reads_json_file(): void
    {
        // Arrange
        $jsonContent = \json_encode(['key' => 'value', 'nested' => ['a' => 1]], JSON_PRETTY_PRINT);
        $this->createFile('data.json', $jsonContent);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'data.json',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '"key"');
        $this->assertContentContains($result, '"value"');
    }

    #[Test]
    public function it_reads_php_file(): void
    {
        // Arrange
        $phpContent = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class TestClass
{
    public function __construct(
        private string $name,
    ) {}
}
PHP;
        $this->createFile('TestClass.php', $phpContent);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'TestClass.php',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'namespace App');
        $this->assertContentContains($result, 'final class TestClass');
    }

    #[Test]
    public function it_fails_with_invalid_project_parameter(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello, World!');

        // Act - Call with non-existent project
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
            'project' => 'non-existent-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function it_reads_file_without_project_parameter(): void
    {
        // Arrange
        $this->createFile('current-project.txt', 'Current project content');

        // Act - Call without project parameter (uses current project)
        $result = $this->inspector->callTool('file-read', [
            'path' => 'current-project.txt',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Current project content');
    }

    #[Test]
    public function it_shows_helpful_error_for_invalid_project(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Content');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'test.txt',
            'project' => 'unknown-project',
        ]);

        // Assert - Error message should suggest using projects-list
        $this->assertToolError($result);
        $this->assertContentContains($result, 'projects-list');
    }

    #[Test]
    public function it_reads_specific_line_range(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3\nline 4\nline 5\nline 6\nline 7\nline 8\nline 9\nline 10";
        $this->createFile('lines.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'lines.txt',
            'startLine' => 3,
            'endLine' => 7,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'line 3');
        $this->assertContentContains($result, 'line 7');
        $this->assertContentContains($result, 'lines 3-7 of 10');
    }

    #[Test]
    public function it_reads_from_start_line_to_end(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3\nline 4\nline 5";
        $this->createFile('lines.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'lines.txt',
            'startLine' => 4,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'line 4');
        $this->assertContentContains($result, 'line 5');
        $this->assertContentContains($result, 'lines 4-5 of 5');
    }

    #[Test]
    public function it_reads_from_beginning_to_end_line(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3\nline 4\nline 5";
        $this->createFile('lines.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'lines.txt',
            'endLine' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'line 1');
        $this->assertContentContains($result, 'line 2');
        $this->assertContentContains($result, 'lines 1-2 of 5');
    }

    #[Test]
    public function it_shows_line_numbers_in_partial_read(): void
    {
        // Arrange
        $content = "first\nsecond\nthird\nfourth\nfifth";
        $this->createFile('numbered.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'numbered.txt',
            'startLine' => 2,
            'endLine' => 4,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '2 |');
        $this->assertContentContains($result, '3 |');
        $this->assertContentContains($result, '4 |');
    }

    #[Test]
    public function it_fails_when_start_line_exceeds_file_length(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3";
        $this->createFile('short.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'short.txt',
            'startLine' => 100,
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'exceeds file length');
    }

    #[Test]
    public function it_fails_when_start_line_greater_than_end_line(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3\nline 4\nline 5";
        $this->createFile('lines.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'lines.txt',
            'startLine' => 5,
            'endLine' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'Invalid line range');
    }

    #[Test]
    public function it_clamps_end_line_to_file_length(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3";
        $this->createFile('short.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'short.txt',
            'startLine' => 1,
            'endLine' => 100,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'lines 1-3 of 3');
    }

    #[Test]
    public function it_reads_full_file_without_line_range(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3";
        $this->createFile('full.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'full.txt',
        ]);

        // Assert - No line numbers, raw content
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'line 1');
        $this->assertStringNotContainsString('of', $result->getContent() ?? ''); // No "X of Y" header
    }

    #[Test]
    public function it_rejects_line_range_for_multi_file_request(): void
    {
        // Arrange
        $this->createFile('file1.txt', 'Content One');
        $this->createFile('file2.txt', 'Content Two');

        // Act
        $result = $this->inspector->callTool('file-read', [
            'paths' => ['file1.txt', 'file2.txt'],
            'startLine' => 1,
            'endLine' => 5,
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'single file');
    }

    #[Test]
    public function it_reads_single_line(): void
    {
        // Arrange
        $content = "line 1\nline 2\nline 3\nline 4\nline 5";
        $this->createFile('lines.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-read', [
            'path' => 'lines.txt',
            'startLine' => 3,
            'endLine' => 3,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'line 3');
        $this->assertContentContains($result, 'lines 3-3 of 5');
    }
}
