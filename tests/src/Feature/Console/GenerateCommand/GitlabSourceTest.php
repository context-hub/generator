<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Butschster\ContextGenerator\Lib\GitlabClient\GitlabClientInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class GitlabSourceTest extends ConsoleTestCase
{
    private string $outputDir;
    private MockGitlabClient $mockGitlabClient;

    public static function commandsProvider(): \Generator
    {
        yield 'generate' => ['generate'];
        yield 'build' => ['build'];
        yield 'compile' => ['compile'];
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function basic_gitlab_source_should_be_rendered(string $command): void
    {
        // Setup mock repository content
        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/TestClass.php',
            content: '<?php class TestClass { public function test() { return true; } }',
            branch: 'main',
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'TestClass.php',
                    'path' => 'src/TestClass.php',
                    'size' => 100,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/src/TestClass.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GitlabSource/basic.yaml'),
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'gitlab-source.md',
                contains: [
                    '# Basic GitLab Source Test',
                    'TestClass.php',
                    'class TestClass',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function server_parameter_is_required(string $command): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GitlabSource/invalid.yaml'),
                command: $command,
            )
            ->assertDocumentError(
                document: 'gitlab-source.md',
                contains: [
                    'GitLab server is not set',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function gitlab_source_with_filters_should_be_rendered(): void
    {
        // Setup mock repository content
        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/Controller/UserController.php',
            content: '<?php class UserController { public function index() { return "Users"; } }',
        );

        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/Model/User.php',
            content: '<?php class User { private $name; public function getName() { return $this->name; } }',
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src',
            files: [
                [
                    'type' => 'tree',
                    'name' => 'Controller',
                    'path' => 'src/Controller',
                ],
                [
                    'type' => 'tree',
                    'name' => 'Model',
                    'path' => 'src/Model',
                ],
            ],
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src/Controller',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'UserController.php',
                    'path' => 'src/Controller/UserController.php',
                    'size' => 120,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/src/Controller/UserController.php',
                ],
            ],
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src/Model',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'User.php',
                    'path' => 'src/Model/User.php',
                    'size' => 110,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/src/Model/User.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GitlabSource/filtered.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'filtered-gitlab.md',
                contains: [
                    '# Filtered GitLab Source',
                    'Repository: https://gitlab.com/group/project',
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
    public function gitlab_source_with_tree_view_should_be_rendered(): void
    {
        // Setup mock repository content with a nested structure
        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/Controller/UserController.php',
            content: '<?php class UserController { public function index() { return "Users"; } }',
        );

        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/Controller/PostController.php',
            content: '<?php class PostController { public function index() { return "Posts"; } }',
        );

        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/Model/User.php',
            content: '<?php class User { private $name; public function getName() { return $this->name; } }',
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src',
            files: [
                [
                    'type' => 'tree',
                    'name' => 'Controller',
                    'path' => 'src/Controller',
                ],
                [
                    'type' => 'tree',
                    'name' => 'Model',
                    'path' => 'src/Model',
                ],
            ],
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src/Controller',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'UserController.php',
                    'path' => 'src/Controller/UserController.php',
                    'size' => 120,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/src/Controller/UserController.php',
                ],
                [
                    'type' => 'blob',
                    'name' => 'PostController.php',
                    'path' => 'src/Controller/PostController.php',
                    'size' => 130,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/src/Controller/PostController.php',
                ],
            ],
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src/Model',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'User.php',
                    'path' => 'src/Model/User.php',
                    'size' => 110,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/src/Model/User.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GitlabSource/tree-view.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'tree-view-gitlab.md',
                contains: [
                    '# GitLab Tree View Test',
                    'Repository: https://gitlab.com/group/project',
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
    public function gitlab_source_with_auth_token_should_use_token(): void
    {
        // Setup mock repository content
        $this->mockGitlabClient->addFile(
            repository: 'group/private-project',
            path: 'src/PrivateClass.php',
            content: '<?php class PrivateClass { private $secret; }',
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/private-project',
            path: 'src',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'PrivateClass.php',
                    'path' => 'src/PrivateClass.php',
                    'size' => 100,
                    'web_url' => 'https://gitlab.com/group/private-project/-/blob/main/src/PrivateClass.php',
                ],
            ],
        );

        // Create an env file with variables
        $envFile = $this->createTempFile(
            "GITLAB_TOKEN=test-gitlab-token\n",
            '.env',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GitlabSource/with-token.yaml'),
                envFile: $envFile,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'private-gitlab.md',
                contains: [
                    '# Private GitLab Repository',
                    'Repository: https://gitlab.com/group/private-project',
                    'PrivateClass.php',
                    'class PrivateClass',
                    'private $secret',
                ],
            );

        // Verify the token was used
        $this->assertEquals('test-gitlab-token', $this->mockGitlabClient->getLastUsedToken());
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function gitlab_source_with_custom_server_and_headers(): void
    {
        // Setup mock repository content
        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/TestClass.php',
            content: '<?php class TestClass { public function test() { return true; } }',
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'TestClass.php',
                    'path' => 'src/TestClass.php',
                    'size' => 100,
                    'web_url' => 'https://custom-gitlab.example.com/group/project/-/blob/main/src/TestClass.php',
                ],
            ],
        );

        // Create an env file with variables
        $envFile = $this->createTempFile(
            "GITLAB_TOKEN=test-gitlab-token\n",
            '.env',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GitlabSource/custom-server.yaml'),
                envFile: $envFile,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'custom-server-gitlab.md',
                contains: [
                    '# Custom Server GitLab Test',
                    'Repository: https://custom-gitlab.example.com/group/project',
                    'TestClass.php',
                    'class TestClass',
                ],
            );

        // Verify custom server URL was used
        $this->assertEquals('https://custom-gitlab.example.com', $this->mockGitlabClient->getLastUsedServerUrl());

        // Verify custom headers were used
        $headers = $this->mockGitlabClient->getLastUsedHeaders();
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('custom-value', $headers['X-Custom-Header']);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function gitlab_source_with_multiple_source_paths_should_be_rendered(): void
    {
        // Setup mock repository content
        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'src/Controller/ApiController.php',
            content: '<?php class ApiController { public function index() { return "API"; } }',
        );

        $this->mockGitlabClient->addFile(
            repository: 'group/project',
            path: 'tests/ApiControllerTest.php',
            content: '<?php class ApiControllerTest { public function testIndex() { $this->assertTrue(true); } }',
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src',
            files: [
                [
                    'type' => 'tree',
                    'name' => 'Controller',
                    'path' => 'src/Controller',
                ],
            ],
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'src/Controller',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'ApiController.php',
                    'path' => 'src/Controller/ApiController.php',
                    'size' => 120,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/src/Controller/ApiController.php',
                ],
            ],
        );

        $this->mockGitlabClient->addDirectory(
            repository: 'group/project',
            path: 'tests',
            files: [
                [
                    'type' => 'blob',
                    'name' => 'ApiControllerTest.php',
                    'path' => 'tests/ApiControllerTest.php',
                    'size' => 150,
                    'web_url' => 'https://gitlab.com/group/project/-/blob/main/tests/ApiControllerTest.php',
                ],
            ],
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/GitlabSource/multiple-paths.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'multiple-paths-gitlab.md',
                contains: [
                    '# Multiple Paths GitLab Source',
                    'Repository: https://gitlab.com/group/project',
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

        // Create mock GitLab client
        $this->mockGitlabClient = new MockGitlabClient();

        // Register mock GitLab client in the container
        $this->getContainer()->bindSingleton(GitlabClientInterface::class, $this->mockGitlabClient);
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
