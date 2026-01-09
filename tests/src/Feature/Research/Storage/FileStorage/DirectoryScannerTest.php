<?php

declare(strict_types=1);

namespace Tests\Feature\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\Research\Storage\FileStorage\DirectoryScanner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Files\Files;

final class DirectoryScannerTest extends TestCase
{
    private DirectoryScanner $scanner;
    private string $testDataPath;
    private string $tempPath;

    #[Test]
    public function it_scans_projects_correctly(): void
    {
        $projectsPath = $this->tempPath . '/projects';
        $projects = $this->scanner->scanResearches($projectsPath);

        $this->assertCount(2, $projects);

        // Verify project paths
        $projectNames = \array_map(basename(...), $projects);
        $this->assertContains('test_project_1', $projectNames);
        $this->assertContains('test_project_2', $projectNames);

        // Verify each project has a project.yaml file
        foreach ($projects as $projectPath) {
            $this->assertFileExists($projectPath . '/research.yaml');
        }
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_projects_path(): void
    {
        $nonexistentPath = $this->tempPath . '/nonexistent';
        $projects = $this->scanner->scanResearches($nonexistentPath);

        $this->assertEmpty($projects);
    }

    #[Test]
    public function it_ignores_directories_without_project_yaml(): void
    {
        // Create a directory without project.yaml
        $invalidProjectPath = $this->tempPath . '/projects/invalid_project';
        \mkdir($invalidProjectPath, 0755, true);
        \file_put_contents($invalidProjectPath . '/readme.txt', 'Not a project');

        $projectsPath = $this->tempPath . '/projects';
        $projects = $this->scanner->scanResearches($projectsPath);

        // Should still only find the 2 valid projects
        $this->assertCount(2, $projects);

        $projectNames = \array_map(basename(...), $projects);
        $this->assertNotContains('invalid_project', $projectNames);
    }

    #[Test]
    public function it_scans_entries_in_project(): void
    {
        $projectPath = $this->tempPath . '/projects/test_project_1';

        $entries = $this->scanner->scanEntries($projectPath);

        $this->assertCount(3, $entries);

        // Verify entry file paths
        $entryFiles = \array_map(basename(...), $entries);
        $this->assertContains('sample_story.md', $entryFiles);
        $this->assertContains('api_design.md', $entryFiles);
    }

    #[Test]
    public function it_scans_all_markdown_files_when_no_entry_dirs_specified(): void
    {
        $projectPath = $this->tempPath . '/projects/test_project_1';

        $entries = $this->scanner->scanEntries($projectPath);

        $this->assertCount(3, $entries);

        foreach ($entries as $entryPath) {
            $this->assertStringEndsWith('.md', $entryPath);
        }
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_project_path(): void
    {
        $nonexistentPath = $this->tempPath . '/projects/nonexistent_project';
        $entries = $this->scanner->scanEntries($nonexistentPath);

        $this->assertEmpty($entries);
    }

    #[Test]
    public function it_handles_project_with_no_entries(): void
    {
        $projectPath = $this->tempPath . '/projects/test_project_2';
        $entries = $this->scanner->scanEntries($projectPath);

        $this->assertEmpty($entries);
    }

    #[Test]
    public function it_gets_entry_directories(): void
    {
        $projectPath = $this->tempPath . '/projects/test_project_1';
        $directories = $this->scanner->getEntryDirectories($projectPath);

        $this->assertGreaterThan(0, \count($directories));
        $this->assertContains('features', $directories);
        $this->assertContains('docs', $directories);
    }

    #[Test]
    public function it_excludes_special_directories(): void
    {
        // Create special directories that should be ignored
        $projectPath = $this->tempPath . '/projects/test_project_1';
        $specialDirs = ['.project', 'resources', '.git', '.idea', 'node_modules'];

        foreach ($specialDirs as $specialDir) {
            $dirPath = $projectPath . '/' . $specialDir;
            if (!\is_dir($dirPath)) {
                \mkdir($dirPath, 0755, true);
            }
        }

        $directories = $this->scanner->getEntryDirectories($projectPath);

        foreach ($specialDirs as $specialDir) {
            $this->assertNotContains($specialDir, $directories);
        }
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_directory(): void
    {
        $nonexistentPath = $this->tempPath . '/nonexistent_directory';
        $directories = $this->scanner->getEntryDirectories($nonexistentPath);

        $this->assertEmpty($directories);
    }

    #[Test]
    public function it_handles_deeply_nested_entry_files(): void
    {
        // Create nested entry structure
        $projectPath = $this->tempPath . '/projects/test_project_1';
        $nestedPath = $projectPath . '/features/user_story/nested';
        \mkdir($nestedPath, 0755, true);

        $nestedEntryContent = <<<'MARKDOWN'
---
entry_id: "nested_entry"
title: "Nested Entry"
entry_type: "user_story"
category: "features"
status: "draft"
---

# Nested Entry

This is a nested entry for testing.
MARKDOWN;

        \file_put_contents($nestedPath . '/nested_entry.md', $nestedEntryContent);

        $entries = $this->scanner->scanEntries($projectPath);

        // Should include the nested entry
        $this->assertCount(4, $entries); // Original 2 + new nested one

        $nestedEntryFound = false;
        foreach ($entries as $entryPath) {
            if (\str_contains((string) $entryPath, 'nested_entry.md')) {
                $nestedEntryFound = true;
                break;
            }
        }

        $this->assertTrue($nestedEntryFound, 'Nested entry should be found');
    }

    #[Test]
    public function it_only_finds_markdown_files(): void
    {
        // Create non-markdown files
        $projectPath = $this->tempPath . '/projects/test_project_1';
        \file_put_contents($projectPath . '/features/readme.txt', 'Not an entry');
        \file_put_contents($projectPath . '/docs/config.json', '{"not": "an entry"}');
        \file_put_contents($projectPath . '/notes.html', '<p>Not an entry</p>');

        $entries = $this->scanner->scanEntries($projectPath);

        // Should only find .md files
        foreach ($entries as $entryPath) {
            $this->assertStringEndsWith('.md', $entryPath);
        }

        // Should still be only 2 entries (the original ones)
        $this->assertCount(3, $entries);
    }

    #[Test]
    public function it_handles_empty_project_directory(): void
    {
        // Create completely empty project directory
        $emptyProjectPath = $this->tempPath . '/projects/empty_project';
        \mkdir($emptyProjectPath, 0755, true);

        $entries = $this->scanner->scanEntries($emptyProjectPath);
        $this->assertEmpty($entries);

        $directories = $this->scanner->getEntryDirectories($emptyProjectPath);
        $this->assertEmpty($directories);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataPath = \dirname(__DIR__, 5) . '/fixtures/Research/FileStorage';
        $this->tempPath = \sys_get_temp_dir() . '/scanner_test_' . \uniqid();

        // Copy fixture data to temp directory
        $this->copyFixturesToTemp();

        $files = new Files();
        $reporter = new class implements ExceptionReporterInterface {
            public function report(\Throwable $exception): void
            {
                // No-op for tests
            }
        };

        $this->scanner = new DirectoryScanner($files, $reporter);
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Clean up temp directory
        if (\is_dir($this->tempPath)) {
            $this->removeDirectory($this->tempPath);
        }

        parent::tearDown();
    }

    private function copyFixturesToTemp(): void
    {
        if (!\is_dir($this->tempPath)) {
            \mkdir($this->tempPath, 0755, true);
        }

        $this->copyDirectory($this->testDataPath, $this->tempPath);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!\is_dir($destination)) {
            \mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!\is_dir($destPath)) {
                    \mkdir($destPath, 0755, true);
                }
            } else {
                \copy($item->getRealPath(), $destPath);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!\is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                \rmdir($item->getRealPath());
            } else {
                \unlink($item->getRealPath());
            }
        }

        \rmdir($directory);
    }
}
