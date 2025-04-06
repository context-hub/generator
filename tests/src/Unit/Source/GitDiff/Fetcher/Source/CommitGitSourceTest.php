<?php

declare(strict_types=1);

namespace Tests\Unit\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\CommitGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spiral\Files\Files;

#[CoversClass(CommitGitSource::class)]
final class CommitGitSourceTest extends GitSourceTestCase
{
    private CommitGitSource $commitGitSource;

    #[Test]
    public function it_should_support_basic_commit_range(): void
    {
        $this->assertTrue($this->commitGitSource->supports('HEAD~1..HEAD'));
        $this->assertTrue($this->commitGitSource->supports('main..HEAD'));
        $this->assertTrue($this->commitGitSource->supports('master..HEAD'));
        $this->assertTrue($this->commitGitSource->supports('develop..HEAD'));
    }

    #[Test]
    public function it_should_support_commit_hash(): void
    {
        $this->assertTrue($this->commitGitSource->supports('abcdef1'));
        $this->assertTrue($this->commitGitSource->supports('abcdef1234567890'));
        $this->assertTrue($this->commitGitSource->supports('abcdef1234567890abcdef1234567890abcdef12'));
    }

    #[Test]
    public function it_should_not_support_other_formats(): void
    {
        $this->assertFalse($this->commitGitSource->supports(''));
        $this->assertFalse($this->commitGitSource->supports('--cached'));
        $this->assertFalse($this->commitGitSource->supports('stash@{0}'));
        $this->assertFalse($this->commitGitSource->supports('HEAD@{1.week.ago}..HEAD'));
        $this->assertFalse($this->commitGitSource->supports('abc123 -- path/to/file'));
    }

    #[Test]
    public function it_should_get_changed_files_in_commit_range(): void
    {
        // Mock the git command for getting changed files
        $expectedCommand = 'diff --name-only HEAD~1..HEAD';
        $expectedFiles = ['test2.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get the changed files
        $changedFiles = $this->commitGitSource->getChangedFiles($this->repoDir, 'HEAD~1..HEAD');

        // Verify that test2.txt is in the changed files
        $this->assertCount(1, $changedFiles);
        $this->assertSame('test2.txt', $changedFiles[0]);
    }

    #[Test]
    public function it_should_get_file_diff_for_specific_file(): void
    {
        // Mock the git command for getting file diff
        $expectedCommand = 'diff HEAD~1..HEAD -- test.txt';
        $expectedDiff = "diff --git a/test.txt b/test.txt\nindex 1234567..abcdef 100644\n--- a/test.txt\n+++ b/test.txt\n@@ -1 +1 @@\n-Original content\n+Modified content\n";
        $this->mockFileDiff($expectedCommand, $expectedDiff);

        // Get the diff for the file
        $diff = $this->commitGitSource->getFileDiff($this->repoDir, 'HEAD~1..HEAD', 'test.txt');

        // Verify that the diff contains expected changes
        $this->assertStringContainsString('-Original content', $diff);
        $this->assertStringContainsString('+Modified content', $diff);
    }

    #[Test]
    public function it_should_format_reference_for_display(): void
    {
        // Test common formats
        $this->assertSame(
            'Changes in last commit',
            $this->commitGitSource->formatReferenceForDisplay('HEAD~1..HEAD'),
        );

        $this->assertSame(
            'Changes in last 5 commits',
            $this->commitGitSource->formatReferenceForDisplay('HEAD~5..HEAD'),
        );

        $this->assertSame(
            'Changes in changes since diverging from main',
            $this->commitGitSource->formatReferenceForDisplay('main..HEAD'),
        );

        // Test custom format
        $this->assertSame(
            'Changes in commit range: custom..range',
            $this->commitGitSource->formatReferenceForDisplay('custom..range'),
        );
    }

    #[Test]
    public function it_should_get_changes_for_specific_commit_hash(): void
    {
        // Mock commit hash
        $commitHash = 'abc1234567890abcdef1234567890abcdef123456';

        // Mock the git command for getting changed files
        $expectedCommand = "diff --name-only {$commitHash}~1..{$commitHash}";
        $expectedFiles = ['specific.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get the changed files for the specific commit
        $changedFiles = $this->commitGitSource->getChangedFiles(
            $this->repoDir,
            $commitHash . '~1..' . $commitHash,
        );

        // Verify that only specific.txt is in the changed files
        $this->assertCount(1, $changedFiles);
        $this->assertSame('specific.txt', $changedFiles[0]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->commitGitSource = new CommitGitSource($this->commandExecutorMock, new Files(), $this->logger);
    }
}
