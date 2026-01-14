<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class FileReplaceContentToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_replaces_content_in_file(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello, World!');

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'test.txt',
            'search' => 'World',
            'replace' => 'Universe',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = \file_get_contents($this->workDir . '/test.txt');
        $this->assertEquals('Hello, Universe!', $content);
    }

    #[Test]
    public function it_replaces_multiline_content(): void
    {
        // Arrange
        $original = <<<'TEXT'
function hello() {
    return 'old value';
}
TEXT;
        $this->createFile('code.js', $original);

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'code.js',
            'search' => "return 'old value';",
            'replace' => "return 'new value';",
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = \file_get_contents($this->workDir . '/code.js');
        $this->assertStringContainsString("return 'new value';", $content);
        $this->assertStringNotContainsString("return 'old value';", $content);
    }

    #[Test]
    public function it_replaces_in_php_file(): void
    {
        // Arrange
        $phpContent = <<<'PHP'
<?php

declare(strict_types=1);

class OldClassName
{
    public function oldMethod(): void
    {
    }
}
PHP;
        $this->createFile('Class.php', $phpContent);

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'Class.php',
            'search' => 'OldClassName',
            'replace' => 'NewClassName',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = \file_get_contents($this->workDir . '/Class.php');
        $this->assertStringContainsString('NewClassName', $content);
        $this->assertStringNotContainsString('OldClassName', $content);
    }

    #[Test]
    public function it_fails_for_non_existent_file(): void
    {
        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'non-existent.txt',
            'search' => 'foo',
            'replace' => 'bar',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_when_old_content_not_found(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello, World!');

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'test.txt',
            'search' => 'nonexistent string',
            'replace' => 'replacement',
        ]);

        // Assert - tool should indicate error when content not found
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_missing_path(): void
    {
        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'search' => 'foo',
            'replace' => 'bar',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_missing_old_parameter(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'test.txt',
            'replace' => 'bar',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_fails_for_missing_new_parameter(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'test.txt',
            'search' => 'foo',
        ]);

        // Assert
        $this->assertToolError($result);
    }

    #[Test]
    public function it_blocks_path_traversal_attempts(): void
    {
        // Act
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => '../../../etc/passwd',
            'search' => 'root',
            'replace' => 'hacked',
        ]);

        // Assert - should fail or return error
        $this->assertTrue(
            !$result->success || $result->isToolError(),
            'Path traversal should be blocked',
        );
    }

    #[Test]
    public function it_replaces_with_whitespace_string(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello, World!');

        // Act - use single space since empty string causes CLI issues
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => 'test.txt',
            'search' => ', World',
            'replace' => ' ',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $content = \file_get_contents($this->workDir . '/test.txt');
        $this->assertEquals('Hello !', $content);
    }
}
