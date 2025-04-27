<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class GithubSourceTest extends ConsoleTestCase
{
    private string $outputDir;
    private MockGithubClient $mockGithubClient;

    public static function commandsProvider(): \Generator
    {
        yield 'generate' => ['generate'];
        yield 'build' => ['build'];
        yield 'compile' => ['compile'];
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function basic_github_source_should_be_rendered(string $command): void
    {
        // Setup mock repository content
        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'src/TestClass.php',
            content: '<?php class TestClass { public function test() { return true; } }',
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src',
            files: [
                [
                    'type' => 'file',
                    'name' => 'TestClass.php',
                    'path' => 'src/TestClass.php',
                    'size' => 100,
                    'html_url' => 'https://github.com/owner/repo/blob/main/src/TestClass.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GithubSource/basic.yaml'),
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'github-source.md',
                contains: [
                    '# Basic GitHub Source Test',
                    'Repository: https://github.com/owner/repo',
                    'TestClass.php',
                    'class TestClass',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function github_source_with_filters_should_be_rendered(): void
    {
        // Setup mock repository content
        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'src/Controller/UserController.php',
            content: '<?php class UserController { public function index() { return "Users"; } }',
        );

        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'src/Model/User.php',
            content: '<?php class User { private $name; public function getName() { return $this->name; } }',
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src',
            files: [
                [
                    'type' => 'dir',
                    'name' => 'Controller',
                    'path' => 'src/Controller',
                ],
                [
                    'type' => 'dir',
                    'name' => 'Model',
                    'path' => 'src/Model',
                ],
            ],
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src/Controller',
            files: [
                [
                    'type' => 'file',
                    'name' => 'UserController.php',
                    'path' => 'src/Controller/UserController.php',
                    'size' => 120,
                    'html_url' => 'https://github.com/owner/repo/blob/main/src/Controller/UserController.php',
                ],
            ],
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src/Model',
            files: [
                [
                    'type' => 'file',
                    'name' => 'User.php',
                    'path' => 'src/Model/User.php',
                    'size' => 110,
                    'html_url' => 'https://github.com/owner/repo/blob/main/src/Model/User.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GithubSource/filtered.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'filtered-github.md',
                contains: [
                    '# Filtered GitHub Source',
                    'Repository: https://github.com/owner/repo',
                    'Controller',
                    'UserController.php',
                    'class UserController {',
                ],
                notContains: [
                    'User.php',
                    'class User {',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function github_source_with_tree_view_should_be_rendered(): void
    {
        // Setup mock repository content with a nested structure
        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'src/Controller/UserController.php',
            content: '<?php class UserController { public function index() { return "Users"; } }',
        );

        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'src/Controller/PostController.php',
            content: '<?php class PostController { public function index() { return "Posts"; } }',
        );

        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'src/Model/User.php',
            content: '<?php class User { private $name; public function getName() { return $this->name; } }',
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src',
            files: [
                [
                    'type' => 'dir',
                    'name' => 'Controller',
                    'path' => 'src/Controller',
                ],
                [
                    'type' => 'dir',
                    'name' => 'Model',
                    'path' => 'src/Model',
                ],
            ],
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src/Controller',
            files: [
                [
                    'type' => 'file',
                    'name' => 'UserController.php',
                    'path' => 'src/Controller/UserController.php',
                    'size' => 120,
                    'html_url' => 'https://github.com/owner/repo/blob/main/src/Controller/UserController.php',
                ],
                [
                    'type' => 'file',
                    'name' => 'PostController.php',
                    'path' => 'src/Controller/PostController.php',
                    'size' => 130,
                    'html_url' => 'https://github.com/owner/repo/blob/main/src/Controller/PostController.php',
                ],
            ],
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src/Model',
            files: [
                [
                    'type' => 'file',
                    'name' => 'User.php',
                    'path' => 'src/Model/User.php',
                    'size' => 110,
                    'html_url' => 'https://github.com/owner/repo/blob/main/src/Model/User.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GithubSource/tree-view.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'tree-view-github.md',
                contains: [
                    '# GitHub Tree View Test',
                    'Repository: https://github.com/owner/repo',
                    '└── src/',
                    '    └── Controller/',
                    '        ├── PostController.php',
                    '        ├── UserController.php',
                    '    └── Model/',
                    '        └── User.php',
                    'class UserController',
                    'class PostController',
                    'class User',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function github_source_with_auth_token_should_use_token(): void
    {
        // Setup mock repository content
        $this->mockGithubClient->addFile(
            repository: 'owner/private-repo',
            path: 'src/PrivateClass.php',
            content: '<?php class PrivateClass { private $secret; }',
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/private-repo',
            path: 'src',
            files: [
                [
                    'type' => 'file',
                    'name' => 'PrivateClass.php',
                    'path' => 'src/PrivateClass.php',
                    'size' => 100,
                    'html_url' => 'https://github.com/owner/private-repo/blob/main/src/PrivateClass.php',
                ],
            ],
        );

        // Create an env file with variables
        $envFile = $this->createTempFile(
            "GITHUB_TOKEN=test-auth-token\n",
            '.env',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GithubSource/with-token.yaml'),
                envFile: $envFile,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'private-github.md',
                contains: [
                    '# Private GitHub Repository',
                    'Repository: https://github.com/owner/private-repo',
                    'PrivateClass.php',
                    'class PrivateClass',
                    'private $secret',
                ],
            );

        // Verify the token was used
        $this->assertEquals('test-auth-token', $this->mockGithubClient->getLastUsedToken());
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function github_source_with_multiple_source_paths_should_be_rendered(): void
    {
        // Setup mock repository content
        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'src/Controller/ApiController.php',
            content: '<?php class ApiController { public function index() { return "API"; } }',
        );

        $this->mockGithubClient->addFile(
            repository: 'owner/repo',
            path: 'tests/ApiControllerTest.php',
            content: '<?php class ApiControllerTest { public function testIndex() { $this->assertTrue(true); } }',
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src',
            files: [
                [
                    'type' => 'dir',
                    'name' => 'Controller',
                    'path' => 'src/Controller',
                ],
            ],
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'src/Controller',
            files: [
                [
                    'type' => 'file',
                    'name' => 'ApiController.php',
                    'path' => 'src/Controller/ApiController.php',
                    'size' => 120,
                    'html_url' => 'https://github.com/owner/repo/blob/main/src/Controller/ApiController.php',
                ],
            ],
        );

        $this->mockGithubClient->addDirectory(
            repository: 'owner/repo',
            path: 'tests',
            files: [
                [
                    'type' => 'file',
                    'name' => 'ApiControllerTest.php',
                    'path' => 'tests/ApiControllerTest.php',
                    'size' => 150,
                    'html_url' => 'https://github.com/owner/repo/blob/main/tests/ApiControllerTest.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GithubSource/multiple-paths.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'multiple-paths-github.md',
                contains: [
                    '# Multiple Paths GitHub Source',
                    'Repository: https://github.com/owner/repo',
                    'ApiController.php',
                    'ApiControllerTest.php',
                    'class ApiController',
                    'class ApiControllerTest',
                ],
            );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = $this->createTempDir();

        // Create mock GitHub client
        $this->mockGithubClient = new MockGithubClient();

        // Register mock GitHub client in the container
        $this->getContainer()->bindSingleton(GithubClientInterface::class, $this->mockGithubClient);
    }

    protected function buildContext(
        string $workDir,
        ?string $configPath = null,
        ?string $inlineJson = null,
        ?string $envFile = null,
        string $command = 'generate',
        bool $asJson = true,
    ): CompilingResult {
        return (new ContextBuilder($this->getConsole()))->build(
            workDir: $workDir,
            configPath: $configPath,
            inlineJson: $inlineJson,
            envFile: $envFile,
            command: $command,
            asJson: $asJson,
        );
    }
}
