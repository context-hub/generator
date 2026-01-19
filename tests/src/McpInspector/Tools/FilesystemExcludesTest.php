<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Tests for exclude configuration in filesystem tools.
 *
 * Verifies that files matching exclude patterns and paths
 * are blocked from being read, written, or modified.
 */
#[Group('mcp-inspector')]
final class FilesystemExcludesTest extends McpInspectorTestCase
{
    #[Test]
    public function it_blocks_reading_excluded_files(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('.env.local', 'LOCAL_SECRET=value');
        $this->createFile('config.php', '<?php return [];');

        // Act - try to read excluded file
        $result = $this->inspector->callTool('file-read', [
            'path' => '.env',
        ]);

        // Assert - should fail with exclude message
        $this->assertToolError($result);
        $this->assertStringContainsString(
            "Path '.env' is excluded by project configuration",
            $result->output ?? $result->error ?? '',
        );
    }

    #[Test]
    public function it_blocks_reading_excluded_paths(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(paths: ['secrets']);
        $this->createFile('secrets/password.txt', 'password123');
        $this->createFile('secrets/api_keys.json', '{}');
        $this->createFile('config.php', '<?php return [];');

        // Act - try to read file in excluded path
        $result = $this->inspector->callTool('file-read', [
            'path' => 'secrets/password.txt',
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertStringContainsString('secrets', $result->output);
        $this->assertStringContainsString('excluded', $result->output);
    }

    #[Test]
    public function it_blocks_writing_to_excluded_files(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('config.php', '<?php return [];');

        // Act - try to write to excluded file
        $result = $this->inspector->callTool('file-write', [
            'path' => '.env',
            'content' => 'NEW_SECRET=value',
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertStringContainsString(
            "Path '.env' is excluded by project configuration",
            $result->output ?? $result->error ?? '',
        );
    }

    #[Test]
    public function it_blocks_writing_to_excluded_paths(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(paths: ['vendor']);
        $this->createFile('config.php', '<?php return [];');

        // Act - try to write to excluded path
        $result = $this->inspector->callTool('file-write', [
            'path' => 'vendor/package/test.php',
            'content' => '<?php echo "test";',
            'createDirectory' => true,
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertStringContainsString('vendor', $result->output);
        $this->assertStringContainsString('excluded', $result->output);
    }

    #[Test]
    public function it_blocks_replacing_content_in_excluded_files(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('.env', 'OLD_SECRET=value');

        // Act - try to replace content in excluded file
        $result = $this->inspector->callTool('file-replace-content', [
            'path' => '.env',
            'search' => 'OLD_SECRET=value',
            'replace' => 'NEW_SECRET=value',
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertStringContainsString(
            "Path '.env' is excluded by project configuration",
            $result->output ?? $result->error ?? '',
        );
    }

    #[Test]
    public function it_blocks_inserting_content_into_excluded_files(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('.env', 'SECRET=value');

        // Act - try to insert content into excluded file
        $result = $this->inspector->callTool('file-insert-content', [
            'path' => '.env',
            'position' => 'after',
            'insertions' => [
                ['line' => 1, 'content' => 'NEW_SECRET=new_value'],
            ],
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertStringContainsString(
            "Path '.env' is excluded by project configuration",
            $result->output ?? $result->error ?? '',
        );
    }

    #[Test]
    public function it_blocks_deleting_content_from_excluded_files(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('.env', "SECRET=value\nANOTHER=value");

        // Act - try to delete content from excluded file
        $result = $this->inspector->callTool('file-delete-content', [
            'path' => '.env',
            'lineNumbers' => [1],
        ]);

        // Assert
        $this->assertToolError($result);
        // Just check that error occurred, don't check exact message format
        $this->assertNotNull($result->output);
    }

    #[Test]
    public function it_allows_reading_non_excluded_files(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('config.php', '<?php return [];');
        $this->createFile('src/App.php', '<?php class App {}');

        // Act - read non-excluded file
        $result = $this->inspector->callTool('file-read', [
            'path' => 'config.php',
        ]);

        // Assert - should succeed
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '<?php return [];');
    }

    #[Test]
    public function it_allows_writing_non_excluded_files(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('.env', 'SECRET=value');

        // Act - write to non-excluded file
        $result = $this->inspector->callTool('file-write', [
            'path' => 'config.php',
            'content' => '<?php return [];',
        ]);

        // Assert - should succeed
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Successfully wrote');
    }

    #[Test]
    public function it_handles_multiple_exclude_patterns(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(
            patterns: ['.env*', '*.log'],
            paths: ['secrets', 'vendor'],
        );
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('app.log', 'log content');
        $this->createFile('secrets/password.txt', 'password123');
        $this->createFile('vendor/test.php', '<?php');
        $this->createFile('config.php', '<?php return [];');

        // Act - try to read excluded file
        $result = $this->inspector->callTool('file-read', [
            'path' => '.env',
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertStringContainsString('excluded', $result->output ?? $result->error ?? '');
    }

    #[Test]
    public function it_allows_operations_when_no_excludes_configured(): void
    {
        // Arrange - no exclude configuration
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('config.php', '<?php return [];');

        // Act - read file that would normally be excluded
        $result = $this->inspector->callTool('file-read', [
            'path' => '.env',
        ]);

        // Assert - should succeed when no excludes are configured
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'SECRET=value');
    }

    /**
     * Create context.yaml with exclude configuration.
     */
    protected function createContextYamlWithExcludes(array $patterns = [], array $paths = []): void
    {
        $yaml = "documents: []\n";

        if (!empty($patterns) || !empty($paths)) {
            $yaml .= "\nexclude:\n";

            if (!empty($patterns)) {
                $yaml .= "  patterns:\n";
                foreach ($patterns as $pattern) {
                    $yaml .= "    - \"{$pattern}\"\n";
                }
            }

            if (!empty($paths)) {
                $yaml .= "  paths:\n";
                foreach ($paths as $path) {
                    $yaml .= "    - \"{$path}\"\n";
                }
            }
        }

        \file_put_contents($this->workDir . '/context.yaml', $yaml);
    }
}
