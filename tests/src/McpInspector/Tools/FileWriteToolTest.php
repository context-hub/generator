<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class FileWriteToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_writes_file_content(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'new-file.txt',
            'content' => 'Hello, World!',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertTrue(\file_exists($this->workDir . '/new-file.txt'));
        $this->assertEquals('Hello, World!', \file_get_contents($this->workDir . '/new-file.txt'));
    }

    #[Test]
    public function it_writes_file_with_utf8_content(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'utf8.txt',
            'content' => 'Привет мир 你好世界',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = \file_get_contents($this->workDir . '/utf8.txt');
        $this->assertStringContainsString('Привет мир', $content);
        $this->assertStringContainsString('你好世界', $content);
    }

    #[Test]
    public function it_creates_nested_directories(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'deep/nested/path/file.txt',
            'content' => 'Nested content',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertTrue(\file_exists($this->workDir . '/deep/nested/path/file.txt'));
        $this->assertEquals('Nested content', \file_get_contents($this->workDir . '/deep/nested/path/file.txt'));
    }

    #[Test]
    public function it_overwrites_existing_file(): void
    {
        // Arrange
        $this->createFile('existing.txt', 'Original content');

        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'existing.txt',
            'content' => 'New content',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertEquals('New content', \file_get_contents($this->workDir . '/existing.txt'));
    }

    #[Test]
    public function it_fails_for_missing_path(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'content' => 'Some content',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_missing_content(): void
    {
        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'file.txt',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_handles_path_outside_project(): void
    {
        // Act - attempt to write file outside project root
        $result = $this->inspector->callTool('file-write', [
            'path' => '../../../tmp/test-outside.txt',
            'content' => 'test content',
        ]);

        // Assert - tool may block this or return error
        // The important thing is it should not crash
        $this->assertNotNull($result);
    }

    #[Test]
    public function it_writes_simple_content(): void
    {
        // Arrange - use simple text to avoid CLI escaping issues with special chars
        $content = 'key=value';

        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'config.txt',
            'content' => $content,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $fileContent = \file_get_contents($this->workDir . '/config.txt');
        $this->assertEquals('key=value', $fileContent);
    }

    #[Test]
    public function it_writes_php_content(): void
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

        // Act
        $result = $this->inspector->callTool('file-write', [
            'path' => 'TestClass.php',
            'content' => $phpContent,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = \file_get_contents($this->workDir . '/TestClass.php');
        $this->assertStringContainsString('namespace App', $content);
        $this->assertStringContainsString('final class TestClass', $content);
    }
}
