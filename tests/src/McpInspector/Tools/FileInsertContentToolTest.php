<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class FileInsertContentToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_inserts_content_after_line(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 2, 'content' => 'Inserted Line'],
            ],
            'position' => 'after',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(4, $lines);
        $this->assertEquals('Line 2', $lines[1]);
        $this->assertEquals('Inserted Line', $lines[2]);
        $this->assertEquals('Line 3', $lines[3]);
    }

    #[Test]
    public function it_inserts_content_before_line(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 2, 'content' => 'Inserted Line'],
            ],
            'position' => 'before',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(4, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Inserted Line', $lines[1]);
        $this->assertEquals('Line 2', $lines[2]);
    }

    #[Test]
    public function it_inserts_at_end_of_file_with_minus_one(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => -1, 'content' => 'Last Line'],
            ],
            'position' => 'after',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(3, $lines);
        $this->assertEquals('Last Line', $lines[2]);
    }

    #[Test]
    public function it_inserts_multiple_lines_with_offset_calculation(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
Line 4
Line 5
TEXT;
        $this->createFile('test.txt', $content);

        // Act - Insert at lines 2 and 4 (original numbering)
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 2, 'content' => 'After Line 2'],
                ['line' => 4, 'content' => 'After Line 4'],
            ],
            'position' => 'after',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(7, $lines);
        // Line 1, Line 2, After Line 2, Line 3, Line 4, After Line 4, Line 5
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 2', $lines[1]);
        $this->assertEquals('After Line 2', $lines[2]);
        $this->assertEquals('Line 3', $lines[3]);
        $this->assertEquals('Line 4', $lines[4]);
        $this->assertEquals('After Line 4', $lines[5]);
        $this->assertEquals('Line 5', $lines[6]);
    }

    #[Test]
    public function it_inserts_multiline_content(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 1, 'content' => "Multi\nLine\nContent"],
            ],
            'position' => 'after',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(5, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Multi', $lines[1]);
        $this->assertEquals('Line', $lines[2]);
        $this->assertEquals('Content', $lines[3]);
        $this->assertEquals('Line 2', $lines[4]);
    }

    #[Test]
    public function it_inserts_php_code(): void
    {
        // Arrange
        $phpContent = <<<'PHP'
<?php

namespace App;

class Service
{
}
PHP;
        $this->createFile('Service.php', $phpContent);

        // Act - Insert use statement after namespace line
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'Service.php',
            'insertions' => [
                ['line' => 4, 'content' => 'use App\Repository\UserRepository;'],
            ],
            'position' => 'after',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/Service.php');
        $this->assertStringContainsString('use App\Repository\UserRepository;', $newContent);
    }

    #[Test]
    public function it_fails_for_non_existent_file(): void
    {
        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'non-existent.txt',
            'insertions' => [
                ['line' => 1, 'content' => 'test'],
            ],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_invalid_line_number(): void
    {
        // Arrange
        $this->createFile('test.txt', "Line 1\nLine 2");

        // Act - Try to insert at line 10 which doesn't exist
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 10, 'content' => 'test'],
            ],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_missing_path(): void
    {
        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'insertions' => [
                ['line' => 1, 'content' => 'test'],
            ],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_empty_insertions(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_invalid_position(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 1, 'content' => 'test'],
            ],
            'position' => 'invalid',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_uses_after_as_default_position(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
TEXT;
        $this->createFile('test.txt', $content);

        // Act - No position specified, should default to 'after'
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 1, 'content' => 'Inserted'],
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Inserted', $lines[1]);
        $this->assertEquals('Line 2', $lines[2]);
    }

    #[Test]
    public function it_inserts_at_first_line_before(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => 'test.txt',
            'insertions' => [
                ['line' => 1, 'content' => 'Very First Line'],
            ],
            'position' => 'before',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertEquals('Very First Line', $lines[0]);
        $this->assertEquals('Line 1', $lines[1]);
    }
}
