<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class FileSearchToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_searches_for_text_in_files(): void
    {
        // Arrange
        $this->createFile('test.php', "<?php\nclass TestClass\n{\n}");

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'TestClass',
            'path' => '.',
            'pattern' => '*.php',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'TestClass');
        $this->assertContentContains($result, 'test.php');
    }

    #[Test]
    public function it_returns_context_lines(): void
    {
        // Arrange
        $content = <<<'PHP'
<?php

namespace App;

class UserService
{
    public function findUser(): void
    {
    }
}
PHP;
        $this->createFile('UserService.php', $content);

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'findUser',
            'path' => '.',
            'contextLines' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'findUser');
        $this->assertContentContains($result, 'class UserService'); // context before
    }

    #[Test]
    public function it_searches_with_regex_pattern(): void
    {
        // Arrange
        $this->createFile('functions.php', "<?php\nfunction getUserById() {}\nfunction getOrderById() {}");

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'function\s+get\w+ById',
            'path' => '.',
            'regex' => true,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'getUserById');
    }

    #[Test]
    public function it_performs_case_insensitive_search(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Hello World');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'HELLO WORLD',
            'path' => '.',
            'caseSensitive' => false,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Hello World');
    }

    #[Test]
    public function it_returns_no_matches_message(): void
    {
        // Arrange
        $this->createFile('test.txt', 'Some content');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'nonexistent_text_xyz123',
            'path' => '.',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'No matches found');
    }

    #[Test]
    public function it_respects_file_pattern_filter(): void
    {
        // Arrange
        $this->createFile('code.php', 'findUser function');
        $this->createFile('readme.md', 'findUser documentation');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'findUser',
            'path' => '.',
            'pattern' => '*.php',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'code.php');
        $this->assertStringNotContainsString('readme.md', $result->getContent() ?? $result->output);
    }

    #[Test]
    public function it_searches_in_nested_directories(): void
    {
        // Arrange
        $this->createFile('src/Services/UserService.php', "<?php\nclass UserService {}");
        $this->createFile('src/Controllers/UserController.php', "<?php\nclass UserController {}");

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'class User',
            'path' => 'src',
            'depth' => 5,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'UserService');
        $this->assertContentContains($result, 'UserController');
    }

    #[Test]
    public function it_respects_depth_limit(): void
    {
        // Arrange
        $this->createFile('level1.php', 'searchterm here');
        $this->createFile('a/level2.php', 'searchterm here');
        $this->createFile('a/b/level3.php', 'searchterm here');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'searchterm',
            'path' => '.',
            'depth' => 1,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'level1.php');
        $this->assertContentContains($result, 'level2.php');
        $this->assertStringNotContainsString('level3.php', $result->getContent() ?? $result->output);
    }

    #[Test]
    public function it_limits_matches_per_file(): void
    {
        // Arrange
        $content = \str_repeat("match line\n", 10);
        $this->createFile('many-matches.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'match line',
            'path' => '.',
            'maxMatchesPerFile' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'truncated');
    }

    #[Test]
    public function it_limits_total_matches(): void
    {
        // Arrange
        for ($i = 1; $i <= 5; $i++) {
            $this->createFile("file{$i}.txt", 'searchable content');
        }

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'searchable',
            'path' => '.',
            'maxTotalMatches' => 3,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '3 match');
    }

    #[Test]
    public function it_shows_line_numbers(): void
    {
        // Arrange
        $content = "line one\nline two\nsearch target\nline four";
        $this->createFile('numbered.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'search target',
            'path' => '.',
            'contextLines' => 1,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '[Line 3]');
    }

    #[Test]
    public function it_fails_for_invalid_regex_pattern(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => '[invalid(regex',
            'path' => '.',
            'regex' => true,
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'Invalid regex');
    }

    #[Test]
    public function it_fails_for_empty_query(): void
    {
        // Arrange
        $this->createFile('test.txt', 'content');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => ' ',
            'path' => '.',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'empty');
    }

    #[Test]
    public function it_skips_binary_files(): void
    {
        // Arrange
        $this->createFile('text.txt', 'searchable text');
        $this->createFile('binary.bin', "searchable\x00binary\x00content");

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'searchable',
            'path' => '.',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'text.txt');
        $this->assertStringNotContainsString('binary.bin', $result->getContent() ?? $result->output);
    }

    #[Test]
    public function it_searches_multiple_patterns(): void
    {
        // Arrange
        $this->createFile('app.php', 'PHP application code');
        $this->createFile('app.js', 'JavaScript application code');
        $this->createFile('app.txt', 'Text application file');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'application',
            'path' => '.',
            'pattern' => '*.php,*.js',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'app.php');
        $this->assertContentContains($result, 'app.js');
        $this->assertStringNotContainsString('app.txt', $result->getContent() ?? $result->output);
    }

    #[Test]
    public function it_fails_with_invalid_project_parameter(): void
    {
        // Arrange
        $this->createFile('test.txt', 'searchable');

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'searchable',
            'path' => '.',
            'project' => 'non-existent-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    #[Test]
    public function it_searches_with_zero_context_lines(): void
    {
        // Arrange
        $content = "before\nsearch target\nafter";
        $this->createFile('minimal.txt', $content);

        // Act
        $result = $this->inspector->callTool('file-search', [
            'query' => 'search target',
            'path' => '.',
            'contextLines' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'search target');
        // Should not contain context lines
        $content = $result->getContent() ?? $result->output;
        $this->assertStringNotContainsString('before', $content);
        $this->assertStringNotContainsString('after', $content);
    }
}
