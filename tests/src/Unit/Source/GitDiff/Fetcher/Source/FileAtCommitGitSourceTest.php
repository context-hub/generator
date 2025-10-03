<?php

declare(strict_types=1);

namespace Tests\Unit\Source\GitDiff\Fetcher\Source;

use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\FileAtCommitGitSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spiral\Files\Files;

#[CoversClass(FileAtCommitGitSource::class)]
final class FileAtCommitGitSourceTest extends GitSourceTestCase
{
    private FileAtCommitGitSource $fileAtCommitGitSource;

    #[Test]
    public function it_should_support_commit_with_path_format(): void
    {
        $this->assertTrue($this->fileAtCommitGitSource->supports(commitReference: 'abcdef1 -- path/to/file.php'));
        $this->assertTrue($this->fileAtCommitGitSource->supports(commitReference: 'HEAD -- src/'));
        $this->assertTrue($this->fileAtCommitGitSource->supports(commitReference: 'main -- vendor/'));
    }

    #[Test]
    public function it_should_not_support_other_formats(): void
    {
        $this->assertFalse($this->fileAtCommitGitSource->supports(commitReference: ''));
        $this->assertFalse($this->fileAtCommitGitSource->supports(commitReference: '--cached'));
        $this->assertFalse($this->fileAtCommitGitSource->supports(commitReference: 'stash@{0}'));
        $this->assertFalse($this->fileAtCommitGitSource->supports(commitReference: 'HEAD~1..HEAD'));
        $this->assertFalse($this->fileAtCommitGitSource->supports(commitReference: 'HEAD@{1.week.ago}..HEAD'));
        $this->assertFalse($this->fileAtCommitGitSource->supports(commitReference: 'abcdef1'));
    }

    #[Test]
    public function it_should_get_file_at_specific_commit(): void
    {
        // Mock commit hash
        $commitHash = 'abc1234567890abcdef1234567890abcdef123456';

        // Mock the git command for getting files at commit with path filter
        $expectedCommand = "show --name-only {$commitHash} -- src/";
        $expectedFiles = ['src/test1.php'];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get only src files at this commit
        $commitReference = $commitHash . ' -- src/';
        $files = $this->fileAtCommitGitSource->getChangedFiles(repository: $this->repoDir, commitReference: $commitReference);

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
        $expectedCommand = "show --name-only {$commitHash} -- src/models/";
        $expectedFiles = [
            'src/models/User.php',
            'src/models/Post.php',
        ];
        $this->mockChangedFiles($expectedCommand, $expectedFiles);

        // Get only model files
        $commitReference = $commitHash . ' -- src/models/';
        $files = $this->fileAtCommitGitSource->getChangedFiles(repository: $this->repoDir, commitReference: $commitReference);

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
        $expectedCommand = "show {$commitHash}:test.php";
        $expectedContent = '<?php echo "Original";';
        $this->mockFileDiff($expectedCommand, $expectedContent);

        // Get the file at the first commit
        $commitReference = $commitHash . ' -- test.php';
        $content = $this->fileAtCommitGitSource->getFileDiff(repository: $this->repoDir, commitReference: $commitReference, file: 'test.php');

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
        $content = $this->fileAtCommitGitSource->getFileDiff(repository: $this->repoDir, commitReference: $commitReference, file: 'vendor/file2.php');

        // Verify that an empty string is returned
        $this->assertSame('', $content);
    }

    #[Test]
    public function it_should_format_reference_for_display(): void
    {
        $commitRef = 'abc1234 -- src/file.php';
        $displayText = $this->fileAtCommitGitSource->formatReferenceForDisplay(commitReference: $commitRef);

        $this->assertSame('Files at commit abc1234 with path: src/file.php', $displayText);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileAtCommitGitSource = new FileAtCommitGitSource(commandsExecutor: $this->commandExecutorMock, files: new Files(), logger: $this->logger);
    }
}
