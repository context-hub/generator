<?php

declare(strict_types=1);

namespace Tests\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\FileAtCommitGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(FileAtCommitGitSource::class)]
final class FileAtCommitGitSourceTest extends GitSourceTestCase
{
    private FileAtCommitGitSource $fileAtCommitGitSource;

    #[Test]
    public function it_should_support_commit_with_path_format(): void
    {
        $this->assertTrue($this->fileAtCommitGitSource->supports('abcdef1 -- path/to/file.php'));
        $this->assertTrue($this->fileAtCommitGitSource->supports('HEAD -- src/'));
        $this->assertTrue($this->fileAtCommitGitSource->supports('main -- vendor/'));
    }

    #[Test]
    public function it_should_not_support_other_formats(): void
    {
        $this->assertFalse($this->fileAtCommitGitSource->supports(''));
        $this->assertFalse($this->fileAtCommitGitSource->supports('--cached'));
        $this->assertFalse($this->fileAtCommitGitSource->supports('stash@{0}'));
        $this->assertFalse($this->fileAtCommitGitSource->supports('HEAD~1..HEAD'));
        $this->assertFalse($this->fileAtCommitGitSource->supports('HEAD@{1.week.ago}..HEAD'));
        $this->assertFalse($this->fileAtCommitGitSource->supports('abcdef1'));
    }

    #[Test]
    public function it_should_get_file_at_specific_commit(): void
    {
        // Mock commit hash
        $commitHash = 'abc1234567890abcdef1234567890abcdef123456';

        // Mock the git command for getting files at commit with path filter
        $expectedCommand = "git show --name-only '{$commitHash}' -- 'src/'";
        $expectedFiles = ['src/test1.php'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get only src files at this commit
        $commitReference = $commitHash . ' -- src/';
        $files = $this->fileAtCommitGitSource->getChangedFiles($this->repoDir, $commitReference);

        // Verify that only src/test1.php is returned
        $this->assertCount(1, $files);
        $this->assertSame('src/test1.php', $files[0]);
    }

    #[Test]
    public function it_should_get_multiple_files_matching_pattern(): void
    {
        // Mock commit hash
        $commitHash = 'abc1234567890abcdef1234567890abcdef123456';

        // Mock the git command for getting multiple files matching pattern
        $expectedCommand = "git show --name-only '{$commitHash}' -- 'src/models/'";
        $expectedFiles = [
            'src/models/User.php',
            'src/models/Post.php',
        ];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get only model files
        $commitReference = $commitHash . ' -- src/models/';
        $files = $this->fileAtCommitGitSource->getChangedFiles($this->repoDir, $commitReference);

        // Verify that only model files are returned (and the commit hash is filtered out)
        $this->assertCount(2, $files);
        $this->assertContains('src/models/User.php', $files);
        $this->assertContains('src/models/Post.php', $files);
        $this->assertNotContains('src/controllers/UserController.php', $files);
    }

    #[Test]
    public function it_should_get_file_content_at_commit(): void
    {
        // Mock commit hash
        $commitHash = 'abc1234567890abcdef1234567890abcdef123456';

        // Mock the git command for getting file content at commit
        $expectedCommand = "git show '{$commitHash}':'test.php'";
        $expectedContent = '<?php echo "Original";';
        $this->mockFileDiff($expectedCommand, $expectedContent);

        // Get the file at the first commit
        $commitReference = $commitHash . ' -- test.php';
        $content = $this->fileAtCommitGitSource->getFileDiff($this->repoDir, $commitReference, 'test.php');

        // Verify that the original content is returned
        $this->assertStringContainsString('<?php echo "Original";', $content);
    }

    #[Test]
    public function it_should_return_empty_for_non_matching_file(): void
    {
        // Mock commit hash
        $commitHash = 'abc1234567890abcdef1234567890abcdef123456';

        // No need to mock git command here as it shouldn't be called

        // Try to get a file that doesn't match the path filter
        $commitReference = $commitHash . ' -- src/';
        $content = $this->fileAtCommitGitSource->getFileDiff($this->repoDir, $commitReference, 'vendor/file2.php');

        // Verify that an empty string is returned
        $this->assertSame('', $content);
    }

    #[Test]
    public function it_should_format_reference_for_display(): void
    {
        $commitRef = 'abc1234 -- src/file.php';
        $displayText = $this->fileAtCommitGitSource->formatReferenceForDisplay($commitRef);

        $this->assertSame('Files at commit abc1234 with path: src/file.php', $displayText);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileAtCommitGitSource = new FileAtCommitGitSource($this->gitClientMock, $this->logger);
    }
}
