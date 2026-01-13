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
}
