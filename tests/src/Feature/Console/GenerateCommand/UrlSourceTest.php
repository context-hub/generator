<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class UrlSourceTest extends ConsoleTestCase
{
    private string $outputDir;
    private MockHttpClient $mockHttpClient;

    public static function commandsProvider(): \Generator
    {
        yield 'generate' => ['generate'];
        yield 'build' => ['build'];
        yield 'compile' => ['compile'];
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function basic_url_source_should_be_rendered(string $command): void
    {
        // Setup mock response
        $this->mockHttpClient->addResponse(
            'https://example.com/api',
            new HttpResponse(
                statusCode: 200,
                body: '<html><body><h1>Example API</h1><p>This is example content</p></body></html>',
                headers: ['Content-Type' => 'text/html'],
            ),
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/UrlSource/basic.yaml'),
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'url-source.md',
                contains: [
                    '# Basic URL Source Test',
                    'URL: https://example.com/api',
                    'Example API',
                    'This is example content',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function source_with_missed_urls_parameter(string $command): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/UrlSource/invalid.yaml'),
                command: $command,
            )
            ->assertDocumentError(
                document: 'url-source.md',
                contains: [
                    'URL source must have a "urls" array property',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_source_with_selector_should_extract_content(): void
    {
        // Setup mock response with content that has elements to select
        $this->mockHttpClient->addResponse(
            'https://example.com/docs',
            new HttpResponse(
                statusCode: 200,
                body: '<html><body><div class="header">Header</div><div class="content"><h2>Documentation</h2><p>Selected content</p></div><div class="footer">Footer</div></body></html>',
                headers: ['Content-Type' => 'text/html'],
            ),
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/UrlSource/with-selector.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'selector-test.md',
                contains: [
                    '# URL Source with Selector',
                    'URL: https://example.com/docs (selector: .content)',
                    'Documentation',
                    'Selected content',
                ],
                notContains: [
                    'Header',
                    'Footer',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_source_with_headers_should_send_headers(): void
    {
        // Setup mock response
        $this->mockHttpClient->addResponse(
            'https://api.example.com/data',
            new HttpResponse(
                statusCode: 200,
                body: '{"message": "Authentication successful", "data": {"name": "Test User", "email": "test@example.com"}}',
                headers: ['Content-Type' => 'application/json'],
            ),
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/UrlSource/with-headers.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'headers-test.md',
                contains: [
                    '# URL Source with Headers',
                    'URL: https://api.example.com/data',
                    'Authentication successful',
                    'Test User',
                ],
            );

        // Verify the headers were sent
        $sentHeaders = $this->mockHttpClient->getRequestHeaders('https://api.example.com/data');
        $this->assertArrayHasKey('Authorization', $sentHeaders);
        $this->assertEquals('Bearer test-token', $sentHeaders['Authorization']);
        $this->assertArrayHasKey('Accept', $sentHeaders);
        $this->assertEquals('application/json', $sentHeaders['Accept']);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_source_with_multiple_urls_should_fetch_all(): void
    {
        // Setup mock responses for multiple URLs
        $this->mockHttpClient->addResponse(
            'https://example.com/page1',
            new HttpResponse(
                statusCode: 200,
                body: '<html><body><h1>Page 1</h1><p>Content from page 1</p></body></html>',
                headers: ['Content-Type' => 'text/html'],
            ),
        );

        $this->mockHttpClient->addResponse(
            'https://example.com/page2',
            new HttpResponse(
                statusCode: 200,
                body: '<html><body><h1>Page 2</h1><p>Content from page 2</p></body></html>',
                headers: ['Content-Type' => 'text/html'],
            ),
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/UrlSource/multiple-urls.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'multiple-urls.md',
                contains: [
                    '# Multiple URLs Test',
                    'URL: https://example.com/page1',
                    'Page 1',
                    'Content from page 1',
                    'URL: https://example.com/page2',
                    'Page 2',
                    'Content from page 2',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_source_with_failed_request_should_show_error(): void
    {
        // Setup mock response with error
        $this->mockHttpClient->addResponse(
            'https://example.com/not-found',
            new HttpResponse(
                statusCode: 404,
                body: 'Not Found',
                headers: [],
            ),
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/UrlSource/failed-request.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'failed-request.md',
                contains: [
                    '# Failed Request Test',
                    'URL: https://example.com/not-found',
                    'Error: HTTP status code 404',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_source_with_variables_should_resolve_variables(): void
    {
        // Setup mock response
        $this->mockHttpClient->addResponse(
            'https://api.production.example.com/data',
            new HttpResponse(
                statusCode: 200,
                body: '<html><body><h1>Production API</h1><p>This is production data</p></body></html>',
                headers: ['Content-Type' => 'text/html'],
            ),
        );

        // Create env file with variables
        $envFile = $this->createTempFile(
            "ENV_NAME=production\nAPI_TOKEN=prod-token\n",
            '.env',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/UrlSource/with-variables.yaml'),
                envFile: $envFile,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'variables-test.md',
                contains: [
                    '# URL Source with Variables',
                    'URL: https://api.production.example.com/data',
                    'Production API',
                    'This is production data',
                ],
            );

        // Verify the headers were sent with resolved variables
        $sentHeaders = $this->mockHttpClient->getRequestHeaders('https://api.production.example.com/data');
        $this->assertArrayHasKey('Authorization', $sentHeaders);
        $this->assertEquals('Bearer prod-token', $sentHeaders['Authorization']);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = $this->createTempDir();

        // Create mock HTTP client
        $this->mockHttpClient = new MockHttpClient();

        // Register mock HTTP client in the container
        $this->getContainer()->bindSingleton(HttpClientInterface::class, $this->mockHttpClient);
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
