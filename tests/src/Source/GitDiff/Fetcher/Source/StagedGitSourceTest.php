<?php

declare(strict_types=1);

namespace Tests\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\StagedGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spiral\Files\Files;

#[CoversClass(StagedGitSource::class)]
final class StagedGitSourceTest extends GitSourceTestCase
{
    private StagedGitSource $stagedGitSource;

    #[Test]
    public function it_should_support_staged_references(): void
    {
        $this->assertTrue($this->stagedGitSource->supports('--cached'));
        $this->assertTrue($this->stagedGitSource->supports('staged'));
    }

    #[Test]
    public function it_should_not_support_other_formats(): void
    {
        $this->assertFalse($this->stagedGitSource->supports(''));
        $this->assertFalse($this->stagedGitSource->supports('HEAD~1..HEAD'));
        $this->assertFalse($this->stagedGitSource->supports('stash@{0}'));
        $this->assertFalse($this->stagedGitSource->supports('HEAD@{1.week.ago}..HEAD'));
        $this->assertFalse($this->stagedGitSource->supports('abcdef1 -- path/to/file'));
    }

    #[Test]
    public function it_should_get_staged_files(): void
    {
        // Mock the git command for getting staged files
        $expectedCommand = 'diff --name-only --cached';
        $expectedFiles = ['staged.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get the staged files
        $stagedFiles = $this->stagedGitSource->getChangedFiles($this->repoDir, '--cached');

        // Verify that only staged.txt is in the list
        $this->assertCount(1, $stagedFiles);
        $this->assertSame('staged.txt', $stagedFiles[0]);
    }

    #[Test]
    public function it_should_get_staged_file_diff(): void
    {
        // Mock the git command for getting file diff
        $expectedCommand = 'diff --cached -- existing.txt';
        $expectedDiff = "diff --git a/existing.txt b/existing.txt\nindex 1234567..abcdef 100644\n--- a/existing.txt\n+++ b/existing.txt\n@@ -1 +1 @@\n-Original content\n+Modified content\n";
        $this->mockFileDiff($expectedCommand, $expectedDiff);

        // Get the diff for the staged file
        $diff = $this->stagedGitSource->getFileDiff($this->repoDir, '--cached', 'existing.txt');

        // Verify that the diff contains expected changes
        $this->assertStringContainsString('-Original content', $diff);
        $this->assertStringContainsString('+Modified content', $diff);
    }

    #[Test]
    public function it_should_format_reference_for_display(): void
    {
        $this->assertSame(
            'Changes staged for commit',
            $this->stagedGitSource->formatReferenceForDisplay('--cached'),
        );

        $this->assertSame(
            'Changes staged for commit',
            $this->stagedGitSource->formatReferenceForDisplay('staged'),
        );
    }

    #[Test]
    public function it_should_handle_new_staged_files(): void
    {
        // Mock the git command for getting staged files
        $expectedCommand = 'diff --cached -- new-staged.txt';
        $expectedDiff = "diff --git a/new-staged.txt b/new-staged.txt\nnew file mode 100644\nindex 0000000..abcdef\n--- /dev/null\n+++ b/new-staged.txt\n@@ -0,0 +1 @@\n+New staged content\n";
        $this->mockFileDiff($expectedCommand, $expectedDiff);

        // Get the diff for the new staged file
        $diff = $this->stagedGitSource->getFileDiff($this->repoDir, '--cached', 'new-staged.txt');

        // Verify that the diff shows the new file content
        $this->assertStringContainsString('+New staged content', $diff);
    }

    #[Test]
    public function it_should_handle_no_staged_changes(): void
    {
        // Mock empty response for no staged changes
        $expectedCommand = 'diff --name-only --cached';
        $this->mockChangedFiles($expectedCommand, []);

        // Get staged files when there are none
        $stagedFiles = $this->stagedGitSource->getChangedFiles($this->repoDir, '--cached');

        // Verify that the list is empty
        $this->assertEmpty($stagedFiles);
    }

    #[Test]
    public function it_should_handle_multiple_staged_files(): void
    {
        // Mock multiple staged files
        $expectedCommand = 'diff --name-only --cached';
        $expectedFiles = ['staged1.txt', 'staged2.txt', 'staged3.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get the staged files
        $stagedFiles = $this->stagedGitSource->getChangedFiles($this->repoDir, '--cached');

        // Verify that all staged files are in the list
        $this->assertCount(3, $stagedFiles);
        $this->assertContains('staged1.txt', $stagedFiles);
        $this->assertContains('staged2.txt', $stagedFiles);
        $this->assertContains('staged3.txt', $stagedFiles);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->stagedGitSource = new StagedGitSource($this->commandExecutorMock, new Files(), $this->logger);
    }
}
