<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Tests for exclude configuration in directory-list tool.
 *
 * Verifies that files matching exclude patterns and paths
 * are hidden from directory listing results.
 */
#[Group('mcp-inspector')]
final class ExcludeConfigTest extends McpInspectorTestCase
{
    #[Test]
    public function it_excludes_files_matching_pattern_from_directory_list(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['.env*']);
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('.env.local', 'LOCAL_SECRET=value');
        $this->createFile('.env.production', 'PROD_SECRET=value');
        $this->createFile('config.php', '<?php return [];');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'config.php');
        $this->assertContentNotContainsExcluded($result, '.env');
        $this->assertContentNotContainsExcluded($result, '.env.local');
        $this->assertContentNotContainsExcluded($result, '.env.production');
    }

    #[Test]
    public function it_excludes_files_in_excluded_path_from_directory_list(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(paths: ['vendor']);
        $this->createFile('vendor/autoload.php', '<?php');
        $this->createFile('vendor/composer/installed.json', '{}');
        $this->createFile('src/App.php', '<?php');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'depth' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'src');
        $this->assertContentNotContainsExcluded($result, 'vendor');
    }

    #[Test]
    public function it_excludes_files_with_multiple_patterns_and_paths(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(
            patterns: ['.env*', '*.log'],
            paths: ['vendor', 'node_modules'],
        );
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('app.log', 'log content');
        $this->createFile('error.log', 'error content');
        $this->createFile('vendor/package/file.php', '<?php');
        $this->createFile('node_modules/package/index.js', 'js');
        $this->createFile('src/Controller.php', '<?php');
        $this->createFile('public/index.php', '<?php');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'depth' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'src');
        $this->assertContentContains($result, 'public');
        $this->assertContentNotContainsExcluded($result, '.env');
        $this->assertContentNotContainsExcluded($result, 'app.log');
        $this->assertContentNotContainsExcluded($result, 'error.log');
        $this->assertContentNotContainsExcluded($result, 'vendor');
        $this->assertContentNotContainsExcluded($result, 'node_modules');
    }

    #[Test]
    public function it_shows_all_files_when_no_exclude_config(): void
    {
        // Arrange - default context.yaml without excludes
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('vendor/autoload.php', '<?php');
        $this->createFile('src/App.php', '<?php');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'depth' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '.env');
        $this->assertContentContains($result, 'vendor');
        $this->assertContentContains($result, 'src');
    }

    #[Test]
    public function it_excludes_nested_files_in_excluded_directory(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(paths: ['cache']);
        $this->createFile('cache/data/file.txt', 'cached data');
        $this->createFile('cache/views/template.php', '<?php');
        $this->createFile('app/data/file.txt', 'app data');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'depth' => 3,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'app');
        $this->assertContentNotContainsExcluded($result, 'cache');
    }

    #[Test]
    public function it_excludes_files_with_glob_pattern(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(patterns: ['*.tmp', '*.bak']);
        $this->createFile('data.tmp', 'temporary');
        $this->createFile('backup.bak', 'backup');
        $this->createFile('data.json', '{}');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'data.json');
        $this->assertContentNotContainsExcluded($result, 'data.tmp');
        $this->assertContentNotContainsExcluded($result, 'backup.bak');
    }

    #[Test]
    public function it_excludes_from_tree_view(): void
    {
        // Arrange
        $this->createContextYamlWithExcludes(
            patterns: ['.env*'],
            paths: ['vendor'],
        );
        $this->createFile('.env', 'SECRET=value');
        $this->createFile('vendor/autoload.php', '<?php');
        $this->createFile('src/App.php', '<?php');

        // Act
        $result = $this->inspector->callTool('directory-list', [
            'path' => '.',
            'showTree' => true,
            'depth' => 2,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'src');
        $this->assertContentNotContainsExcluded($result, '.env');
        $this->assertContentNotContainsExcluded($result, 'vendor');
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

    /**
     * Assert that the content does not contain an excluded file/directory.
     */
    protected function assertContentNotContainsExcluded(
        \Tests\McpInspector\McpInspectorResult $result,
        string $excluded,
    ): void {
        $content = $result->getContent() ?? $result->output;

        $this->assertStringNotContainsString(
            $excluded,
            $content,
            "Content should not contain excluded item: {$excluded}",
        );
    }
}
