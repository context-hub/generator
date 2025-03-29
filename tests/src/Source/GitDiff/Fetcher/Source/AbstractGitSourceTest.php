<?php

declare(strict_types=1);

namespace Tests\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\AbstractGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\SplFileInfo;

#[CoversClass(AbstractGitSource::class)]
final class AbstractGitSourceTest extends GitSourceTestCase
{
    /**
     * Concrete implementation of AbstractGitSource for testing
     */
    private $abstractGitSource;

    #[Test]
    public function it_should_create_file_infos_for_diffs(): void
    {
        // Set up a temporary directory path for testing
        $tempDir = \sys_get_temp_dir() . '/git-source-test-abstract';

        // Mock the file operations:
        // we can't use the actual filesystem in unit tests, but we need to test
        // that the method produces the correct SplFileInfo objects with the right content

        // This test checks that createFileInfos produces the correct file infos
        $fileInfos = $this->abstractGitSource->createFileInfos($this->repoDir, 'test-reference', $tempDir);

        // Verify the file infos were created correctly
        $this->assertCount(3, $fileInfos);

        // Check the first file info
        $fileInfo = $fileInfos[0];
        $this->assertInstanceOf(\Symfony\Component\Finder\SplFileInfo::class, $fileInfo);
        $this->assertSame('file1.php', $fileInfo->getRelativePathname());

        // Check that the file contains the correct diff content
        $this->assertStringContainsString('-Old content', $fileInfo->getContents());
        $this->assertStringContainsString('+New content', $fileInfo->getContents());

        // Check additional methods from the anonymous class extension
        $this->assertSame('file1.php', $fileInfo->getOriginalPath());
    }

    #[Test]
    public function it_should_handle_empty_changed_files_list(): void
    {
        // Override the getChangedFiles method to return an empty array
        $emptySource = new class($this->gitClientMock, $this->logger) extends AbstractGitSource {
            public function supports(string $commitReference): bool
            {
                return true;
            }

            public function getChangedFiles(string $repository, string $commitReference): array
            {
                return [];
            }

            public function getFileDiff(string $repository, string $commitReference, string $file): string
            {
                return '';
            }

            public function formatReferenceForDisplay(string $commitReference): string
            {
                return '';
            }
        };

        // Create file infos for empty source
        $tempDir = \sys_get_temp_dir() . '/git-source-test-empty';
        $fileInfos = $emptySource->createFileInfos($this->repoDir, 'test-reference', $tempDir);

        // Verify that an empty array is returned
        $this->assertEmpty($fileInfos);
    }

    #[Test]
    public function it_should_handle_empty_diff_content(): void
    {
        // Override the getFileDiff method to return an empty string
        $emptyDiffSource = new class($this->gitClientMock, $this->logger) extends AbstractGitSource {
            public function supports(string $commitReference): bool
            {
                return true;
            }

            public function getChangedFiles(string $repository, string $commitReference): array
            {
                return ['empty-diff.php'];
            }

            public function getFileDiff(string $repository, string $commitReference, string $file): string
            {
                return '';
            }

            public function formatReferenceForDisplay(string $commitReference): string
            {
                return '';
            }
        };

        // Create file infos for source with empty diff
        $tempDir = \sys_get_temp_dir() . '/git-source-test-empty-diff';
        $fileInfos = $emptyDiffSource->createFileInfos($this->repoDir, 'test-reference', $tempDir);

        // Verify that an empty array is returned (since the diff is empty)
        $this->assertEmpty($fileInfos);
    }

    #[Test]
    public function it_should_execute_git_command_and_return_output(): void
    {
        // Mock the git command execution
        $expectedCommand = 'git status --porcelain';
        $expectedOutput = ['?? new-file.txt'];
        $this->mockGitCommand($this->repoDir, $expectedCommand, $expectedOutput);

        // Execute a simple git command
        $output = $this->abstractGitSource->exposedExecuteGitCommand($this->repoDir, $expectedCommand);

        // Verify that the expected array is returned
        $this->assertSame($expectedOutput, $output);
    }

    #[Test]
    public function it_should_execute_git_command_and_return_string(): void
    {
        // Mock the git command execution
        $expectedCommand = 'git log -1 --pretty=format:"%s"';
        $expectedOutput = 'Initial commit';
        $this->mockFileDiff($expectedCommand, $expectedOutput);

        // Execute a simple git command
        $output = $this->abstractGitSource->exposedExecuteGitCommandString($this->repoDir, $expectedCommand);

        // Verify that the expected string is returned
        $this->assertSame($expectedOutput, $output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Create a concrete implementation of the abstract class
        $this->abstractGitSource = new class($this->gitClientMock, $this->logger) extends AbstractGitSource {
            public function supports(string $commitReference): bool
            {
                return $commitReference === 'test-reference';
            }

            public function getChangedFiles(string $repository, string $commitReference): array
            {
                return ['file1.php', 'file2.php', 'subdir/file3.php'];
            }

            public function getFileDiff(string $repository, string $commitReference, string $file): string
            {
                return "diff --git a/{$file} b/{$file}\nindex abc123..def456 100644\n--- a/{$file}\n+++ b/{$file}\n@@ -1,3 +1,3 @@\n-Old content\n+New content\n Context line";
            }

            public function formatReferenceForDisplay(string $commitReference): string
            {
                return "Test reference: {$commitReference}";
            }

            // Expose protected methods for testing
            public function exposedExecuteGitCommand(string $repository, string $command): array
            {
                return $this->executeGitCommand($repository, $command);
            }

            public function exposedExecuteGitCommandString(string $repository, string $command): string
            {
                return $this->executeGitCommandString($repository, $command);
            }
        };
    }
}
