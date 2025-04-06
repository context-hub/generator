<?php

declare(strict_types=1);

namespace Tests\Unit\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\TimeRangeGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spiral\Files\Files;

#[CoversClass(TimeRangeGitSource::class)]
final class TimeRangeGitSourceTest extends GitSourceTestCase
{
    private TimeRangeGitSource $timeRangeGitSource;

    #[Test]
    public function it_should_support_time_based_ranges(): void
    {
        $this->assertTrue($this->timeRangeGitSource->supports('HEAD@{1.week.ago}..HEAD'));
        $this->assertTrue($this->timeRangeGitSource->supports('HEAD@{1.days.ago}..HEAD@{0.days.ago}'));
        $this->assertTrue($this->timeRangeGitSource->supports('HEAD@{0:00:00}..HEAD'));
        $this->assertTrue($this->timeRangeGitSource->supports('--since=2023-01-01'));
    }

    #[Test]
    public function it_should_not_support_other_formats(): void
    {
        $this->assertFalse($this->timeRangeGitSource->supports(''));
        $this->assertFalse($this->timeRangeGitSource->supports('--cached'));
        $this->assertFalse($this->timeRangeGitSource->supports('HEAD~1..HEAD'));
        $this->assertFalse($this->timeRangeGitSource->supports('stash@{0}'));
        $this->assertFalse($this->timeRangeGitSource->supports('abcdef1 -- path/to/file'));
    }

    #[Test]
    public function it_should_get_changed_files_for_time_range(): void
    {
        // Mock the git command for getting files in a time range with --since format
        $expectedCommand = 'log --since=1.minute.ago --name-only --pretty=format:""';
        $expectedFiles = ['', 'time-test1.txt', '', 'time-test2.txt', ''];  // Git log output includes empty lines
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get files changed using --since format
        $changedFiles = $this->timeRangeGitSource->getChangedFiles(
            $this->repoDir,
            '--since=1.minute.ago',
        );

        // Verify both files are included in the time range (empty lines filtered out)
        $this->assertCount(2, $changedFiles);
        $this->assertContains('time-test1.txt', $changedFiles);
        $this->assertContains('time-test2.txt', $changedFiles);
    }

    #[Test]
    public function it_should_get_changed_files_for_head_time_range(): void
    {
        // Mock the git command for getting files in a HEAD time range
        $expectedCommand = "diff --name-only HEAD@{1.week.ago}..HEAD";
        $expectedFiles = ['time-test1.txt', 'time-test2.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get files changed in the time range
        $changedFiles = $this->timeRangeGitSource->getChangedFiles(
            $this->repoDir,
            'HEAD@{1.week.ago}..HEAD',
        );

        // Verify both files are included
        $this->assertCount(2, $changedFiles);
        $this->assertContains('time-test1.txt', $changedFiles);
        $this->assertContains('time-test2.txt', $changedFiles);
    }

    #[Test]
    public function it_should_get_file_diff_for_time_range(): void
    {
        // Mock the git command for getting file diff with --since format
        $expectedCommand = "log --since=1.minute.ago -p -- time-diff.txt";
        $expectedDiff = "commit abcdef1234567890abcdef1234567890abcdef1234\nAuthor: Test User <test@example.com>\nDate:   Sun Mar 31 12:00:00 2025 +0000\n\n    Test commit\n\ndiff --git a/time-diff.txt b/time-diff.txt\nindex 1234567..abcdef 100644\n--- a/time-diff.txt\n+++ b/time-diff.txt\n@@ -1 +1 @@\n-Original time content\n+Modified time content\n";
        $this->mockFileDiff($expectedCommand, $expectedDiff);

        // Get the diff for this file in the time range
        $diff = $this->timeRangeGitSource->getFileDiff(
            $this->repoDir,
            '--since=1.minute.ago',
            'time-diff.txt',
        );

        // Verify that the diff contains the expected changes
        $this->assertStringContainsString('Original time content', $diff);
        $this->assertStringContainsString('Modified time content', $diff);
    }

    #[Test]
    public function it_should_get_file_diff_for_head_time_range(): void
    {
        // Mock the git command for getting file diff with HEAD time range
        $expectedCommand = "diff HEAD@{1.week.ago}..HEAD -- time-diff.txt";
        $expectedDiff = "diff --git a/time-diff.txt b/time-diff.txt\nindex 1234567..abcdef 100644\n--- a/time-diff.txt\n+++ b/time-diff.txt\n@@ -1 +1 @@\n-Original time content\n+Modified time content\n";
        $this->mockFileDiff($expectedCommand, $expectedDiff);

        // Get the diff for this file in the HEAD time range
        $diff = $this->timeRangeGitSource->getFileDiff(
            $this->repoDir,
            'HEAD@{1.week.ago}..HEAD',
            'time-diff.txt',
        );

        // Verify that the diff contains the expected changes
        $this->assertStringContainsString('Original time content', $diff);
        $this->assertStringContainsString('Modified time content', $diff);
    }

    #[Test]
    public function it_should_format_reference_for_display(): void
    {
        // Test common formats
        $this->assertSame(
            'Changes from today',
            $this->timeRangeGitSource->formatReferenceForDisplay('HEAD@{0:00:00}..HEAD'),
        );

        $this->assertSame(
            'Changes from last week',
            $this->timeRangeGitSource->formatReferenceForDisplay('HEAD@{1.week.ago}..HEAD'),
        );

        $this->assertSame(
            'Changes from last month',
            $this->timeRangeGitSource->formatReferenceForDisplay('HEAD@{1.month.ago}..HEAD'),
        );

        // Test --since format
        $this->assertSame(
            'Changes since 2023-01-01',
            $this->timeRangeGitSource->formatReferenceForDisplay('--since=2023-01-01'),
        );

        // Test custom format
        $this->assertSame(
            'Changes in time range: custom@{time}..range',
            $this->timeRangeGitSource->formatReferenceForDisplay('custom@{time}..range'),
        );
    }

    #[Test]
    public function it_should_handle_empty_time_range(): void
    {
        // Mock the git command for an empty time range
        $expectedCommand = 'log --since=2099-01-01 --name-only --pretty=format:""';
        $this->mockChangedFiles($expectedCommand, []);

        // Try to get changes in a time range with no commits
        $changedFiles = $this->timeRangeGitSource->getChangedFiles(
            $this->repoDir,
            '--since=2099-01-01', // Future date with no changes
        );

        // Verify that an empty array is returned
        $this->assertEmpty($changedFiles);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->timeRangeGitSource = new TimeRangeGitSource($this->commandExecutorMock, new Files(), $this->logger);
    }
}
