<?php

declare(strict_types=1);

namespace Tests\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\UnstagedGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(UnstagedGitSource::class)]
final class UnstagedGitSourceTest extends GitSourceTestCase
{
    private UnstagedGitSource $unstagedGitSource;

    #[Test]
    public function it_should_support_unstaged_references(): void
    {
        $this->assertTrue($this->unstagedGitSource->supports(''));
        $this->assertTrue($this->unstagedGitSource->supports('unstaged'));
    }

    #[Test]
    public function it_should_not_support_other_formats(): void
    {
        $this->assertFalse($this->unstagedGitSource->supports('--cached'));
        $this->assertFalse($this->unstagedGitSource->supports('HEAD~1..HEAD'));
        $this->assertFalse($this->unstagedGitSource->supports('stash@{0}'));
        $this->assertFalse($this->unstagedGitSource->supports('HEAD@{1.week.ago}..HEAD'));
        $this->assertFalse($this->unstagedGitSource->supports('abcdef1 -- path/to/file'));
    }

    #[Test]
    public function it_should_get_unstaged_files(): void
    {
        // Mock the git command for getting unstaged files
        $expectedCommand = 'git diff --name-only';
        $expectedFiles = ['unstaged.txt', 'committed.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get the unstaged files
        $unstagedFiles = $this->unstagedGitSource->getChangedFiles($this->repoDir, '');

        // Verify that unstaged files are in the list
        $this->assertCount(2, $unstagedFiles);
        $this->assertContains('unstaged.txt', $unstagedFiles);
        $this->assertContains('committed.txt', $unstagedFiles);
    }

    #[Test]
    public function it_should_get_unstaged_file_diff(): void
    {
        // Mock the git command for getting unstaged file diff
        $expectedCommand = "git diff -- 'unstaged-diff.txt'";
        $expectedDiff = "diff --git a/unstaged-diff.txt b/unstaged-diff.txt\nindex 1234567..abcdef 100644\n--- a/unstaged-diff.txt\n+++ b/unstaged-diff.txt\n@@ -1 +1 @@\n-Original unstaged content\n+Modified unstaged content\n";
        $this->mockFileDiff($expectedCommand, $expectedDiff);

        // Get the diff for the unstaged file
        $diff = $this->unstagedGitSource->getFileDiff($this->repoDir, '', 'unstaged-diff.txt');

        // Verify that the diff contains expected changes
        $this->assertStringContainsString('-Original unstaged content', $diff);
        $this->assertStringContainsString('+Modified unstaged content', $diff);
    }

    #[Test]
    public function it_should_format_reference_for_display(): void
    {
        $this->assertSame(
            'Unstaged changes',
            $this->unstagedGitSource->formatReferenceForDisplay(''),
        );

        $this->assertSame(
            'Unstaged changes',
            $this->unstagedGitSource->formatReferenceForDisplay('unstaged'),
        );
    }

    #[Test]
    public function it_should_handle_new_unstaged_files(): void
    {
        // Mock the git command for getting unstaged files
        $expectedCommand = 'git diff --name-only';
        $expectedFiles = ['new-unstaged.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get the unstaged files
        $unstagedFiles = $this->unstagedGitSource->getChangedFiles($this->repoDir, '');

        // Verify that the new file is in the unstaged files
        $this->assertContains('new-unstaged.txt', $unstagedFiles);
    }

    #[Test]
    public function it_should_handle_no_unstaged_changes(): void
    {
        // Mock empty response for no unstaged changes
        $expectedCommand = 'git diff --name-only';
        $this->mockChangedFiles($expectedCommand, []);

        // Get unstaged files when there are none
        $unstagedFiles = $this->unstagedGitSource->getChangedFiles($this->repoDir, '');

        // Verify that the list is empty
        $this->assertEmpty($unstagedFiles);
    }

    #[Test]
    public function it_should_handle_multiple_unstaged_changes(): void
    {
        // Mock multiple unstaged files
        $expectedCommand = 'git diff --name-only';
        $expectedFiles = ['base1.txt', 'new1.txt', 'new2.txt'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get all unstaged files
        $unstagedFiles = $this->unstagedGitSource->getChangedFiles($this->repoDir, '');

        // Verify that all expected unstaged files are in the list
        $this->assertCount(3, $unstagedFiles);
        $this->assertContains('base1.txt', $unstagedFiles);
        $this->assertContains('new1.txt', $unstagedFiles);
        $this->assertContains('new2.txt', $unstagedFiles);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->unstagedGitSource = new UnstagedGitSource($this->gitClientMock, $this->logger);
    }
}
