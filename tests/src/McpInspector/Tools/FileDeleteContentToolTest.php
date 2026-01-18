<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class FileDeleteContentToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_deletes_single_line(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [['line' => 2]],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(2, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 3', $lines[1]);
    }

    #[Test]
    public function it_deletes_multiple_individual_lines(): void
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

        // Act - Delete lines 2 and 4
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [['line' => 2], ['line' => 4]],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(3, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 3', $lines[1]);
        $this->assertEquals('Line 5', $lines[2]);
    }

    #[Test]
    public function it_deletes_range_of_lines(): void
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

        // Act - Delete lines 2-4
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [
                ['line' => 2, 'to' => 4],
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(2, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 5', $lines[1]);
    }

    #[Test]
    public function it_deletes_mixed_individual_and_range(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
Line 4
Line 5
Line 6
Line 7
TEXT;
        $this->createFile('test.txt', $content);

        // Act - Delete line 2 and lines 4-6
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [
                ['line' => 2],
                ['line' => 4, 'to' => 6],
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(3, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 3', $lines[1]);
        $this->assertEquals('Line 7', $lines[2]);
    }

    #[Test]
    public function it_deletes_first_line(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [['line' => 1]],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(2, $lines);
        $this->assertEquals('Line 2', $lines[0]);
        $this->assertEquals('Line 3', $lines[1]);
    }

    #[Test]
    public function it_deletes_last_line(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
TEXT;
        $this->createFile('test.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [['line' => 3]],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(2, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 2', $lines[1]);
    }

    #[Test]
    public function it_deletes_php_code_lines(): void
    {
        // Arrange
        $phpContent = <<<'PHP'
<?php

namespace App;

use App\OldImport;
use App\AnotherOldImport;

class Service
{
}
PHP;
        $this->createFile('Service.php', $phpContent);

        // Act - Delete the old import lines (5 and 6)
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'Service.php',
            'lines' => [['line' => 5], ['line' => 6]],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/Service.php');
        $this->assertStringNotContainsString('OldImport', $newContent);
        $this->assertStringNotContainsString('AnotherOldImport', $newContent);
        $this->assertStringContainsString('class Service', $newContent);
    }

    #[Test]
    public function it_handles_reversed_range(): void
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

        // Act - Use reversed range (line > to), should still work
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [
                ['line' => 4, 'to' => 2],
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(2, $lines);
        $this->assertEquals('Line 1', $lines[0]);
        $this->assertEquals('Line 5', $lines[1]);
    }

    #[Test]
    public function it_handles_duplicate_line_numbers(): void
    {
        // Arrange
        $content = <<<'TEXT'
Line 1
Line 2
Line 3
TEXT;
        $this->createFile('test.txt', $content);

        // Act - Specify line 2 multiple times
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [['line' => 2], ['line' => 2], ['line' => 2]],
        ]);

        // Assert - Should only delete once
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $lines = \explode("\n", $newContent);
        $this->assertCount(2, $lines);
    }

    #[Test]
    public function it_fails_for_non_existent_file(): void
    {
        // Act
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'non-existent.txt',
            'lines' => [['line' => 1]],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_invalid_line_number(): void
    {
        // Arrange
        $this->createFile('test.txt', "Line 1\nLine 2");

        // Act - Try to delete line 10 which doesn't exist
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [['line' => 10]],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_zero_line_number(): void
    {
        // Arrange
        $this->createFile('test.txt', "Line 1\nLine 2");

        // Act - Line numbers are 1-based, 0 is invalid
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [['line' => 0]],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_missing_path(): void
    {
        // Act
        $result = $this->inspector->callTool('file-delete-content', [
            'lines' => [['line' => 1]],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_empty_lines(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [],
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_blocks_path_traversal_attempts(): void
    {
        // Act
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => '../../../etc/passwd',
            'lines' => [['line' => 1]],
        ]);

        // Assert - should fail or return error
        $this->assertTrue(
            !$result->success || $result->isToolError(),
            'Path traversal should be blocked',
        );
    }

    #[Test]
    public function it_deletes_all_lines_except_one(): void
    {
        // Arrange
        $content = <<<'TEXT'
Keep this line
Delete 1
Delete 2
Delete 3
TEXT;
        $this->createFile('test.txt', $content);

        // Act - Delete lines 2-4
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => 'test.txt',
            'lines' => [
                ['line' => 2, 'to' => 4],
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $newContent = \file_get_contents($this->workDir . '/test.txt');
        $this->assertEquals('Keep this line', $newContent);
    }
}
