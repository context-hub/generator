<?php

declare(strict_types=1);

namespace Tests\Unit\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\StashGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spiral\Files\Files;

#[CoversClass(StashGitSource::class)]
final class StashGitSourceTest extends GitSourceTestCase
{
    private StashGitSource $stashGitSource;

    #[Test]
    public function it_should_support_stash_references(): void
    {
        $this->assertTrue($this->stashGitSource->supports(commitReference: 'stash@{0}'));
        $this->assertTrue($this->stashGitSource->supports(commitReference: 'stash@{1}'));
        $this->assertTrue($this->stashGitSource->supports(commitReference: 'stash@{0}..stash@{2}'));
        $this->assertTrue($this->stashGitSource->supports(commitReference: 'stash@{/message}'));
    }

    #[Test]
    public function it_should_not_support_other_formats(): void
    {
        $this->assertFalse($this->stashGitSource->supports(commitReference: ''));
        $this->assertFalse($this->stashGitSource->supports(commitReference: '--cached'));
        $this->assertFalse($this->stashGitSource->supports(commitReference: 'HEAD~1..HEAD'));
        $this->assertFalse($this->stashGitSource->supports(commitReference: 'HEAD@{1.week.ago}..HEAD'));
        $this->assertFalse($this->stashGitSource->supports(commitReference: 'abcdef1 -- path/to/file'));
    }

    #[Test]
    public function it_should_get_files_from_stash(): void
    {
        // Mock the git command for getting files from stash
        $expectedCommand = 'stash show --name-only stash@{0}';
        $expectedFiles = ['stashed.txt', 'new-stashed.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get the files from the stash
        $stashedFiles = $this->stashGitSource->getChangedFiles(repository: $this->repoDir, commitReference: 'stash@{0}');

        // Verify that both files are in the stash
        $this->assertContains('stashed.txt', $stashedFiles);
        $this->assertContains('new-stashed.txt', $stashedFiles);
    }

    #[Test]
    public function it_should_get_stash_diff_for_specific_file(): void
    {
        // Mock the git command for getting stash diff
        $expectedCommand = "diff stash@{0} -- stashed-diff.txt";
        $expectedDiff = "diff --git a/stashed-diff.txt b/stashed-diff.txt\nindex 1234567..abcdef 100644\n--- a/stashed-diff.txt\n+++ b/stashed-diff.txt\n@@ -1 +1 @@\n-Original content\n+Modified content for stashing\n";
        $this->mockFileDiff($expectedCommand, $expectedDiff);

        // Get the diff for the stashed file
        $diff = $this->stashGitSource->getFileDiff(repository: $this->repoDir, commitReference: 'stash@{0}', file: 'stashed-diff.txt');

        // Verify that the diff contains expected changes
        $this->assertStringContainsString('Modified content for stashing', $diff);
    }

    #[Test]
    public function it_should_format_reference_for_display(): void
    {
        // Test common formats
        $this->assertSame(
            'Changes in latest stash',
            $this->stashGitSource->formatReferenceForDisplay(commitReference: 'stash@{0}'),
        );

        $this->assertSame(
            'Changes in second most recent stash',
            $this->stashGitSource->formatReferenceForDisplay(commitReference: 'stash@{1}'),
        );

        $this->assertSame(
            'Changes in latest 2 stashes',
            $this->stashGitSource->formatReferenceForDisplay(commitReference: 'stash@{0}..stash@{1}'),
        );

        // Test custom format
        $this->assertSame(
            'Changes in stash: stash@{/custom-message}',
            $this->stashGitSource->formatReferenceForDisplay(commitReference: 'stash@{/custom-message}'),
        );
    }

    #[Test]
    public function it_should_return_empty_array_for_non_existent_stash(): void
    {
        // Mock the git command for a non-existent stash
        $expectedCommand = 'stash show --name-only stash@{100}';
        $this->mockChangedFiles($expectedCommand, []);

        // Try to get files from a non-existent stash index
        $files = $this->stashGitSource->getChangedFiles(repository: $this->repoDir, commitReference: 'stash@{100}');

        // Verify that an empty array is returned
        $this->assertEmpty($files);
    }

    #[Test]
    public function it_should_return_empty_string_for_non_existent_stash_diff(): void
    {
        // Mock the git command for a non-existent stash diff
        $expectedCommand = "diff stash@{100} -- file.txt";
        $this->mockFileDiff($expectedCommand, '');

        // Try to get diff from a non-existent stash
        $diff = $this->stashGitSource->getFileDiff(repository: $this->repoDir, commitReference: 'stash@{100}', file: 'file.txt');

        // Verify that an empty string is returned
        $this->assertSame('', $diff);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->stashGitSource = new StashGitSource(commandsExecutor: $this->commandExecutorMock, files: new Files(), logger: $this->logger);
    }
}
